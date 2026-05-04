<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\PasswordResetEmailGuard;
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

    public function verifyCode(Request $request, PasswordResetEmailGuard $emailGuard): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'code' => ['required', 'digits:6'],
        ]);

        $email = $emailGuard->normalize($request->string('email')->toString());
        [$isValid, $message] = $emailGuard->validate($email);

        if (!$isValid) {
            return back()
                ->withErrors(['email' => $message])
                ->withInput(['email' => $email]);
        }

        $code = $request->string('code')->toString();

        $row = DB::table('password_reset_tokens')->where('email', $email)->first();

        if (!$row) {
            return back()
                ->withErrors(['code' => 'El código es inválido o expiró.'])
                ->withInput();
        }

        $expirationMinutes = (int) config('auth.passwords.users.expire', 60);
        $expiresAt = Carbon::parse($row->created_at)->addMinutes($expirationMinutes);

        if (now()->greaterThan($expiresAt)) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();

            return back()
                ->withErrors(['code' => 'El código es inválido o expiró.'])
                ->withInput();
        }

        if (!Hash::check($code, $row->token)) {
            return back()
                ->withErrors(['code' => 'El código es inválido o expiró.'])
                ->withInput();
        }

        session([
            'password_reset_step' => 3,
            'password_reset_email' => $email,
            'password_code_verified_at' => now()->toDateTimeString(),
        ]);

        return back()->with('status', 'Código verificado. Ahora ya puedes cambiar tu contraseña.');
    }

    public function reset(Request $request, PasswordResetEmailGuard $emailGuard): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $email = $emailGuard->normalize($request->string('email')->toString());
        [$isValid, $message, $user] = $emailGuard->validate($email);

        if (!$isValid) {
            return back()->withErrors(['email' => $message])->withInput(['email' => $email]);
        }

        if ((int) session('password_reset_step', 1) < 3 || session('password_reset_email') !== $email) {
            return back()->withErrors(['email' => 'Debes verificar el código antes de cambiar la contraseña.']);
        }

        $row = DB::table('password_reset_tokens')->where('email', $email)->first();
        if (!$row) {
            return back()->withErrors(['email' => 'No fue posible completar el cambio. Solicita un nuevo código.']);
        }

        $expirationMinutes = (int) config('auth.passwords.users.expire', 60);
        $expiresAt = Carbon::parse($row->created_at)->addMinutes($expirationMinutes);

        if (now()->greaterThan($expiresAt)) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();
            session()->forget(['password_reset_step', 'password_reset_email', 'password_code_verified_at']);

            return back()->withErrors(['email' => 'No fue posible completar el cambio. Solicita un nuevo código.']);
        }

        if (!$user) {
            return back()->withErrors(['email' => 'No fue posible completar el cambio. Solicita un nuevo código.']);
        }

        $user->forceFill([
            'password' => Hash::make($request->string('password')->toString()),
            'remember_token' => Str::random(60),
        ])->save();

        event(new PasswordReset($user));

        DB::table('password_reset_tokens')->where('email', $email)->delete();
        session()->forget(['password_reset_step', 'password_reset_email', 'password_code_verified_at']);

        return redirect()->route('login')->with('status', 'Contraseña actualizada correctamente. Ya puedes iniciar sesión.');
    }
}
