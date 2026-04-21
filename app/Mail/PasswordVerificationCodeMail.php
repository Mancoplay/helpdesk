<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordVerificationCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $code
    ) {
    }

    public function envelope(): Envelope
    {
        $fromAddress = config('mail.from.address') ?: 'verify.access.plus@gmail.com';
        $fromName = config('mail.from.name') ?: config('app.name', 'Helpdesk');

        return new Envelope(
            from: new Address($fromAddress, $fromName),
            subject: 'Código de verificación para recuperar tu contraseña',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.auth.password-verification-code',
            with: [
                'code' => $this->code,
                'expiresIn' => (int) config('auth.passwords.users.expire', 60),
            ],
        );
    }
}
