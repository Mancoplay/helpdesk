<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check()) {
            return $next($request);
        }

        $user = $request->user();

        if ($user->hasAnyRole(['Empleado', 'Usuario', 'Cliente']) && !$user->activo) {
            return $this->logoutDisabledUser($request, $user->email);
        }

        return $next($request);
    }

    private function logoutDisabledUser(Request $request, string $email): Response
    {
        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->with('disabled_account_error', 'Tu cuenta está deshabilitada. Contacta al administrador.')
            ->withInput(['email' => $email]);
    }
}
