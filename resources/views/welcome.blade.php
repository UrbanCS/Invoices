@extends('layouts.app')

@section('content')
<section class="grid gap-10 lg:grid-cols-[1.1fr_.9fr] lg:items-center">
    <div>
        <p class="label">{{ __('app.landing_eyebrow') }}</p>
        <h1 class="mt-3 max-w-3xl text-5xl font-extrabold leading-tight text-villeneuve-forest">Nettoyeur Villeneuve</h1>
        <p class="mt-5 max-w-2xl text-lg text-stone-700">{{ __('app.landing_copy') }}</p>
        <div class="mt-8 flex gap-3">
            <a class="btn btn-primary" href="{{ route('login') }}">{{ __('app.sign_in') }}</a>
        </div>
    </div>
    <div class="panel p-8">
        <h2 class="text-2xl font-bold text-villeneuve-forest">{{ __('app.services') }}</h2>
        <div class="mt-5 grid gap-4">
            <div class="border-l-4 border-villeneuve-green pl-4"><strong>{{ __('app.hotel_valet_cleaning') }}</strong><p class="text-stone-600">{{ __('app.hotel_valet_cleaning_copy') }}</p></div>
            <div class="border-l-4 border-villeneuve-green pl-4"><strong>{{ __('app.commercial_accounts') }}</strong><p class="text-stone-600">{{ __('app.commercial_accounts_copy') }}</p></div>
            <div class="border-l-4 border-villeneuve-green pl-4"><strong>{{ __('app.client_portal') }}</strong><p class="text-stone-600">{{ __('app.client_portal_copy') }}</p></div>
        </div>
        <div class="mt-8 border-t border-villeneuve-line pt-5">
            <h2 class="text-xl font-bold text-villeneuve-forest">{{ __('app.contact') }}</h2>
            <p class="mt-2 text-stone-700">{{ $settings?->display_name ?? 'Nettoyeur Villeneuve' }}</p>
            <p class="text-stone-700">{{ $settings?->phone }}</p>
            <p class="text-stone-700">{{ $settings?->email }}</p>
        </div>
    </div>
</section>
@endsection
