<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }}</title>
    @php
        $manifestPath = public_path('build/manifest.json');
        $viteManifest = file_exists($manifestPath) ? json_decode(file_get_contents($manifestPath), true) : null;
    @endphp
    @if($viteManifest)
        <link rel="stylesheet" href="{{ asset('build/'.$viteManifest['resources/css/app.css']['file']) }}">
        <script type="module" src="{{ asset('build/'.$viteManifest['resources/js/app.js']['file']) }}"></script>
    @else
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body>
<div class="min-h-screen">
    <header class="border-b border-villeneuve-line bg-white">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4">
            <a href="{{ auth()->check() ? (auth()->user()->isClientUser() ? route('portal.invoices.index') : route('dashboard')) : route('home') }}" class="flex items-center gap-3">
                <span class="grid h-10 w-10 place-items-center rounded bg-villeneuve-forest text-lg font-black text-white">NV</span>
                <span>
                    <span class="block text-lg font-extrabold text-villeneuve-forest">Nettoyeur Villeneuve</span>
                    <span class="block text-xs font-semibold uppercase text-stone-500">{{ __('app.invoice_portal') }}</span>
                </span>
            </a>
            <nav class="flex flex-wrap items-center gap-2 text-sm font-semibold">
                @auth
                    @if(auth()->user()->canManage())
                        <a class="px-2 py-1 hover:text-villeneuve-green" href="{{ route('dashboard') }}">{{ __('app.dashboard') }}</a>
                        @if(auth()->user()->isSuperAdmin())
                            <a class="px-2 py-1 hover:text-villeneuve-green" href="{{ route('clients.index') }}">{{ __('app.clients') }}</a>
                        @endif
                        <a class="px-2 py-1 hover:text-villeneuve-green" href="{{ route('daily-records.index') }}">{{ __('app.daily_records') }}</a>
                        <a class="px-2 py-1 hover:text-villeneuve-green" href="{{ route('monthly-invoices.index') }}">{{ __('app.invoices') }}</a>
                        <a class="px-2 py-1 hover:text-villeneuve-green" href="{{ route('reports.index') }}">{{ __('app.reports') }}</a>
                        @if(auth()->user()->isSuperAdmin())
                            <a class="px-2 py-1 hover:text-villeneuve-green" href="{{ route('settings.business.edit') }}">{{ __('app.settings') }}</a>
                        @endif
                    @else
                        <a class="px-2 py-1 hover:text-villeneuve-green" href="{{ route('portal.invoices.index') }}">{{ __('app.my_invoices') }}</a>
                    @endif
                    <form method="post" action="{{ route('logout') }}">@csrf <button class="btn btn-secondary">{{ __('app.logout') }}</button></form>
                @else
                    <a class="btn btn-primary" href="{{ route('login') }}">{{ __('app.sign_in') }}</a>
                @endauth
            </nav>
        </div>
    </header>
    <main class="mx-auto max-w-7xl px-4 py-8">
        @if(session('status'))
            <div class="mb-6 border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-900">{{ session('status') }}</div>
        @endif
        @if($errors->any())
            <div class="mb-6 border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">{{ $errors->first() }}</div>
        @endif
        @yield('content')
    </main>
</div>
</body>
</html>
