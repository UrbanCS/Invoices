<?php

namespace App\Http\Controllers;

use App\Models\BusinessSetting;
use App\Models\CleaningOrder;
use App\Models\Client;
use App\Models\MonthlyInvoice;
use App\Services\AuditLogService;
use App\Services\InvoiceCalculationService;
use App\Services\InvoiceNumberService;
use App\Services\MoneyFormatter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AccountStatementController extends Controller
{
    public function index(MoneyFormatter $money): View
    {
        $month = (int) request('month', now()->month);
        $year = (int) request('year', now()->year);
        $clientId = request('client_id');

        $orders = CleaningOrder::with(['client', 'items'])
            ->whereMonth('service_date', $month)
            ->whereYear('service_date', $year)
            ->when($clientId, fn ($query) => $query->where('client_id', $clientId))
            ->latest('service_date')
            ->get();

        $invoiceableOrdersCount = $clientId
            ? CleaningOrder::where('client_id', $clientId)
                ->whereMonth('service_date', $month)
                ->whereYear('service_date', $year)
                ->where('status', 'reviewed')
                ->whereNull('monthly_invoice_id')
                ->count()
            : 0;

        return view('account-statements.index', [
            'orders' => $orders,
            'clients' => Client::orderBy('name')->get(),
            'month' => $month,
            'year' => $year,
            'clientId' => $clientId,
            'money' => $money,
            'subtotalCents' => $orders->sum('subtotal_cents'),
            'adjustmentCents' => $orders->sum('adjustment_cents'),
            'totalCents' => $orders->sum('total_cents'),
            'invoiceableOrdersCount' => $invoiceableOrdersCount,
        ]);
    }

    public function createInvoice(Request $request, InvoiceNumberService $numbers, InvoiceCalculationService $calculator, AuditLogService $audit): RedirectResponse
    {
        abort_unless(Auth::user()->canManage(), 403);

        $data = $request->validate([
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'client_id' => ['required', 'exists:clients,id'],
        ]);

        $client = Client::findOrFail($data['client_id']);
        $settings = BusinessSetting::first();
        $orders = CleaningOrder::with(['items.category'])
            ->where('client_id', $client->id)
            ->whereMonth('service_date', (int) $data['month'])
            ->whereYear('service_date', (int) $data['year'])
            ->where('status', 'reviewed')
            ->whereNull('monthly_invoice_id')
            ->orderBy('service_date')
            ->get();

        if ($orders->isEmpty()) {
            return back()->withErrors('Aucune commande approuvée non facturée pour ce client et cette période.');
        }

        $invoice = DB::transaction(function () use ($client, $orders, $data, $numbers, $calculator, $settings) {
            $categorySnapshot = $orders
                ->flatMap->items
                ->unique('client_category_id')
                ->map(fn ($item) => [
                    'id' => $item->client_category_id,
                    'name' => $item->item_name_snapshot,
                    'sort_order' => $item->category?->sort_order ?? 0,
                    'is_taxable' => $item->category?->is_taxable ?? true,
                ])
                ->values()
                ->all();

            $invoice = MonthlyInvoice::create([
                'client_id' => $client->id,
                'invoice_number' => $numbers->next((int) $data['month'], (int) $data['year']),
                'invoice_month' => (int) $data['month'],
                'invoice_year' => (int) $data['year'],
                'invoice_date' => now()->toDateString(),
                'status' => 'draft',
                'source_mode' => 'manual_grid',
                'category_snapshot' => $categorySnapshot,
                'notes' => 'Facture mensuelle générée depuis les commandes client approuvées.',
                'payment_instructions' => $settings?->default_payment_instructions,
                'thank_you_message' => $settings?->default_thank_you_message,
                'created_by' => Auth::id(),
            ]);

            $entryRows = $orders->flatMap(fn ($order) => $order->items->map(fn ($item) => [
                'order' => $order,
                'item' => $item,
                'service_day' => (int) $order->service_date->day,
                'client_category_id' => $item->client_category_id,
            ]));

            foreach ($entryRows->groupBy(fn ($row) => $row['service_day'].'-'.($row['client_category_id'] ?? 'none')) as $rows) {
                $first = $rows->first();
                $invoice->entries()->create([
                    'service_day' => $first['service_day'],
                    'client_category_id' => $first['client_category_id'] ?: null,
                    'category_name_snapshot' => $first['item']->item_name_snapshot,
                    'amount_cents' => (int) $rows->sum(fn ($row) => $row['item']->total_cents),
                    'item_details' => $rows->map(fn ($row) => [
                        'label' => $row['item']->item_name_snapshot,
                        'quantity' => rtrim(rtrim(number_format((float) $row['item']->quantity, 2, '.', ''), '0'), '.'),
                        'unit_price_cents' => $row['item']->unit_price_cents,
                        'total_cents' => $row['item']->total_cents,
                        'employee_name' => $row['order']->employee_name,
                        'department_number' => $row['order']->department_number,
                    ])->values()->all(),
                    'source_type' => 'manual_monthly_grid',
                ]);
            }

            foreach ($orders->where('adjustment_cents', '!=', 0) as $order) {
                $invoice->adjustments()->create([
                    'client_category_id' => null,
                    'label' => $order->adjustment_note ?: 'Ajustement commande '.$order->service_date->format('Y-m-d'),
                    'type' => $order->adjustment_cents < 0 ? 'credit' : 'fee',
                    'amount_cents' => abs($order->adjustment_cents),
                ]);
            }

            $invoice->load('client', 'entries', 'adjustments');
            $invoice->update($calculator->calculate(
                $invoice->client,
                $invoice->entries,
                $invoice->adjustments,
                $categorySnapshot,
            ));

            $orders->each->update([
                'status' => 'invoiced',
                'monthly_invoice_id' => $invoice->id,
            ]);

            return $invoice;
        });

        $audit->record('monthly_invoice.created_from_account_statement', $invoice);

        return redirect()->route('monthly-invoices.show', $invoice)
            ->with('status', 'Facture mensuelle créée à partir des commandes approuvées.');
    }

    public function adjustment(Request $request, CleaningOrder $order, MoneyFormatter $money): RedirectResponse
    {
        abort_unless(Auth::user()->isSuperAdmin(), 403);

        $data = $request->validate([
            'adjustment_amount' => ['nullable', 'string', 'max:50'],
            'adjustment_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $adjustmentCents = $money->parse($data['adjustment_amount'] ?? null);

        $order->update([
            'adjustment_cents' => $adjustmentCents,
            'adjustment_note' => $data['adjustment_note'] ?? null,
            'total_cents' => $order->subtotal_cents + $adjustmentCents,
        ]);

        return back()->with('status', 'Ajustement sauvegardé.');
    }
}
