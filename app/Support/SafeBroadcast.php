<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class SafeBroadcast
{
    public static function dispatch(object $event): void
    {
        if (self::broadcastIsCoolingDown()) {
            return;
        }

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
        if (self::broadcastIsCoolingDown()) {
            return;
        }

        try {
            event($event);
        } catch (Throwable $exception) {
            self::coolDownBroadcasts();

            Log::warning('No se pudo emitir el evento en tiempo real.', [
                'event' => $event::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private static function broadcastIsCoolingDown(): bool
    {
        return Cache::has(self::cooldownKey());
    }

    private static function coolDownBroadcasts(): void
    {
        Cache::put(self::cooldownKey(), true, now()->addSeconds(30));
    }

    private static function cooldownKey(): string
    {
        return 'broadcast:cooldown:' . (string) config('broadcasting.default', 'null');
    }
}
