@extends('layouts.app')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3">
    <div>
        <p class="label">Catalogue et tarifs</p>
        <h1 class="text-3xl font-extrabold text-villeneuve-forest">{{ $client->name }}</h1>
    </div>
    <div class="flex flex-wrap gap-2">
        @if($client->categories->where('is_active', false)->isNotEmpty())
            <form method="post" action="{{ route('clients.categories.activate-all', $client) }}">
                @csrf
                <button class="btn btn-primary">Activer tous les items</button>
            </form>
        @endif
        <a class="btn btn-secondary" href="{{ route('clients.show', $client) }}">Retour au client</a>
    </div>
</div>

<div class="mt-6 grid gap-3 sm:grid-cols-3">
    <div class="panel p-4">
        <span class="label">Items enregistrés</span>
        <strong class="mt-1 block text-2xl text-villeneuve-forest">{{ $client->categories->count() }}</strong>
    </div>
    <div class="panel p-4">
        <span class="label">Items actifs</span>
        <strong class="mt-1 block text-2xl text-emerald-700">{{ $client->categories->where('is_active', true)->count() }}</strong>
    </div>
    <div class="panel p-4">
        <span class="label">Items inactifs</span>
        <strong class="mt-1 block text-2xl text-amber-700">{{ $client->categories->where('is_active', false)->count() }}</strong>
    </div>
</div>

<div class="mt-6 grid gap-6 lg:grid-cols-3">
    <form class="panel p-5" method="post" action="{{ route('clients.categories.copy', $client) }}">
        @csrf
        <h2 class="text-xl font-bold text-villeneuve-forest">Copier ce catalogue</h2>
        <p class="mt-1 text-sm text-stone-600">
            Les items actifs et leurs prix seront copiés. Les anciens items des clients ciblés seront désactivés, sans modifier leurs anciennes factures.
        </p>
        <label class="label mt-4" for="copy_target_client_ids">Clients ciblés</label>
        <select id="copy_target_client_ids" class="mt-1 min-h-44 w-full" name="target_client_ids[]" multiple required>
            @foreach($catalogTargets->where('id', '!=', $client->id) as $target)
                <option value="{{ $target->id }}">{{ $target->name }}</option>
            @endforeach
        </select>
        <p class="mt-1 text-xs text-stone-500">Maintiens Ctrl (Windows) ou Cmd (Mac) pour sélectionner plusieurs clients.</p>
        <button class="btn btn-primary mt-4">Copier vers les clients sélectionnés</button>
    </form>

    <form class="panel p-5" method="post" action="{{ route('clients.categories.apply-store-template', $client) }}">
        @csrf
        <h2 class="text-xl font-bold text-villeneuve-forest">Modèle commerces Ottawa</h2>
        <p class="mt-1 text-sm text-stone-600">
            Applique la liste complète du document Word « price list store ottawa », incluant les prix fixes et les prix de base.
        </p>
        <label class="label mt-4" for="store_target_client_ids">Commerces ciblés</label>
        <select id="store_target_client_ids" class="mt-1 min-h-44 w-full" name="target_client_ids[]" multiple required>
            @foreach($catalogTargets as $target)
                <option value="{{ $target->id }}">{{ $target->name }}</option>
            @endforeach
        </select>
        <p class="mt-1 text-xs text-stone-500">Le profil de taxes de chaque commerce est conservé.</p>
        <button class="btn btn-primary mt-4">Appliquer le modèle aux commerces</button>
    </form>

    <form
        class="panel p-5"
        method="post"
        action="{{ route('clients.categories.apply-employee-template', $client) }}"
        onsubmit="return confirm('Ajouter ou mettre à jour la section EMPLOYÉS dans tous les hôtels affichés? Les autres items seront conservés.');"
    >
        @csrf
        <h2 class="text-xl font-bold text-villeneuve-forest">Modèle EMPLOYÉS</h2>
        <p class="mt-1 text-sm text-stone-600">
            Ajoute les 8 items et prix de base demandés sans remplacer les catalogues existants.
            Hilton Lac-Leamy est exclu.
        </p>
        <div class="mt-4 rounded border border-villeneuve-line bg-stone-50 p-3 text-sm">
            <div class="label">Hôtels ciblés</div>
            @forelse($employeeHotelTargets as $target)
                <div class="mt-1">{{ $target->name }}</div>
            @empty
                <div class="mt-1 text-amber-800">Aucun hôtel configuré trouvé.</div>
            @endforelse
        </div>
        <button class="btn btn-primary mt-4" @disabled($employeeHotelTargets->isEmpty())>
            Appliquer EMPLOYÉS aux hôtels
        </button>
    </form>
