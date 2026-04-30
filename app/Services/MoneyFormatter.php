<?php

namespace App\Services;

class MoneyFormatter
{
    public function format(int $cents, string $language = 'en'): string
    {
        $amount = abs($cents) / 100;
        $negative = $cents < 0 ? '-' : '';

        if ($language === 'fr') {
            return $negative.number_format($amount, 2, ',', ' ').' $';
        }

        return $negative.'$'.number_format($amount, 2, '.', ',');
    }

    public function parse(?string $value): int
    {
        $value = trim((string) $value);
        if ($value === '') {
            return 0;
        }

        $normalized = str_replace(['$', ' ', ','], ['', '', '.'], $value);
        if (substr_count($normalized, '.') > 1) {
            $normalized = preg_replace('/\.(?=.*\.)/', '', $normalized);
        }

        return (int) round(((float) $normalized) * 100);
    }
}
