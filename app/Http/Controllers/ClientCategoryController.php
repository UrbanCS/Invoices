<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientCategory;
use App\Services\MoneyFormatter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClientCategoryController extends Controller
{
    public function index(Client $client): View
    {
        return view('clients.categories', ['client' => $client->load('categories')]);
    }

    public function store(Request $request, Client $client, MoneyFormatter $money): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'service_type' => ['required', 'in:'.implode(',', array_keys(ClientCategory::SERVICE_TYPES))],
            'audience' => ['required', 'in:'.implode(',', array_keys(ClientCategory::AUDIENCES))],
            'default_price' => ['nullable', 'string', 'max:50'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_taxable' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $defaultPriceCents = max(0, $money->parse($data['default_price'] ?? null));
        unset($data['default_price']);

        $client->categories()->create([
            ...$data,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_taxable' => $request->boolean('is_taxable', true),
            'default_price_cents' => $defaultPriceCents,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return back()->with('status', 'Catégorie sauvegardée.');
    }

    public function update(
        Request $request,
        Client $client,
        ClientCategory $category,
        MoneyFormatter $money,
    ): RedirectResponse {
        abort_unless($category->client_id === $client->id, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'service_type' => ['required', 'in:'.implode(',', array_keys(ClientCategory::SERVICE_TYPES))],
            'audience' => ['required', 'in:'.implode(',', array_keys(ClientCategory::AUDIENCES))],
            'default_price' => ['nullable', 'string', 'max:50'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999999'],
            'is_taxable' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $category->update([
            'name' => $data['name'],
            'service_type' => $data['service_type'],
            'audience' => $data['audience'],
            'sort_order' => $data['sort_order'],
            'is_taxable' => $request->boolean('is_taxable'),
            'default_price_cents' => max(0, $money->parse($data['default_price'] ?? null)),
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('status', 'Item et ordre d’affichage mis à jour.');
    }

    public function destroy(Client $client, ClientCategory $category): RedirectResponse
    {
        abort_unless($category->client_id === $client->id, 404);
        $category->update(['is_active' => false]);

        return back()->with('status', 'Item retiré du catalogue. Les anciennes commandes restent intactes.');
    }

    public function activateAll(Client $client): RedirectResponse
    {
        $activated = $client->categories()
            ->where('is_active', false)
            ->update(['is_active' => true]);

        return back()->with(
            'status',
            $activated > 0
                ? "{$activated} item(s) réactivé(s). Ils sont maintenant disponibles dans les commandes et les factures."
                : 'Tous les items du catalogue sont déjà actifs.',
        );
    }
}
