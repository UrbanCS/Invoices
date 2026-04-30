@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-md panel p-8">
    <h1 class="text-2xl font-extrabold text-villeneuve-forest">Sign in</h1>
    <form class="mt-6 space-y-4" method="post" action="{{ route('login.store') }}">
        @csrf
        <div><label class="label">Email</label><input class="mt-1 w-full" type="email" name="email" value="{{ old('email') }}" required autofocus></div>
        <div><label class="label">Password</label><input class="mt-1 w-full" type="password" name="password" required></div>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="remember"> Remember me</label>
        <button class="btn btn-primary w-full">Login</button>
    </form>
</div>
@endsection
