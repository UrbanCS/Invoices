<?php

namespace App\Http\Controllers;

use App\Models\CleaningOrder;
use App\Models\ClientEmployeeName;
use App\Services\MoneyFormatter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PortalOrderController extends Controller
{
    public function index(MoneyFormatter $money): View
    {
        $orders = CleaningOrder::where('client_id', Auth::user()->client_id)
            ->with('items')
            ->latest('service_date')
            ->paginate(20);

        return view('portal.orders.index', ['orders' => $orders, 'money' => $money]);
    }

    public function create(): View
    {
        $client = Auth::user()->client()->with(['activeCategories', 'employeeNames'])->firstOrFail();

        return view('portal.orders.create', ['client' => $client]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'service_date' => ['required', 'date'],
            'employee_name' => ['nullable', 'string', 'max:255'],
            'new_employee_name' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'quantities' => ['nullable', 'array'],
        ]);

        $client = Auth::user()->client()->with('activeCategories')->firstOrFail();
        $newEmployeeName = trim((string) ($data['new_employee_name'] ?? ''));
        $employeeName = $newEmployeeName !== ''
            ? $newEmployeeName
            : trim((string) ($data['employee_name'] ?? ''));

        if ($employeeName === '') {
            throw ValidationException::withMessages([
                'employee_name' => 'Entre ou choisis le nom de l’employé.',
            ]);
        }

        $items = [];
        $subtotalCents = 0;

        foreach ($request->input('quantities', []) as $categoryId => $quantityValue) {
            $quantity = (float) str_replace(',', '.', (string) $quantityValue);

            if ($quantity <= 0) {
                continue;
            }

            $category = $client->activeCategories->firstWhere('id', (int) $categoryId);

            if (! $category) {
                continue;
            }

            $totalCents = (int) round($quantity * $category->default_price_cents);
            $subtotalCents += $totalCents;
            $items[] = [
                'client_category_id' => $category->id,
                'item_name_snapshot' => $category->name,
                'unit_price_cents' => $category->default_price_cents,
                'quantity' => $quantity,
                'total_cents' => $totalCents,
            ];
        }

        if ($items === []) {
            throw ValidationException::withMessages([
                'quantities' => 'Sélectionne au moins un item avec une quantité.',
            ]);
        }

        $order = DB::transaction(function () use ($client, $employeeName, $data, $items, $subtotalCents) {
            ClientEmployeeName::firstOrCreate([
                'client_id' => $client->id,
                'name' => $employeeName,
            ]);

            $order = CleaningOrder::create([
                'client_id' => $client->id,
                'user_id' => Auth::id(),
                'service_date' => $data['service_date'],
                'employee_name' => $employeeName,
                'status' => 'submitted',
                'subtotal_cents' => $subtotalCents,
                'adjustment_cents' => 0,
                'total_cents' => $subtotalCents,
                'notes' => $data['notes'] ?? null,
            ]);

            $order->items()->createMany($items);

            return $order;
        });

        return redirect()->route('portal.orders.show', $order)->with('status', 'Commande envoyée.');
    }

    public function show(CleaningOrder $order, MoneyFormatter $money): View
    {
        abort_unless(Auth::user()->client_id === $order->client_id, 403);

        return view('portal.orders.show', ['order' => $order->load('items'), 'money' => $money]);
    }
}
