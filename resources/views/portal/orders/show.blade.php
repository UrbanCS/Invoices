@extends('layouts.app')

@section('content')
@php($statuses = ['submitted' => 'Envoyée', 'reviewed' => 'Révisée', 'invoiced' => 'Facturée', 'cancelled' => 'Annulée'])
@php($language = auth()->user()->client->default_language ?? 'fr')

<div class="flex flex-wrap items-center justify-between gap-4">
    <div>
        <p class="label">{{ $statuses[$order->status] ?? $order->status }}</p>
        <h1 class="text-3xl font-extrabold text-villeneuve-forest">Commande du {{ $order->service_date->format('Y-m-d') }}</h1>
    </div>
    <a class="btn btn-secondary" href="{{ route('portal.orders.index') }}">Mes commandes</a>
</div>

<section class="panel mt-6 p-6">
    <div class="grid gap-4 md:grid-cols-3">
        <div><span class="label">Employé</span><div class="mt-1 font-semibold">{{ $order->employee_name }}</div></div>
        <div><span class="label">Date</span><div class="mt-1 font-semibold">{{ $order->service_date->format('Y-m-d') }}</div></div>
        <div><span class="label">Total</span><div class="mt-1 font-black text-villeneuve-forest">{{ $money->format($order->total_cents, $language) }}</div></div>
    </div>
    @if($order->notes)
        <div class="mt-4"><span class="label">Notes</span><p class="mt-1">{{ $order->notes }}</p></div>
    @endif
</section>

<section class="panel mt-6 overflow-x-auto p-6">
    <table class="table w-full">
        <tr>
            <th>Item</th>
            <th class="text-right">Prix unit.</th>
            <th class="text-right">Quantité</th>
            <th class="text-right">Total</th>
        </tr>
        @foreach($order->items as $item)
            <tr>
                <td>{{ $item->item_name_snapshot }}</td>
                <td class="text-right">{{ $money->format($item->unit_price_cents, $language) }}</td>
                <td class="text-right">{{ rtrim(rtrim(number_format((float) $item->quantity, 2, ',', ' '), '0'), ',') }}</td>
                <td class="text-right font-semibold">{{ $money->format($item->total_cents, $language) }}</td>
            </tr>
        @endforeach
    </table>
</section>
@endsection
