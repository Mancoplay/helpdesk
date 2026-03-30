<?php

use App\Mail\PasswordVerificationCodeMail;
use App\Models\User;
use Carbon\Carbon;
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

        $user = User::query()->where('email', $this->email)->first();
        if (!$user) {
            $this->addError('email', 'No encontramos ese correo en el sistema.');
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
        session()->flash('status', 'Te enviamos un codigo de verificacion a tu correo.');
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

        $row = DB::table('password_reset_tokens')->where('email', $this->email)->first();
        if (!$row) {
            $this->addError('code', 'Primero debes solicitar un codigo de verificacion.');
            return;
        }

        $expirationMinutes = (int) config('auth.passwords.users.expire', 60);
        $expiresAt = Carbon::parse($row->created_at)->addMinutes($expirationMinutes);

        if (now()->greaterThan($expiresAt)) {
            DB::table('password_reset_tokens')->where('email', $this->email)->delete();
            $this->addError('code', 'El codigo ha expirado. Solicita uno nuevo.');
            $this->codeSent = false;
            return;
        }

        if (!Hash::check($this->code, $row->token)) {
            $this->addError('code', 'El codigo es incorrecto.');
            return;
        }

        $user = User::query()->where('email', $this->email)->first();
        if (!$user) {
            $this->addError('email', 'No encontramos ese correo en el sistema.');
            return;
        }

        $user->forceFill([
            'password' => Hash::make($this->password),
            'remember_token' => Str::random(60),
        ])->save();

        DB::table('password_reset_tokens')->where('email', $this->email)->delete();

        session()->flash('status', 'Contrasena actualizada correctamente. Ya puedes iniciar sesion.');
        $this->redirectRoute('login', navigate: true);
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header title="Recuperar contrasena" description="Primero ingresa tu correo y luego el codigo de verificacion." />

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
                    label="{{ __('Codigo de verificacion') }}"
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
                    label="{{ __('Nueva contrasena') }}"
                    type="password"
                    name="password"
                    required
                    autocomplete="new-password"
                    placeholder="Nueva contrasena"
                />
            </div>

            <div class="grid gap-2">
                <flux:input
                    wire:model="password_confirmation"
                    id="password_confirmation"
                    label="{{ __('Confirmar contrasena') }}"
                    type="password"
                    name="password_confirmation"
                    required
                    autocomplete="new-password"
                    placeholder="Confirma tu contrasena"
                />
            </div>
        @endif

        <flux:button variant="primary" type="submit" class="w-full">
            {{ $codeSent ? 'Verificar codigo y cambiar contrasena' : 'Enviar codigo de verificacion' }}
        </flux:button>
    </form>

    <div class="space-x-1 text-center text-sm text-zinc-400">
        Volver a
        <x-text-link href="{{ route('login') }}">iniciar sesion</x-text-link>
    </div>
</div>
