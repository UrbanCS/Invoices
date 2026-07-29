@extends('layouts.app')

@section('content')
@php
    $hasCategories = $selectedCategories->isNotEmpty();
    $singleCategory = $selectedCategories->count() === 1;
    $useCompactGrid = $selectedCategories->count() > 8;
    $inactiveCategoryCount = $selectedClient?->categories->where('is_active', false)->count() ?? 0;
    $willAutoApprove = ! $invoice->exists && auth()->user()->isSuperAdmin();
@endphp

<div class="flex flex-wrap items-center justify-between gap-4">
    <h1 class="text-3xl font-extrabold text-villeneuve-forest">{{ $invoice->exists ? 'Modifier la facture' : 'Nouvelle facture' }}</h1>
    @if(auth()->user()->isSuperAdmin())
        <a class="btn btn-secondary" href="{{ route('clients.create') }}">Ajouter un client</a>
    @endif
</div>

<form class="mt-6 space-y-6" method="post" action="{{ $invoice->exists ? route('monthly-invoices.update', $invoice) : route('monthly-invoices.store') }}">
    @csrf
    @if($invoice->exists)
        @method('put')
    @endif

    <section class="panel grid gap-4 p-6 md:grid-cols-5">
        <div>
            <label class="label">Client</label>
            <select
                class="mt-1 w-full"
                name="client_id"
                autocomplete="off"
                data-client-selector
                data-rendered-client-id="{{ $selectedClient?->id }}"
                required
            >
                @if($clients->isEmpty())
                    <option value="">Aucun client actif</option>
                @endif
                @foreach($clients as $client)
                    <option value="{{ $client->id }}" @selected($selectedClient?->id === $client->id)>
                        {{ $client->name }} — {{ $client->activeCategories->count() }} item(s) actif(s)
                    </option>
                @endforeach
            </select>
            @if($selectedClient)
                <p class="mt-1 text-xs font-semibold {{ $hasCategories ? 'text-emerald-700' : 'text-amber-700' }}">
                    Catalogue chargé : {{ $selectedCategories->count() }} item(s) actif(s).
                </p>
            @endif
        </div>
        <div><label class="label">No de facture</label><input class="mt-1 w-full" name="invoice_number" value="{{ old('invoice_number', $invoice->invoice_number) }}" required></div>
        <div><label class="label">Mois</label><input class="mt-1 w-full" type="number" min="1" max="12" name="invoice_month" value="{{ old('invoice_month', $invoice->invoice_month) }}" required></div>
        <div><label class="label">Année</label><input class="mt-1 w-full" type="number" name="invoice_year" value="{{ old('invoice_year', $invoice->invoice_year) }}" required></div>
        <div><label class="label">Date de facture</label><input class="mt-1 w-full" type="date" name="invoice_date" value="{{ old('invoice_date', optional($invoice->invoice_date)->format('Y-m-d') ?? now()->toDateString()) }}"></div>
        <div>
            <label class="label">Mode</label>
            <select class="mt-1 w-full" name="source_mode">
                <option value="manual_grid" @selected(old('source_mode', $invoice->source_mode) === 'manual_grid')>Grille mensuelle</option>
                <option value="daily_records" @selected(old('source_mode', $invoice->source_mode) === 'daily_records')>Depuis registres révisés</option>
            </select>
        </div>
        <div class="md:col-span-4"><label class="label">Notes / crédit</label><input class="mt-1 w-full" name="notes" value="{{ old('notes', $invoice->notes) }}"></div>
    </section>

    <section class="panel p-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h2 class="text-xl font-bold text-villeneuve-forest">Calcul item × quantité</h2>
                <p class="mt-1 text-sm text-stone-600">
                    Choisis un jour, un item et une quantité. Le prix fixe du catalogue et le total seront appliqués automatiquement.
                </p>
            </div>
            <button type="button" class="btn btn-secondary" data-item-add>
                {{ $useCompactGrid ? 'Ajouter à la facture' : 'Ajouter à la grille' }}
            </button>
        </div>
        <div class="mt-4 grid gap-3 md:grid-cols-5" data-item-calculator>
            <div>
                <label class="label">Jour</label>
                <input class="mt-1 w-full" type="number" min="1" max="31" value="1" data-item-day>
            </div>
            <div>
                <label class="label">Item</label>
                <select class="mt-1 w-full" data-item-category>
                    @foreach($selectedCategories->groupBy('service_type') as $serviceType => $serviceCategories)
                        @foreach($serviceCategories->groupBy('audience') as $audience => $audienceCategories)
                            <optgroup label="{{ App\Models\ClientCategory::serviceLabel($serviceType) }} · {{ App\Models\ClientCategory::audienceLabel($audience) }}">
                                @foreach($audienceCategories as $category)
                                    <option
                                        value="{{ $category->id }}"
                                        data-unit-price="{{ $category->default_price_cents }}"
                                    >{{ $category->name }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label">Quantité</label>
                <input class="mt-1 w-full text-right" type="number" min="0" step="0.01" value="1" data-item-quantity>
            </div>
            <div>
                <label class="label">Prix unitaire</label>
                <input class="mt-1 w-full bg-stone-100 text-right" inputmode="decimal" readonly value="0,00" data-item-unit-price>
            </div>
            <div>
                <label class="label">Total calculé</label>
                <input class="mt-1 w-full text-right font-bold text-villeneuve-forest" readonly value="0,00" data-item-total>
            </div>
        </div>
    </section>

    <section class="panel overflow-x-auto p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-xl font-bold text-villeneuve-forest">
                    {{ $useCompactGrid ? 'Items ajoutés à la facture' : 'Grille mensuelle' }}
                </h2>
                @if($useCompactGrid)
                    <p class="mt-1 text-sm text-stone-600">
                        Le catalogue contient {{ $selectedCategories->count() }} items. Utilise le calculateur ci-dessus;
                        chaque ajout apparaîtra dans ce résumé.
                    </p>
                @endif
            </div>
        </div>
        @if($clients->isEmpty())
            <div class="mt-4 border border-amber-200 bg-amber-50 p-3 text-sm font-semibold text-amber-900">
                Aucun client actif pour l’instant. Ajoute d’abord un client, puis reviens créer la facture.
            </div>
        @elseif(! $hasCategories)
            <div class="mt-4 border border-amber-200 bg-amber-50 p-3 text-sm font-semibold text-amber-900">
                @if($inactiveCategoryCount > 0)
                    Le client sélectionné possède {{ $inactiveCategoryCount }} item(s) inactif(s), mais aucun item actif.
                @else
                    Le client sélectionné n’a aucun item enregistré.
                @endif
                @if(auth()->user()->isSuperAdmin() && $selectedClient)
                    <a class="ml-2 underline" href="{{ route('clients.categories.index', $selectedClient) }}">
                        Ouvrir le catalogue et activer les items
                    </a>
                @endif
            </div>
        @endif

        @if($useCompactGrid)
            <div class="mt-4 border border-villeneuve-line" data-added-items-summary>
                <table class="w-full text-sm">
                    <thead>
                        <tr>
                            <th class="bg-villeneuve-mint p-3 text-left">Jour</th>
                            <th class="bg-villeneuve-mint p-3 text-left">Item</th>
                            <th class="bg-villeneuve-mint p-3 text-right">Quantité</th>
                            <th class="bg-villeneuve-mint p-3 text-right">Prix unitaire</th>
                            <th class="bg-villeneuve-mint p-3 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody data-added-items-body>
                        @foreach($entries->sortBy('service_day') as $entry)
                            @php($entryCategory = $selectedCategories->firstWhere('id', $entry->client_category_id))
                            @if(collect($entry->item_details)->isNotEmpty())
                                @foreach($entry->item_details as $detail)
                                    <tr class="border-t border-villeneuve-line">
                                        <td class="p-3 font-bold">{{ $entry->service_day }}</td>
                                        <td class="p-3">{{ $detail['label'] ?? $entryCategory?->name ?? 'Item' }}</td>
                                        <td class="p-3 text-right">{{ $detail['quantity'] ?? '—' }}</td>
                                        <td class="p-3 text-right">{{ number_format(($detail['unit_price_cents'] ?? 0) / 100, 2, ',', ' ') }} $</td>
                                        <td class="p-3 text-right font-bold">{{ number_format(($detail['total_cents'] ?? 0) / 100, 2, ',', ' ') }} $</td>
                                    </tr>
                                @endforeach
                            @elseif($entry->amount_cents > 0)
                                <tr class="border-t border-villeneuve-line">
                                    <td class="p-3 font-bold">{{ $entry->service_day }}</td>
                                    <td class="p-3">{{ $entryCategory?->name ?? $entry->category_name_snapshot }}</td>
                                    <td class="p-3 text-right">—</td>
                                    <td class="p-3 text-right">—</td>
                                    <td class="p-3 text-right font-bold">{{ number_format($entry->amount_cents / 100, 2, ',', ' ') }} $</td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
                <p
                    class="p-4 text-center text-sm text-stone-500"
                    data-added-items-empty
                    @if($entries->where('amount_cents', '>', 0)->isNotEmpty()) hidden @endif
                >
                    Aucun item ajouté pour l’instant.
                </p>
            </div>

            <details class="mt-4 border border-villeneuve-line p-4">
                <summary class="cursor-pointer font-bold text-villeneuve-forest">
                    Afficher la grille mensuelle détaillée ({{ $selectedCategories->count() }} items)
                </summary>
                <p class="mt-2 text-sm text-stone-600">
                    Cette grille sert uniquement aux corrections manuelles avancées.
                </p>
        @endif

        <table class="mt-4 w-full border-collapse text-sm">
            <thead>
                <tr>
                    <th class="border bg-villeneuve-mint p-2">Jour</th>
                    @foreach($selectedCategories as $category)
                        <th class="border bg-villeneuve-mint p-2 text-right">
                            @unless($singleCategory)
                                <span class="block text-[10px] font-semibold text-stone-500">
                                    {{ App\Models\ClientCategory::serviceLabel($category->service_type) }}
                                    · {{ App\Models\ClientCategory::audienceLabel($category->audience) }}
                                </span>
                            @endunless
                            {{ $singleCategory ? 'Montant' : $category->name }}
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @for($day = 1; $day <= 31; $day++)
                    <tr>
                        <td class="border p-2 font-bold">{{ $day }}</td>
                        @foreach($selectedCategories as $category)
                            @php($entry = $entries->first(fn ($e) => $e->service_day == $day && $e->client_category_id == $category->id))
                            <td class="border p-1 align-top">
                                <input class="w-full border-0 text-right" inputmode="decimal" placeholder="0,00" name="grid[{{ $day }}][{{ $category->id }}]" data-grid-day="{{ $day }}" data-grid-category="{{ $category->id }}" value="{{ old("grid.$day.$category->id", $entry ? number_format($entry->amount_cents / 100, 2) : '') }}">
                                <div class="mt-1 space-y-1 text-xs text-stone-600" data-detail-list data-detail-day="{{ $day }}" data-detail-category="{{ $category->id }}">
                                    @foreach($entry?->item_details ?? [] as $detailIndex => $detail)
                                        @php($unit = number_format(($detail['unit_price_cents'] ?? 0) / 100, 2, ',', ' '))
                                        @php($total = number_format(($detail['total_cents'] ?? 0) / 100, 2, ',', ' '))
                                        <div class="rounded bg-villeneuve-mint px-2 py-1">
                                            Qté {{ $detail['quantity'] ?? '' }} × Prix unit. {{ $unit }} $ = {{ $total }} $
                                        </div>
                                        <input type="hidden" name="details[{{ $day }}][{{ $category->id }}][{{ $detailIndex }}][label]" value="{{ $detail['label'] ?? $category->name }}">
                                        <input type="hidden" name="details[{{ $day }}][{{ $category->id }}][{{ $detailIndex }}][quantity]" value="{{ $detail['quantity'] ?? '' }}">
                                        <input type="hidden" name="details[{{ $day }}][{{ $category->id }}][{{ $detailIndex }}][unit_price]" value="{{ number_format(($detail['unit_price_cents'] ?? 0) / 100, 2, ',', ' ') }}">
                                    @endforeach
                                </div>
                            </td>
                        @endforeach
                    </tr>
                @endfor
            </tbody>
        </table>

        @if($useCompactGrid)
            </details>
        @endif
    </section>

    <section class="panel p-6">
        <h2 class="text-xl font-bold text-villeneuve-forest">Rabais, crédits et frais</h2>
        @for($i = 0; $i < 5; $i++)
            @php($adj = $adjustments[$i] ?? null)
            <div class="mt-3 grid gap-3 md:grid-cols-4">
                <input name="adjustments[{{ $i }}][label]" aria-label="Libellé" value="{{ $adj?->label }}">
                <select name="adjustments[{{ $i }}][type]" aria-label="Type">
                    <option value="discount" @selected($adj?->type === 'discount')>Rabais</option>
                    <option value="credit" @selected($adj?->type === 'credit')>Crédit</option>
                    <option value="fee" @selected($adj?->type === 'fee')>Frais</option>
                </select>
                <select name="adjustments[{{ $i }}][client_category_id]" aria-label="Catégorie">
                    <option value="">Facture entière</option>
                    @foreach($selectedCategories as $category)
                        <option value="{{ $category->id }}" @selected($adj?->client_category_id === $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
                <input class="text-right" name="adjustments[{{ $i }}][amount]" aria-label="Montant" value="{{ $adj ? number_format($adj->amount_cents / 100, 2) : '' }}">
            </div>
        @endfor
    </section>

    <div class="flex flex-wrap items-center gap-3">
        <button class="btn btn-primary" @disabled($clients->isEmpty() || ! $hasCategories)>
            @if($invoice->exists)
                Enregistrer les modifications
            @elseif($willAutoApprove)
                Créer la facture
            @else
                Sauvegarder brouillon
            @endif
        </button>
        @if($willAutoApprove)
            <p class="text-sm font-semibold text-emerald-700">
                La facture sera approuvée automatiquement.
            </p>
        @endif
    </div>
</form>

<script>
    (() => {
        const clientSelector = document.querySelector('[data-client-selector]');

        const loadSelectedClient = () => {
            if (! clientSelector?.value) return;

            const url = new URL(window.location.href);
            url.searchParams.set('client_id', clientSelector.value);
            url.searchParams.set('month', document.querySelector('[name=invoice_month]')?.value || '{{ $invoice->invoice_month }}');
            url.searchParams.set('year', document.querySelector('[name=invoice_year]')?.value || '{{ $invoice->invoice_year }}');
            window.location.assign(url.toString());
        };

        const synchronizeRestoredSelection = () => {
            if (
                clientSelector?.value
                && clientSelector.value !== clientSelector.dataset.renderedClientId
            ) {
                loadSelectedClient();
            }
        };

        clientSelector?.addEventListener('change', loadSelectedClient);
        window.addEventListener('pageshow', synchronizeRestoredSelection);
        synchronizeRestoredSelection();

        const calculator = document.querySelector('[data-item-calculator]');
        if (! calculator) return;

        const dayInput = calculator.querySelector('[data-item-day]');
        const categoryInput = calculator.querySelector('[data-item-category]');
        const quantityInput = calculator.querySelector('[data-item-quantity]');
        const unitPriceInput = calculator.querySelector('[data-item-unit-price]');
        const totalInput = calculator.querySelector('[data-item-total]');
        const addButton = document.querySelector('[data-item-add]');
        const addedItemsBody = document.querySelector('[data-added-items-body]');
        const addedItemsEmpty = document.querySelector('[data-added-items-empty]');
        let detailIndex = Date.now();

        const parseMoney = (value) => {
            const normalized = String(value || '')
                .replace(/\s/g, '')
                .replace('$', '')
                .replace(',', '.');

            const amount = Number.parseFloat(normalized);

            return Number.isFinite(amount) ? amount : 0;
        };

        const formatMoney = (amount) => amount.toFixed(2).replace('.', ',');

        const currentTotal = () => {
            const quantity = Number.parseFloat(quantityInput.value || '0') || 0;
            const unitPrice = parseMoney(unitPriceInput.value);

            return quantity * unitPrice;
        };

        const updateTotal = () => {
            totalInput.value = formatMoney(currentTotal());
        };

        const useCatalogPrice = () => {
            const option = categoryInput.options[categoryInput.selectedIndex];
            const cents = Number.parseInt(option?.dataset.unitPrice || '0', 10);
            unitPriceInput.value = formatMoney(cents / 100);
            updateTotal();
        };

        const addToGrid = () => {
            const day = dayInput.value;
            const category = categoryInput.value;
            const total = currentTotal();
            const target = document.querySelector(`[data-grid-day="${day}"][data-grid-category="${category}"]`);
            const detailList = document.querySelector(`[data-detail-day="${day}"][data-detail-category="${category}"]`);
            const selectedLabel = categoryInput.options[categoryInput.selectedIndex]?.text || 'Item';

            if (! target || ! detailList || total <= 0) return;

            const existing = parseMoney(target.value);
            target.value = formatMoney(existing + total);
            detailIndex++;

            const row = document.createElement('div');
            row.className = 'rounded bg-villeneuve-mint px-2 py-1';
            row.textContent = `Qté ${quantityInput.value || 0} × Prix unit. ${formatMoney(parseMoney(unitPriceInput.value))} $ = ${formatMoney(total)} $`;
            detailList.appendChild(row);

            const fields = {
                label: selectedLabel,
                quantity: quantityInput.value || '0',
                unit_price: formatMoney(parseMoney(unitPriceInput.value)),
            };

            Object.entries(fields).forEach(([name, value]) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = `details[${day}][${category}][${detailIndex}][${name}]`;
                input.value = value;
                detailList.appendChild(input);
            });

            if (addedItemsBody) {
                const summaryRow = document.createElement('tr');
                summaryRow.className = 'border-t border-villeneuve-line';

                [
                    { value: day, className: 'p-3 font-bold' },
                    { value: selectedLabel, className: 'p-3' },
                    { value: quantityInput.value || '0', className: 'p-3 text-right' },
                    { value: `${formatMoney(parseMoney(unitPriceInput.value))} $`, className: 'p-3 text-right' },
                    { value: `${formatMoney(total)} $`, className: 'p-3 text-right font-bold' },
                ].forEach(({ value, className }) => {
                    const cell = document.createElement('td');
                    cell.className = className;
                    cell.textContent = value;
                    summaryRow.appendChild(cell);
                });

                addedItemsBody.appendChild(summaryRow);
                if (addedItemsEmpty) addedItemsEmpty.hidden = true;
            }

            quantityInput.value = '1';
            useCatalogPrice();
            if (target.offsetParent !== null) target.focus();
        };

        quantityInput.addEventListener('input', updateTotal);
        categoryInput.addEventListener('change', useCatalogPrice);
        addButton?.addEventListener('click', addToGrid);
        useCatalogPrice();
    })();
</script>
@endsection
