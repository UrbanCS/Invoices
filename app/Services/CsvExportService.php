<?php

namespace App\Services;

use App\Models\MonthlyInvoice;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvExportService
{
    public function invoices($query): StreamedResponse
    {
        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Invoice #', 'Client', 'Month', 'Year', 'Status', 'Subtotal cents', 'Tax cents', 'Grand total cents']);
            $query->with('client')->orderByDesc('invoice_date')->chunk(200, function ($invoices) use ($out) {
                foreach ($invoices as $invoice) {
                    fputcsv($out, [
                        $invoice->invoice_number,
                        $invoice->client->name,
                        $invoice->invoice_month,
                        $invoice->invoice_year,
                        $invoice->status,
                        $invoice->subtotal_cents,
                        $invoice->tax_cents,
                        $invoice->grand_total_cents,
                    ]);
                }
            });
            fclose($out);
        }, 'invoices.csv', ['Content-Type' => 'text/csv']);
    }

    public function invoiceDetails(MonthlyInvoice $invoice): StreamedResponse
    {
        return response()->streamDownload(function () use ($invoice) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Day', 'Category', 'Amount cents']);
            foreach ($invoice->entries()->orderBy('service_day')->get() as $entry) {
                fputcsv($out, [$entry->service_day, $entry->category_name_snapshot, $entry->amount_cents]);
            }
            fclose($out);
        }, 'invoice-'.$invoice->invoice_number.'.csv', ['Content-Type' => 'text/csv']);
    }
}
