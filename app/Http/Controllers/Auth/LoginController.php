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
use Illuminate\Support\Facades\Hash;

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

        $candidate = User::where('email', (string) $request->input($this->username()))->first();
        $rawPassword = (string) $request->input('password', '');

        if (
            $candidate
            && Hash::check($rawPassword, (string) $candidate->password)
            && $this->hasActiveSessionForUser((int) $candidate->id)
        ) {
            $this->incrementLoginAttempts($request);

            return back()
                ->withErrors([$this->username() => 'Esta cuenta ya tiene una sesion activa en otro dispositivo.'])
                ->withInput($request->only($this->username(), 'remember'));
        }

        if ($this->attemptLogin($request)) {
            return $this->sendLoginResponse($request);
        }

        $this->incrementLoginAttempts($request);

        return $this->sendFailedLoginResponse($request);
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

    private function hasActiveSessionForUser(int $userId): bool
    {
        if ($userId <= 0 || config('session.driver') !== 'database') {
            return false;
        }

        $cutoff = now()->subMinutes((int) config('session.lifetime', 120))->timestamp;

        return DB::table(config('session.table', 'sessions'))
            ->where('user_id', $userId)
            ->where('last_activity', '>=', $cutoff)
            ->exists();
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
