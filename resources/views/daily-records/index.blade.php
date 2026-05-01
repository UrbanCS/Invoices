@extends('layouts.app')

@section('content')
@php($statuses = ['draft' => 'Brouillon', 'reviewed' => 'Révisé', 'invoiced' => 'Facturé'])

<div class="flex items-center justify-between">
    <h1 class="text-3xl font-extrabold text-villeneuve-forest">Registres quotidiens</h1>
    <a class="btn btn-primary" href="{{ route('daily-records.create') }}">Nouveau registre</a>
</div>

<form class="mt-6 flex flex-wrap gap-3" method="get">
    <select name="client_id">
        <option value="">Tous les clients</option>
        @foreach($clients as $client)
            <option value="{{ $client->id }}" @selected(request('client_id') == $client->id)>{{ $client->name }}</option>
        @endforeach
    </select>
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
        <tr><th>Date</th><th>Client</th><th>Statut</th><th>Référence</th></tr>
        @foreach($records as $record)
            <tr>
                <td><a class="font-bold text-villeneuve-green" href="{{ route('daily-records.show', $record) }}">{{ $record->service_date->format('Y-m-d') }}</a></td>
                <td>{{ $record->client->name }}</td>
                <td>{{ $statuses[$record->status] ?? $record->status }}</td>
                <td>{{ $record->reference_number }}</td>
            </tr>
        @endforeach
    </table>
</div>

{{ $records->links() }}
@endsection
