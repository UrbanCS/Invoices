<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClientCategoryController extends Controller
{
    public function index(Client $client): View
    {
        return view('clients.categories', ['client' => $client->load('categories')]);
    }

    public function store(Request $request, Client $client): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_taxable' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $client->categories()->create([
            ...$data,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_taxable' => $request->boolean('is_taxable', true),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return back()->with('status', 'Category saved.');
    }
}
