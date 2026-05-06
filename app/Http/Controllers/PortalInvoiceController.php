<?php

namespace App\Http\Controllers;

use App\Models\MonthlyInvoice;
use App\Services\MoneyFormatter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class PortalInvoiceController extends Controller
{
    public function index(): View
    {
        $status = in_array(request('status'), ['approved', 'sent', 'paid', 'cancelled'], true)
            ? request('status')
            : null;
        $year = filled(request('year')) ? (int) request('year') : null;

        $invoices = MonthlyInvoice::where('client_id', Auth::user()->client_id)
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($year, fn ($q) => $q->where('invoice_year', $year))
            ->latest('invoice_date')
            ->paginate(20)
            ->withQueryString();

        return view('portal.invoices', [
            'invoices' => $invoices,
            'money' => app(MoneyFormatter::class),
            'selectedStatus' => $status,
            'selectedYear' => $year,
        ]);
    }

    public function show(MonthlyInvoice $invoice, MoneyFormatter $money): View
    {
        abort_unless(Auth::user()->client_id === $invoice->client_id, 403);
        return view('portal.show', ['invoice' => $invoice->load('entries', 'client'), 'money' => $money]);
    }

    public function download(MonthlyInvoice $invoice)
    {
        abort_unless(Auth::user()->client_id === $invoice->client_id, 403);
        abort_unless($invoice->pdf_path && Storage::disk('public')->exists($invoice->pdf_path), 404);
        return Storage::disk('public')->download($invoice->pdf_path);
    }
}
