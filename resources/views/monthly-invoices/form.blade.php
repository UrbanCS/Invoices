@extends('layouts.app')

@section('content')
@php
    $selectedClient = $clients->firstWhere('id', (int) old('client_id', $invoice->client_id)) ?? $clients->first();
    $hasCategories = (bool) $selectedClient?->activeCategories->isNotEmpty();
    $singleCategory = $selectedClient?->activeCategories->count() === 1;
@endphp

<div class="flex flex-wrap items-center justify-between gap-4">
    <h1 class="text-3xl font-extrabold text-villeneuve-forest">{{ $invoice->exists ? 'Modifier la facture' : 'Nouvelle facture' }}</h1>
    @if(auth()->user()->isSuperAdmin())
        <a class="btn btn-secondary" href="{{ route('clients.create') }}">Ajouter un client</a>
    @endif
</div>

<form class="mt-6 space-y-6" method="post" action="{{ $invoice->exists ? route('monthly-invoices.update', $invoice) : route('monthly-invoices.store') }}">
    @csrf
    @if($invoice->exists)
        @method('put')
    @endif

    <section class="panel grid gap-4 p-6 md:grid-cols-5">
        <div>
            <label class="label">Client</label>
            <select
                class="mt-1 w-full"
                name="client_id"
                required
                @if(! $invoice->exists)
                    onchange="const url = new URL(window.location.href); url.searchParams.set('client_id', this.value); url.searchParams.set('month', document.querySelector('[name=invoice_month]').value || '{{ $invoice->invoice_month }}'); url.searchParams.set('year', document.querySelector('[name=invoice_year]').value || '{{ $invoice->invoice_year }}'); window.location.href = url.toString();"
                @endif
            >
                @if($clients->isEmpty())
                    <option value="">Aucun client actif</option>
                @endif
                @foreach($clients as $client)
                    <option value="{{ $client->id }}" @selected($selectedClient?->id === $client->id)>{{ $client->name }}</option>
                @endforeach
            </select>
        </div>
        <div><label class="label">No de facture</label><input class="mt-1 w-full" name="invoice_number" value="{{ old('invoice_number', $invoice->invoice_number) }}" required></div>
        <div><label class="label">Mois</label><input class="mt-1 w-full" type="number" min="1" max="12" name="invoice_month" value="{{ old('invoice_month', $invoice->invoice_month) }}" required></div>
        <div><label class="label">Année</label><input class="mt-1 w-full" type="number" name="invoice_year" value="{{ old('invoice_year', $invoice->invoice_year) }}" required></div>
        <div><label class="label">Date de facture</label><input class="mt-1 w-full" type="date" name="invoice_date" value="{{ old('invoice_date', optional($invoice->invoice_date)->format('Y-m-d') ?? now()->toDateString()) }}"></div>
        <div>
            <label class="label">Mode</label>
            <select class="mt-1 w-full" name="source_mode">
                <option value="manual_grid" @selected(old('source_mode', $invoice->source_mode) === 'manual_grid')>Grille mensuelle</option>
                <option value="daily_records" @selected(old('source_mode', $invoice->source_mode) === 'daily_records')>Depuis registres révisés</option>
            </select>
        </div>
        <div class="md:col-span-4"><label class="label">Notes / crédit</label><input class="mt-1 w-full" name="notes" value="{{ old('notes', $invoice->notes) }}"></div>
    </section>

    <section class="panel overflow-x-auto p-6">
        <h2 class="text-xl font-bold text-villeneuve-forest">Grille mensuelle</h2>
        @if($clients->isEmpty())
            <div class="mt-4 border border-amber-200 bg-amber-50 p-3 text-sm font-semibold text-amber-900">
                Aucun client actif pour l’instant. Ajoute d’abord un client, puis reviens créer la facture.
            </div>
        @elseif(! $hasCategories)
            <div class="mt-4 border border-amber-200 bg-amber-50 p-3 text-sm font-semibold text-amber-900">
                Le client sélectionné n’a aucune catégorie active. Ajoute une catégorie au client avant d’entrer des montants.
            </div>
        @endif
        <table class="mt-4 w-full border-collapse text-sm">
            <thead>
                <tr>
                    <th class="border bg-villeneuve-mint p-2">Jour</th>
                    @foreach($selectedClient?->activeCategories ?? [] as $category)
                        <th class="border bg-villeneuve-mint p-2 text-right">{{ $singleCategory ? 'Montant' : $category->name }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @for($day = 1; $day <= 31; $day++)
                    <tr>
                        <td class="border p-2 font-bold">{{ $day }}</td>
                        @foreach($selectedClient?->activeCategories ?? [] as $category)
                            @php($entry = $entries->first(fn ($e) => $e->service_day == $day && $e->client_category_id == $category->id))
                            <td class="border p-1"><input class="w-full border-0 text-right" inputmode="decimal" placeholder="0,00" name="grid[{{ $day }}][{{ $category->id }}]" value="{{ old("grid.$day.$category->id", $entry ? number_format($entry->amount_cents / 100, 2) : '') }}"></td>
                        @endforeach
                    </tr>
                @endfor
            </tbody>
        </table>
    </section>

    <section class="panel p-6">
        <h2 class="text-xl font-bold text-villeneuve-forest">Rabais, crédits et frais</h2>
        @for($i = 0; $i < 5; $i++)
            @php($adj = $adjustments[$i] ?? null)
            <div class="mt-3 grid gap-3 md:grid-cols-4">
                <input name="adjustments[{{ $i }}][label]" aria-label="Libellé" value="{{ $adj?->label }}">
                <select name="adjustments[{{ $i }}][type]" aria-label="Type">
                    <option value="discount" @selected($adj?->type === 'discount')>Rabais</option>
                    <option value="credit" @selected($adj?->type === 'credit')>Crédit</option>
                    <option value="fee" @selected($adj?->type === 'fee')>Frais</option>
                </select>
                <select name="adjustments[{{ $i }}][client_category_id]" aria-label="Catégorie">
                    <option value="">Facture entière</option>
                    @foreach($selectedClient?->activeCategories ?? [] as $category)
                        <option value="{{ $category->id }}" @selected($adj?->client_category_id === $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
                <input class="text-right" name="adjustments[{{ $i }}][amount]" aria-label="Montant" value="{{ $adj ? number_format($adj->amount_cents / 100, 2) : '' }}">
            </div>
        @endfor
    </section>

    <button class="btn btn-primary" @disabled($clients->isEmpty() || ! $hasCategories)>Sauvegarder brouillon</button>
</form>
@endsection
