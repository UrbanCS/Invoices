<?php

namespace App\Http\Controllers;

use App\Models\CleaningOrder;
use App\Models\Client;
use App\Services\MoneyFormatter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AccountStatementController extends Controller
{
    public function index(MoneyFormatter $money): View
    {
        $month = (int) request('month', now()->month);
        $year = (int) request('year', now()->year);
        $clientId = request('client_id');

        $orders = CleaningOrder::with(['client', 'items'])
            ->whereMonth('service_date', $month)
            ->whereYear('service_date', $year)
            ->when($clientId, fn ($query) => $query->where('client_id', $clientId))
            ->latest('service_date')
            ->get();

        return view('account-statements.index', [
            'orders' => $orders,
            'clients' => Client::orderBy('name')->get(),
            'month' => $month,
            'year' => $year,
            'clientId' => $clientId,
            'money' => $money,
            'subtotalCents' => $orders->sum('subtotal_cents'),
            'adjustmentCents' => $orders->sum('adjustment_cents'),
            'totalCents' => $orders->sum('total_cents'),
        ]);
    }

    public function adjustment(Request $request, CleaningOrder $order, MoneyFormatter $money): RedirectResponse
    {
        abort_unless(Auth::user()->isSuperAdmin(), 403);

        $data = $request->validate([
            'adjustment_amount' => ['nullable', 'string', 'max:50'],
            'adjustment_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $adjustmentCents = $money->parse($data['adjustment_amount'] ?? null);

        $order->update([
            'adjustment_cents' => $adjustmentCents,
            'adjustment_note' => $data['adjustment_note'] ?? null,
            'total_cents' => $order->subtotal_cents + $adjustmentCents,
        ]);

        return back()->with('status', 'Ajustement sauvegardé.');
    }
}
