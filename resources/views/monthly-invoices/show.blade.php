@extends('layouts.app')

@section('content')
@php
    $statuses = [
        'draft' => 'Brouillon',
        'approved' => 'Approuvée',
        'sent' => 'Envoyée',
        'paid' => 'Payée',
        'cancelled' => 'Annulée',
    ];
    $categories = collect($invoice->category_snapshot ?? []);
    $singleCategory = $categories->count() === 1;
    $useCompactLayout = $categories->count() > 8;
    $invoiceLanguage = $invoice->client?->default_language ?? 'fr';
    $categoriesById = $categories->keyBy(fn ($category) => (string) ($category['id'] ?? ''));
    $lineItems = $invoice->entries
        ->sortBy(fn ($entry) => sprintf('%02d-%08d', $entry->service_day, $entry->id))
        ->flatMap(function ($entry) use ($categoriesById) {
            $category = $categoriesById->get((string) $entry->client_category_id, []);
            $serviceLabel = isset($category['service_type'])
                ? App\Models\ClientCategory::serviceLabel($category['service_type'])
                : null;
            $audienceLabel = isset($category['audience'])
                ? App\Models\ClientCategory::audienceLabel($category['audience'])
                : null;
            $details = collect($entry->item_details ?? []);

            if ($details->isEmpty()) {
                return $entry->amount_cents > 0 ? [[
                    'day' => $entry->service_day,
                    'service' => collect([$serviceLabel, $audienceLabel])->filter()->join(' · '),
                    'label' => $category['name'] ?? $entry->category_name_snapshot,
                    'quantity' => null,
                    'unit_price_cents' => null,
                    'total_cents' => $entry->amount_cents,
                ]] : [];
            }

            return $details->map(fn ($detail) => [
                'day' => $entry->service_day,
                'service' => collect([$serviceLabel, $audienceLabel])->filter()->join(' · '),
                'label' => $detail['label'] ?? $category['name'] ?? $entry->category_name_snapshot,
                'quantity' => $detail['quantity'] ?? null,
                'unit_price_cents' => $detail['unit_price_cents'] ?? null,
                'total_cents' => $detail['total_cents'] ?? 0,
            ])->all();
        })
        ->values();
@endphp

<div class="flex flex-wrap items-center justify-between gap-3">
    <div>
        <p class="label">{{ $invoice->client?->name ?? 'Client supprimé' }} · {{ $statuses[$invoice->status] ?? $invoice->status }}</p>
        <h1 class="text-3xl font-extrabold text-villeneuve-forest">Facture {{ $invoice->invoice_number }}</h1>
    </div>
    <div class="flex flex-wrap gap-2">
        <a class="btn btn-secondary" href="{{ route('monthly-invoices.edit', $invoice) }}">Modifier</a>
        @if($invoice->status === 'draft' && auth()->user()->isSuperAdmin())
            <form method="post" action="{{ route('monthly-invoices.approve', $invoice) }}">@csrf<button class="btn btn-secondary">Approuver</button></form>
        @endif
        <form method="post" action="{{ route('monthly-invoices.generate-pdf', $invoice) }}">@csrf<button class="btn btn-primary">Générer PDF</button></form>
        @if($invoice->pdf_path)
            <a class="btn btn-secondary" href="{{ route('monthly-invoices.download', $invoice) }}">Télécharger PDF</a>
        @endif
    </div>
</div>

