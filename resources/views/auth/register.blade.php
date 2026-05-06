@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-md panel p-8">
    <h1 class="text-2xl font-extrabold text-villeneuve-forest">Créer un compte client</h1>
    <p class="mt-3 text-sm text-stone-700">
        Utilise le courriel associé à ton dossier client chez Nettoyeur Villeneuve.
        Si le courriel n’est pas reconnu, le compte ne sera pas créé.
    </p>

    <form class="mt-6 space-y-4" method="post" action="{{ route('register.store') }}">
        @csrf
        <div>
            <label class="label">Nom</label>
            <input class="mt-1 w-full" name="name" value="{{ old('name') }}" required autofocus>
        </div>
        <div>
            <label class="label">Courriel associé au client</label>
            <input class="mt-1 w-full" type="email" name="email" value="{{ old('email') }}" required>
        </div>
        <div>
            <label class="label">Mot de passe</label>
            <input class="mt-1 w-full" type="password" name="password" required>
        </div>
        <div>
            <label class="label">Confirmer le mot de passe</label>
            <input class="mt-1 w-full" type="password" name="password_confirmation" required>
        </div>
        <button class="btn btn-primary w-full">Créer mon compte</button>
    </form>

    <div class="mt-5 border-t border-villeneuve-line pt-5 text-sm text-stone-700">
        Déjà un compte?
        <a class="font-bold text-villeneuve-forest underline" href="{{ route('login') }}">Connexion</a>
    </div>
</div>
@endsection