</div>

<div class="mt-6 grid gap-6 xl:grid-cols-[1fr_360px]">
    <section class="panel overflow-x-auto">
        <div class="border-b border-villeneuve-line p-5">
            <h2 class="text-xl font-bold text-villeneuve-forest">Items sauvegardés</h2>
            <p class="mt-1 text-sm text-stone-600">Modifie un item ou son numéro d’ordre, puis clique sur « Sauvegarder ».</p>
        </div>
        <table class="table min-w-[1050px] w-full">
            <tr>
                <th>Service</th>
                <th>Section</th>
                <th>Item</th>
                <th class="text-right">Prix fixe</th>
                <th class="text-right">Ordre</th>
                <th>Taxable</th>
                <th>Actif</th>
                <th></th>
            </tr>
            @forelse($client->categories as $category)
                @php($formId = 'category-update-'.$category->id)
                <tr class="{{ $category->is_active ? '' : 'opacity-60' }}">
                    <td>
                        <select class="w-full min-w-48" name="service_type" form="{{ $formId }}">
                            @foreach(App\Models\ClientCategory::SERVICE_TYPES as $value => $label)
                                <option value="{{ $value }}" @selected($category->service_type === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td>
                        <select class="w-full min-w-40" name="audience" form="{{ $formId }}">
                            @foreach(App\Models\ClientCategory::AUDIENCES as $value => $label)
                                <option value="{{ $value }}" @selected($category->audience === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td><input class="w-full min-w-40" name="name" value="{{ $category->name }}" form="{{ $formId }}" required></td>
                    <td><input class="w-28 text-right" name="default_price" value="{{ number_format($category->default_price_cents / 100, 2, ',', ' ') }}" inputmode="decimal" form="{{ $formId }}"></td>
                    <td><input class="w-20 text-right" type="number" min="0" name="sort_order" value="{{ $category->sort_order }}" form="{{ $formId }}" required></td>
                    <td><input type="checkbox" name="is_taxable" value="1" form="{{ $formId }}" @checked($category->is_taxable)></td>
                    <td><input type="checkbox" name="is_active" value="1" form="{{ $formId }}" @checked($category->is_active)></td>
                    <td>
                        <div class="flex gap-2">
                            <form id="{{ $formId }}" method="post" action="{{ route('clients.categories.update', [$client, $category]) }}">
                                @csrf
                                @method('put')
                                <button class="btn btn-primary">Sauvegarder</button>
                            </form>
                            @if($category->is_active)
                                <form method="post" action="{{ route('clients.categories.destroy', [$client, $category]) }}" onsubmit="return confirm('Retirer cet item du catalogue? Les anciennes commandes seront conservées.');">
                                    @csrf
                                    @method('delete')
                                    <button class="btn btn-secondary text-red-700">Retirer</button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="py-6 text-center text-stone-500">Aucun item dans ce catalogue.</td></tr>
            @endforelse
        </table>
    </section>
    <form class="panel space-y-4 p-5" method="post" action="{{ route('clients.categories.store', $client) }}">
        @csrf
        <h2 class="text-xl font-bold text-villeneuve-forest">Ajouter un item</h2>
        <div>
            <label class="label">Service</label>
            <select class="mt-1 w-full" name="service_type">
                @foreach(App\Models\ClientCategory::SERVICE_TYPES as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label">Section</label>
            <select class="mt-1 w-full" name="audience">
                @foreach(App\Models\ClientCategory::AUDIENCES as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div><label class="label">Nom de l’item</label><input class="mt-1 w-full" name="name" placeholder="Ex. Complet / Suit" required></div>
        <div><label class="label">Prix fixe</label><input class="mt-1 w-full text-right" name="default_price" inputmode="decimal" placeholder="0,00"></div>
        <div><label class="label">Ordre dans la section</label><input class="mt-1 w-full" type="number" min="0" name="sort_order" value="1"></div>
        <label class="flex gap-2"><input type="checkbox" name="is_taxable" value="1" checked> Taxable</label>
        <label class="flex gap-2"><input type="checkbox" name="is_active" value="1" checked> Active</label>
        <button class="btn btn-primary w-full">Ajouter au catalogue</button>
    </form>
</div>
@endsection
