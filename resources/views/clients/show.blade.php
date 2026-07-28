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
        <h2 class="font-bold text-villeneuve-forest">Catalogue de nettoyage</h2>
        @forelse($client->categories->where('is_active', true)->groupBy('service_type') as $serviceType => $serviceCategories)
            <div class="mt-4 border-t border-villeneuve-line pt-3 first:mt-3 first:border-0 first:pt-0">
                <h3 class="font-bold text-villeneuve-forest">{{ App\Models\ClientCategory::serviceLabel($serviceType) }}</h3>
                @foreach($serviceCategories->groupBy('audience') as $audience => $audienceCategories)
                    <div class="mt-3">
                        <div class="label">{{ App\Models\ClientCategory::audienceLabel($audience) }}</div>
                        @foreach($audienceCategories as $category)
                            <p class="mt-1">
                                {{ $category->sort_order }}. {{ $category->name }}
                                <span class="text-stone-500">- {{ number_format($category->default_price_cents / 100, 2, ',', ' ') }} $</span>
                                {{ $category->is_taxable ? '' : '(non taxable)' }}
                            </p>
                        @endforeach
                    </div>
                @endforeach
            </div>
        @empty
            <p class="mt-2 text-stone-500">Aucun item actif.</p>
        @endforelse
    </section>
</div>
@endsection
