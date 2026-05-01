@extends('layouts.app')

@section('content')
@php($selectedClient = $clients->firstWhere('id', old('client_id', $invoice->client_id)) ?? $clients->first())

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
            <select class="mt-1 w-full" name="client_id">
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
        <table class="mt-4 w-full border-collapse text-sm">
            <thead>
                <tr>
                    <th class="border bg-villeneuve-mint p-2">Jour</th>
                    @foreach($selectedClient?->activeCategories ?? [] as $category)
                        <th class="border bg-villeneuve-mint p-2 text-right">{{ $category->name }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @for($day = 1; $day <= 31; $day++)
                    <tr>
                        <td class="border p-2 font-bold">{{ $day }}</td>
                        @foreach($selectedClient?->activeCategories ?? [] as $category)
                            @php($entry = $entries->first(fn ($e) => $e->service_day == $day && $e->client_category_id == $category->id))
                            <td class="border p-1"><input class="w-full border-0 text-right" name="grid[{{ $day }}][{{ $category->id }}]" value="{{ old("grid.$day.$category->id", $entry ? number_format($entry->amount_cents / 100, 2) : '') }}"></td>
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

    <button class="btn btn-primary">Sauvegarder brouillon</button>
</form>
@endsection
