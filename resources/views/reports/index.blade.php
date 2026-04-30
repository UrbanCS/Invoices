@extends('layouts.app')
@section('content')
<h1 class="text-3xl font-extrabold text-villeneuve-forest">Reports</h1>
<div class="mt-6 grid gap-4 md:grid-cols-4"><a class="panel p-5 font-bold text-villeneuve-forest" href="{{ route('reports.invoices') }}">Invoices by date</a><a class="panel p-5 font-bold text-villeneuve-forest" href="{{ route('reports.revenue') }}">Revenue by month</a><a class="panel p-5 font-bold text-villeneuve-forest" href="{{ route('reports.category-totals') }}">Category totals</a><a class="panel p-5 font-bold text-villeneuve-forest" href="{{ route('reports.export.csv') }}">Export CSV</a></div>
<section class="panel mt-6 p-5"><h2 class="text-xl font-bold text-villeneuve-forest">Unpaid Invoices</h2><table class="table mt-4 w-full">@foreach($unpaid as $invoice)<tr><td>{{ $invoice->invoice_number }}</td><td>{{ $invoice->client->name }}</td><td>{{ $invoice->status }}</td><td class="text-right">{{ $money->format($invoice->grand_total_cents,$invoice->client->default_language) }}</td></tr>@endforeach</table></section>
@endsection
