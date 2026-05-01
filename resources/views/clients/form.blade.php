@extends('layouts.app')

@section('content')
<h1 class="text-3xl font-extrabold text-villeneuve-forest">{{ $client->exists ? 'Modifier le client' : 'Nouveau client' }}</h1>

<form class="panel mt-6 grid gap-4 p-6 md:grid-cols-2" method="post" enctype="multipart/form-data" action="{{ $client->exists ? route('clients.update', $client) : route('clients.store') }}">
    @csrf
    @if($client->exists)
        @method('put')
    @endif

    <div><label class="label">Nom</label><input class="mt-1 w-full" name="name" value="{{ old('name', $client->name) }}" required></div>
    <div><label class="label">Nom légal</label><input class="mt-1 w-full" name="legal_name" value="{{ old('legal_name', $client->legal_name) }}"></div>
    <div class="md:col-span-2"><label class="label">Adresse de facturation</label><input class="mt-1 w-full" name="billing_address" value="{{ old('billing_address', $client->billing_address) }}"></div>
    <div><label class="label">Ville</label><input class="mt-1 w-full" name="city" value="{{ old('city', $client->city) }}"></div>
    <div><label class="label">Province</label><input class="mt-1 w-full" name="province" value="{{ old('province', $client->province) }}"></div>
    <div><label class="label">Code postal</label><input class="mt-1 w-full" name="postal_code" value="{{ old('postal_code', $client->postal_code) }}"></div>
    <div><label class="label">Courriel</label><input class="mt-1 w-full" name="email" value="{{ old('email', $client->email) }}"></div>
    <div>
        <label class="label">Profil de taxes</label>
        <select class="mt-1 w-full" name="tax_profile">
            <option value="on_hst" @selected(old('tax_profile', $client->tax_profile) === 'on_hst')>TVH Ontario</option>
            <option value="qc_tps_tvq" @selected(old('tax_profile', $client->tax_profile) === 'qc_tps_tvq')>TPS/TVQ Québec</option>
            <option value="custom" @selected(old('tax_profile', $client->tax_profile) === 'custom')>Personnalisé</option>
        </select>
    </div>
    <div>
        <label class="label">Langue</label>
        <select class="mt-1 w-full" name="default_language">
            <option value="fr" @selected(old('default_language', $client->default_language) === 'fr')>Français</option>
        </select>
    </div>
    <div>
        <span class="label">Logo du client</span>
        <label class="btn btn-secondary mt-1 w-full cursor-pointer">
            Choisir un fichier
            <input class="sr-only" type="file" name="logo">
        </label>
    </div>
    <div class="md:col-span-2"><label class="label">Notes</label><textarea class="mt-1 w-full" name="notes">{{ old('notes', $client->notes) }}</textarea></div>
    <label class="flex items-center gap-2"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $client->is_active ?? true))> Actif</label>
    <div class="md:col-span-2"><button class="btn btn-primary">Sauvegarder</button></div>
</form>
@endsection
