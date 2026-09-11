@extends('layouts.app')

@section('content')
@php($statuses = ['submitted' => 'À approuver', 'reviewed' => 'Approuvée', 'invoiced' => 'Facturée', 'cancelled' => 'Annulée'])

<div class="flex flex-wrap items-center justify-between gap-4">
    <div>
        <p class="label">Administration</p>
        <h1 class="text-3xl font-extrabold text-villeneuve-forest">États de compte</h1>
    </div>
</div>

<form class="panel mt-6 grid gap-4 p-6 md:grid-cols-4" method="get" action="{{ route('account-statements.index') }}">
    <div>
        <label class="label">Mois</label>
        <input class="mt-1 w-full" type="number" min="1" max="12" name="month" value="{{ $month }}">
    </div>
    <div>
        <label class="label">Année</label>
        <input class="mt-1 w-full" type="number" name="year" value="{{ $year }}">
    </div>
    <div>
        <label class="label">Client</label>
        <select class="mt-1 w-full" name="client_id">
            <option value="">Tous les clients</option>
            @foreach($clients as $client)
                <option value="{{ $client->id }}" @selected((string) $clientId === (string) $client->id)>{{ $client->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="flex items-end">
        <button class="btn btn-primary w-full">Filtrer</button>
    </div>
</form>

<section class="panel mt-6 p-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-villeneuve-forest">Factures du mois</h2>
            <p class="mt-1 text-sm text-stone-600">{{ $statement['rows']->count() }} facture(s) approuvée(s), envoyée(s) ou payée(s) pour la période sélectionnée. Les brouillons et factures annulées sont exclus.</p>
        </div>
        @if($clientId && $statement['rows']->isNotEmpty())
            <div class="flex flex-wrap gap-2">
                <a class="btn btn-secondary" href="{{ route('account-statements.summary', ['month' => $month, 'year' => $year, 'client_id' => $clientId]) }}">Voir le récapitulatif du mois</a>
                <a class="btn btn-primary" href="{{ route('account-statements.summary', ['month' => $month, 'year' => $year, 'client_id' => $clientId, 'pdf' => 1]) }}">Télécharger le récapitulatif du mois (PDF)</a>
            </div>
        @elseif(!$clientId)
            <p class="text-sm text-stone-600">Choisis un client et clique sur Filtrer pour obtenir son récapitulatif mensuel.</p>
        @endif
    </div>
    <p class="mt-3 text-sm text-stone-600">Le récapitulatif réunit les factures existantes dans un seul document à envoyer au client, sans les facturer à nouveau. Le PDF utilise la langue du client.</p>
    <div class="mt-4 grid gap-4 md:grid-cols-3">
        <div><div class="label">Total facturé (taxes incluses)</div><strong class="text-2xl">{{ $money->format($statement['total_cents'], 'fr') }}</strong></div>
        <div><div class="label">Paiements reçus</div><strong class="text-2xl">{{ $money->format($statement['paid_cents'], 'fr') }}</strong></div>
        <div><div class="label">Solde des factures du mois</div><strong class="text-2xl">{{ $money->format($statement['balance_cents'], 'fr') }}</strong></div>
    </div>
    <div class="mt-4 overflow-x-auto">
        <table class="w-full border-collapse text-sm">
            <thead><tr>
                @foreach(['Date', 'Facture', 'Client', 'Statut', 'Avant taxes', 'Taxes', 'Total', 'Paiements', 'Solde'] as $heading)
                    <th class="border bg-villeneuve-mint p-2 text-left">{{ $heading }}</th>
                @endforeach
            </tr></thead>
            <tbody>
                @forelse($statement['rows'] as $row)
                    <tr>
                        <td class="border p-2">{{ $row['invoice']->invoice_date?->format('Y-m-d') }}</td>
                        <td class="border p-2"><a class="font-bold underline" href="{{ route('monthly-invoices.show', $row['invoice']) }}">{{ $row['invoice']->invoice_number }}</a></td>
                        <td class="border p-2">{{ $row['invoice']->client?->name }}</td>
                        <td class="border p-2">{{ trans('invoice.statuses.'.$row['invoice']->status, [], 'fr') }}</td>
                        @foreach(['net_cents', 'tax_cents', 'total_cents', 'paid_cents', 'balance_cents'] as $field)
                            <td class="border p-2 text-right">{{ $money->format($row[$field], 'fr') }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="9" class="border p-4 text-center">Aucune facture approuvée, envoyée ou payée pour cette période.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

<h2 class="mt-8 text-xl font-bold text-villeneuve-forest">Commandes de nettoyage</h2>
<section class="mt-4 grid gap-4 md:grid-cols-3">
    <div class="panel p-5">
        <div class="label">Sous-total commandes</div>
        <div class="mt-2 text-2xl font-black text-villeneuve-forest">{{ $money->format($subtotalCents, 'fr') }}</div>
    </div>
    <div class="panel p-5">
        <div class="label">Ajustements</div>
        <div class="mt-2 text-2xl font-black text-villeneuve-forest">{{ $money->format($adjustmentCents, 'fr') }}</div>
    </div>
    <div class="panel p-5">
        <div class="label">Montant total</div>
        <div class="mt-2 text-2xl font-black text-villeneuve-forest">{{ $money->format($totalCents, 'fr') }}</div>
    </div>
</section>

<section class="panel mt-6 p-5">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-villeneuve-forest">Facturer les commandes non facturées</h2>
            @if($clientId)
                <p class="mt-1 text-sm text-stone-600">
                    Génère une seule facture pour les commandes approuvées non facturées de ce client pendant ce mois.
                    Commandes prêtes: {{ $invoiceableOrdersCount }}.
                </p>
            @else
                <p class="mt-1 text-sm text-stone-600">Choisis un client précis pour générer une facture mensuelle.</p>
            @endif
        </div>
        @if($clientId)
            <form method="post" action="{{ route('account-statements.create-invoice') }}">
                @csrf
                <input type="hidden" name="month" value="{{ $month }}">
                <input type="hidden" name="year" value="{{ $year }}">
                <input type="hidden" name="client_id" value="{{ $clientId }}">
                <button class="btn btn-primary" @disabled($invoiceableOrdersCount === 0)>Créer une facture depuis les commandes</button>
            </form>
        @endif
    </div>
</section>

<section class="panel mt-6 overflow-x-auto p-6">
    <table class="w-full border-collapse text-sm">
        <tr>
            <th class="border bg-villeneuve-mint p-2 text-left">Date</th>
            <th class="border bg-villeneuve-mint p-2 text-left">Client</th>
            <th class="border bg-villeneuve-mint p-2 text-left">Type</th>
            <th class="border bg-villeneuve-mint p-2 text-left">Nom / référence</th>
            <th class="border bg-villeneuve-mint p-2 text-left">Items</th>
            <th class="border bg-villeneuve-mint p-2 text-right">Sous-total</th>
            <th class="border bg-villeneuve-mint p-2 text-right">Ajustement</th>
            <th class="border bg-villeneuve-mint p-2 text-right">Total</th>
            <th class="border bg-villeneuve-mint p-2 text-left">Ajuster</th>
        </tr>
        @forelse($orders as $order)
            <tr>
                <td class="border p-2">{{ $order->service_date->format('Y-m-d') }}</td>
                <td class="border p-2">{{ $order->client?->name }}</td>
                <td class="border p-2">{{ $order->orderTypeLabel() }}</td>
                <td class="border p-2">
                    <strong>{{ $order->billingName() ?: '—' }}</strong>
                    <span class="block text-xs text-stone-500">
                        No d’étiquette: {{ $order->billingTagNumber() ?: '—' }}
                    </span>
                    @if($order->billingLocationNumber())
                        <span class="block text-xs text-stone-500">{{ $order->billingLocationLabel() }}: {{ $order->billingLocationNumber() }}</span>
                    @endif
                </td>
                <td class="border p-2">
                    <div class="space-y-1">
                        @foreach($order->items as $item)
                            <div>
                                {{ $item->item_name_snapshot }}
                                · Qté {{ rtrim(rtrim(number_format((float) $item->quantity, 2, ',', ' '), '0'), ',') }}
                                × {{ $money->format($item->unit_price_cents, 'fr') }}
                            </div>
                        @endforeach
                    </div>
                    @if($order->notes)
                        <div class="mt-2 text-xs text-stone-600">Note: {{ $order->notes }}</div>
                    @endif
                </td>
                <td class="border p-2 text-right">{{ $money->format($order->subtotal_cents, 'fr') }}</td>
                <td class="border p-2 text-right">{{ $money->format($order->adjustment_cents, 'fr') }}</td>
                <td class="border p-2 text-right font-bold">{{ $money->format($order->total_cents, 'fr') }}</td>
                <td class="border p-2">
                    @if(auth()->user()->isSuperAdmin())
                        <form class="grid gap-2" method="post" action="{{ route('account-statements.adjustment', $order) }}">
                            @csrf
                            <input class="w-full text-right" name="adjustment_amount" value="{{ number_format($order->adjustment_cents / 100, 2, ',', ' ') }}" placeholder="-5,00">
                            <input class="w-full" name="adjustment_note" value="{{ $order->adjustment_note }}" placeholder="Raison de l’ajustement">
                            <button class="btn btn-secondary">Sauvegarder</button>
                        </form>
                    @else
                        <span class="text-stone-500">Admin seulement</span>
                    @endif
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="9" class="border p-4 text-center text-stone-600">Aucune commande pour cette période.</td>
            </tr>
        @endforelse
    </table>
</section>
@endsection
