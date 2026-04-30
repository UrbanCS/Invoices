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
        $invoices = MonthlyInvoice::where('client_id', Auth::user()->client_id)
            ->when(request('status'), fn ($q) => $q->where('status', request('status')))
            ->when(request('year'), fn ($q) => $q->where('invoice_year', request('year')))
            ->latest('invoice_date')
            ->paginate(20);

        return view('portal.invoices', ['invoices' => $invoices, 'money' => app(MoneyFormatter::class)]);
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
