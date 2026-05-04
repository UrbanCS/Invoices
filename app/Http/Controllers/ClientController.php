<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\AuditLog;
use App\Models\DailyRecordItem;
use App\Models\InvoiceAdjustment;
use App\Models\MonthlyInvoiceEntry;
use App\Models\Payment;
use App\Models\UploadedDocument;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ClientController extends Controller
{
    public function index(): View
    {
        return view('clients.index', ['clients' => Client::orderBy('name')->paginate(20)]);
    }

    public function create(): View
    {
        return view('clients.form', ['client' => new Client()]);
    }

    public function store(Request $request, AuditLogService $audit): RedirectResponse
    {
        $client = Client::create($this->validated($request));
        $audit->record('client.created', $client);

        return redirect()->route('clients.show', $client)->with('status', 'Client créé.');
    }

    public function show(Client $client): View
    {
        return view('clients.show', ['client' => $client->load('categories', 'invoices')]);
    }

    public function edit(Client $client): View
    {
        return view('clients.form', ['client' => $client]);
    }

    public function update(Request $request, Client $client, AuditLogService $audit): RedirectResponse
    {
        $before = $client->toArray();
        $client->update($this->validated($request));
        $audit->record('client.updated', $client, $before);

        return redirect()->route('clients.show', $client)->with('status', 'Client mis à jour.');
    }

    public function destroy(Client $client): RedirectResponse
    {
        DB::transaction(function () use ($client) {
            $invoiceIds = $client->invoices()->pluck('id');
            $dailyRecordIds = $client->dailyRecords()->pluck('id');

            UploadedDocument::query()
                ->where('client_id', $client->id)
                ->orWhereIn('monthly_invoice_id', $invoiceIds)
                ->orWhereIn('daily_record_id', $dailyRecordIds)
                ->delete();

            Payment::whereIn('monthly_invoice_id', $invoiceIds)->delete();
            InvoiceAdjustment::whereIn('monthly_invoice_id', $invoiceIds)->delete();
            MonthlyInvoiceEntry::whereIn('monthly_invoice_id', $invoiceIds)->delete();

            DB::table('monthly_invoice_daily_record')
                ->whereIn('monthly_invoice_id', $invoiceIds)
                ->orWhereIn('daily_record_id', $dailyRecordIds)
                ->delete();

            DailyRecordItem::whereIn('daily_record_id', $dailyRecordIds)->delete();

            $client->invoices()->delete();
            $client->dailyRecords()->delete();

            $client->users()->delete();
            $client->taxRates()->delete();
            $client->categories()->delete();

            User::where('client_id', $client->id)->delete();
            AuditLog::where('entity_type', Client::class)->where('entity_id', $client->id)->delete();

            $client->delete();
        });

        return redirect()->route('clients.index')->with('status', 'Client supprimé définitivement.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'billing_address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:50'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'tax_profile' => ['required', 'in:qc_tps_tvq,on_hst,custom'],
            'default_language' => ['required', 'in:fr,en'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]) + ['is_active' => $request->boolean('is_active', true)];

        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store('client-logos', 'public');
        }

        unset($data['logo']);

        return $data;
    }
}
