<?php

namespace App\Http\Controllers;

use App\Models\BusinessSetting;
use App\Models\CleaningOrder;
use App\Models\MonthlyInvoice;
use App\Services\AuditLogService;
use App\Services\InvoiceCalculationService;
use App\Services\InvoiceNumberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AdminCleaningOrderController extends Controller
{
    public function approve(CleaningOrder $order, AuditLogService $audit): RedirectResponse
    {
        abort_unless(Auth::user()->canManage(), 403);

        if ($order->status !== 'submitted') {
            return back()->withErrors('Cette commande ne peut plus être approuvée.');
        }

        $order->update(['status' => 'reviewed']);
        $audit->record('cleaning_order.approved', $order);

        return back()->with('status', 'Commande approuvée. Elle est prête à être facturée.');
    }

    public function createInvoice(
        CleaningOrder $order,
        InvoiceNumberService $numbers,
        InvoiceCalculationService $calculator,
        AuditLogService $audit,
    ): RedirectResponse {
        abort_unless(Auth::user()->canManage(), 403);

        if ($order->status !== 'reviewed' || $order->monthly_invoice_id) {
            return back()->withErrors('Cette commande doit être approuvée et ne pas avoir déjà été facturée.');
        }

        $order->load(['client.categories', 'items.category']);
        $settings = BusinessSetting::first();

        $invoice = DB::transaction(function () use ($order, $numbers, $calculator, $settings) {
            $month = (int) $order->service_date->month;
            $year = (int) $order->service_date->year;
            $categorySnapshot = $order->items
                ->unique('client_category_id')
                ->map(fn ($item) => [
                    'id' => $item->client_category_id,
                    'name' => $item->item_name_snapshot,
                    'sort_order' => $item->category?->sort_order ?? 0,
                    'is_taxable' => $item->category?->is_taxable ?? true,
                ])
                ->values()
                ->all();

            $notes = collect([
                'Commande client #'.$order->id,
                'Employé: '.$order->employee_name,
                $order->department_number ? 'No de département: '.$order->department_number : null,
                $order->notes,
            ])->filter()->implode("\n");

            $invoice = MonthlyInvoice::create([
                'client_id' => $order->client_id,
                'invoice_number' => $numbers->next($month, $year),
                'invoice_month' => $month,
                'invoice_year' => $year,
                'invoice_date' => now()->toDateString(),
                'status' => 'draft',
                'source_mode' => 'manual_grid',
                'category_snapshot' => $categorySnapshot,
                'notes' => $notes,
                'payment_instructions' => $settings?->default_payment_instructions,
                'thank_you_message' => $settings?->default_thank_you_message,
                'created_by' => Auth::id(),
            ]);

            foreach ($order->items->groupBy('client_category_id') as $categoryId => $items) {
                $first = $items->first();
                $invoice->entries()->create([
                    'service_day' => (int) $order->service_date->day,
                    'client_category_id' => $categoryId ?: null,
                    'category_name_snapshot' => $first->item_name_snapshot,
                    'amount_cents' => (int) $items->sum('total_cents'),
                    'item_details' => $items->map(fn ($item) => [
                        'label' => $item->item_name_snapshot,
                        'quantity' => rtrim(rtrim(number_format((float) $item->quantity, 2, '.', ''), '0'), '.'),
                        'unit_price_cents' => $item->unit_price_cents,
                        'total_cents' => $item->total_cents,
                    ])->values()->all(),
                    'source_type' => 'manual_monthly_grid',
                ]);
            }

            if ($order->adjustment_cents !== 0) {
                $invoice->adjustments()->create([
                    'client_category_id' => null,
                    'label' => $order->adjustment_note ?: 'Ajustement de commande',
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

            $order->update([
                'status' => 'invoiced',
                'monthly_invoice_id' => $invoice->id,
            ]);

            return $invoice;
        });

        $audit->record('monthly_invoice.created_from_cleaning_order', $invoice);

        return redirect()->route('monthly-invoices.show', $invoice)
            ->with('status', 'Brouillon de facture créé à partir de la commande.');
    }
}
