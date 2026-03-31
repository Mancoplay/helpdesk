<?php

namespace App\Http\Middleware;

use App\Models\Cliente;
use App\Models\Empleado;
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

        if ($user->hasRole('Empleado')) {
            $empleado = Empleado::where('user_id', $user->id)
                ->orWhere('email', $user->email)
                ->first();

            if (!$empleado || !$empleado->activo) {
                return $this->logoutDisabledUser($request, $user->email);
            }
        }

        if ($user->hasAnyRole(['Usuario', 'Cliente'])) {
            $cliente = Cliente::where('email', $user->email)->first();

            if ($cliente && !$cliente->activo) {
                return $this->logoutDisabledUser($request, $user->email);
            }
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
            ->withErrors(['email' => 'Tu cuenta esta deshabilitada. Contacta al administrador.'])
            ->withInput(['email' => $email]);
    }
}
