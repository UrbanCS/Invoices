@extends('layouts.app')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-4">
    <div>
        <p class="label">{{ $client->name }}</p>
        <h1 class="text-3xl font-extrabold text-villeneuve-forest">Nouvelle commande de nettoyage</h1>
    </div>
    <a class="btn btn-secondary" href="{{ route('portal.orders.index') }}">Mes commandes</a>
</div>

<form class="mt-6 space-y-6" method="post" action="{{ route('portal.orders.store') }}">
    @csrf

    <section class="panel grid gap-4 p-6 md:grid-cols-3">
        <div>
            <label class="label">Date du service</label>
            <input class="mt-1 w-full" type="date" name="service_date" value="{{ old('service_date', now()->toDateString()) }}" required>
        </div>
        <div>
            <label class="label">Nom de l’employé</label>
            <select class="mt-1 w-full" name="employee_name">
                <option value="">Choisir un nom sauvegardé</option>
                @foreach($client->employeeNames as $employeeName)
                    <option value="{{ $employeeName->name }}" @selected(old('employee_name') === $employeeName->name)>{{ $employeeName->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label">Ajouter un nouveau nom</label>
            <input class="mt-1 w-full" name="new_employee_name" value="{{ old('new_employee_name') }}" placeholder="Ex. Julian, Marie, réception">
        </div>
        <div class="md:col-span-3">
            <label class="label">Notes</label>
            <textarea class="mt-1 w-full" name="notes" rows="2" placeholder="Informations utiles pour Nettoyeur Villeneuve">{{ old('notes') }}</textarea>
        </div>
    </section>

    <section class="panel overflow-x-auto p-6">
        <h2 class="text-xl font-bold text-villeneuve-forest">Items à nettoyer</h2>
        <p class="mt-1 text-sm text-stone-600">Les prix sont fixés par Nettoyeur Villeneuve. Tu peux seulement entrer les quantités.</p>

        <table class="mt-4 w-full border-collapse text-sm">
            <tr>
                <th class="border bg-villeneuve-mint p-2 text-left">Item</th>
                <th class="border bg-villeneuve-mint p-2 text-right">Prix unit.</th>
                <th class="border bg-villeneuve-mint p-2 text-right">Quantité</th>
                <th class="border bg-villeneuve-mint p-2 text-right">Total</th>
            </tr>
            @forelse($client->activeCategories as $category)
                <tr data-order-row>
                    <td class="border p-2 font-semibold">{{ $category->name }}</td>
                    <td class="border p-2 text-right" data-unit-price="{{ $category->default_price_cents }}">
                        {{ number_format($category->default_price_cents / 100, 2, ',', ' ') }} $
                    </td>
                    <td class="border p-1">
                        <input
                            class="w-full text-right"
                            type="number"
                            min="0"
                            step="0.01"
                            name="quantities[{{ $category->id }}]"
                            value="{{ old('quantities.'.$category->id) }}"
                            data-quantity
                        >
                    </td>
                    <td class="border p-2 text-right font-semibold" data-line-total>0,00 $</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="border p-3 text-center text-stone-600">Aucun item disponible pour ce client.</td>
                </tr>
            @endforelse
        </table>

        <div class="mt-4 flex justify-end text-xl font-black text-villeneuve-forest">
            Total: <span class="ml-3" data-order-total>0,00 $</span>
        </div>
    </section>

    <button class="btn btn-primary" @disabled($client->activeCategories->isEmpty())>Envoyer la commande</button>
</form>

<script>
    (() => {
        const format = (cents) => `${(cents / 100).toFixed(2).replace('.', ',')} $`;
        const rows = document.querySelectorAll('[data-order-row]');
        const orderTotal = document.querySelector('[data-order-total]');

        const recalculate = () => {
            let total = 0;

            rows.forEach((row) => {
                const unit = Number.parseInt(row.querySelector('[data-unit-price]').dataset.unitPrice || '0', 10);
                const quantity = Number.parseFloat(row.querySelector('[data-quantity]').value || '0') || 0;
                const line = Math.round(unit * quantity);

                total += line;
                row.querySelector('[data-line-total]').textContent = format(line);
            });

            if (orderTotal) {
                orderTotal.textContent = format(total);
            }
        };

        rows.forEach((row) => row.querySelector('[data-quantity]').addEventListener('input', recalculate));
        recalculate();
    })();
</script>
@endsection
