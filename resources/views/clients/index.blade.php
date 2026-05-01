@extends('layouts.app')

@section('content')
@php
    $taxProfiles = ['qc_tps_tvq' => 'TPS/TVQ Québec', 'on_hst' => 'TVH Ontario', 'custom' => 'Personnalisé'];
@endphp

<div class="flex items-center justify-between">
    <h1 class="text-3xl font-extrabold text-villeneuve-forest">Clients</h1>
    <a class="btn btn-primary" href="{{ route('clients.create') }}">Nouveau client</a>
</div>

<div class="panel mt-6 overflow-x-auto">
    <table class="table w-full">
        <tr><th>Nom</th><th>Taxes</th><th>Langue</th><th>Statut</th></tr>
        @foreach($clients as $client)
            <tr>
                <td><a class="font-bold text-villeneuve-green" href="{{ route('clients.show', $client) }}">{{ $client->name }}</a></td>
                <td>{{ $taxProfiles[$client->tax_profile] ?? $client->tax_profile }}</td>
                <td>{{ $client->default_language === 'fr' ? 'Français' : 'Anglais' }}</td>
                <td>{{ $client->is_active ? 'Actif' : 'Archivé' }}</td>
            </tr>
        @endforeach
    </table>
</div>

{{ $clients->links() }}
@endsection
