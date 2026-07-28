<?php

namespace App\Http\Controllers;

use App\Models\CleaningOrder;
use App\Models\Client;
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

        return view('portal.orders.create', [
            'client' => $client,
            'order' => new CleaningOrder(['service_date' => now()]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $client = Auth::user()->client()->with('activeCategories')->firstOrFail();
        $employeeName = $this->employeeName($data);
        [$items, $subtotalCents] = $this->itemsAndSubtotal($client, $data['quantities'] ?? []);

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
                'department_number' => trim($data['department_number']),
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

    public function edit(CleaningOrder $order): View
    {
        $this->authorizeEditableOrder($order);
        $client = Auth::user()->client()->with(['activeCategories', 'employeeNames'])->firstOrFail();

        return view('portal.orders.create', [
            'client' => $client,
            'order' => $order->load('items'),
        ]);
    }

    public function update(Request $request, CleaningOrder $order): RedirectResponse
    {
        $this->authorizeEditableOrder($order);
        $data = $this->validated($request);
        $client = Auth::user()->client()->with('activeCategories')->firstOrFail();
        $employeeName = $this->employeeName($data);
        [$items, $subtotalCents] = $this->itemsAndSubtotal($client, $data['quantities'] ?? []);

        DB::transaction(function () use ($order, $client, $employeeName, $data, $items, $subtotalCents) {
            ClientEmployeeName::firstOrCreate([
                'client_id' => $client->id,
                'name' => $employeeName,
            ]);

            $order->update([
                'service_date' => $data['service_date'],
                'employee_name' => $employeeName,
                'department_number' => trim($data['department_number']),
                'subtotal_cents' => $subtotalCents,
                'total_cents' => $subtotalCents + $order->adjustment_cents,
                'notes' => $data['notes'] ?? null,
            ]);

            $order->items()->delete();
            $order->items()->createMany($items);
        });

        return redirect()->route('portal.orders.show', $order)
            ->with('status', 'Commande corrigée et renvoyée à Nettoyeur Villeneuve.');
    }

    public function show(CleaningOrder $order, MoneyFormatter $money): View
    {
        abort_unless(Auth::user()->client_id === $order->client_id, 403);

        return view('portal.orders.show', ['order' => $order->load('items.category'), 'money' => $money]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'service_date' => ['required', 'date'],
            'employee_name' => ['nullable', 'string', 'max:255'],
            'new_employee_name' => ['nullable', 'string', 'max:255'],
            'department_number' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'quantities' => ['nullable', 'array'],
            'quantities.*' => ['nullable', 'numeric', 'min:0', 'max:999999'],
        ]);
    }

    private function employeeName(array $data): string
    {
        $newEmployeeName = trim((string) ($data['new_employee_name'] ?? ''));
        $employeeName = $newEmployeeName !== ''
            ? $newEmployeeName
            : trim((string) ($data['employee_name'] ?? ''));

        if ($employeeName === '') {
            throw ValidationException::withMessages([
                'employee_name' => 'Entre ou choisis le nom de l’employé.',
            ]);
        }

        return $employeeName;
    }

    private function itemsAndSubtotal(Client $client, array $quantities): array
    {
        $items = [];
        $subtotalCents = 0;

        foreach ($quantities as $categoryId => $quantityValue) {
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

        return [$items, $subtotalCents];
    }

    private function authorizeEditableOrder(CleaningOrder $order): void
    {
        abort_unless(Auth::user()->client_id === $order->client_id, 403);
        abort_unless($order->status === 'submitted' && ! $order->monthly_invoice_id, 403);
    }
}
