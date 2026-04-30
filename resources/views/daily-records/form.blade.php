@extends('layouts.app')
@section('content')
<form x-data="{rows: {{ max(5, $items->count()) }}}" class="panel bg-white p-6" method="post" action="{{ $record->exists ? route('daily-records.update',$record) : route('daily-records.store') }}">
    @csrf @if($record->exists) @method('put') @endif
    <div class="border-b-4 border-villeneuve-forest pb-5">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div><div class="text-3xl font-black text-villeneuve-forest">Nettoyeur Villeneuve</div><div class="label mt-1">Daily Valet Record</div></div>
            <div class="grid gap-3 md:grid-cols-3">
                <div><label class="label">Hotel / Client</label><select class="mt-1 w-full" name="client_id" required>@foreach($clients as $client)<option value="{{ $client->id }}" @selected(old('client_id',$record->client_id)==$client->id)>{{ $client->name }}</option>@endforeach</select></div>
                <div><label class="label">Date</label><input class="mt-1 w-full" type="date" name="service_date" value="{{ old('service_date', optional($record->service_date)->format('Y-m-d') ?? now()->toDateString()) }}" required></div>
                <div><label class="label">Reference</label><input class="mt-1 w-full" name="reference_number" value="{{ old('reference_number',$record->reference_number) }}"></div>
            </div>
        </div>
    </div>
    @if($record->status === 'invoiced')<div class="mt-4 border border-amber-200 bg-amber-50 p-3 text-sm font-semibold text-amber-900">This record has already been invoiced. Edit carefully.</div>@endif
    <div class="mt-6 overflow-x-auto">
        <table class="w-full border-collapse text-sm">
            <thead><tr class="bg-villeneuve-mint text-villeneuve-forest"><th class="border p-2">Name</th><th class="border p-2">Dept / Room / Reference</th><th class="border p-2">Description</th><th class="border p-2">Category</th><th class="border p-2 text-right">Charges</th></tr></thead>
            <tbody>
            @for($i=0;$i<31;$i++)
                @php($item = $items[$i] ?? null)
                <tr x-show="{{ $i }} < rows">
                    <td class="border p-1"><input class="w-full border-0" name="items[{{ $i }}][customer_name]" value="{{ old("items.$i.customer_name", $item?->customer_name) }}"></td>
                    <td class="border p-1"><input class="w-full border-0" name="items[{{ $i }}][department_or_room]" value="{{ old("items.$i.department_or_room", $item?->department_or_room) }}"></td>
                    <td class="border p-1"><input class="w-full border-0" name="items[{{ $i }}][description]" value="{{ old("items.$i.description", $item?->description) }}"></td>
                    <td class="border p-1"><select class="w-full border-0" name="items[{{ $i }}][client_category_id]">@foreach($clients->flatMap->activeCategories->unique('id') as $category)<option value="{{ $category->id }}" @selected(old("items.$i.client_category_id", $item?->client_category_id)==$category->id)>{{ $category->client->name ?? '' }} {{ $category->name }}</option>@endforeach</select></td>
                    <td class="border p-1"><input class="w-full border-0 text-right" name="items[{{ $i }}][amount]" value="{{ old("items.$i.amount", $item ? number_format($item->amount_cents/100,2) : '') }}"></td>
                </tr>
            @endfor
            </tbody>
        </table>
    </div>
    <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
        <button type="button" class="btn btn-secondary" x-on:click="rows = Math.min(31, rows + 1)">Add row</button>
        <button type="button" class="btn btn-secondary" x-on:click="rows = Math.max(1, rows - 1)">Remove row</button>
        <div class="text-right text-lg font-bold text-villeneuve-forest">Total calculated after save</div>
    </div>
    <div class="mt-5"><label class="label">Notes</label><textarea class="mt-1 w-full" name="notes">{{ old('notes',$record->notes) }}</textarea></div>
    <div class="mt-6 flex gap-3"><button class="btn btn-secondary" name="action" value="draft">Save Draft</button><button class="btn btn-primary" name="action" value="review">Mark Reviewed</button></div>
</form>
@endsection
