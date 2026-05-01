@extends('layouts.app')

@section('content')
@php
    $statuses = [
        'draft' => 'Brouillon',
        'approved' => 'Approuvée',
        'sent' => 'Envoyée',
        'paid' => 'Payée',
        'cancelled' => 'Annulée',
    ];
@endphp

<div class="flex flex-wrap items-center justify-between gap-3">
    <div>
        <p class="label">{{ $invoice->client->name }} · {{ $statuses[$invoice->status] ?? $invoice->status }}</p>
        <h1 class="text-3xl font-extrabold text-villeneuve-forest">Facture {{ $invoice->invoice_number }}</h1>
    </div>
    <div class="flex flex-wrap gap-2">
        <a class="btn btn-secondary" href="{{ route('monthly-invoices.edit', $invoice) }}">Modifier</a>
        <form method="post" action="{{ route('monthly-invoices.approve', $invoice) }}">@csrf<button class="btn btn-secondary">Approuver</button></form>
        <form method="post" action="{{ route('monthly-invoices.generate-pdf', $invoice) }}">@csrf<button class="btn btn-primary">Générer PDF</button></form>
        @if($invoice->pdf_path)
            <a class="btn btn-secondary" href="{{ route('monthly-invoices.download', $invoice) }}">Télécharger PDF</a>
        @endif
    </div>
</div>

<div class="mt-6 grid gap-6 lg:grid-cols-[1fr_340px]">
    <section class="panel overflow-x-auto p-6">
        <table class="table w-full">
            <tr>
                <th>Jour</th>
                @foreach($invoice->category_snapshot ?? [] as $category)
                    <th class="text-right">{{ $category['name'] }}</th>
                @endforeach
            </tr>
            @for($day = 1; $day <= 31; $day++)
                <tr>
                    <td class="font-bold">{{ $day }}</td>
                    @foreach($invoice->category_snapshot ?? [] as $category)
                        @php($sum = $invoice->entries->where('service_day', $day)->where('client_category_id', $category['id'])->sum('amount_cents'))
                        <td class="text-right">{{ $sum ? $money->format($sum, $invoice->client->default_language) : '' }}</td>
                    @endforeach
                </tr>
            @endfor
        </table>
    </section>

    <aside class="panel p-6">
        <h2 class="text-xl font-bold text-villeneuve-forest">Totaux</h2>
        <dl class="mt-4 space-y-3">
            <div class="flex justify-between"><dt>Sous-total</dt><dd>{{ $money->format($invoice->subtotal_cents, $invoice->client->default_language) }}</dd></div>
            <div class="flex justify-between"><dt>Rabais / crédits</dt><dd>-{{ $money->format($invoice->discount_cents, $invoice->client->default_language) }}</dd></div>
            @foreach($invoice->tax_profile_snapshot ?? [] as $tax)
                <div class="flex justify-between"><dt>{{ $tax['label'] }}</dt><dd>{{ $money->format($tax['amount_cents'], $invoice->client->default_language) }}</dd></div>
            @endforeach
            <div class="border-t pt-3 flex justify-between text-xl font-black text-villeneuve-forest"><dt>Grand total</dt><dd>{{ $money->format($invoice->grand_total_cents, $invoice->client->default_language) }}</dd></div>
        </dl>

        <div class="mt-6 grid gap-2">
            <form method="post" action="{{ route('monthly-invoices.mark-sent', $invoice) }}">@csrf<button class="btn btn-secondary w-full">Marquer envoyée</button></form>
            <form method="post" action="{{ route('monthly-invoices.mark-paid', $invoice) }}">@csrf<button class="btn btn-secondary w-full">Marquer payée</button></form>
            <form method="post" action="{{ route('monthly-invoices.cancel', $invoice) }}">@csrf<button class="btn btn-secondary w-full">Annuler</button></form>
            <a class="btn btn-secondary w-full" href="{{ route('monthly-invoices.export', $invoice) }}">Exporter CSV</a>
        </div>

        <form class="mt-6 border-t pt-4" method="post" enctype="multipart/form-data" action="{{ route('monthly-invoices.attachments', $invoice) }}">
            @csrf
            <label class="label">Pièce jointe</label>
            <input class="mt-2 w-full" type="file" name="attachment">
            <button class="btn btn-secondary mt-3 w-full">Téléverser</button>
        </form>
    </aside>
</div>
@endsection
