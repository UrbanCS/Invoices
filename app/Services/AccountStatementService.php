<?php

namespace App\Services;

use App\Models\MonthlyInvoice;

class AccountStatementService
{
    public function forPeriod(int $month, int $year, ?int $clientId = null): array
    {
        // Use the invoiced period, even when a month-end invoice was issued later.
        $invoices = MonthlyInvoice::with('client')
            ->withSum('payments', 'amount_cents')
            ->where('invoice_month', $month)
            ->where('invoice_year', $year)
            ->whereIn('status', ['approved', 'sent', 'paid'])
            ->when($clientId !== null, fn ($query) => $query->where('client_id', $clientId))
            ->orderBy('invoice_date')->orderBy('id')->get();

        $rows = $invoices->map(function (MonthlyInvoice $invoice): array {
            $total = (int) $invoice->grand_total_cents;
            $paid = $invoice->status === 'paid'
                ? max($total, (int) $invoice->payments_sum_amount_cents)
                : (int) $invoice->payments_sum_amount_cents;

            return [
                'invoice' => $invoice,
                'net_cents' => (int) $invoice->total_cents,
                'tax_cents' => (int) $invoice->tax_cents,
                'total_cents' => $total,
                'paid_cents' => $paid,
                'balance_cents' => $total - $paid,
            ];
        });

        return [
            'rows' => $rows,
            'net_cents' => (int) $rows->sum('net_cents'),
            'tax_cents' => (int) $rows->sum('tax_cents'),
            'total_cents' => (int) $rows->sum('total_cents'),
            'paid_cents' => (int) $rows->sum('paid_cents'),
            'balance_cents' => (int) $rows->sum('balance_cents'),
        ];
    }
}
