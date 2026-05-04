<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Throwable;

class SafeBroadcast
{
    public static function dispatch(object $event): void
    {
        if (!app()->runningInConsole()) {
            app()->terminating(static function () use ($event): void {
                self::dispatchNow($event);
            });

            return;
        }

        self::dispatchNow($event);
    }

    private static function dispatchNow(object $event): void
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
