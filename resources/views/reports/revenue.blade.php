@extends('layouts.app')
@section('content')
<h1 class="text-3xl font-extrabold text-villeneuve-forest">Revenue by Month</h1>
<div class="panel mt-6 overflow-x-auto"><table class="table w-full"><tr><th>Month</th><th class="text-right">Revenue</th></tr>@foreach($rows as $row)<tr><td>{{ $row->invoice_month }}/{{ $row->invoice_year }}</td><td class="text-right">{{ $money->format($row->total_cents) }}</td></tr>@endforeach</table></div>
@endsection
