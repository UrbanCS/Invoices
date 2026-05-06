@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-md panel p-8">
    <h1 class="text-2xl font-extrabold text-villeneuve-forest">{{ __('app.sign_in') }}</h1>
    <form class="mt-6 space-y-4" method="post" action="{{ route('login.store') }}">
        @csrf
        <div><label class="label">{{ __('app.email') }}</label><input class="mt-1 w-full" type="email" name="email" value="{{ old('email') }}" required autofocus></div>
        <div><label class="label">{{ __('app.password') }}</label><input class="mt-1 w-full" type="password" name="password" required></div>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="remember"> {{ __('app.remember_me') }}</label>
        <button class="btn btn-primary w-full">{{ __('app.sign_in') }}</button>
    </form>
    <div class="mt-5 border-t border-villeneuve-line pt-5 text-sm text-stone-700">
        Vous êtes un client avec un courriel déjà associé?
        <a class="font-bold text-villeneuve-forest underline" href="{{ route('register') }}">Créer un compte client</a>
    </div>
</div>
@endsection
