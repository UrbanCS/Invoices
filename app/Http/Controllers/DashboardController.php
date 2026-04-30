<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\DailyRecord;
use App\Models\MonthlyInvoice;
use Carbon\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $start = Carbon::now()->startOfMonth();
        $end = Carbon::now()->endOfMonth();

        return view('dashboard.index', [
            'invoiceCount' => MonthlyInvoice::whereBetween('invoice_date', [$start, $end])->count(),
            'revenueCents' => MonthlyInvoice::where('status', 'paid')->whereBetween('invoice_date', [$start, $end])->sum('grand_total_cents'),
            'draftCount' => MonthlyInvoice::where('status', 'draft')->count(),
            'sentCount' => MonthlyInvoice::where('status', 'sent')->count(),
            'paidCount' => MonthlyInvoice::where('status', 'paid')->count(),
            'clients' => Client::latest()->take(5)->get(),
            'dailyRecords' => DailyRecord::with('client')->latest()->take(6)->get(),
            'invoices' => MonthlyInvoice::with('client')->latest()->take(6)->get(),
        ]);
    }
}
