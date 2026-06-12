<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Services\MoneyFormatter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClientCategoryController extends Controller
{
    public function index(Client $client): View
    {
        return view('clients.categories', ['client' => $client->load('categories')]);
    }

    public function store(Request $request, Client $client, MoneyFormatter $money): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'default_price' => ['nullable', 'string', 'max:50'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_taxable' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $defaultPriceCents = max(0, $money->parse($data['default_price'] ?? null));
        unset($data['default_price']);

        $client->categories()->create([
            ...$data,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_taxable' => $request->boolean('is_taxable', true),
            'default_price_cents' => $defaultPriceCents,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return back()->with('status', 'Catégorie sauvegardée.');
    }
}
