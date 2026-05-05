<?php

namespace App\Http\Controllers;

use App\Models\BusinessSetting;
use App\Models\Client;
use App\Models\MonthlyInvoice;
use App\Models\UploadedDocument;
use App\Services\AuditLogService;
use App\Services\CsvExportService;
use App\Services\DailyRecordAggregationService;
use App\Services\InvoiceCalculationService;
use App\Services\InvoiceNumberService;
use App\Services\InvoicePdfService;
use App\Services\MoneyFormatter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class MonthlyInvoiceController extends Controller
{
    public function index(): View
    {
        $status = in_array(request('status'), ['draft', 'approved', 'sent', 'paid', 'cancelled'], true)
            ? request('status')
            : null;

        $query = MonthlyInvoice::with('client')
            ->when(request('client_id'), fn ($q) => $q->where('client_id', request('client_id')))
            ->when(request('client_name'), fn ($q) => $q->whereHas('client', fn ($clientQuery) => $clientQuery->where('name', 'like', '%'.request('client_name').'%')))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when(request('invoice_number'), fn ($q) => $q->where('invoice_number', 'like', '%'.request('invoice_number').'%'))
            ->when(request('year'), fn ($q) => $q->where('invoice_year', request('year')));

        return view('monthly-invoices.index', [
            'invoices' => $query->latest('invoice_date')->paginate(20)->withQueryString(),
            'clients' => Client::orderBy('name')->get(),
        ]);
    }

    public function create(InvoiceNumberService $numbers): View
    {
        $month = (int) request('month', now()->month);
        $year = (int) request('year', now()->year);
        $clients = Client::with('activeCategories')->where('is_active', true)->orderBy('name')->get();
        $selectedClientId = request('client_id') ?: $clients->first()?->id;

        return view('monthly-invoices.form', [
            'invoice' => new MonthlyInvoice([
                'client_id' => $selectedClientId,
                'invoice_month' => $month,
                'invoice_year' => $year,
                'invoice_date' => now(),
                'invoice_number' => $numbers->next($month, $year),
                'source_mode' => request('source_mode', 'manual_grid'),
                'status' => 'draft',
            ]),
            'clients' => $clients,
            'entries' => collect(),
            'adjustments' => collect(),
        ]);
    }

    public function store(Request $request, MoneyFormatter $money, InvoiceCalculationService $calculator, DailyRecordAggregationService $aggregator, AuditLogService $audit): RedirectResponse
    {
        $data = $this->validated($request);
        $client = Client::with('activeCategories')->findOrFail($data['client_id']);
        $settings = BusinessSetting::first();
        $categories = $this->categorySnapshot($client);

        if ($data['source_mode'] === 'manual_grid' && $client->activeCategories->isEmpty()) {
            throw ValidationException::withMessages([
                'client_id' => 'Ajoute au moins une catégorie au client avant de créer une facture.',
            ]);
        }

        $invoice = MonthlyInvoice::create([
            ...$data,
            'status' => 'draft',
            'category_snapshot' => $categories,
            'payment_instructions' => $settings?->default_payment_instructions,
            'thank_you_message' => $settings?->default_thank_you_message,
            'created_by' => Auth::id(),
        ]);

        if ($data['source_mode'] === 'daily_records') {
            $records = $aggregator->reviewedRecords($client, (int) $data['invoice_month'], (int) $data['invoice_year']);
            $invoice->entries()->createMany($aggregator->entriesFromRecords($records)->all());
            $invoice->dailyRecords()->sync($records->pluck('id'));
        } else {
            $createdEntries = $this->syncGrid($invoice, $request, $client, $money);

            if ($createdEntries === 0) {
                $invoice->delete();

                throw ValidationException::withMessages([
                    'grid' => 'Entre au moins un montant dans la grille mensuelle avant de sauvegarder la facture.',
                ]);
            }
        }

        $this->syncAdjustments($invoice, $request, $money);
        $this->recalculate($invoice, $calculator);
        $audit->record('monthly_invoice.created', $invoice);

        return redirect()->route('monthly-invoices.show', $invoice)->with('status', 'Brouillon de facture créé.');
    }

    public function show(MonthlyInvoice $invoice, MoneyFormatter $money): View
    {
        $this->authorizeInvoice($invoice);
        return view('monthly-invoices.show', ['invoice' => $invoice->load('client', 'entries', 'adjustments'), 'money' => $money]);
    }

    public function edit(MonthlyInvoice $invoice): View
    {
        $this->authorizeInvoice($invoice, true);
        return view('monthly-invoices.form', [
            'invoice' => $invoice->load('entries', 'adjustments'),
            'clients' => Client::with('activeCategories')->where('is_active', true)->orderBy('name')->get(),
            'entries' => $invoice->entries,
            'adjustments' => $invoice->adjustments,
        ]);
    }

    public function update(Request $request, MonthlyInvoice $invoice, MoneyFormatter $money, InvoiceCalculationService $calculator, AuditLogService $audit): RedirectResponse
    {
        $this->authorizeInvoice($invoice, true);
        $before = $invoice->load('entries', 'adjustments')->toArray();
        $data = $this->validated($request, $invoice->id);
        $client = Client::with('activeCategories')->findOrFail($data['client_id']);
        if ($data['source_mode'] === 'manual_grid' && $client->activeCategories->isEmpty()) {
            throw ValidationException::withMessages([
                'client_id' => 'Ajoute au moins une catégorie au client avant de créer une facture.',
            ]);
        }

        $invoice->update([...$data, 'category_snapshot' => $this->categorySnapshot($client)]);
        $invoice->entries()->delete();
        $createdEntries = $this->syncGrid($invoice, $request, $client, $money);

        if ($data['source_mode'] === 'manual_grid' && $createdEntries === 0) {
            throw ValidationException::withMessages([
                'grid' => 'Entre au moins un montant dans la grille mensuelle avant de sauvegarder la facture.',
            ]);
        }

        $invoice->adjustments()->delete();
        $this->syncAdjustments($invoice, $request, $money);
        $this->recalculate($invoice, $calculator);
        $audit->record('monthly_invoice.updated', $invoice, $before);

        return redirect()->route('monthly-invoices.show', $invoice)->with('status', 'Facture mise à jour.');
    }

    public function approve(MonthlyInvoice $invoice, AuditLogService $audit): RedirectResponse
    {
        $this->authorizeInvoice($invoice, true);
        $invoice->update(['status' => 'approved']);
        $invoice->dailyRecords()->update(['status' => 'invoiced']);
        $audit->record('monthly_invoice.approved', $invoice);
        return back()->with('status', 'Facture approuvée.');
    }

    public function generatePdf(MonthlyInvoice $invoice, InvoicePdfService $pdf, AuditLogService $audit): RedirectResponse
    {
        $this->authorizeInvoice($invoice, true);

        try {
            $pdf->generate($invoice);
            $audit->record('monthly_invoice.pdf_generated', $invoice);

            return back()->with('status', 'PDF généré.');
        } catch (Throwable $exception) {
            Log::error('Erreur de génération PDF', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'message' => $exception->getMessage(),
            ]);

            return back()->withErrors('PDF non généré. Vérifie les permissions du dossier storage ou le journal Laravel.');
        }
    }

    public function download(MonthlyInvoice $invoice)
    {
        $this->authorizeInvoice($invoice);
        abort_unless($invoice->pdf_path && Storage::disk('public')->exists($invoice->pdf_path), 404);
        return Storage::disk('public')->download($invoice->pdf_path);
    }

    public function markSent(MonthlyInvoice $invoice, AuditLogService $audit): RedirectResponse
    {
        $this->authorizeInvoice($invoice, true);
        $invoice->update(['status' => 'sent']);
        $audit->record('monthly_invoice.sent', $invoice);
        return back()->with('status', 'Facture marquée envoyée.');
    }

    public function markPaid(MonthlyInvoice $invoice, AuditLogService $audit): RedirectResponse
    {
        $this->authorizeInvoice($invoice, true);
        $invoice->update(['status' => 'paid']);
        $invoice->payments()->create(['amount_cents' => $invoice->grand_total_cents, 'paid_at' => now(), 'method' => 'manual']);
        $audit->record('monthly_invoice.paid', $invoice);
        return back()->with('status', 'Facture marquée payée.');
    }

    public function cancel(MonthlyInvoice $invoice): RedirectResponse
    {
        $this->authorizeInvoice($invoice, true);
        $invoice->update(['status' => 'cancelled']);
        return back()->with('status', 'Facture annulée.');
    }

    public function export(MonthlyInvoice $invoice, CsvExportService $csv)
    {
        $this->authorizeInvoice($invoice);
        return $csv->invoiceDetails($invoice);
    }

    public function attachments(Request $request, MonthlyInvoice $invoice): RedirectResponse
    {
        $this->authorizeInvoice($invoice, true);
        $file = $request->validate(['attachment' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf,csv,xls,xlsx', 'max:10240']])['attachment'];
        $path = $file->store('monthly-invoices/'.$invoice->id, 'public');

        UploadedDocument::create([
            'client_id' => $invoice->client_id,
            'monthly_invoice_id' => $invoice->id,
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'uploaded_by' => Auth::id(),
        ]);

        return back()->with('status', 'Pièce jointe ajoutée à la facture.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'invoice_number' => ['required', 'string', 'max:255', 'unique:monthly_invoices,invoice_number'.($ignoreId ? ','.$ignoreId : '')],
            'invoice_month' => ['required', 'integer', 'between:1,12'],
            'invoice_year' => ['required', 'integer', 'between:2000,2100'],
            'invoice_date' => ['required', 'date'],
            'source_mode' => ['required', 'in:daily_records,manual_grid'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    private function syncGrid(MonthlyInvoice $invoice, Request $request, Client $client, MoneyFormatter $money): int
    {
        $createdEntries = 0;

        foreach ($request->input('grid', []) as $day => $columns) {
            foreach ($columns as $categoryId => $amount) {
                $cents = $money->parse($amount);
                if ($cents <= 0) {
                    continue;
                }
                $category = $client->activeCategories->firstWhere('id', (int) $categoryId);
                if (! $category) {
                    continue;
                }
                $invoice->entries()->create([
                    'service_day' => (int) $day,
                    'client_category_id' => $category->id,
                    'category_name_snapshot' => $category->name,
                    'amount_cents' => $cents,
                    'source_type' => 'manual_monthly_grid',
                ]);
                $createdEntries++;
            }
        }

        return $createdEntries;
    }

    private function syncAdjustments(MonthlyInvoice $invoice, Request $request, MoneyFormatter $money): void
    {
        foreach ($request->input('adjustments', []) as $row) {
            if (blank($row['label'] ?? null) || blank($row['amount'] ?? null)) {
                continue;
            }
            $invoice->adjustments()->create([
                'client_category_id' => $row['client_category_id'] ?? null,
                'label' => $row['label'],
                'type' => $row['type'] ?? 'discount',
                'amount_cents' => max(0, $money->parse($row['amount'])),
            ]);
        }
    }

    private function recalculate(MonthlyInvoice $invoice, InvoiceCalculationService $calculator): void
    {
        $invoice->load('client', 'entries', 'adjustments');
        $invoice->update($calculator->calculate($invoice->client, $invoice->entries, $invoice->adjustments, $invoice->category_snapshot ?? []));
    }

    private function categorySnapshot(Client $client): array
    {
        return $client->activeCategories->map(fn ($category) => [
            'id' => $category->id,
            'name' => $category->name,
            'sort_order' => $category->sort_order,
            'is_taxable' => $category->is_taxable,
        ])->values()->all();
    }

    private function authorizeInvoice(MonthlyInvoice $invoice, bool $manage = false): void
    {
        $user = Auth::user();
        abort_unless($manage ? $user->canManage() : ($user->canManage() || ($user->isClientUser() && $user->client_id === $invoice->client_id)), 403);
    }
}
