@extends('layouts.app')

@section('content')
@php($statuses = ['draft' => 'Brouillon', 'approved' => 'Approuvée', 'sent' => 'Envoyée', 'paid' => 'Payée', 'cancelled' => 'Annulée'])

<h1 class="text-3xl font-extrabold text-villeneuve-forest">Rapports</h1>

<div class="mt-6 grid gap-4 md:grid-cols-4">
    <a class="panel p-5 font-bold text-villeneuve-forest" href="{{ route('reports.invoices') }}">Factures par date</a>
    <a class="panel p-5 font-bold text-villeneuve-forest" href="{{ route('reports.revenue') }}">Revenus par mois</a>
    <a class="panel p-5 font-bold text-villeneuve-forest" href="{{ route('reports.category-totals') }}">Totaux par catégorie</a>
    <a class="panel p-5 font-bold text-villeneuve-forest" href="{{ route('reports.export.csv') }}">Exporter CSV</a>
</div>

<section class="panel mt-6 p-5">
    <h2 class="text-xl font-bold text-villeneuve-forest">Factures impayées</h2>
    <table class="table mt-4 w-full">
        @foreach($unpaid as $invoice)
            <tr><td>{{ $invoice->invoice_number }}</td><td>{{ $invoice->client->name }}</td><td>{{ $statuses[$invoice->status] ?? $invoice->status }}</td><td class="text-right">{{ $money->format($invoice->grand_total_cents, $invoice->client->default_language) }}</td></tr>
        @endforeach
    </table>
</section>
@endsection
