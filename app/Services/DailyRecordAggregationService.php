<?php

namespace App\Services;

use App\Models\Client;
use App\Models\DailyRecord;
use Illuminate\Support\Collection;

class DailyRecordAggregationService
{
    public function reviewedRecords(Client $client, int $month, int $year): Collection
    {
        return DailyRecord::query()
            ->with('items.category')
            ->where('client_id', $client->id)
            ->where('status', 'reviewed')
            ->whereMonth('service_date', $month)
            ->whereYear('service_date', $year)
            ->get();
    }

    public function entriesFromRecords(Collection $records): Collection
    {
        return $records->flatMap(function (DailyRecord $record) {
            return $record->items->map(function ($item) use ($record) {
                $billingType = $item->category?->audience === 'employees'
                    ? 'employee'
                    : 'hotel_guest';

                return [
                    'service_day' => (int) $record->service_date->format('j'),
                    'client_category_id' => $item->client_category_id,
                    'category_name_snapshot' => $item->category?->name ?? 'Catégorie',
                    'amount_cents' => $item->amount_cents,
                    'item_details' => [[
                        'label' => $item->category?->name ?? $item->description ?? 'Item',
                        'quantity' => null,
                        'unit_price_cents' => null,
                        'total_cents' => $item->amount_cents,
                        'billing_type' => $billingType,
                        'person_name' => $item->customer_name,
                        'reference_number' => $record->reference_number,
                        'department_number' => $billingType === 'employee'
                            ? $item->department_or_room
                            : null,
                        'room_number' => $billingType === 'hotel_guest'
                            ? $item->department_or_room
                            : null,
                    ]],
                    'source_type' => 'daily_record',
                ];
            });
        })->groupBy(fn ($row) => $row['service_day'].'-'.$row['client_category_id'])
            ->map(function ($rows) {
                $first = $rows->first();
                $first['amount_cents'] = (int) $rows->sum('amount_cents');
                $first['item_details'] = $rows
                    ->flatMap(fn ($row) => $row['item_details'] ?? [])
                    ->values()
                    ->all();

                return $first;
            })->values();
    }
}
