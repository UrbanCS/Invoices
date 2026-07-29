<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientCategory;
use App\Services\AuditLogService;
use App\Services\MoneyFormatter;
use App\Services\SharedCatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ClientCategoryController extends Controller
{
    public function index(Client $client): View
    {
        return view('clients.categories', [
            'client' => $client->load('categories'),
            'catalogTargets' => Client::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
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

    public function copy(
        Request $request,
        Client $client,
        SharedCatalogService $catalogs,
        AuditLogService $audit,
    ): RedirectResponse {
        $targetIds = $this->targetIds($request)->reject(fn (int $id) => $id === $client->id);

        if ($targetIds->isEmpty()) {
            throw ValidationException::withMessages([
                'target_client_ids' => 'Choisis au moins un autre client.',
            ]);
        }

        $sourceItemCount = $client->categories()->where('is_active', true)->count();
        if ($sourceItemCount === 0) {
            throw ValidationException::withMessages([
                'target_client_ids' => 'Le catalogue source ne contient aucun item actif.',
            ]);
        }

        $targets = Client::whereIn('id', $targetIds)->where('is_active', true)->get();
        foreach ($targets as $target) {
            $copied = $catalogs->copyActiveCatalog($client, $target);
            $audit->record(
                'client.catalog_copied',
                $target,
                ['source_client_id' => $client->id],
                ['source_client_id' => $client->id, 'active_items' => $copied],
            );
        }

        return back()->with(
            'status',
            "{$sourceItemCount} item(s) copié(s) vers {$targets->count()} client(s).",
        );
    }

    public function applyStoreTemplate(
        Request $request,
        Client $client,
        SharedCatalogService $catalogs,
        AuditLogService $audit,
    ): RedirectResponse {
        $targetIds = $this->targetIds($request);
        $targets = Client::whereIn('id', $targetIds)->where('is_active', true)->get();

        foreach ($targets as $target) {
            $applied = $catalogs->applyStoreOttawaCatalog($target);
            $audit->record(
                'client.store_catalog_applied',
                $target,
                ['template' => 'store_ottawa'],
                ['template' => 'store_ottawa', 'active_items' => $applied],
            );
        }

        return redirect()
            ->route('clients.categories.index', $client)
            ->with('status', "Catalogue commerces Ottawa appliqué à {$targets->count()} client(s).");
    }

    private function targetIds(Request $request): Collection
    {
        $data = $request->validate([
            'target_client_ids' => ['required', 'array', 'min:1'],
            'target_client_ids.*' => ['required', 'integer', 'distinct', 'exists:clients,id'],
        ]);

        return collect($data['target_client_ids'])->map(fn ($id) => (int) $id)->unique()->values();
    }
}
