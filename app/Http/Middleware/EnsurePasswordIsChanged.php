<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordIsChanged
{
    private const EXCEPT_ROUTES_NAMES = [
        'first-login-change-password.show',
        'first-login-change-password.store',
        'password.request',
        'password.email',
        'password.reset',
        'password.store',
        'verification.notice',
        'verification.verify',
        'verification.send',
        'logout',
        'password.confirm',
        'password.confirm.store',
        'profile.edit',
        'profile.update',
        'profile.destroy',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        if (!$user) return $next($request);

        if ($request->route() && in_array($request->route()->getName(), self::EXCEPT_ROUTES_NAMES, true)) {
            return $next($request);
        }

        // Si password_changed_at === NULL et on force le mot de passe
        if ($user->password_changed_at === null) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Pour votre sécurité, changez votre mot de passe avant de continuer.'], 403);
            }
            return redirect()->route('first-login-change-password.show')
                ->with('info', '⚠️ Pour votre sécurité, changez votre mot de passe temporaire avant de continuer.');
        }

        return $next($request);
    }
}
