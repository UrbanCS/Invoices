<?php

namespace App\Http\Controllers;

use App\Models\BusinessSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class BusinessSettingController extends Controller
{
    public function edit(): View
    {
        return view('settings.business', ['settings' => BusinessSetting::firstOrCreate([
            'legal_name' => '8419965 Canada inc',
            'display_name' => 'Nettoyeur Villeneuve',
        ])]);
    }

    public function update(Request $request): RedirectResponse
    {
        $settings = BusinessSetting::firstOrFail();
        $data = $request->validate([
            'legal_name' => ['required', 'string', 'max:255'],
            'display_name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:50'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'gst_number' => ['nullable', 'string', 'max:50'],
            'qst_number' => ['nullable', 'string', 'max:50'],
            'default_payment_instructions' => ['nullable', 'string'],
            'default_thank_you_message' => ['nullable', 'string'],
            'default_language' => ['required', 'in:fr,en'],
            'logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store('logos', 'public');
        }

        unset($data['logo']);
        $settings->update($data);

        return back()->with('status', 'Business settings saved.');
    }
}
