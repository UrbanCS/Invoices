<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        return redirect()->route('clients.show', $client)->with('status', 'Client created.');
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

        return redirect()->route('clients.show', $client)->with('status', 'Client updated.');
    }

    public function destroy(Client $client): RedirectResponse
    {
        $client->update(['is_active' => false]);
        return redirect()->route('clients.index')->with('status', 'Client archived.');
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
