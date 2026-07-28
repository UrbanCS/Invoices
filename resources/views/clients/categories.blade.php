@extends('layouts.app')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3">
    <div>
        <p class="label">Catalogue et tarifs</p>
        <h1 class="text-3xl font-extrabold text-villeneuve-forest">{{ $client->name }}</h1>
    </div>
    <a class="btn btn-secondary" href="{{ route('clients.show', $client) }}">Retour au client</a>
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
