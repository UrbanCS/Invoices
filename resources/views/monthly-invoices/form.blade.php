@extends('layouts.app')

@section('content')
@php
    $hasCategories = $selectedCategories->isNotEmpty();
    $singleCategory = $selectedCategories->count() === 1;
    $useCompactGrid = $selectedCategories->count() > 8;
    $inactiveCategoryCount = $selectedClient?->categories->where('is_active', false)->count() ?? 0;
    $willAutoApprove = ! $invoice->exists && auth()->user()->isSuperAdmin();
    $hasEmployeeCategories = $selectedCategories->contains('audience', 'employees');
    $hasGuestCategories = $selectedCategories->contains(fn ($category) => $category->audience !== 'employees');
    $defaultBillingType = $hasEmployeeCategories ? 'employee' : 'hotel_guest';
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
                Ajouter à la facture
            </button>
        </div>
        <div class="mt-4 grid gap-3 md:grid-cols-6" data-item-calculator data-default-billing-type="{{ $defaultBillingType }}">
            <div>
                <label class="label">Jour</label>
                <input class="mt-1 w-full" type="number" min="1" max="31" value="1" data-item-day>
            </div>
            <div>
                <label class="label">Type</label>
                <select class="mt-1 w-full" data-item-billing-type>
                    <option value="employee" @disabled(! $hasEmployeeCategories)>Employé</option>
                    <option value="hotel_guest" @disabled(! $hasGuestCategories)>Client de l’hôtel</option>
                </select>
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
                                        data-billing-type="{{ $category->audience === 'employees' ? 'employee' : 'hotel_guest' }}"
                                        data-item-label="{{ $category->name }}"
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

        <div class="mt-3 flex justify-end">
            <button type="button" class="btn btn-secondary" data-item-stage>Ajouter item</button>
        </div>

        <div class="mt-4 overflow-hidden rounded border border-villeneuve-line" data-pending-items hidden>
            <div class="bg-villeneuve-mint px-4 py-3">
                <h3 class="font-bold text-villeneuve-forest">Items de la même journée à ajouter</h3>
                <p class="mt-1 text-xs text-stone-600">Ajoute tous les items, puis confirme-les ensemble avec « Ajouter à la facture ».</p>
            </div>
            <table class="w-full text-sm">
                <thead>
                    <tr>
                        <th class="p-3 text-left">Item</th>
                        <th class="p-3 text-right">Quantité</th>
                        <th class="p-3 text-right">Prix unitaire</th>
                        <th class="p-3 text-right">Total</th>
                        <th class="p-3"></th>
                    </tr>
                </thead>
                <tbody data-pending-items-body></tbody>
            </table>
        </div>

        <div class="mt-4 grid gap-3 rounded border border-villeneuve-line bg-stone-50 p-4 md:grid-cols-3" data-item-identity="employee">
            <div>
                <label class="label">Nom de l’employé</label>
                <input class="mt-1 w-full" list="employee-name-options" autocomplete="off" data-item-person-name placeholder="Ex. Julian">
                <datalist id="employee-name-options" data-employee-name-options>
                    @foreach($selectedClient?->employeeNames ?? collect() as $employeeName)
                        <option value="{{ $employeeName->name }}"></option>
                    @endforeach
                </datalist>
                <p class="mt-1 text-xs text-stone-500">Choisis un nom déjà utilisé ou écris-en un nouveau. Il sera mémorisé pour cet hôtel.</p>
            </div>
            <div>
                <label class="label">No d’étiquette</label>
                <input class="mt-1 w-full" data-item-reference-number placeholder="Ex. 0096">
            </div>
            <div>
                <label class="label">No de département (facultatif)</label>
                <input class="mt-1 w-full" data-item-department-number placeholder="Ex. 2357">
            </div>
        </div>

        <div class="mt-4 grid gap-3 rounded border border-villeneuve-line bg-stone-50 p-4 md:grid-cols-3" data-item-identity="hotel_guest">
            <div>
                <label class="label">Nom du client</label>
                <input class="mt-1 w-full" data-item-person-name placeholder="Nom du client de l’hôtel">
            </div>
            <div>
                <label class="label">No d’étiquette</label>
                <input class="mt-1 w-full" data-item-reference-number placeholder="Ex. 0096">
            </div>
            <div>
                <label class="label">No de chambre</label>
                <input class="mt-1 w-full" data-item-room-number placeholder="Ex. 478">
            </div>
        </div>

        <p class="mt-3 text-sm font-semibold text-red-700" data-item-error hidden></p>
        <div class="mt-4 flex justify-end">
            <button type="button" class="btn btn-primary" data-item-add>Ajouter à la facture</button>
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

        <div class="mt-4 overflow-x-auto border border-villeneuve-line">
            <div class="border-b border-villeneuve-line bg-stone-50 px-4 py-3">
                <h3 class="font-bold text-villeneuve-forest">Répartition mensuelle</h3>
                <p class="mt-1 text-xs text-stone-600">Les montants sont séparés automatiquement selon le type et le tarif choisis.</p>
            </div>
            <table class="w-full min-w-[560px] border-collapse text-sm">
                <thead>
                    <tr>
                        <th class="border bg-villeneuve-mint p-2 text-left">Jour</th>
                        <th class="border bg-villeneuve-mint p-2 text-right">EMPLOYÉS</th>
                        <th class="border bg-villeneuve-mint p-2 text-right">CLIENTS</th>
                        <th class="border bg-villeneuve-mint p-2 text-right">Total du jour</th>
                    </tr>
                </thead>
                <tbody>
                    @for($day = 1; $day <= 31; $day++)
                        <tr>
                            <td class="border p-2 font-bold">{{ $day }}</td>
                            <td class="border p-2 text-right tabular-nums" data-billing-summary-day="{{ $day }}" data-billing-summary-type="employee">0,00 $</td>
                            <td class="border p-2 text-right tabular-nums" data-billing-summary-day="{{ $day }}" data-billing-summary-type="hotel_guest">0,00 $</td>
                            <td class="border p-2 text-right font-semibold tabular-nums" data-billing-summary-day-total="{{ $day }}">0,00 $</td>
                        </tr>
                    @endfor
                </tbody>
                <tfoot>
                    <tr class="font-black text-villeneuve-forest">
                        <td class="border bg-villeneuve-mint p-2">Sous-totaux</td>
                        <td class="border bg-villeneuve-mint p-2 text-right" data-billing-summary-subtotal="employee">0,00 $</td>
                        <td class="border bg-villeneuve-mint p-2 text-right" data-billing-summary-subtotal="hotel_guest">0,00 $</td>
                        <td class="border bg-villeneuve-mint p-2 text-right" data-billing-summary-grand-total>0,00 $</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        @if($useCompactGrid)
            <div class="mt-4 border border-villeneuve-line" data-added-items-summary>
                <table class="w-full text-sm">
                    <thead>
                        <tr>
                            <th class="bg-villeneuve-mint p-3 text-left">Jour</th>
                            <th class="bg-villeneuve-mint p-3 text-left">Type</th>
                            <th class="bg-villeneuve-mint p-3 text-left">Nom / référence</th>
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
                                    @php($detailBillingType = $detail['billing_type'] ?? ($entryCategory?->audience === 'employees' ? 'employee' : 'hotel_guest'))
                                    @php($detailHasRoomNumber = array_key_exists('room_number', $detail))
                                    @php($detailTagNumber = $detailBillingType === 'employee' || $detailHasRoomNumber ? ($detail['reference_number'] ?? null) : null)
                                    @php($detailRoomNumber = $detailBillingType === 'hotel_guest' ? ($detailHasRoomNumber ? ($detail['room_number'] ?? null) : ($detail['reference_number'] ?? null)) : null)
                                    <tr class="border-t border-villeneuve-line">
                                        <td class="p-3 font-bold">{{ $entry->service_day }}</td>
                                        <td class="p-3">{{ $detailBillingType === 'employee' ? 'Employé' : 'Client de l’hôtel' }}</td>
                                        <td class="p-3">
                                            <strong>{{ $detail['person_name'] ?? '—' }}</strong>
                                            <span class="block text-xs text-stone-500">No étiquette: {{ $detailTagNumber ?: '—' }}</span>
                                            @if($detailRoomNumber)
                                                <span class="block text-xs text-stone-500">No chambre: {{ $detailRoomNumber }}</span>
                                            @endif
                                        </td>
                                        <td class="p-3">{{ $detail['label'] ?? $entryCategory?->name ?? 'Item' }}</td>
                                        <td class="p-3 text-right">{{ $detail['quantity'] ?? '—' }}</td>
                                        <td class="p-3 text-right">{{ number_format(($detail['unit_price_cents'] ?? 0) / 100, 2, ',', ' ') }} $</td>
                                        <td class="p-3 text-right font-bold">{{ number_format(($detail['total_cents'] ?? 0) / 100, 2, ',', ' ') }} $</td>
                                    </tr>
                                @endforeach
                            @elseif($entry->amount_cents > 0)
                                <tr class="border-t border-villeneuve-line">
                                    <td class="p-3 font-bold">{{ $entry->service_day }}</td>
                                    <td class="p-3">{{ $entryCategory?->audience === 'employees' ? 'Employé' : 'Client de l’hôtel' }}</td>
                                    <td class="p-3">—</td>
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
                    @if($entries->isNotEmpty()) hidden @endif
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
                                <input class="w-full border-0 text-right" inputmode="decimal" placeholder="0,00" name="grid[{{ $day }}][{{ $category->id }}]" data-grid-day="{{ $day }}" data-grid-category="{{ $category->id }}" data-grid-billing-type="{{ $category->audience === 'employees' ? 'employee' : 'hotel_guest' }}" value="{{ old("grid.$day.$category->id", $entry ? number_format($entry->amount_cents / 100, 2) : '') }}">
                                <div class="mt-1 space-y-1 text-xs text-stone-600" data-detail-list data-detail-day="{{ $day }}" data-detail-category="{{ $category->id }}">
                                    @foreach($entry?->item_details ?? [] as $detailIndex => $detail)
                                        @php($unit = number_format(($detail['unit_price_cents'] ?? 0) / 100, 2, ',', ' '))
                                        @php($total = number_format(($detail['total_cents'] ?? 0) / 100, 2, ',', ' '))
                                        @php($detailBillingType = $detail['billing_type'] ?? ($category->audience === 'employees' ? 'employee' : 'hotel_guest'))
                                        @php($detailHasRoomNumber = array_key_exists('room_number', $detail))
                                        @php($detailTagNumber = $detailBillingType === 'employee' || $detailHasRoomNumber ? ($detail['reference_number'] ?? null) : null)
                                        @php($detailRoomNumber = $detailBillingType === 'hotel_guest' ? ($detailHasRoomNumber ? ($detail['room_number'] ?? null) : ($detail['reference_number'] ?? null)) : null)
                                        <div class="rounded bg-villeneuve-mint px-2 py-1">
                                            {{ $detailBillingType === 'employee' ? 'Employé' : 'Client' }}
                                            @if($detail['person_name'] ?? null)
                                                · {{ $detail['person_name'] }}
                                            @endif
                                            @if($detailTagNumber)
                                                · Étiquette {{ $detailTagNumber }}
                                            @endif
                                            @if($detailRoomNumber)
                                                · Chambre {{ $detailRoomNumber }}
                                            @endif
                                            · Qté {{ $detail['quantity'] ?? '' }} × {{ $unit }} $ = {{ $total }} $
                                        </div>
                                        <input type="hidden" name="details[{{ $day }}][{{ $category->id }}][{{ $detailIndex }}][label]" value="{{ $detail['label'] ?? $category->name }}">
                                        <input type="hidden" name="details[{{ $day }}][{{ $category->id }}][{{ $detailIndex }}][quantity]" value="{{ $detail['quantity'] ?? '' }}">
                                        <input type="hidden" name="details[{{ $day }}][{{ $category->id }}][{{ $detailIndex }}][unit_price]" value="{{ number_format(($detail['unit_price_cents'] ?? 0) / 100, 2, ',', ' ') }}">
                                        @if(array_key_exists('billing_type', $detail))
                                            <input type="hidden" name="details[{{ $day }}][{{ $category->id }}][{{ $detailIndex }}][billing_type]" value="{{ $detailBillingType }}">
                                            <input type="hidden" name="details[{{ $day }}][{{ $category->id }}][{{ $detailIndex }}][person_name]" value="{{ $detail['person_name'] ?? '' }}">
                                            <input type="hidden" name="details[{{ $day }}][{{ $category->id }}][{{ $detailIndex }}][reference_number]" value="{{ $detail['reference_number'] ?? '' }}">
                                            <input type="hidden" name="details[{{ $day }}][{{ $category->id }}][{{ $detailIndex }}][department_number]" value="{{ $detail['department_number'] ?? '' }}">
                                            @if($detailHasRoomNumber)
                                                <input type="hidden" name="details[{{ $day }}][{{ $category->id }}][{{ $detailIndex }}][room_number]" value="{{ $detail['room_number'] ?? '' }}">
                                            @endif
                                        @endif
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
        const billingTypeInput = calculator.querySelector('[data-item-billing-type]');
        const unitPriceInput = calculator.querySelector('[data-item-unit-price]');
        const totalInput = calculator.querySelector('[data-item-total]');
        const addButtons = document.querySelectorAll('[data-item-add]');
        const stageButton = document.querySelector('[data-item-stage]');
        const pendingItemsPanel = document.querySelector('[data-pending-items]');
        const pendingItemsBody = document.querySelector('[data-pending-items-body]');
        const addedItemsBody = document.querySelector('[data-added-items-body]');
        const addedItemsEmpty = document.querySelector('[data-added-items-empty]');
        const itemError = document.querySelector('[data-item-error]');
        const gridInputs = document.querySelectorAll('[data-grid-day][data-grid-category]');
        const invoiceForm = calculator.closest('form');
        let detailIndex = Date.now();
        let pendingItems = [];
        let pendingDay = null;
        let pendingBillingType = null;

        const parseMoney = (value) => {
            const normalized = String(value || '')
                .replace(/\s/g, '')
                .replace('$', '')
                .replace(',', '.');

            const amount = Number.parseFloat(normalized);

            return Number.isFinite(amount) ? amount : 0;
        };

        const formatMoney = (amount) => amount.toFixed(2).replace('.', ',');
        const formatCurrency = (amount) => `${formatMoney(amount)} $`;

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

        const updateIdentityFields = () => {
            document.querySelectorAll('[data-item-identity]').forEach((group) => {
                const active = group.dataset.itemIdentity === billingTypeInput.value;
                group.hidden = ! active;
                group.querySelectorAll('input').forEach((field) => {
                    field.disabled = ! active;
                });
            });
        };

        const updateCategories = () => {
            let firstAvailable = null;

            Array.from(categoryInput.options).forEach((option) => {
                const available = option.dataset.billingType === billingTypeInput.value;
                option.disabled = ! available;
                option.hidden = ! available;
                if (available && ! firstAvailable) firstAvailable = option;
            });

            const selected = categoryInput.options[categoryInput.selectedIndex];
            if (! selected || selected.disabled) {
                categoryInput.value = firstAvailable?.value || '';
            }

            updateIdentityFields();
            useCatalogPrice();
        };

        const recalculateBillingSummary = () => {
            const totals = {};
            let grandTotal = 0;

            for (let day = 1; day <= 31; day++) {
                totals[day] = { employee: 0, hotel_guest: 0 };
            }

            gridInputs.forEach((input) => {
                const day = Number.parseInt(input.dataset.gridDay || '0', 10);
                const type = input.dataset.gridBillingType === 'employee' ? 'employee' : 'hotel_guest';
                if (day >= 1 && day <= 31) totals[day][type] += parseMoney(input.value);
            });

            const subtotals = { employee: 0, hotel_guest: 0 };
            Object.entries(totals).forEach(([day, row]) => {
                const dayTotal = row.employee + row.hotel_guest;
                subtotals.employee += row.employee;
                subtotals.hotel_guest += row.hotel_guest;
                grandTotal += dayTotal;

                document.querySelector(`[data-billing-summary-day="${day}"][data-billing-summary-type="employee"]`).textContent = formatCurrency(row.employee);
                document.querySelector(`[data-billing-summary-day="${day}"][data-billing-summary-type="hotel_guest"]`).textContent = formatCurrency(row.hotel_guest);
                document.querySelector(`[data-billing-summary-day-total="${day}"]`).textContent = formatCurrency(dayTotal);
            });

            document.querySelector('[data-billing-summary-subtotal="employee"]').textContent = formatCurrency(subtotals.employee);
            document.querySelector('[data-billing-summary-subtotal="hotel_guest"]').textContent = formatCurrency(subtotals.hotel_guest);
            document.querySelector('[data-billing-summary-grand-total]').textContent = formatCurrency(grandTotal);
        };

        const showItemError = (message) => {
            if (! itemError) return;
            itemError.textContent = message;
            itemError.hidden = false;
        };

        const clearItemError = () => {
            if (itemError) itemError.hidden = true;
        };

        const releasePendingBatch = () => {
            pendingItems = [];
            pendingDay = null;
            pendingBillingType = null;
            dayInput.disabled = false;
            billingTypeInput.disabled = false;
            if (pendingItemsPanel) pendingItemsPanel.hidden = true;
            if (pendingItemsBody) pendingItemsBody.replaceChildren();
        };

        const renderPendingItems = () => {
            if (! pendingItemsBody || ! pendingItemsPanel) return;

            pendingItemsBody.replaceChildren();
            pendingItemsPanel.hidden = pendingItems.length === 0;

            pendingItems.forEach((item, index) => {
                const row = document.createElement('tr');
                row.className = 'border-t border-villeneuve-line';

                [
                    { value: item.label, className: 'p-3 font-semibold' },
                    { value: item.quantity, className: 'p-3 text-right' },
                    { value: formatCurrency(item.unitPriceCents / 100), className: 'p-3 text-right' },
                    { value: formatCurrency(item.totalCents / 100), className: 'p-3 text-right font-bold' },
                ].forEach(({ value, className }) => {
                    const cell = document.createElement('td');
                    cell.className = className;
                    cell.textContent = value;
                    row.appendChild(cell);
                });

                const actionCell = document.createElement('td');
                actionCell.className = 'p-3 text-right';
                const removeButton = document.createElement('button');
                removeButton.type = 'button';
                removeButton.className = 'font-semibold text-red-700 underline';
                removeButton.textContent = 'Retirer';
                removeButton.addEventListener('click', () => {
                    pendingItems.splice(index, 1);
                    if (pendingItems.length === 0) {
                        releasePendingBatch();
                    } else {
                        renderPendingItems();
                    }
                });
                actionCell.appendChild(removeButton);
                row.appendChild(actionCell);
                pendingItemsBody.appendChild(row);
            });
        };

        const stageCurrentItem = () => {
            const day = Number.parseInt(dayInput.value || '0', 10);
            const selectedOption = categoryInput.options[categoryInput.selectedIndex];
            const category = categoryInput.value;
            const quantity = Number.parseFloat(quantityInput.value || '0') || 0;
            const unitPriceCents = Number.parseInt(selectedOption?.dataset.unitPrice || '0', 10);
            const totalCents = Math.round(quantity * unitPriceCents);
            const billingType = billingTypeInput.value;

            if (day < 1 || day > 31 || ! category || quantity <= 0 || unitPriceCents < 0 || totalCents < 0) {
                showItemError('Choisis un jour, un item et une quantité supérieure à zéro.');
                return false;
            }

            if (selectedOption?.dataset.billingType !== billingType) {
                showItemError('Le tarif choisi ne correspond pas au type de facturation.');
                return false;
            }

            if (pendingItems.length > 0 && (pendingDay !== day || pendingBillingType !== billingType)) {
                showItemError('Ajoute d’abord les items en attente à la facture avant de changer le jour ou le type.');
                return false;
            }

            if (pendingItems.length === 0) {
                pendingDay = day;
                pendingBillingType = billingType;
                dayInput.disabled = true;
                billingTypeInput.disabled = true;
            }

            pendingItems.push({
                day: String(day),
                category,
                label: selectedOption?.dataset.itemLabel || selectedOption?.text || 'Item',
                quantity: quantityInput.value || '0',
                unitPriceCents,
                totalCents,
                billingType,
            });

            clearItemError();
            renderPendingItems();
            quantityInput.value = '1';
            useCatalogPrice();
            categoryInput.focus();

            return true;
        };

        const appendItemToInvoice = (item, identity) => {
            const target = document.querySelector(`[data-grid-day="${item.day}"][data-grid-category="${item.category}"]`);
            const detailList = document.querySelector(`[data-detail-day="${item.day}"][data-detail-category="${item.category}"]`);
            if (! target || ! detailList) return null;

            const total = item.totalCents / 100;
            const unitPrice = item.unitPriceCents / 100;
            const existing = parseMoney(target.value);
            target.value = formatMoney(existing + total);
            detailIndex++;

            const referenceSummary = [
                `Étiquette ${identity.referenceNumber}`,
                item.billingType === 'employee' && identity.departmentNumber
                    ? `Département ${identity.departmentNumber}`
                    : null,
                item.billingType === 'hotel_guest' ? `Chambre ${identity.roomNumber}` : null,
            ].filter(Boolean).join(' · ');

            const row = document.createElement('div');
            row.className = 'rounded bg-villeneuve-mint px-2 py-1';
            row.textContent = `${identity.billingLabel} · ${identity.personName} · ${referenceSummary} · Qté ${item.quantity} × ${formatMoney(unitPrice)} $ = ${formatMoney(total)} $`;
            detailList.appendChild(row);

            const fields = {
                label: item.label,
                quantity: item.quantity,
                unit_price: formatMoney(unitPrice),
                billing_type: item.billingType,
                person_name: identity.personName,
                reference_number: identity.referenceNumber,
                department_number: item.billingType === 'employee' ? identity.departmentNumber : '',
                room_number: item.billingType === 'hotel_guest' ? identity.roomNumber : '',
            };

            Object.entries(fields).forEach(([name, value]) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = `details[${item.day}][${item.category}][${detailIndex}][${name}]`;
                input.value = value;
                detailList.appendChild(input);
            });

            if (addedItemsBody) {
                const summaryRow = document.createElement('tr');
                summaryRow.className = 'border-t border-villeneuve-line';

                [
                    { value: item.day, className: 'p-3 font-bold' },
                    { value: identity.billingLabel, className: 'p-3' },
                    { value: `${identity.personName} · ${referenceSummary}`, className: 'p-3' },
                    { value: item.label, className: 'p-3' },
                    { value: item.quantity, className: 'p-3 text-right' },
                    { value: `${formatMoney(unitPrice)} $`, className: 'p-3 text-right' },
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

            return target;
        };

        const rememberEmployeeOption = (name) => {
            const options = document.querySelector('[data-employee-name-options]');
            if (! options) return;

            const normalizedName = name.toLocaleLowerCase('fr-CA');
            const alreadyExists = Array.from(options.options)
                .some((option) => option.value.trim().toLocaleLowerCase('fr-CA') === normalizedName);

            if (! alreadyExists) {
                const option = document.createElement('option');
                option.value = name;
                options.appendChild(option);
            }
        };

        const addToInvoice = () => {
            if (pendingItems.length === 0 && ! stageCurrentItem()) return;

            const billingType = pendingBillingType;
            const identityGroup = document.querySelector(`[data-item-identity="${billingType}"]`);
            const personName = identityGroup?.querySelector('[data-item-person-name]')?.value.trim() || '';
            const referenceNumber = identityGroup?.querySelector('[data-item-reference-number]')?.value.trim() || '';
            const departmentNumber = identityGroup?.querySelector('[data-item-department-number]')?.value.trim() || '';
            const roomNumber = identityGroup?.querySelector('[data-item-room-number]')?.value.trim() || '';

            if (! personName || ! referenceNumber || (billingType === 'hotel_guest' && ! roomNumber)) {
                showItemError(billingType === 'employee'
                    ? 'Entre le nom de l’employé et son numéro d’étiquette.'
                    : 'Entre le nom du client, son numéro d’étiquette et son numéro de chambre.');
                return;
            }

            const identity = {
                personName,
                referenceNumber,
                departmentNumber,
                roomNumber,
                billingLabel: billingType === 'employee' ? 'Employé' : 'Client de l’hôtel',
            };
            let firstTarget = null;

            pendingItems.forEach((item) => {
                const target = appendItemToInvoice(item, identity);
                firstTarget ??= target;
            });

            if (billingType === 'employee') rememberEmployeeOption(personName);

            identityGroup?.querySelectorAll('input').forEach((field) => {
                field.value = '';
            });
            releasePendingBatch();
            clearItemError();
            useCatalogPrice();
            recalculateBillingSummary();
            if (firstTarget?.offsetParent !== null) firstTarget.focus();
        };

        quantityInput.addEventListener('input', updateTotal);
        categoryInput.addEventListener('change', useCatalogPrice);
        billingTypeInput.addEventListener('change', updateCategories);
        stageButton?.addEventListener('click', stageCurrentItem);
        addButtons.forEach((button) => button.addEventListener('click', addToInvoice));
        gridInputs.forEach((input) => input.addEventListener('input', recalculateBillingSummary));
        invoiceForm?.addEventListener('submit', (event) => {
            if (pendingItems.length === 0) return;

            event.preventDefault();
            showItemError('Clique sur « Ajouter à la facture » pour confirmer les items en attente avant d’enregistrer.');
            itemError?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
        billingTypeInput.value = calculator.dataset.defaultBillingType;
        updateCategories();
        recalculateBillingSummary();
    })();
</script>
@endsection
