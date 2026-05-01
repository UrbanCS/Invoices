@extends('layouts.app')

@section('content')
@php($statuses = ['approved' => 'Approuvée', 'sent' => 'Envoyée', 'paid' => 'Payée', 'cancelled' => 'Annulée'])

<h1 class="text-3xl font-extrabold text-villeneuve-forest">Mes factures</h1>

<form class="mt-6 flex gap-3" method="get">
    <input name="year" aria-label="Année" value="{{ request('year') }}">
    <select name="status">
        <option value="">Tous les statuts</option>
        @foreach($statuses as $value => $label)
            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
        @endforeach
    </select>
    <button class="btn btn-secondary">Filtrer</button>
</form>

<div class="panel mt-6 overflow-x-auto">
    <table class="table w-full">
        <tr><th>Facture</th><th>Mois</th><th>Statut</th><th class="text-right">Total</th><th></th></tr>
        @foreach($invoices as $invoice)
            <tr><td><a class="font-bold text-villeneuve-green" href="{{ route('portal.invoices.show', $invoice) }}">{{ $invoice->invoice_number }}</a></td><td>{{ $invoice->invoice_month }}/{{ $invoice->invoice_year }}</td><td>{{ $statuses[$invoice->status] ?? $invoice->status }}</td><td class="text-right">{{ $money->format($invoice->grand_total_cents, auth()->user()->client->default_language ?? 'fr') }}</td><td>@if($invoice->pdf_path)<a class="btn btn-secondary" href="{{ route('portal.invoices.download', $invoice) }}">Télécharger</a>@endif</td></tr>
        @endforeach
    </table>
</div>

{{ $invoices->links() }}
@endsection
