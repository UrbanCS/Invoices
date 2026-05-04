@extends('layouts.app')

@section('content')
@php
    $money = app(App\Services\MoneyFormatter::class);
    $statuses = [
        'draft' => 'Brouillon',
        'approved' => 'Approuvée',
        'sent' => 'Envoyée',
        'paid' => 'Payée',
        'cancelled' => 'Annulée',
    ];
@endphp

<div class="flex flex-wrap items-center justify-between gap-4">
    <h1 class="text-3xl font-extrabold text-villeneuve-forest">Factures mensuelles</h1>
    <div class="flex flex-wrap gap-2">
        @if(auth()->user()->isSuperAdmin())
            <a class="btn btn-secondary" href="{{ route('clients.create') }}">Ajouter un client</a>
        @endif
        <a class="btn btn-primary" href="{{ route('monthly-invoices.create') }}">Nouvelle facture</a>
    </div>
</div>

<form class="mt-6 grid gap-3 md:grid-cols-[180px_1fr_180px_auto]" method="get">
    <div>
        <label class="label" for="invoice_number">No de facture</label>
        <input id="invoice_number" class="mt-1 w-full" name="invoice_number" value="{{ request('invoice_number') }}">
    </div>
    <div>
        <label class="label" for="client_name">Nom du client</label>
        <input id="client_name" class="mt-1 w-full" name="client_name" value="{{ request('client_name') }}" list="client_names">
        <datalist id="client_names">
            @foreach($clients as $client)
                <option value="{{ $client->name }}"></option>
            @endforeach
        </datalist>
    </div>
    <div>
        <label class="label" for="status">Statut</label>
        <select id="status" class="mt-1 w-full" name="status">
            <option value="">Tous les statuts</option>
            @foreach($statuses as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="flex items-end">
        <button class="btn btn-secondary w-full">Filtrer</button>
    </div>
</form>

<div class="panel mt-6 overflow-x-auto">
    <table class="table w-full">
        <tr>
            <th>Facture</th>
            <th>Client</th>
            <th>Mois</th>
            <th>Statut</th>
            <th class="text-right">Grand total</th>
        </tr>
        @forelse($invoices as $invoice)
            <tr>
                <td><a class="font-bold text-villeneuve-green" href="{{ route('monthly-invoices.show', $invoice) }}">{{ $invoice->invoice_number }}</a></td>
                <td>{{ $invoice->client?->name ?? 'Client supprimé' }}</td>
                <td>{{ $invoice->invoice_month }}/{{ $invoice->invoice_year }}</td>
                <td>{{ $statuses[$invoice->status] ?? $invoice->status }}</td>
                <td class="text-right">{{ $money->format($invoice->grand_total_cents, $invoice->client?->default_language ?? 'fr') }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="5" class="py-6 text-center text-stone-500">Aucune facture trouvée.</td>
            </tr>
        @endforelse
    </table>
</div>

{{ $invoices->links() }}
@endsection
