@extends('layouts.app')
@section('content')
<div class="flex items-center justify-between"><div><p class="label">{{ $invoice->client->name }}</p><h1 class="text-3xl font-extrabold text-villeneuve-forest">Invoice {{ $invoice->invoice_number }}</h1></div>@if($invoice->pdf_path)<a class="btn btn-primary" href="{{ route('portal.invoices.download',$invoice) }}">Download PDF</a>@endif</div>
<div class="panel mt-6 p-6"><p>Status: <strong>{{ $invoice->status }}</strong></p><p>Grand total: <strong>{{ $money->format($invoice->grand_total_cents, $invoice->client->default_language) }}</strong></p><p>Invoice date: {{ $invoice->invoice_date->format('Y-m-d') }}</p></div>
@endsection
