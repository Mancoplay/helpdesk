<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\SessionAccessService;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function __construct(
        private readonly SessionAccessService $sessionAccessService
    ) {
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
            $loggedUser = $request->user();
            $currentSessionId = (string) $request->session()->getId();

            if ($loggedUser) {
                $this->sessionAccessService->clearExpiredSessionsForUser((int) $loggedUser->id, $currentSessionId);
                $this->sessionAccessService->clearRecoverableSessionsForClient(
                    (int) $loggedUser->id,
                    $currentSessionId,
                    $request->ip(),
                    (string) $request->userAgent()
                );

                if (
                    $this->sessionAccessService->shouldEnforceSingleLogin()
                    && $this->sessionAccessService->hasAnotherActiveSession((int) $loggedUser->id, $currentSessionId)
                ) {
                    auth()->logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();

                    return back()
                        ->withErrors([
                            $this->username() => 'Ya existe una sesion activa con este usuario. Cierra la otra sesion antes de ingresar nuevamente.',
                        ])
                        ->onlyInput($this->username());
                }
            }

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
        if ($this->employeeIsDisabled($user) || $this->clientIsDisabled($user)) {
            $this->sessionAccessService->clearSessionById((string) $request->session()->getId());
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->with('disabled_account_error', 'Tu cuenta está deshabilitada. Contacta al administrador.')
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

    private function employeeIsDisabled(User $user): bool
    {
        return $user->hasRole('Empleado') && !$user->activo;
    }

    private function clientIsDisabled(User $user): bool
    {
        return $user->hasAnyRole(['Usuario', 'Cliente']) && !$user->activo;
    }
}
