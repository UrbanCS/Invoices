@extends('layouts.app')

@section('content')
@php
    $catalogRows = collect($categoryRows ?? []);
    $oldCategoryNames = old('category_names');

    if (is_array($oldCategoryNames)) {
        $oldCategoryIds = old('category_ids', []);
        $oldCategoryPrices = old('category_prices', []);
        $oldServiceTypes = old('category_service_types', []);
        $oldAudiences = old('category_audiences', []);
        $oldSortOrders = old('category_sort_orders', []);
        $catalogRows = collect($oldCategoryNames)->map(fn ($name, $index) => [
            'id' => $oldCategoryIds[$index] ?? null,
            'name' => $name,
            'price' => $oldCategoryPrices[$index] ?? '',
            'service_type' => $oldServiceTypes[$index] ?? 'dry_cleaning',
            'audience' => $oldAudiences[$index] ?? 'gentlemen',
            'sort_order' => $oldSortOrders[$index] ?? ($index + 1),
        ]);
    }

    if ($catalogRows->isEmpty()) {
        $catalogRows = collect([[
            'id' => null,
            'name' => '',
            'price' => '',
            'service_type' => 'dry_cleaning',
            'audience' => 'gentlemen',
            'sort_order' => 1,
        ]]);
    }
@endphp

<h1 class="text-3xl font-extrabold text-villeneuve-forest">{{ $client->exists ? 'Modifier le client' : 'Nouveau client' }}</h1>

