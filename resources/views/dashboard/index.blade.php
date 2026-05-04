@extends('layouts.app')

@section('content')
@php($money = app(App\Services\MoneyFormatter::class))
<div class="flex flex-wrap items-center justify-between gap-4">
    <div><p class="label">{{ __('app.operations') }}</p><h1 class="text-3xl font-extrabold text-villeneuve-forest">{{ __('app.dashboard') }}</h1></div>
    <div class="flex gap-2"><a class="btn btn-primary" href="{{ route('daily-records.create') }}">{{ __('app.new_daily_record') }}</a><a class="btn btn-secondary" href="{{ route('monthly-invoices.create') }}">{{ __('app.new_invoice') }}</a></div>
</div>
<div class="mt-6 grid gap-4 md:grid-cols-5">
    <div class="panel p-5"><p class="label">{{ __('app.invoices_this_month') }}</p><p class="mt-2 text-3xl font-bold">{{ $invoiceCount }}</p></div>
    <div class="panel p-5"><p class="label">{{ __('app.revenue_this_month') }}</p><p class="mt-2 text-3xl font-bold">{{ $money->format($revenueCents, 'fr') }}</p></div>
    <div class="panel p-5"><p class="label">{{ __('app.draft') }}</p><p class="mt-2 text-3xl font-bold">{{ $draftCount }}</p></div>
    <div class="panel p-5"><p class="label">{{ __('app.sent') }}</p><p class="mt-2 text-3xl font-bold">{{ $sentCount }}</p></div>
    <div class="panel p-5"><p class="label">{{ __('app.paid') }}</p><p class="mt-2 text-3xl font-bold">{{ $paidCount }}</p></div>
</div>
<div class="mt-8 grid gap-6 lg:grid-cols-2">
    <section class="panel p-5"><h2 class="text-xl font-bold text-villeneuve-forest">{{ __('app.recent_daily_records') }}</h2><table class="table mt-4 w-full">@foreach($dailyRecords as $record)<tr><td>{{ $record->service_date->format('Y-m-d') }}</td><td>{{ $record->client?->name ?? 'Client supprimé' }}</td><td>{{ $record->status }}</td></tr>@endforeach</table></section>
    <section class="panel p-5"><h2 class="text-xl font-bold text-villeneuve-forest">{{ __('app.recent_invoices') }}</h2><table class="table mt-4 w-full">@foreach($invoices as $invoice)<tr><td><a class="font-bold text-villeneuve-green" href="{{ route('monthly-invoices.show', $invoice) }}">{{ $invoice->invoice_number }}</a></td><td>{{ $invoice->client?->name ?? 'Client supprimé' }}</td><td>{{ $money->format($invoice->grand_total_cents, $invoice->client?->default_language ?? 'fr') }}</td></tr>@endforeach</table></section>
</div>
@endsection
