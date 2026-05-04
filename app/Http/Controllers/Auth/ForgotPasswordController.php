<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\PasswordVerificationCodeMail;
use App\Support\PasswordResetEmailGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ForgotPasswordController extends Controller
{
    public function __construct()
    {
        $this->middleware('guest');
        $this->middleware('throttle:6,1')->only('sendResetLinkEmail');
    }

    public function showLinkRequestForm()
    {
        return view('auth.passwords.email');
    }

    public function sendResetLinkEmail(Request $request, PasswordResetEmailGuard $emailGuard): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $email = $emailGuard->normalize($request->string('email')->toString());
        [$isValid, $message] = $emailGuard->validate($email);

        if (!$isValid) {
            session()->forget(['password_reset_step', 'password_reset_email']);

            return back()
                ->withErrors(['email' => $message])
                ->withInput(['email' => $email]);
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
        }

        session([
            'password_reset_step' => 2,
            'password_reset_email' => $email,
        ]);

        return back()->with('status', 'Te enviamos un codigo de verificacion a tu correo.');
    }
}
