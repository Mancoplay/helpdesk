<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Empleado;
use App\Models\User;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = '/dashboard';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
        $this->middleware('auth')->only('logout');
    }

    protected function authenticated(Request $request, User $user): ?RedirectResponse
    {
        if ($this->employeeIsDisabled($user) || $this->clientIsDisabled($user)) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->with('disabled_account_error', 'Tu cuenta esta deshabilitada. Contacta al administrador.')
                ->withInput(['email' => (string) $request->input('email', $user->email)]);
        }

        return redirect()->route('dashboard');
    }

    private function employeeIsDisabled(User $user): bool
    {
        if (!$user->hasRole('Empleado')) {
            return false;
        }

        $empleado = Empleado::where('user_id', $user->id)
            ->orWhere('email', $user->email)
            ->first();

        return $empleado ? !$empleado->activo : false;
    }

    private function clientIsDisabled(User $user): bool
    {
        if (!$user->hasAnyRole(['Usuario', 'Cliente'])) {
            return false;
        }

        $cliente = Cliente::where('email', $user->email)->first();

        return $cliente ? !$cliente->activo : false;
    }
}
