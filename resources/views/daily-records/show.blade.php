@extends('layouts.app')

@section('content')
@php($statuses = ['draft' => 'Brouillon', 'reviewed' => 'Révisé', 'invoiced' => 'Facturé'])

<div class="flex items-center justify-between">
    <div><p class="label">{{ $record->client->name }}</p><h1 class="text-3xl font-extrabold text-villeneuve-forest">Registre valet quotidien {{ $record->service_date->format('Y-m-d') }}</h1></div>
    <a class="btn btn-secondary" href="{{ route('daily-records.edit', $record) }}">Modifier</a>
</div>

<div class="panel mt-6 overflow-x-auto p-6">
    <div class="mb-4 flex justify-between"><span class="font-bold">Statut: {{ $statuses[$record->status] ?? $record->status }}</span><span class="font-bold">Référence: {{ $record->reference_number }}</span></div>
    <table class="table w-full">
        <tr><th>Nom</th><th>Dept / chambre / réf.</th><th>Description</th><th>Catégorie</th><th class="text-right">Frais</th></tr>
        @foreach($record->items as $item)
            <tr><td>{{ $item->customer_name }}</td><td>{{ $item->department_or_room }}</td><td>{{ $item->description }}</td><td>{{ $item->category?->name }}</td><td class="text-right">{{ $money->format($item->amount_cents, $record->client->default_language) }}</td></tr>
        @endforeach
        <tr><td colspan="4" class="text-right font-bold">Total quotidien</td><td class="text-right font-bold">{{ $money->format($record->totalCents(), $record->client->default_language) }}</td></tr>
    </table>
</div>

<div class="mt-6 grid gap-6 lg:grid-cols-2">
    <form class="panel p-5" method="post" action="{{ route('daily-records.attachments', $record) }}" enctype="multipart/form-data">
        @csrf
        <h2 class="font-bold text-villeneuve-forest">Joindre la feuille originale photo/PDF</h2>
        <label class="btn btn-secondary mt-4 cursor-pointer">
            Choisir un fichier
            <input class="sr-only" type="file" name="attachment" accept=".jpg,.jpeg,.png,.pdf" required>
        </label>
        <button class="btn btn-primary mt-4">Téléverser</button>
    </form>
    <div class="panel p-5">
        <h2 class="font-bold text-villeneuve-forest">Révision</h2>
        @if($record->status === 'draft')
            <form class="mt-4" method="post" action="{{ route('daily-records.review', $record) }}">@csrf<button class="btn btn-primary">Marquer révisé</button></form>
        @else
            <p class="mt-4">Les registres révisés ou facturés peuvent être utilisés dans les factures mensuelles.</p>
        @endif
    </div>
</div>
@endsection
