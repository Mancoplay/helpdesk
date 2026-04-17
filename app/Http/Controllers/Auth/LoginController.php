<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Empleado;
use App\Models\User;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

    public function login(Request $request)
    {
        $this->validateLogin($request);

        if (method_exists($this, 'hasTooManyLoginAttempts') && $this->hasTooManyLoginAttempts($request)) {
            $this->fireLockoutEvent($request);

            return $this->sendLockoutResponse($request);
        }

        if ($this->attemptLogin($request)) {
            return $this->sendLoginResponse($request);
        }

        $this->incrementLoginAttempts($request);

        return $this->sendFailedLoginResponse($request);
    }

    protected function maxAttempts(): int
    {
        return 5;
    }

    protected function decayMinutes(): int
    {
        return 2;
    }

    protected function throttleKey(Request $request): string
    {
        return Str::lower((string) $request->input('email')) . '|' . $request->ip();
    }

    protected function authenticated(Request $request, User $user): ?RedirectResponse
    {
        $this->closeOtherSessionsForUser((int) $user->id, (string) $request->session()->getId());

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

    protected function attemptLogin(Request $request): bool
    {
        // Seguridad: no persistir sesion con "remember me".
        return $this->guard()->attempt(
            $this->credentials($request),
            false
        );
    }

    private function closeOtherSessionsForUser(int $userId, string $currentSessionId): void
    {
        if ($userId <= 0 || $currentSessionId === '' || config('session.driver') !== 'database') {
            return;
        }

        DB::table(config('session.table', 'sessions'))
            ->where('user_id', $userId)
            ->where('id', '!=', $currentSessionId)
            ->delete();
    }

    private function employeeIsDisabled(User $user): bool
    {
        if (!$user->hasRole('Empleado')) {
            return false;
        }

        $empleado = Empleado::whereKey($user->id)
            ->orWhere('email', $user->email)
            ->first();

        return $empleado ? !$empleado->activo : false;
    }

    private function clientIsDisabled(User $user): bool
    {
        if (!$user->hasAnyRole(['Usuario', 'Cliente'])) {
            return false;
        }

        $cliente = Cliente::whereKey($user->id)
            ->orWhere('email', $user->email)
            ->first();

        return $cliente ? !$cliente->activo : false;
    }
}
