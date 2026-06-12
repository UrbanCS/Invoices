@extends('layouts.app')

@section('content')
@php($statuses = ['submitted' => 'Envoyée', 'reviewed' => 'Révisée', 'invoiced' => 'Facturée', 'cancelled' => 'Annulée'])

<div class="flex flex-wrap items-center justify-between gap-4">
    <h1 class="text-3xl font-extrabold text-villeneuve-forest">Mes commandes</h1>
    <a class="btn btn-primary" href="{{ route('portal.orders.create') }}">Nouvelle commande</a>
</div>

<div class="panel mt-6 overflow-x-auto">
    <table class="table w-full">
        <tr>
            <th>Date</th>
            <th>Employé</th>
            <th>Statut</th>
            <th class="text-right">Total</th>
            <th></th>
        </tr>
        @forelse($orders as $order)
            <tr>
                <td>{{ $order->service_date->format('Y-m-d') }}</td>
                <td>{{ $order->employee_name }}</td>
                <td>{{ $statuses[$order->status] ?? $order->status }}</td>
                <td class="text-right">{{ $money->format($order->total_cents, auth()->user()->client->default_language ?? 'fr') }}</td>
                <td class="text-right"><a class="btn btn-secondary" href="{{ route('portal.orders.show', $order) }}">Voir</a></td>
            </tr>
        @empty
            <tr>
                <td colspan="5" class="text-center text-stone-600">Aucune commande pour l’instant.</td>
            </tr>
        @endforelse
    </table>
</div>

{{ $orders->links() }}
@endsection
