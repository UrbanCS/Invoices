@extends('layouts.app')

@section('content')
@php($statuses = ['draft' => 'Brouillon', 'approved' => 'Approuvée', 'sent' => 'Envoyée', 'paid' => 'Payée', 'cancelled' => 'Annulée'])

<div class="flex items-center justify-between">
    <div><p class="label">{{ $invoice->client->name }}</p><h1 class="text-3xl font-extrabold text-villeneuve-forest">Facture {{ $invoice->invoice_number }}</h1></div>
    @if($invoice->pdf_path)
        <a class="btn btn-primary" href="{{ route('portal.invoices.download', $invoice) }}">Télécharger PDF</a>
    @endif
</div>

<div class="panel mt-6 p-6">
    <p>Statut: <strong>{{ $statuses[$invoice->status] ?? $invoice->status }}</strong></p>
    <p>Grand total: <strong>{{ $money->format($invoice->grand_total_cents, $invoice->client->default_language) }}</strong></p>
    <p>Date de facture: {{ $invoice->invoice_date->format('Y-m-d') }}</p>
</div>
@endsection
