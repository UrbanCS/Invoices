<?php

namespace App\Services;

use App\Models\MonthlyInvoice;

class InvoiceNumberService
{
    public function next(int $month, int $year): string
    {
        $base = str_pad((string) $month, 2, '0', STR_PAD_LEFT).substr((string) $year, -2);
        if (! MonthlyInvoice::where('invoice_number', $base)->exists()) {
            return $base;
        }

        $suffix = 2;
        while (MonthlyInvoice::where('invoice_number', $base.'-'.$suffix)->exists()) {
            $suffix++;
        }

        return $base.'-'.$suffix;
    }
}
