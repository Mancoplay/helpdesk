<?php

use App\Mail\PasswordVerificationCodeMail;
use Carbon\Carbon;
use App\Support\PasswordResetEmailGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    public string $email = '';
    public string $code = '';
    public string $password = '';
    public string $password_confirmation = '';
    public bool $codeSent = false;

    /**
     * Send a verification code only if the email exists in users table.
     */
    public function sendPasswordResetLink(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $emailGuard = app(PasswordResetEmailGuard::class);
        $this->email = $emailGuard->normalize($this->email);
        [$isValid, $message] = $emailGuard->validate($this->email);

        if (!$isValid) {
            $this->addError('email', $message);
            $this->codeSent = false;
            return;
        }

        $code = (string) random_int(100000, 999999);
        $hashedCode = Hash::make($code);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $this->email],
            [
                'token' => $hashedCode,
                'created_at' => now(),
            ]
        );

        Mail::to($this->email)->send(new PasswordVerificationCodeMail($code));

        $this->codeSent = true;
        $this->reset('code', 'password', 'password_confirmation');
        session()->flash('status', 'Te enviamos un código de verificación a tu correo.');
    }

    /**
     * Verify code and update password.
     */
    public function verifyCodeAndResetPassword(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
            'code' => ['required', 'digits:6'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $emailGuard = app(PasswordResetEmailGuard::class);
        $this->email = $emailGuard->normalize($this->email);
        [$isValid, $message, $user] = $emailGuard->validate($this->email);

        if (!$isValid || !$user) {
            $this->addError('email', $message ?: 'No encontramos ese correo en el sistema.');
            return;
        }

        $row = DB::table('password_reset_tokens')->where('email', $this->email)->first();
        if (!$row) {
            $this->addError('code', 'Primero debes solicitar un código de verificación.');
            return;
        }

        $expirationMinutes = (int) config('auth.passwords.users.expire', 60);
        $expiresAt = Carbon::parse($row->created_at)->addMinutes($expirationMinutes);

        if (now()->greaterThan($expiresAt)) {
            DB::table('password_reset_tokens')->where('email', $this->email)->delete();
            $this->addError('code', 'El código ha expirado. Solicita uno nuevo.');
            $this->codeSent = false;
            return;
        }

        if (!Hash::check($this->code, $row->token)) {
            $this->addError('code', 'El código es incorrecto.');
            return;
        }

        $user->forceFill([
            'password' => Hash::make($this->password),
            'remember_token' => Str::random(60),
        ])->save();

        DB::table('password_reset_tokens')->where('email', $this->email)->delete();

        session()->flash('status', 'Contraseña actualizada correctamente. Ya puedes iniciar sesión.');
        $this->redirectRoute('login', navigate: true);
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header title="Recuperar contraseña" description="Primero ingresa tu correo y luego el código de verificación." />

    <!-- Session Status -->
    <x-auth-session-status class="text-center" :status="session('status')" />

    <form wire:submit="{{ $codeSent ? 'verifyCodeAndResetPassword' : 'sendPasswordResetLink' }}" class="flex flex-col gap-6">
        <div class="grid gap-2">
            <flux:input
                wire:model="email"
                label="{{ __('Correo electronico') }}"
                type="email"
                name="email"
                required
                autofocus
                placeholder="correo@ejemplo.com"
            />
        </div>

        @if ($codeSent)
            <div class="grid gap-2">
                <flux:input
                    wire:model="code"
                    label="{{ __('Código de verificación') }}"
                    type="text"
                    name="code"
                    inputmode="numeric"
                    maxlength="6"
                    required
                    placeholder="123456"
                />
            </div>

            <div class="grid gap-2">
                <flux:input
                    wire:model="password"
                    id="password"
                    label="{{ __('Nueva contraseña') }}"
                    type="password"
                    name="password"
                    required
                    autocomplete="new-password"
                    placeholder="Nueva contraseña"
                />
            </div>

            <div class="grid gap-2">
                <flux:input
                    wire:model="password_confirmation"
                    id="password_confirmation"
                    label="{{ __('Confirmar contraseña') }}"
                    type="password"
                    name="password_confirmation"
                    required
                    autocomplete="new-password"
                    placeholder="Confirma tu contraseña"
                />
            </div>
        @endif

        <flux:button variant="primary" type="submit" class="w-full">
            {{ $codeSent ? 'Verificar código y cambiar contraseña' : 'Enviar código de verificación' }}
        </flux:button>
    </form>

    <div class="space-x-1 text-center text-sm text-zinc-400">
        Volver a
        <x-text-link href="{{ route('login') }}">iniciar sesión</x-text-link>
    </div>
</div>
