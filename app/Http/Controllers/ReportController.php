<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\MonthlyInvoice;
use App\Services\CsvExportService;
use App\Services\MoneyFormatter;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(MoneyFormatter $money): View
    {
        return view('reports.index', [
            'unpaid' => MonthlyInvoice::with('client')->whereIn('status', ['sent', 'approved'])->get(),
            'clients' => Client::orderBy('name')->get(),
            'money' => $money,
        ]);
    }

    public function invoices(MoneyFormatter $money): View
    {
        return view('reports.invoices', [
            'invoices' => $this->filteredInvoices()->with('client')->paginate(50),
            'money' => $money,
        ]);
    }

    public function revenue(MoneyFormatter $money): View
    {
        $rows = MonthlyInvoice::selectRaw('invoice_year, invoice_month, SUM(grand_total_cents) as total_cents')
            ->whereIn('status', ['sent', 'paid', 'approved'])
            ->groupBy('invoice_year', 'invoice_month')
            ->orderByDesc('invoice_year')
            ->orderByDesc('invoice_month')
            ->get();

        return view('reports.revenue', compact('rows', 'money'));
    }

    public function categoryTotals(MoneyFormatter $money): View
    {
        $rows = MonthlyInvoice::query()
            ->join('monthly_invoice_entries', 'monthly_invoices.id', '=', 'monthly_invoice_entries.monthly_invoice_id')
            ->join('clients', 'clients.id', '=', 'monthly_invoices.client_id')
            ->selectRaw('clients.name as client_name, invoice_year, invoice_month, category_name_snapshot, SUM(amount_cents) as total_cents')
            ->groupBy('clients.name', 'invoice_year', 'invoice_month', 'category_name_snapshot')
            ->orderByDesc('invoice_year')
            ->orderByDesc('invoice_month')
            ->get();

        return view('reports.category-totals', compact('rows', 'money'));
    }

    public function exportCsv(Request $request, CsvExportService $csv)
    {
        return $csv->invoices($this->filteredInvoices());
    }

    private function filteredInvoices()
    {
        return MonthlyInvoice::query()
            ->when(request('client_id'), fn ($q) => $q->where('client_id', request('client_id')))
            ->when(request('status'), fn ($q) => $q->where('status', request('status')))
            ->when(request('from'), fn ($q) => $q->whereDate('invoice_date', '>=', request('from')))
            ->when(request('to'), fn ($q) => $q->whereDate('invoice_date', '<=', request('to')));
    }
}
