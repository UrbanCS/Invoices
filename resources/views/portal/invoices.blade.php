@extends('layouts.app')

@section('content')
@php($statuses = ['approved' => 'Approuvée', 'sent' => 'Envoyée', 'paid' => 'Payée', 'cancelled' => 'Annulée'])

<h1 class="text-3xl font-extrabold text-villeneuve-forest">Mes factures</h1>

<form class="mt-6 flex flex-wrap gap-3" method="get" action="{{ route('portal.invoices.index') }}">
    <input name="year" aria-label="Année" placeholder="Année" value="{{ $selectedYear ?: '' }}">
    <select name="status" onchange="this.form.submit()">
        <option value="">Tous les statuts</option>
        @foreach($statuses as $value => $label)
            <option value="{{ $value }}" @selected($selectedStatus === $value)>{{ $label }}</option>
        @endforeach
    </select>
    <button class="btn btn-secondary">Filtrer</button>
    @if($selectedStatus || $selectedYear)
        <a class="btn btn-secondary" href="{{ route('portal.invoices.index') }}">Réinitialiser</a>
    @endif
</form>

<div class="panel mt-6 overflow-x-auto">
    <table class="table w-full">
        <tr><th>Facture</th><th>Mois</th><th>Statut</th><th class="text-right">Total</th><th></th></tr>
        @forelse($invoices as $invoice)
            <tr><td><a class="font-bold text-villeneuve-green" href="{{ route('portal.invoices.show', $invoice) }}">{{ $invoice->invoice_number }}</a></td><td>{{ $invoice->invoice_month }}/{{ $invoice->invoice_year }}</td><td>{{ $statuses[$invoice->status] ?? $invoice->status }}</td><td class="text-right">{{ $money->format($invoice->grand_total_cents, auth()->user()->client->default_language ?? 'fr') }}</td><td>@if($invoice->pdf_path)<a class="btn btn-secondary" href="{{ route('portal.invoices.download', $invoice) }}">Télécharger</a>@endif</td></tr>
        @empty
            <tr>
                <td colspan="5" class="text-center text-stone-600">Aucune facture ne correspond aux filtres.</td>
            </tr>
        @endforelse
    </table>
</div>

{{ $invoices->links() }}
@endsection
