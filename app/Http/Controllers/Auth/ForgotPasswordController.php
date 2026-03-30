<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\PasswordVerificationCodeMail;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ForgotPasswordController extends Controller
{
    public function showLinkRequestForm()
    {
        return view('auth.passwords.email');
    }

    public function sendResetLinkEmail(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $email = $request->string('email')->toString();

        $user = User::query()->where('email', $email)->first();
        if (!$user) {
            return back()
                ->withErrors(['email' => 'No encontramos ese correo en el sistema.'])
                ->withInput();
        }

        $code = (string) random_int(100000, 999999);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            [
                'token' => Hash::make($code),
                'created_at' => now(),
            ]
        );

        try {
            Mail::to($email)->send(new PasswordVerificationCodeMail($code));
        } catch (Throwable $e) {
            report($e);

            return back()
                ->withErrors(['email' => 'No se pudo enviar el correo en este momento. Revisa la configuracion SMTP e intenta de nuevo.'])
                ->withInput();
        }

        session([
            'password_reset_step' => 2,
            'password_reset_email' => $email,
        ]);

        return back()->with('status', 'Te enviamos un codigo de verificacion a tu correo.');
    }
}
