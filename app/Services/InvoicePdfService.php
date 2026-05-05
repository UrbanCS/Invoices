<?php

namespace App\Services;

use App\Models\BusinessSetting;
use App\Models\MonthlyInvoice;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class InvoicePdfService
{
    public function generate(MonthlyInvoice $invoice): string
    {
        @ini_set('memory_limit', '768M');
        @set_time_limit(90);

        $invoice->load(['client', 'entries', 'adjustments']);
        $settings = BusinessSetting::first();
        $orientation = count($invoice->category_snapshot ?? []) > 2 ? 'landscape' : 'portrait';
        $workPath = storage_path('app/dompdf');

        File::ensureDirectoryExists($workPath);
        File::ensureDirectoryExists(storage_path('app/public/invoices/'.$invoice->invoice_year));

        $html = view('pdf.monthly-invoice', [
            'invoice' => $invoice,
            'settings' => $settings,
            'money' => app(MoneyFormatter::class),
        ])->render();

        $options = new Options();
        $options->set('defaultFont', 'Helvetica');
        $options->set('dpi', 72);
        $options->set('fontDir', $workPath);
        $options->set('fontCache', $workPath);
        $options->set('tempDir', $workPath);
        $options->set('chroot', base_path());
        $options->set('isRemoteEnabled', false);
        $options->set('isFontSubsettingEnabled', true);

        $pdf = new Dompdf($options);
        $pdf->setPaper('letter', $orientation);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->render();

        $path = 'invoices/'.$invoice->invoice_year.'/'.$invoice->invoice_number.'.pdf';
        Storage::disk('public')->makeDirectory('invoices/'.$invoice->invoice_year);
        Storage::disk('public')->put($path, $pdf->output());

        $invoice->update(['pdf_path' => $path]);

        return $path;
    }
}
