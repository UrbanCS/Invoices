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
    $invoiceLanguage = $invoice->client?->default_language ?? 'fr';
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
    <div class="space-y-6">
        <section class="panel overflow-x-auto p-6">
            <div>
                <h2 class="text-xl font-bold text-villeneuve-forest">Répartition mensuelle</h2>
                <p class="mt-1 text-sm text-stone-600">
                    Les commandes d’employés et de clients de l’hôtel sont présentées séparément.
                </p>
            </div>

            <table class="mt-4 w-full border-collapse text-sm">
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
                        @php($daily = $dailyBillingTotals->get($day))
                        <tr>
                            <td class="border p-2 font-bold">{{ $day }}</td>
                            <td class="border p-2 text-right tabular-nums">
                                {{ $daily['employee'] ? $money->format($daily['employee'], $invoiceLanguage) : '' }}
                            </td>
                            <td class="border p-2 text-right tabular-nums">
                                {{ $daily['hotel_guest'] ? $money->format($daily['hotel_guest'], $invoiceLanguage) : '' }}
                            </td>
                            <td class="border p-2 text-right font-semibold tabular-nums">
                                {{ array_sum($daily) ? $money->format(array_sum($daily), $invoiceLanguage) : '' }}
                            </td>
                        </tr>
                    @endfor
                </tbody>
                <tfoot>
                    <tr class="font-black text-villeneuve-forest">
                        <td class="border bg-villeneuve-mint p-2">Sous-totaux</td>
                        <td class="border bg-villeneuve-mint p-2 text-right">{{ $money->format($billingSubtotals['employee'], $invoiceLanguage) }}</td>
                        <td class="border bg-villeneuve-mint p-2 text-right">{{ $money->format($billingSubtotals['hotel_guest'], $invoiceLanguage) }}</td>
                        <td class="border bg-villeneuve-mint p-2 text-right">{{ $money->format(array_sum($billingSubtotals), $invoiceLanguage) }}</td>
                    </tr>
                </tfoot>
            </table>
        </section>

        <section class="panel overflow-x-auto p-6">
            <h2 class="text-xl font-bold text-villeneuve-forest">Détail des items facturés</h2>
            <p class="mt-1 text-sm text-stone-600">
                Le nom et la référence sont conservés avec chaque item de la facture.
            </p>

            <table class="mt-4 w-full text-sm">
                <thead>
                    <tr>
                        <th class="bg-villeneuve-mint p-3 text-left">Jour</th>
                        <th class="bg-villeneuve-mint p-3 text-left">Type</th>
                        <th class="bg-villeneuve-mint p-3 text-left">Nom / référence</th>
                        <th class="bg-villeneuve-mint p-3 text-left">Item</th>
                        <th class="bg-villeneuve-mint p-3 text-right">Qté</th>
                        <th class="bg-villeneuve-mint p-3 text-right">Prix unit.</th>
                        <th class="bg-villeneuve-mint p-3 text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($lineItems as $lineItem)
                        <tr class="border-t border-villeneuve-line">
                            <td class="p-3 font-bold">{{ $lineItem['day'] }}</td>
                            <td class="p-3">{{ $lineItem['billing_label'] }}</td>
                            <td class="p-3">
                                <strong>{{ $lineItem['person_name'] ?: '—' }}</strong>
                                <span class="block text-xs text-stone-500">
                                    {{ $lineItem['reference_label'] }}: {{ $lineItem['reference_number'] ?: '—' }}
                                </span>
                                @if($lineItem['department_number'])
                                    <span class="block text-xs text-stone-500">Département: {{ $lineItem['department_number'] }}</span>
                                @endif
                            </td>
                            <td class="p-3">
                                <strong>{{ $lineItem['label'] }}</strong>
                                @if($lineItem['service'])
                                    <span class="block text-xs text-stone-500">{{ $lineItem['service'] }}</span>
                                @endif
                            </td>
                            <td class="p-3 text-right">
                                {{ $lineItem['quantity'] !== null ? rtrim(rtrim(number_format((float) $lineItem['quantity'], 2, ',', ' '), '0'), ',') : '—' }}
                            </td>
                            <td class="p-3 text-right">
                                {{ $lineItem['unit_price_cents'] !== null ? $money->format($lineItem['unit_price_cents'], $invoiceLanguage) : '—' }}
                            </td>
                            <td class="p-3 text-right font-bold">{{ $money->format($lineItem['total_cents'], $invoiceLanguage) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td class="p-6 text-center text-stone-500" colspan="7">Aucun item facturé.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        @if($categories->isNotEmpty())
            <details class="panel overflow-x-auto p-5">
                <summary class="cursor-pointer font-bold text-villeneuve-forest">Afficher la grille technique par item</summary>
                <p class="mt-2 text-sm text-stone-600">Cette vue sert aux vérifications et corrections administratives avancées.</p>
                <table class="mt-4 w-full border-collapse text-xs">
                    <thead>
                        <tr>
                            <th class="border bg-villeneuve-mint p-2">Jour</th>
                            @foreach($categories as $category)
                                <th class="border bg-villeneuve-mint p-2 text-right">{{ $category['name'] ?? 'Item' }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @for($day = 1; $day <= 31; $day++)
                            <tr>
                                <td class="border p-2 font-bold">{{ $day }}</td>
                                @foreach($categories as $category)
                                    @php($sum = $invoice->entries->where('service_day', $day)->where('client_category_id', $category['id'] ?? null)->sum('amount_cents'))
                                    <td class="border p-2 text-right">{{ $sum ? $money->format($sum, $invoiceLanguage) : '' }}</td>
                                @endforeach
                            </tr>
                        @endfor
                    </tbody>
                </table>
            </details>
        @endif
    </div>

    <aside class="panel h-fit p-6">
        <h2 class="text-xl font-bold text-villeneuve-forest">Totaux</h2>
        <dl class="mt-4 space-y-3">
            <div class="flex justify-between"><dt>Employés</dt><dd>{{ $money->format($billingSubtotals['employee'], $invoiceLanguage) }}</dd></div>
            <div class="flex justify-between"><dt>Clients</dt><dd>{{ $money->format($billingSubtotals['hotel_guest'], $invoiceLanguage) }}</dd></div>
            <div class="flex justify-between border-t pt-3"><dt>Sous-total</dt><dd>{{ $money->format($invoice->subtotal_cents, $invoiceLanguage) }}</dd></div>
            <div class="flex justify-between"><dt>Rabais / crédits</dt><dd>-{{ $money->format($invoice->discount_cents, $invoiceLanguage) }}</dd></div>
            @foreach($invoice->tax_profile_snapshot ?? [] as $tax)
                <div class="flex justify-between"><dt>{{ $tax['label'] }}</dt><dd>{{ $money->format($tax['amount_cents'], $invoiceLanguage) }}</dd></div>
            @endforeach
            <div class="flex justify-between border-t pt-3 text-xl font-black text-villeneuve-forest"><dt>Grand total</dt><dd>{{ $money->format($invoice->grand_total_cents, $invoiceLanguage) }}</dd></div>
        </dl>

        <div class="mt-6 grid gap-2">
            <form method="post" action="{{ route('monthly-invoices.mark-sent', $invoice) }}">@csrf<button class="btn btn-secondary w-full">Marquer envoyée</button></form>
            <form method="post" action="{{ route('monthly-invoices.mark-paid', $invoice) }}">@csrf<button class="btn btn-secondary w-full">Marquer payée</button></form>
            <form method="post" action="{{ route('monthly-invoices.cancel', $invoice) }}">@csrf<button class="btn btn-secondary w-full">Annuler</button></form>
            <a class="btn btn-secondary w-full" href="{{ route('monthly-invoices.export', $invoice) }}">Exporter CSV</a>
            @if(auth()->user()->isSuperAdmin())
                <form
                    method="post"
                    action="{{ route('monthly-invoices.destroy', $invoice) }}"
                    onsubmit="return confirm('Supprimer définitivement cette facture? Son PDF et ses paiements seront supprimés. Les commandes liées redeviendront disponibles pour la facturation.');"
                >
                    @csrf
                    @method('delete')
                    <button class="btn btn-secondary w-full border-red-300 text-red-700">Supprimer définitivement</button>
                </form>
            @endif
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
