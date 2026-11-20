<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\FirstLoginChangePasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class ResetFirstPasswordController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('verified');
    }

    public function show(): View|RedirectResponse
    {
        $u = auth()->user();
        if ($u && $u->password_changed_at !== null) {
            return redirect()->route('dashboard');
        }
        return view('auth.first-login-change-password');
    }

    public function store(FirstLoginChangePasswordRequest $request): RedirectResponse
    {
        $valid = $request->validated();
        try {
            $u = $request->user();
            $u->update([
                'password'            => Hash::make($valid['password']),
                'password_changed_at' => now(),
            ]);

            app(\App\Services\SecurityLogger::class)->motDePasseChange((string) $u->email, $u->id);

            try {
                \Illuminate\Support\Facades\Auth::logoutOtherDevices($valid['password']);
            } catch (\Throwable) { /* ignore si sessions non configurées */ }

            $nom = e($u->nomComplet ?? $u->name ?? '');
            return redirect()->intended(route('dashboard'))
                ->with('swal', [
                    'type'  => 'success',
                    'title' => 'Bienvenue sur TV+A OS ✅',
                    'html'  => "<p>Mot de passe mis à jour avec succès.<br>Bon travail, <strong>{$nom}</strong> 👋.</p>",
                    'timer' => 2400,
                ]);
        } catch (\Throwable $e) {
            report($e);
            return back()->withErrors(['password' => $e->getMessage()]);
        }
    }
}
