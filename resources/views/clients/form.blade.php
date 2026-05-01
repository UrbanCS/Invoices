@extends('layouts.app')
@section('content')
<h1 class="text-3xl font-extrabold text-villeneuve-forest">{{ $client->exists ? 'Edit Client' : 'New Client' }}</h1>
<form class="panel mt-6 grid gap-4 p-6 md:grid-cols-2" method="post" enctype="multipart/form-data" action="{{ $client->exists ? route('clients.update', $client) : route('clients.store') }}">
    @csrf @if($client->exists) @method('put') @endif
    <div><label class="label">Name</label><input class="mt-1 w-full" name="name" value="{{ old('name', $client->name) }}" required></div>
    <div><label class="label">Legal name</label><input class="mt-1 w-full" name="legal_name" value="{{ old('legal_name', $client->legal_name) }}"></div>
    <div class="md:col-span-2"><label class="label">Billing address</label><input class="mt-1 w-full" name="billing_address" value="{{ old('billing_address', $client->billing_address) }}"></div>
    <div><label class="label">City</label><input class="mt-1 w-full" name="city" value="{{ old('city', $client->city) }}"></div>
    <div><label class="label">Province</label><input class="mt-1 w-full" name="province" value="{{ old('province', $client->province) }}"></div>
    <div><label class="label">Postal code</label><input class="mt-1 w-full" name="postal_code" value="{{ old('postal_code', $client->postal_code) }}"></div>
    <div><label class="label">Email</label><input class="mt-1 w-full" name="email" value="{{ old('email', $client->email) }}"></div>
    <div><label class="label">Tax profile</label><select class="mt-1 w-full" name="tax_profile"><option value="on_hst" @selected(old('tax_profile',$client->tax_profile)==='on_hst')>Ontario HST/GST-style</option><option value="qc_tps_tvq" @selected(old('tax_profile',$client->tax_profile)==='qc_tps_tvq')>Québec TPS/TVQ</option><option value="custom" @selected(old('tax_profile',$client->tax_profile)==='custom')>Custom</option></select></div>
    <div><label class="label">Language</label><select class="mt-1 w-full" name="default_language"><option value="en" @selected(old('default_language',$client->default_language)==='en')>English</option><option value="fr" @selected(old('default_language',$client->default_language)==='fr')>French</option></select></div>
    <div><label class="label">Logo du client</label><input class="mt-1 w-full" type="file" name="logo"></div>
    <div class="md:col-span-2"><label class="label">Notes</label><textarea class="mt-1 w-full" name="notes">{{ old('notes', $client->notes) }}</textarea></div>
    <label class="flex items-center gap-2"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $client->is_active ?? true))> Active</label>
    <div class="md:col-span-2"><button class="btn btn-primary">Save Client</button></div>
</form>
@endsection
