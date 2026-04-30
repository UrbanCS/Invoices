<?php

namespace App\Services;

use App\Models\BusinessSetting;
use App\Models\MonthlyInvoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class InvoicePdfService
{
    public function generate(MonthlyInvoice $invoice): string
    {
        $invoice->load(['client', 'entries', 'adjustments']);
        $settings = BusinessSetting::first();
        $orientation = count($invoice->category_snapshot ?? []) > 2 ? 'landscape' : 'portrait';

        $pdf = Pdf::loadView('pdf.monthly-invoice', [
            'invoice' => $invoice,
            'settings' => $settings,
            'money' => app(MoneyFormatter::class),
        ])->setPaper('letter', $orientation);

        $path = 'invoices/'.$invoice->invoice_year.'/'.$invoice->invoice_number.'.pdf';
        Storage::disk('public')->put($path, $pdf->output());

        $invoice->update(['pdf_path' => $path]);

        return $path;
    }
}
