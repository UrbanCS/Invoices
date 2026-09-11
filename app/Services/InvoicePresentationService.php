<?php

namespace App\Services;

use App\Models\ClientCategory;
use App\Models\MonthlyInvoice;
use Illuminate\Support\Collection;

class InvoicePresentationService
{
    public function lineItems(MonthlyInvoice $invoice): Collection
    {
        $categoriesById = collect($invoice->category_snapshot ?? [])
            ->keyBy(fn (array $category) => (string) ($category['id'] ?? ''));

        return $invoice->entries
            ->sortBy(fn ($entry) => sprintf('%02d-%08d', $entry->service_day, $entry->id))
            ->flatMap(function ($entry) use ($categoriesById) {
                $category = $categoriesById->get((string) $entry->client_category_id, []);
                $billingType = $this->billingType([], $category);
                $service = collect([
                    isset($category['service_type'])
                        ? ClientCategory::serviceLabel($category['service_type'])
                        : null,
                    isset($category['audience'])
                        ? ClientCategory::audienceLabel($category['audience'])
                        : null,
                ])->filter()->join(' · ');
                $details = collect($entry->item_details ?? []);

                if ($details->isEmpty()) {
                    return $entry->amount_cents > 0 ? [[
                        'day' => (int) $entry->service_day,
                        'billing_type' => $billingType,
                        'billing_label' => $this->billingLabel($billingType),
                        'person_name' => null,
                        'reference_number' => null,
                        'reference_label' => $this->referenceLabel($billingType),
                        'department_number' => null,
                        'room_number' => null,
                        'service_type' => $category['service_type'] ?? null,
                        'audience' => $category['audience'] ?? null,
                        'service' => $service,
                        'label' => $category['name'] ?? $entry->category_name_snapshot,
                        'quantity' => null,
                        'unit_price_cents' => null,
                        'total_cents' => (int) $entry->amount_cents,
                    ]] : [];
                }

                return $details->map(function (array $detail) use ($entry, $category, $service) {
                    $type = $this->billingType($detail, $category);
                    $isLegacyGuestDetail = $type === 'hotel_guest'
                        && ! array_key_exists('room_number', $detail);

                    return [
                        'day' => (int) $entry->service_day,
                        'billing_type' => $type,
                        'billing_label' => $this->billingLabel($type),
                        'person_name' => $detail['person_name'] ?? $detail['employee_name'] ?? null,
                        'reference_number' => $isLegacyGuestDetail
                            ? null
                            : ($detail['reference_number'] ?? null),
                        'reference_label' => $this->referenceLabel($type),
                        'department_number' => $detail['department_number'] ?? null,
                        'room_number' => $type === 'hotel_guest'
                            ? ($isLegacyGuestDetail
                                ? ($detail['reference_number'] ?? null)
                                : ($detail['room_number'] ?? null))
                            : null,
                        'service_type' => $category['service_type'] ?? null,
                        'audience' => $category['audience'] ?? null,
                        'service' => $service,
                        'label' => $detail['label'] ?? $category['name'] ?? $entry->category_name_snapshot,
                        'quantity' => $detail['quantity'] ?? null,
                        'unit_price_cents' => isset($detail['unit_price_cents'])
                            ? (int) $detail['unit_price_cents']
                            : null,
                        'total_cents' => (int) ($detail['total_cents'] ?? 0),
                    ];
                })->all();
            })
            ->values();
    }

    public function groupedLineItems(Collection $lineItems): Collection
    {
        return $lineItems
            ->values()
            ->groupBy(function (array $lineItem, int $index): string {
                $identity = [
                    (int) $lineItem['day'],
                    (string) $lineItem['billing_type'],
                    trim((string) ($lineItem['person_name'] ?? '')),
                    trim((string) ($lineItem['reference_number'] ?? '')),
                    trim((string) ($lineItem['room_number'] ?? '')),
                    trim((string) ($lineItem['department_number'] ?? '')),
                ];

                if (collect(array_slice($identity, 2))->every(fn ($value) => $value === '')) {
                    return 'unidentified-'.$index;
                }

                return json_encode($identity, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            })
            ->map(function (Collection $items): array {
                $first = $items->first();

                return [
                    'day' => $first['day'],
                    'billing_type' => $first['billing_type'],
                    'billing_label' => $first['billing_label'],
                    'person_name' => $first['person_name'],
                    'reference_number' => $first['reference_number'],
                    'reference_label' => $first['reference_label'],
                    'department_number' => $first['department_number'],
                    'room_number' => $first['room_number'],
                    'items' => $items->values(),
                    'total_cents' => (int) $items->sum('total_cents'),
                ];
            })
            ->values();
    }

    public function dailyBillingTotals(Collection $lineItems): Collection
    {
        $totals = collect(range(1, 31))->mapWithKeys(fn (int $day) => [
            $day => ['employee' => 0, 'hotel_guest' => 0],
        ]);

        foreach ($lineItems as $lineItem) {
            $day = (int) $lineItem['day'];
            $type = $lineItem['billing_type'] === 'employee' ? 'employee' : 'hotel_guest';
            $row = $totals->get($day);
            $row[$type] += (int) $lineItem['total_cents'];
            $totals->put($day, $row);
        }

        return $totals;
    }

    public function billingSubtotals(Collection $lineItems): array
    {
        return [
            'employee' => (int) $lineItems
                ->where('billing_type', 'employee')
                ->sum('total_cents'),
            'hotel_guest' => (int) $lineItems
                ->where('billing_type', 'hotel_guest')
                ->sum('total_cents'),
        ];
    }

    private function billingType(array $detail, array $category): string
    {
        if (in_array($detail['billing_type'] ?? null, ['employee', 'hotel_guest'], true)) {
            return $detail['billing_type'];
        }

        return ($category['audience'] ?? null) === 'employees'
            ? 'employee'
            : 'hotel_guest';
    }

    private function billingLabel(string $type): string
    {
        return $type === 'employee' ? 'Employé' : 'Client de l’hôtel';
    }

    private function referenceLabel(string $type): string
    {
        return 'No d’étiquette';
    }
}
