<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

class ResetPasswordController extends Controller
{
    public function __construct()
    {
        $this->middleware('guest');
        $this->middleware('throttle:10,1')->only(['verifyCode', 'reset']);
    }

    public function showResetForm()
    {
        return redirect()->route('password.request');
    }

    public function verifyCode(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'code' => ['required', 'digits:6'],
        ]);

        $email = $request->string('email')->toString();
        $code = $request->string('code')->toString();

        $row = DB::table('password_reset_tokens')->where('email', $email)->first();

        if (!$row) {
            return back()
                ->withErrors(['code' => 'El codigo es invalido o expiro.'])
                ->withInput();
        }

        $expirationMinutes = (int) config('auth.passwords.users.expire', 60);
        $expiresAt = Carbon::parse($row->created_at)->addMinutes($expirationMinutes);

        if (now()->greaterThan($expiresAt)) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();

            return back()
                ->withErrors(['code' => 'El codigo es invalido o expiro.'])
                ->withInput();
        }

        if (!Hash::check($code, $row->token)) {
            return back()
                ->withErrors(['code' => 'El codigo es invalido o expiro.'])
                ->withInput();
        }

        session([
            'password_reset_step' => 3,
            'password_reset_email' => $email,
            'password_code_verified_at' => now()->toDateTimeString(),
        ]);

        return back()->with('status', 'Codigo verificado. Ahora ya puedes cambiar tu contrasena.');
    }

    public function reset(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $email = $request->string('email')->toString();

        if ((int) session('password_reset_step', 1) < 3 || session('password_reset_email') !== $email) {
            return back()->withErrors(['email' => 'Debes verificar el codigo antes de cambiar la contrasena.']);
        }

        $row = DB::table('password_reset_tokens')->where('email', $email)->first();
        if (!$row) {
            return back()->withErrors(['email' => 'No fue posible completar el cambio. Solicita un nuevo codigo.']);
        }

        $expirationMinutes = (int) config('auth.passwords.users.expire', 60);
        $expiresAt = Carbon::parse($row->created_at)->addMinutes($expirationMinutes);

        if (now()->greaterThan($expiresAt)) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();
            session()->forget(['password_reset_step', 'password_reset_email', 'password_code_verified_at']);

            return back()->withErrors(['email' => 'No fue posible completar el cambio. Solicita un nuevo codigo.']);
        }

        $user = User::query()->where('email', $email)->first();
        if (!$user) {
            return back()->withErrors(['email' => 'No fue posible completar el cambio. Solicita un nuevo codigo.']);
        }

        $user->forceFill([
            'password' => Hash::make($request->string('password')->toString()),
            'remember_token' => Str::random(60),
        ])->save();

        event(new PasswordReset($user));

        DB::table('password_reset_tokens')->where('email', $email)->delete();
        session()->forget(['password_reset_step', 'password_reset_email', 'password_code_verified_at']);

        return redirect()->route('login')->with('status', 'Contrasena actualizada correctamente. Ya puedes iniciar sesion.');
    }
}
