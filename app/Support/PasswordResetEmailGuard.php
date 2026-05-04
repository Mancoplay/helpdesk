<?php

namespace App\Support;

use App\Models\User;

class PasswordResetEmailGuard
{
    private const DISPOSABLE_DOMAINS = [
        '10minutemail.com',
        '20minutemail.com',
        'guerrillamail.com',
        'mailinator.com',
        'maildrop.cc',
        'moakt.com',
        'tempmail.com',
        'temp-mail.org',
        'throwawaymail.com',
        'yopmail.com',
    ];

    public function normalize(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    public function validate(string $email): array
    {
        $email = $this->normalize($email);

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [false, 'Ingresa un correo electronico valido.', null];
        }

        $domain = mb_strtolower((string) substr(strrchr($email, '@') ?: '', 1));
        if ($domain === '' || in_array($domain, self::DISPOSABLE_DOMAINS, true)) {
            return [false, 'No se permiten correos temporales o desechables.', null];
        }

        if (!$this->domainCanReceiveMail($domain)) {
            return [false, 'El dominio del correo no existe o no puede recibir mensajes.', null];
        }

        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if (!$user) {
            return [false, 'No encontramos ese correo en el sistema.', null];
        }

        if (isset($user->activo) && !$user->activo) {
            return [false, 'La cuenta esta deshabilitada. Contacta al administrador.', null];
        }

        return [true, '', $user];
    }

    private function domainCanReceiveMail(string $domain): bool
    {
        if ($domain === '') {
            return false;
        }

        if (function_exists('idn_to_ascii')) {
            $asciiDomain = idn_to_ascii($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
            if (is_string($asciiDomain) && $asciiDomain !== '') {
                $domain = $asciiDomain;
            }
        }

        return checkdnsrr($domain, 'MX') || checkdnsrr($domain, 'A') || checkdnsrr($domain, 'AAAA');
    }
}
