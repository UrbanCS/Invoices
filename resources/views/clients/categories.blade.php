@extends('layouts.app')

@section('content')
<h1 class="text-3xl font-extrabold text-villeneuve-forest">Catégories de {{ $client->name }}</h1>

<div class="mt-6 grid gap-6 lg:grid-cols-[1fr_360px]">
    <section class="panel overflow-x-auto">
        <table class="table w-full">
            <tr><th>Nom</th><th>Ordre</th><th>Taxable</th><th>Active</th></tr>
            @foreach($client->categories as $category)
                <tr><td>{{ $category->name }}</td><td>{{ $category->sort_order }}</td><td>{{ $category->is_taxable ? 'Oui' : 'Non' }}</td><td>{{ $category->is_active ? 'Oui' : 'Non' }}</td></tr>
            @endforeach
        </table>
    </section>
    <form class="panel space-y-4 p-5" method="post" action="{{ route('clients.categories.store', $client) }}">
        @csrf
        <h2 class="font-bold text-villeneuve-forest">Ajouter une catégorie</h2>
        <div><label class="label">Nom</label><input class="mt-1 w-full" name="name" required></div>
        <div><label class="label">Ordre d’affichage</label><input class="mt-1 w-full" type="number" name="sort_order" value="0"></div>
        <label class="flex gap-2"><input type="checkbox" name="is_taxable" value="1" checked> Taxable</label>
        <label class="flex gap-2"><input type="checkbox" name="is_active" value="1" checked> Active</label>
        <button class="btn btn-primary">Sauvegarder</button>
    </form>
</div>
@endsection
