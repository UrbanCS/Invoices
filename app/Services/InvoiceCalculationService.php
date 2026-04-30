<?php

namespace App\Services;

use App\Models\Client;
use Illuminate\Support\Collection;

class InvoiceCalculationService
{
    public function __construct(private TaxCalculationService $taxes)
    {
    }

    public function calculate(Client $client, Collection $entries, Collection $adjustments, array $categorySnapshot): array
    {
        $categoryTaxable = collect($categorySnapshot)->mapWithKeys(fn ($category) => [
            (int) $category['id'] => (bool) $category['is_taxable'],
        ]);

        $subtotal = (int) $entries->sum('amount_cents');
        $discount = (int) $adjustments->whereIn('type', ['discount', 'credit'])->sum('amount_cents');
        $fees = (int) $adjustments->where('type', 'fee')->sum('amount_cents');

        $taxableSubtotal = 0;
        foreach ($entries as $entry) {
            if ($categoryTaxable->get((int) $entry->client_category_id, true)) {
                $taxableSubtotal += (int) $entry->amount_cents;
            }
        }

        foreach ($adjustments as $adjustment) {
            $amount = (int) $adjustment->amount_cents;
            $isTaxable = $adjustment->client_category_id
                ? $categoryTaxable->get((int) $adjustment->client_category_id, true)
                : true;

            if ($isTaxable && in_array($adjustment->type, ['discount', 'credit'], true)) {
                $taxableSubtotal -= $amount;
            }

            if ($isTaxable && $adjustment->type === 'fee') {
                $taxableSubtotal += $amount;
            }
        }

        $taxableSubtotal = max(0, $taxableSubtotal);
        $tax = $this->taxes->calculate($client, $taxableSubtotal);
        $total = $subtotal - $discount + $fees;

        return [
            'subtotal_cents' => $subtotal,
            'discount_cents' => $discount,
            'taxable_subtotal_cents' => $taxableSubtotal,
            'tax_cents' => $tax['total_cents'],
            'gst_cents' => $tax['lines'][0]['amount_cents'] ?? null,
            'qst_cents' => $tax['lines'][1]['amount_cents'] ?? null,
            'total_cents' => $total,
            'grand_total_cents' => $total + $tax['total_cents'],
            'tax_profile_snapshot' => $tax['lines'],
        ];
    }
}
