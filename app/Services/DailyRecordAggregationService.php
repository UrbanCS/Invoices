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
            return $record->items->map(fn ($item) => [
                'service_day' => (int) $record->service_date->format('j'),
                'client_category_id' => $item->client_category_id,
                'category_name_snapshot' => $item->category?->name ?? 'Category',
                'amount_cents' => $item->amount_cents,
                'source_type' => 'daily_record',
            ]);
        })->groupBy(fn ($row) => $row['service_day'].'-'.$row['client_category_id'])
            ->map(function ($rows) {
                $first = $rows->first();
                $first['amount_cents'] = (int) $rows->sum('amount_cents');
                return $first;
            })->values();
    }
}
