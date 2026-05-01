@extends('layouts.app')

@section('content')
<h1 class="text-3xl font-extrabold text-villeneuve-forest">Totaux par catégorie</h1>
<div class="panel mt-6 overflow-x-auto">
    <table class="table w-full">
        <tr><th>Client</th><th>Mois</th><th>Catégorie</th><th class="text-right">Total</th></tr>
        @foreach($rows as $row)
            <tr><td>{{ $row->client_name }}</td><td>{{ $row->invoice_month }}/{{ $row->invoice_year }}</td><td>{{ $row->category_name_snapshot }}</td><td class="text-right">{{ $money->format($row->total_cents) }}</td></tr>
        @endforeach
    </table>
</div>
@endsection
