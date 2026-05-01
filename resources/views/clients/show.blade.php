@extends('layouts.app')

@section('content')
@php($taxProfiles = ['qc_tps_tvq' => 'TPS/TVQ Québec', 'on_hst' => 'TVH Ontario', 'custom' => 'Personnalisé'])

<div class="flex items-center justify-between">
    <div><p class="label">Client</p><h1 class="text-3xl font-extrabold text-villeneuve-forest">{{ $client->name }}</h1></div>
    <div class="flex gap-2"><a class="btn btn-secondary" href="{{ route('clients.edit', $client) }}">Modifier</a><a class="btn btn-primary" href="{{ route('clients.categories.index', $client) }}">Catégories</a></div>
</div>

<div class="mt-6 grid gap-6 lg:grid-cols-2">
    <section class="panel p-6">
        <h2 class="font-bold text-villeneuve-forest">Facturation</h2>
        <p class="mt-3">{{ $client->billing_address }}</p>
        <p>{{ $client->city }} {{ $client->province }} {{ $client->postal_code }}</p>
        <p class="mt-3">{{ $client->email }}</p>
        <p class="mt-3">Taxes: {{ $taxProfiles[$client->tax_profile] ?? $client->tax_profile }}</p>
    </section>
    <section class="panel p-6">
        <h2 class="font-bold text-villeneuve-forest">Catégories</h2>
        @foreach($client->categories as $category)
            <p class="mt-2">{{ $category->sort_order }}. {{ $category->name }} {{ $category->is_taxable ? '' : '(non taxable)' }}</p>
        @endforeach
    </section>
</div>
@endsection
