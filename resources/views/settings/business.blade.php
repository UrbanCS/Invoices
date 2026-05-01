@extends('layouts.app')

@section('content')
<h1 class="text-3xl font-extrabold text-villeneuve-forest">Paramètres de l’entreprise</h1>

<form class="panel mt-6 grid gap-4 p-6 md:grid-cols-2" method="post" enctype="multipart/form-data" action="{{ route('settings.business.update') }}">
    @csrf
    @method('put')

    @foreach([
        'legal_name' => 'Nom légal',
        'display_name' => 'Nom affiché',
        'address' => 'Adresse',
        'city' => 'Ville',
        'province' => 'Province',
        'postal_code' => 'Code postal',
        'phone' => 'Téléphone',
        'email' => 'Courriel',
        'gst_number' => 'Numéro TPS/GST',
        'qst_number' => 'Numéro TVQ/QST',
    ] as $field => $label)
        <div><label class="label">{{ $label }}</label><input class="mt-1 w-full" name="{{ $field }}" value="{{ old($field, $settings->$field) }}"></div>
    @endforeach

    <div>
        <label class="label">Langue</label>
        <select class="mt-1 w-full" name="default_language">
            <option value="fr" selected>Français</option>
        </select>
    </div>
    <div>
        <span class="label">Logo</span>
        <label class="btn btn-secondary mt-1 w-full cursor-pointer">
            Choisir un fichier
            <input class="sr-only" type="file" name="logo">
        </label>
    </div>
    <div class="md:col-span-2"><label class="label">Instructions de paiement</label><textarea class="mt-1 w-full" name="default_payment_instructions">{{ old('default_payment_instructions', $settings->default_payment_instructions) }}</textarea></div>
    <div class="md:col-span-2"><label class="label">Message de remerciement</label><textarea class="mt-1 w-full" name="default_thank_you_message">{{ old('default_thank_you_message', $settings->default_thank_you_message) }}</textarea></div>
    <button class="btn btn-primary">Sauvegarder les paramètres</button>
</form>
@endsection
