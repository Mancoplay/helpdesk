<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Throwable;

class SafeBroadcast
{
    public static function dispatch(object $event): void
    {
        try {
            event($event);
        } catch (Throwable $exception) {
            Log::warning('No se pudo emitir el evento en tiempo real.', [
                'event' => $event::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
