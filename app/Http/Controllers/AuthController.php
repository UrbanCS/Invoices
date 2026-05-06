<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function showRegister(): View
    {
        return view('auth.register');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (Auth::attempt([...$credentials, 'is_active' => true], $request->boolean('remember'))) {
            $request->session()->regenerate();
            return redirect()->intended(Auth::user()->isClientUser() ? route('portal.invoices.index') : route('dashboard'));
        }

        return back()->withErrors(['email' => 'The provided credentials could not be verified.'])->onlyInput('email');
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $email = mb_strtolower(trim($data['email']));
        $client = Client::whereRaw('LOWER(email) = ?', [$email])
            ->where('is_active', true)
            ->first();

        if (! $client) {
            return back()
                ->withErrors(['email' => 'Ce courriel n’est associé à aucun client actif de Nettoyeur Villeneuve.'])
                ->onlyInput('name', 'email');
        }

        $user = User::where('email', $email)->first();

        if ($user && ! $user->isClientUser()) {
            return back()
                ->withErrors(['email' => 'Ce courriel est déjà utilisé par un compte interne.'])
                ->onlyInput('name', 'email');
        }

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $data['name'],
                'password' => $data['password'],
                'role' => 'client',
                'client_id' => $client->id,
                'is_active' => true,
            ]
        );

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('portal.invoices.index')->with('status', 'Compte client créé. Vous êtes connecté.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
