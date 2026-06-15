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

<section class="mt-6 grid gap-4 md:grid-cols-3">
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

<section class="panel mt-6 overflow-x-auto p-6">
    <table class="w-full border-collapse text-sm">
        <tr>
            <th class="border bg-villeneuve-mint p-2 text-left">Date</th>
            <th class="border bg-villeneuve-mint p-2 text-left">Client</th>
            <th class="border bg-villeneuve-mint p-2 text-left">Employé</th>
            <th class="border bg-villeneuve-mint p-2 text-left">No de département</th>
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
                <td class="border p-2">{{ $order->employee_name }}</td>
                <td class="border p-2">{{ $order->department_number ?: '—' }}</td>
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
