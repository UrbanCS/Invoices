<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\AuditLog;
use App\Models\ClientCategory;
use App\Models\DailyRecordItem;
use App\Models\InvoiceAdjustment;
use App\Models\MonthlyInvoiceEntry;
use App\Models\Payment;
use App\Models\UploadedDocument;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\MoneyFormatter;
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
        return view('clients.form', ['client' => new Client(), 'categoryRows' => collect()]);
    }

    public function store(Request $request, AuditLogService $audit): RedirectResponse
    {
        $client = Client::create($this->validated($request));
        $this->syncNewCategories($client, $request);
        $portalStatus = $this->syncPortalUser($client, $request);
        $audit->record('client.created', $client);

        return redirect()->route('clients.show', $client)->with('status', trim('Client créé. '.$portalStatus));
    }

    public function show(Client $client): View
    {
        return view('clients.show', ['client' => $client->load('categories', 'invoices')]);
    }

    public function edit(Client $client): View
    {
        $client->load('categories');

        return view('clients.form', [
            'client' => $client,
            'categoryRows' => $client->categories->where('is_active', true)->values()->map(fn ($category) => [
                'id' => $category->id,
                'name' => $category->name,
                'price' => number_format($category->default_price_cents / 100, 2, ',', ' '),
                'service_type' => $category->service_type,
                'audience' => $category->audience,
                'sort_order' => $category->sort_order,
            ]),
        ]);
    }

    public function update(Request $request, Client $client, AuditLogService $audit): RedirectResponse
    {
        $before = $client->toArray();
        $client->update($this->validated($request));
        $this->syncNewCategories($client, $request);
        $portalStatus = $this->syncPortalUser($client, $request);
        $audit->record('client.updated', $client, $before);

        return redirect()->route('clients.show', $client)->with('status', trim('Client mis à jour. '.$portalStatus));
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
            'invoice_style' => ['required', 'in:standard,hotel,compact'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'create_portal_user' => ['nullable', 'boolean'],
            'portal_password' => ['nullable', 'string', 'min:8'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['nullable', 'integer'],
            'category_names' => ['nullable', 'array'],
            'category_names.*' => ['nullable', 'string', 'max:255'],
            'category_prices' => ['nullable', 'array'],
            'category_prices.*' => ['nullable', 'string', 'max:50'],
            'category_service_types' => ['nullable', 'array'],
            'category_service_types.*' => ['nullable', 'in:'.implode(',', array_keys(ClientCategory::SERVICE_TYPES))],
            'category_audiences' => ['nullable', 'array'],
            'category_audiences.*' => ['nullable', 'in:'.implode(',', array_keys(ClientCategory::AUDIENCES))],
            'category_sort_orders' => ['nullable', 'array'],
            'category_sort_orders.*' => ['nullable', 'integer', 'min:0', 'max:999999'],
        ]) + ['is_active' => $request->boolean('is_active', true)];

        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store('client-logos', 'public');
        }

        unset(
            $data['logo'],
            $data['create_portal_user'],
            $data['portal_password'],
            $data['category_ids'],
            $data['category_names'],
            $data['category_prices'],
            $data['category_service_types'],
            $data['category_audiences'],
            $data['category_sort_orders'],
        );

        return $data;
    }

    private function syncPortalUser(Client $client, Request $request): string
    {
        if (! $request->boolean('create_portal_user') || blank($client->email)) {
            return '';
        }

        $email = mb_strtolower(trim($client->email));
        $user = User::where('email', $email)->first();

        if ($user && ! $user->isClientUser()) {
            return 'Aucun accès portail créé: ce courriel appartient déjà à un utilisateur interne.';
        }

        $password = $request->input('portal_password') ?: 'password';
        $payload = [
            'name' => $client->name,
            'role' => 'client',
            'client_id' => $client->id,
            'is_active' => true,
        ];

        if (! $user || filled($request->input('portal_password'))) {
            $payload['password'] = $password;
        }

        User::updateOrCreate(['email' => $email], $payload);

        return $request->filled('portal_password')
            ? 'Accès portail client créé/mis à jour.'
            : 'Accès portail client créé/mis à jour. Mot de passe temporaire: password';
    }

    private function syncNewCategories(Client $client, Request $request): void
    {
        $money = app(MoneyFormatter::class);
        $ids = $request->input('category_ids', []);
        $prices = $request->input('category_prices', []);
        $serviceTypes = $request->input('category_service_types', []);
        $audiences = $request->input('category_audiences', []);
        $sortOrders = $request->input('category_sort_orders', []);
        $rows = collect($request->input('category_names', []))
            ->map(fn ($name, $index) => [
                'id' => isset($ids[$index]) && (int) $ids[$index] > 0 ? (int) $ids[$index] : null,
                'name' => trim((string) $name),
                'price_cents' => max(0, $money->parse($prices[$index] ?? null)),
                'service_type' => array_key_exists($serviceTypes[$index] ?? '', ClientCategory::SERVICE_TYPES)
                    ? $serviceTypes[$index]
                    : 'other',
                'audience' => array_key_exists($audiences[$index] ?? '', ClientCategory::AUDIENCES)
                    ? $audiences[$index]
                    : 'unisex',
                'sort_order' => max(0, (int) ($sortOrders[$index] ?? ($index + 1))),
            ])
            ->filter(fn ($row) => filled($row['name']))
            ->unique(fn ($row) => $row['id']
                ? 'id:'.$row['id']
                : 'new:'.$row['service_type'].':'.$row['audience'].':'.mb_strtolower($row['name']))
            ->values();

        $existing = $client->categories()->get()->keyBy('id');
        $activeIds = [];

        foreach ($rows as $row) {
            $category = $row['id'] ? $existing->get($row['id']) : null;
            $payload = [
                'name' => $row['name'],
                'service_type' => $row['service_type'],
                'audience' => $row['audience'],
                'sort_order' => $row['sort_order'],
                'is_taxable' => true,
                'default_price_cents' => $row['price_cents'],
                'is_active' => true,
            ];

            if ($category) {
                $category->update($payload);
            } else {
                $category = $client->categories()->create($payload);
            }

            $activeIds[] = $category->id;
        }

        if ($activeIds === []) {
            $client->categories()->update(['is_active' => false]);
        } else {
            $client->categories()->whereNotIn('id', $activeIds)->update(['is_active' => false]);
        }
    }
}