<form class="panel mt-6 grid gap-4 p-6 md:grid-cols-2" method="post" enctype="multipart/form-data" action="{{ $client->exists ? route('clients.update', $client) : route('clients.store') }}">
    @csrf
    @if($client->exists)
        @method('put')
    @endif

    <div><label class="label">Nom</label><input class="mt-1 w-full" name="name" value="{{ old('name', $client->name) }}" required></div>
    <div><label class="label">Nom légal</label><input class="mt-1 w-full" name="legal_name" value="{{ old('legal_name', $client->legal_name) }}"></div>
    <div class="md:col-span-2"><label class="label">Adresse de facturation</label><input class="mt-1 w-full" name="billing_address" value="{{ old('billing_address', $client->billing_address) }}"></div>
    <div><label class="label">Ville</label><input class="mt-1 w-full" name="city" value="{{ old('city', $client->city) }}"></div>
    <div><label class="label">Province</label><input class="mt-1 w-full" name="province" value="{{ old('province', $client->province) }}"></div>
    <div><label class="label">Code postal</label><input class="mt-1 w-full" name="postal_code" value="{{ old('postal_code', $client->postal_code) }}"></div>
    <div><label class="label">Courriel</label><input class="mt-1 w-full" name="email" value="{{ old('email', $client->email) }}"></div>
    <div class="rounded border border-villeneuve-line bg-villeneuve-mint/50 p-4">
        <label class="flex items-center gap-2 font-bold text-villeneuve-forest">
            <input
                type="checkbox"
                name="create_portal_user"
                value="1"
                @checked(old('create_portal_user', filled($client->email)))
            >
            Créer / mettre à jour l’accès portail client
        </label>
        <p class="mt-2 text-sm text-stone-600">
            Le client pourra se connecter avec le courriel ci-dessus et verra seulement ses propres factures.
        </p>
        <label class="label mt-3">Mot de passe temporaire</label>
        <input
            class="mt-1 w-full"
            name="portal_password"
            type="text"
            placeholder="{{ $client->exists ? 'Laisser vide pour conserver le mot de passe' : 'Laisser vide pour utiliser password' }}"
        >
    </div>
    <div>
        <label class="label">Profil de taxes</label>
        <select class="mt-1 w-full" name="tax_profile">
            <option value="on_hst" @selected(old('tax_profile', $client->tax_profile) === 'on_hst')>TVH Ontario</option>
            <option value="qc_tps_tvq" @selected(old('tax_profile', $client->tax_profile) === 'qc_tps_tvq')>TPS/TVQ Québec</option>
            <option value="custom" @selected(old('tax_profile', $client->tax_profile) === 'custom')>Personnalisé</option>
        </select>
    </div>
    <div>
        <label class="label">Langue</label>
        <select class="mt-1 w-full" name="default_language">
            <option value="fr" @selected(old('default_language', $client->default_language) === 'fr')>Français</option>
        </select>
    </div>
    <div>
        <label class="label">Style de facture PDF</label>
        <select class="mt-1 w-full" name="invoice_style">
            <option value="standard" @selected(old('invoice_style', $client->invoice_style ?? 'standard') === 'standard')>Standard Nettoyeur Villeneuve</option>
            <option value="hotel" @selected(old('invoice_style', $client->invoice_style ?? 'standard') === 'hotel')>Hôtel / client</option>
            <option value="compact" @selected(old('invoice_style', $client->invoice_style ?? 'standard') === 'compact')>Compact entreprise</option>
        </select>
        <p class="mt-1 text-sm text-stone-600">Ce choix adapte la présentation du PDF pour ce client.</p>
    </div>
    <div>
        <span class="label">Logo du client</span>
        <label class="btn btn-secondary mt-1 w-full cursor-pointer">
            Choisir un fichier
            <input class="sr-only" type="file" name="logo">
        </label>
    </div>
    <div class="md:col-span-2">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <label class="label">Catalogue de nettoyage et prix</label>
                <p class="mt-1 text-sm text-stone-600">
                    Classe chaque item par service et clientèle. Le client choisira seulement les items et quantités; les prix resteront verrouillés.
                </p>
            </div>
            <button class="btn btn-secondary" type="button" data-catalog-add>Ajouter un item</button>
        </div>

        <div class="mt-4 hidden grid-cols-[1.2fr_1fr_1.4fr_140px_90px_130px] gap-2 px-3 md:grid">
            <span class="label">Service</span>
            <span class="label">Section</span>
            <span class="label">Item</span>
            <span class="label text-right">Prix fixe</span>
            <span class="label text-right">Ordre</span>
            <span class="label">Actions</span>
        </div>

        <div class="mt-2 grid gap-3" data-catalog-rows>
            @foreach($catalogRows as $row)
                <div class="grid gap-2 rounded border border-villeneuve-line bg-stone-50 p-3 md:grid-cols-[1.2fr_1fr_1.4fr_140px_90px_130px]" data-catalog-row>
                    <input type="hidden" name="category_ids[]" value="{{ $row['id'] ?? '' }}">
                    <select class="w-full" name="category_service_types[]" aria-label="Type de service">
                        @foreach(App\Models\ClientCategory::SERVICE_TYPES as $value => $label)
                            <option value="{{ $value }}" @selected(($row['service_type'] ?? 'other') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <select class="w-full" name="category_audiences[]" aria-label="Section">
                        @foreach(App\Models\ClientCategory::AUDIENCES as $value => $label)
                            <option value="{{ $value }}" @selected(($row['audience'] ?? 'unisex') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <input
                        class="w-full"
                        name="category_names[]"
                        value="{{ $row['name'] ?? '' }}"
                        placeholder="Ex. Complet / Suit"
                    >
                    <input
                        class="w-full text-right"
                        name="category_prices[]"
                        inputmode="decimal"
                        value="{{ $row['price'] ?? '' }}"
                        placeholder="0,00"
                    >
                    <input
                        class="w-full text-right"
                        type="number"
                        min="0"
                        name="category_sort_orders[]"
                        value="{{ $row['sort_order'] ?? $loop->iteration }}"
                        data-catalog-order
                        aria-label="Ordre d’affichage"
                    >
                    <div class="flex gap-1">
                        <button class="btn btn-secondary px-3" type="button" title="Monter" data-catalog-up>↑</button>
                        <button class="btn btn-secondary px-3" type="button" title="Descendre" data-catalog-down>↓</button>
                        <button class="btn btn-secondary px-3 text-red-700" type="button" title="Retirer" data-catalog-remove>×</button>
                    </div>
                </div>
            @endforeach
        </div>

        <template data-catalog-template>
            <div class="grid gap-2 rounded border border-villeneuve-line bg-stone-50 p-3 md:grid-cols-[1.2fr_1fr_1.4fr_140px_90px_130px]" data-catalog-row>
                <input type="hidden" name="category_ids[]" value="">
                <select class="w-full" name="category_service_types[]" aria-label="Type de service">
                    @foreach(App\Models\ClientCategory::SERVICE_TYPES as $value => $label)
                        <option value="{{ $value }}" @selected($value === 'dry_cleaning')>{{ $label }}</option>
                    @endforeach
                </select>
                <select class="w-full" name="category_audiences[]" aria-label="Section">
                    @foreach(App\Models\ClientCategory::AUDIENCES as $value => $label)
                        <option value="{{ $value }}" @selected($value === 'gentlemen')>{{ $label }}</option>
                    @endforeach
                </select>
                <input class="w-full" name="category_names[]" placeholder="Ex. Complet / Suit">
                <input class="w-full text-right" name="category_prices[]" inputmode="decimal" placeholder="0,00">
                <input class="w-full text-right" type="number" min="0" name="category_sort_orders[]" value="" data-catalog-order aria-label="Ordre d’affichage">
                <div class="flex gap-1">
                    <button class="btn btn-secondary px-3" type="button" title="Monter" data-catalog-up>↑</button>
                    <button class="btn btn-secondary px-3" type="button" title="Descendre" data-catalog-down>↓</button>
                    <button class="btn btn-secondary px-3 text-red-700" type="button" title="Retirer" data-catalog-remove>×</button>
                </div>
            </div>
        </template>
    </div>
    <div class="md:col-span-2"><label class="label">Notes</label><textarea class="mt-1 w-full" name="notes">{{ old('notes', $client->notes) }}</textarea></div>
    <label class="flex items-center gap-2"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $client->is_active ?? true))> Actif</label>
    <div class="md:col-span-2"><button class="btn btn-primary">Sauvegarder</button></div>
</form>

<script>
    (() => {
        const container = document.querySelector('[data-catalog-rows]');
        const template = document.querySelector('[data-catalog-template]');
        const addButton = document.querySelector('[data-catalog-add]');

        if (!container || !template || !addButton) return;

        const rows = () => [...container.querySelectorAll('[data-catalog-row]')];
        const renumber = () => rows().forEach((row, index) => {
            row.querySelector('[data-catalog-order]').value = index + 1;
        });

        addButton.addEventListener('click', () => {
            const row = template.content.firstElementChild.cloneNode(true);
            container.appendChild(row);
            row.querySelector('[data-catalog-order]').value = rows().length;
            row.querySelector('[name="category_names[]"]').focus();
        });

        container.addEventListener('click', (event) => {
            const row = event.target.closest('[data-catalog-row]');
            if (!row) return;

            if (event.target.closest('[data-catalog-up]') && row.previousElementSibling) {
                container.insertBefore(row, row.previousElementSibling);
                renumber();
            }

            if (event.target.closest('[data-catalog-down]') && row.nextElementSibling) {
                container.insertBefore(row.nextElementSibling, row);
                renumber();
            }

            if (event.target.closest('[data-catalog-remove]')) {
                row.remove();
                renumber();
            }
        });
    })();
</script>
@endsection
