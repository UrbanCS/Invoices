<?php

namespace App\Services;

use App\Models\Client;

class TaxCalculationService
{
    public function ratesFor(Client $client): array
    {
        if ($client->tax_profile === 'qc_tps_tvq') {
            return [
                ['label' => 'TPS/GST', 'rate_basis_points' => 500],
                ['label' => 'TVQ/QST', 'rate_basis_points' => 998],
            ];
        }

        if ($client->tax_profile === 'custom') {
            return $client->taxRates()->where('is_active', true)->get(['label', 'rate_basis_points'])->toArray();
        }

        return [['label' => 'HST/GST', 'rate_basis_points' => 1300]];
    }

    public function calculate(Client $client, int $taxableCents): array
    {
        $lines = [];
        $total = 0;

        foreach ($this->ratesFor($client) as $rate) {
            $amount = intdiv($taxableCents * (int) $rate['rate_basis_points'] + 5000, 10000);
            $lines[] = [
                'label' => $rate['label'],
                'rate_basis_points' => (int) $rate['rate_basis_points'],
                'amount_cents' => $amount,
            ];
            $total += $amount;
        }

        return ['lines' => $lines, 'total_cents' => $total];
    }
}