<div class="mt-6 grid gap-6 lg:grid-cols-[1fr_340px]">
    <section class="panel overflow-x-auto p-6">
        @if($useCompactLayout)
            <div>
                <h2 class="text-xl font-bold text-villeneuve-forest">Détail des items facturés</h2>
                <p class="mt-1 text-sm text-stone-600">
                    Seuls les items ajoutés à la facture sont affichés.
                </p>
            </div>

            <table class="mt-4 w-full text-sm">
                <thead>
                    <tr>
                        <th class="bg-villeneuve-mint p-3 text-left">Jour</th>
                        <th class="bg-villeneuve-mint p-3 text-left">Service</th>
                        <th class="bg-villeneuve-mint p-3 text-left">Item</th>
                        <th class="bg-villeneuve-mint p-3 text-right">Quantité</th>
                        <th class="bg-villeneuve-mint p-3 text-right">Prix unitaire</th>
                        <th class="bg-villeneuve-mint p-3 text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($lineItems as $lineItem)
                        <tr class="border-t border-villeneuve-line">
                            <td class="p-3 font-bold">{{ $lineItem['day'] }}</td>
                            <td class="p-3 text-xs text-stone-600">{{ $lineItem['service'] ?: '—' }}</td>
                            <td class="p-3 font-semibold">{{ $lineItem['label'] }}</td>
                            <td class="p-3 text-right">
                                @if($lineItem['quantity'] !== null)
                                    {{ rtrim(rtrim(number_format((float) $lineItem['quantity'], 2, ',', ' '), '0'), ',') }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="p-3 text-right">
                                {{ $lineItem['unit_price_cents'] !== null ? $money->format($lineItem['unit_price_cents'], $invoiceLanguage) : '—' }}
                            </td>
                            <td class="p-3 text-right font-bold">{{ $money->format($lineItem['total_cents'], $invoiceLanguage) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td class="p-6 text-center text-stone-500" colspan="6">Aucun item facturé.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <details class="mt-6 border border-villeneuve-line p-4">
                <summary class="cursor-pointer font-bold text-villeneuve-forest">
                    Afficher la grille mensuelle détaillée ({{ $categories->count() }} items)
                </summary>
                <p class="mt-2 text-sm text-stone-600">
                    Cette grille est conservée pour les vérifications avancées.
                </p>
        @endif

        <table class="w-full table-fixed border-collapse text-sm">
            <tr>
                <th class="w-20 border border-villeneuve-line bg-villeneuve-mint px-3 py-2 text-left text-xs font-bold uppercase text-villeneuve-forest">Jour</th>
                @foreach($categories as $category)
                    <th class="border border-villeneuve-line bg-villeneuve-mint px-3 py-2 text-center text-xs font-bold uppercase text-villeneuve-forest">
                        @if(! $singleCategory && isset($category['service_type'], $category['audience']))
                            <span class="mb-1 block text-[9px] font-semibold normal-case text-stone-500">
                                {{ App\Models\ClientCategory::serviceLabel($category['service_type']) }}
                                · {{ App\Models\ClientCategory::audienceLabel($category['audience']) }}
                            </span>
                        @endif
                        {{ $singleCategory ? 'Montant' : $category['name'] }}
                    </th>
                @endforeach
            </tr>
            @for($day = 1; $day <= 31; $day++)
                <tr>
                    <td class="border border-villeneuve-line px-3 py-2 font-bold">{{ $day }}</td>
                    @foreach($categories as $category)
                        @php($cellEntries = $invoice->entries->where('service_day', $day)->where('client_category_id', $category['id']))
                        @php($sum = $cellEntries->sum('amount_cents'))
                        <td class="border border-villeneuve-line px-3 py-2 text-right tabular-nums">
                            @if($sum)
                                <div class="font-semibold">{{ $money->format($sum, $invoiceLanguage) }}</div>
                            @endif
                            @foreach($cellEntries as $entry)
                                @foreach($entry->item_details ?? [] as $detail)
                                    <div class="mt-1 text-xs leading-snug text-stone-600">
                                        Qté {{ $detail['quantity'] ?? '' }}
                                        × Prix unit. {{ $money->format($detail['unit_price_cents'] ?? 0, $invoiceLanguage) }}
                                        = {{ $money->format($detail['total_cents'] ?? 0, $invoiceLanguage) }}
                                    </div>
                                @endforeach
                            @endforeach
                        </td>
                    @endforeach
                </tr>
            @endfor
        </table>

        @if($useCompactLayout)
            </details>
        @endif
    </section>

    <aside class="panel p-6">
        <h2 class="text-xl font-bold text-villeneuve-forest">Totaux</h2>
        <dl class="mt-4 space-y-3">
            <div class="flex justify-between"><dt>Sous-total</dt><dd>{{ $money->format($invoice->subtotal_cents, $invoiceLanguage) }}</dd></div>
            <div class="flex justify-between"><dt>Rabais / crédits</dt><dd>-{{ $money->format($invoice->discount_cents, $invoiceLanguage) }}</dd></div>
            @foreach($invoice->tax_profile_snapshot ?? [] as $tax)
                <div class="flex justify-between"><dt>{{ $tax['label'] }}</dt><dd>{{ $money->format($tax['amount_cents'], $invoiceLanguage) }}</dd></div>
            @endforeach
            <div class="border-t pt-3 flex justify-between text-xl font-black text-villeneuve-forest"><dt>Grand total</dt><dd>{{ $money->format($invoice->grand_total_cents, $invoiceLanguage) }}</dd></div>
        </dl>

        <div class="mt-6 grid gap-2">
            <form method="post" action="{{ route('monthly-invoices.mark-sent', $invoice) }}">@csrf<button class="btn btn-secondary w-full">Marquer envoyée</button></form>
            <form method="post" action="{{ route('monthly-invoices.mark-paid', $invoice) }}">@csrf<button class="btn btn-secondary w-full">Marquer payée</button></form>
            <form method="post" action="{{ route('monthly-invoices.cancel', $invoice) }}">@csrf<button class="btn btn-secondary w-full">Annuler</button></form>
            <a class="btn btn-secondary w-full" href="{{ route('monthly-invoices.export', $invoice) }}">Exporter CSV</a>
        </div>

        <form class="mt-6 border-t pt-4" method="post" enctype="multipart/form-data" action="{{ route('monthly-invoices.attachments', $invoice) }}">
            @csrf
            <label class="label">Pièce jointe</label>
            <label class="btn btn-secondary mt-2 cursor-pointer">
                Choisir un fichier
                <input class="sr-only" type="file" name="attachment">
            </label>
            <button class="btn btn-secondary mt-3 w-full">Téléverser</button>
        </form>
    </aside>
</div>
@endsection
