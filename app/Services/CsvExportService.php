<?php

namespace App\Services;

use App\Models\MonthlyInvoice;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvExportService
{
    public function __construct(
        private readonly InvoicePresentationService $presentation,
    ) {
    }

    public function invoices($query): StreamedResponse
    {
        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['No de facture', 'Client', 'Mois', 'Année', 'Statut', 'Sous-total', 'Taxes', 'Grand total']);
            $query->with('client')->orderByDesc('invoice_date')->chunk(200, function ($invoices) use ($out) {
                foreach ($invoices as $invoice) {
                    fputcsv($out, [
                        $invoice->invoice_number,
                        $invoice->client?->name ?? 'Client supprimé',
                        $invoice->invoice_month,
                        $invoice->invoice_year,
                        $invoice->status,
                        $this->dollars($invoice->subtotal_cents),
                        $this->dollars($invoice->tax_cents),
                        $this->dollars($invoice->grand_total_cents),
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
            $invoice->loadMissing('entries');
            fputcsv($out, [
                'Jour',
                'Type',
                'Nom',
                'No étiquette / chambre',
                'No département',
                'Service',
                'Item',
                'Quantité',
                'Prix unitaire',
                'Total',
            ]);

            foreach ($this->presentation->lineItems($invoice) as $lineItem) {
                fputcsv($out, [
                    $lineItem['day'],
                    $lineItem['billing_label'],
                    $lineItem['person_name'],
                    $lineItem['reference_number'],
                    $lineItem['department_number'],
                    $lineItem['service'],
                    $lineItem['label'],
                    $lineItem['quantity'],
                    $lineItem['unit_price_cents'] !== null
                        ? $this->dollars($lineItem['unit_price_cents'])
                        : null,
                    $this->dollars($lineItem['total_cents']),
                ]);
            }
            fclose($out);
        }, 'invoice-'.$invoice->invoice_number.'.csv', ['Content-Type' => 'text/csv']);
    }

    private function dollars(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
