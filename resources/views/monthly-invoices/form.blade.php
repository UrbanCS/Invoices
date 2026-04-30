@extends('layouts.app')
@section('content')
@php($selectedClient = $clients->firstWhere('id', old('client_id',$invoice->client_id)) ?? $clients->first())
<h1 class="text-3xl font-extrabold text-villeneuve-forest">{{ $invoice->exists ? 'Edit Monthly Invoice' : 'New Monthly Invoice' }}</h1>
<form class="mt-6 space-y-6" method="post" action="{{ $invoice->exists ? route('monthly-invoices.update',$invoice) : route('monthly-invoices.store') }}">
@csrf @if($invoice->exists) @method('put') @endif
<section class="panel grid gap-4 p-6 md:grid-cols-5">
    <div><label class="label">Client</label><select class="mt-1 w-full" name="client_id">@foreach($clients as $client)<option value="{{ $client->id }}" @selected($selectedClient?->id===$client->id)>{{ $client->name }}</option>@endforeach</select></div>
    <div><label class="label">Invoice #</label><input class="mt-1 w-full" name="invoice_number" value="{{ old('invoice_number',$invoice->invoice_number) }}" required></div>
    <div><label class="label">Month</label><input class="mt-1 w-full" type="number" min="1" max="12" name="invoice_month" value="{{ old('invoice_month',$invoice->invoice_month) }}" required></div>
    <div><label class="label">Year</label><input class="mt-1 w-full" type="number" name="invoice_year" value="{{ old('invoice_year',$invoice->invoice_year) }}" required></div>
    <div><label class="label">Invoice Date</label><input class="mt-1 w-full" type="date" name="invoice_date" value="{{ old('invoice_date', optional($invoice->invoice_date)->format('Y-m-d') ?? now()->toDateString()) }}"></div>
    <div><label class="label">Mode</label><select class="mt-1 w-full" name="source_mode"><option value="manual_grid" @selected(old('source_mode',$invoice->source_mode)==='manual_grid')>Quick Monthly Grid</option><option value="daily_records" @selected(old('source_mode',$invoice->source_mode)==='daily_records')>Generate From Daily Records</option></select></div>
    <div class="md:col-span-4"><label class="label">Notes / credit text</label><input class="mt-1 w-full" name="notes" value="{{ old('notes',$invoice->notes) }}"></div>
</section>
<section class="panel overflow-x-auto p-6">
    <h2 class="text-xl font-bold text-villeneuve-forest">Monthly Grid</h2>
    <table class="mt-4 w-full border-collapse text-sm">
        <thead><tr><th class="border bg-villeneuve-mint p-2">Day</th>@foreach($selectedClient?->activeCategories ?? [] as $category)<th class="border bg-villeneuve-mint p-2 text-right">{{ $category->name }}</th>@endforeach</tr></thead>
        <tbody>@for($day=1;$day<=31;$day)<tr><td class="border p-2 font-bold">{{ $day }}</td>@foreach($selectedClient?->activeCategories ?? [] as $category)@php($entry = $entries->first(fn($e) => $e->service_day == $day && $e->client_category_id == $category->id))<td class="border p-1"><input class="w-full border-0 text-right" name="grid[{{ $day }}][{{ $category->id }}]" value="{{ old("grid.$day.$category->id", $entry ? number_format($entry->amount_cents/100,2) : '') }}"></td>@endforeach</tr>@endfor</tbody>
    </table>
</section>
<section class="panel p-6">
    <h2 class="text-xl font-bold text-villeneuve-forest">Discounts, Credits, Fees</h2>
    @for($i=0;$i<5;$i++)@php($adj=$adjustments[$i]??null)<div class="mt-3 grid gap-3 md:grid-cols-4"><input name="adjustments[{{ $i }}][label]" placeholder="Label" value="{{ $adj?->label }}"><select name="adjustments[{{ $i }}][type]"><option value="discount" @selected($adj?->type==='discount')>Discount</option><option value="credit" @selected($adj?->type==='credit')>Credit</option><option value="fee" @selected($adj?->type==='fee')>Fee</option></select><select name="adjustments[{{ $i }}][client_category_id]"><option value="">Invoice-level</option>@foreach($selectedClient?->activeCategories ?? [] as $category)<option value="{{ $category->id }}" @selected($adj?->client_category_id===$category->id)>{{ $category->name }}</option>@endforeach</select><input class="text-right" name="adjustments[{{ $i }}][amount]" placeholder="0.00" value="{{ $adj ? number_format($adj->amount_cents/100,2) : '' }}"></div>@endfor
</section>
<button class="btn btn-primary">Save Draft</button>
</form>
@endsection
