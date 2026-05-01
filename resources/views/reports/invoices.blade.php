@extends('layouts.app')

@section('content')
@php($statuses = ['draft' => 'Brouillon', 'approved' => 'Approuvée', 'sent' => 'Envoyée', 'paid' => 'Payée', 'cancelled' => 'Annulée'])

<h1 class="text-3xl font-extrabold text-villeneuve-forest">Rapport des factures</h1>
<div class="panel mt-6 overflow-x-auto">
    <table class="table w-full">
        <tr><th>#</th><th>Client</th><th>Date</th><th>Statut</th><th class="text-right">Total</th></tr>
        @foreach($invoices as $invoice)
            <tr><td>{{ $invoice->invoice_number }}</td><td>{{ $invoice->client->name }}</td><td>{{ $invoice->invoice_date->format('Y-m-d') }}</td><td>{{ $statuses[$invoice->status] ?? $invoice->status }}</td><td class="text-right">{{ $money->format($invoice->grand_total_cents, $invoice->client->default_language) }}</td></tr>
        @endforeach
    </table>
</div>
{{ $invoices->links() }}
@endsection
