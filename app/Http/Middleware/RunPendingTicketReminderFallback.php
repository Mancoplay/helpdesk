<?php

namespace App\Http\Middleware;

use App\Services\TicketNotificationService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class RunPendingTicketReminderFallback
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (!$this->shouldRun($request)) {
            return $response;
        }

        app()->terminating(function (): void {
            $this->runSafely();
        });

        return $response;
    }

    private function shouldRun(Request $request): bool
    {
        if (!config('helpdesk.pending_ticket_reminders.web_fallback_enabled', true)) {
            return false;
        }

        if (!$request->user()) {
            return false;
        }

        if (!$request->isMethod('GET')) {
            return false;
        }

        if ($request->ajax() || $request->expectsJson() || $request->wantsJson()) {
            return false;
        }

        if (!$request->acceptsHtml()) {
            return false;
        }

        if ($request->header('Sec-Fetch-Dest') && $request->header('Sec-Fetch-Dest') !== 'document') {
            return false;
        }

        return true;
    }

    private function runSafely(): void
    {
        try {
            $lock = Cache::lock('tickets:notify-pending:fallback-lock', 20);
            $intervalMinutes = max(1, (int) config('helpdesk.pending_ticket_reminders.interval_minutes', 5));
            $minimumReminderSeconds = $intervalMinutes * 60;
            $configuredCheckSeconds = (int) config('helpdesk.pending_ticket_reminders.fallback_check_seconds', $minimumReminderSeconds);
            $checkEverySeconds = max($minimumReminderSeconds, $configuredCheckSeconds);

            if (!$lock->get()) {
                return;
            }

            try {
                $lastRunAt = (int) Cache::get('tickets:notify-pending:fallback-last-run-at', 0);
                if ($lastRunAt > 0 && (time() - $lastRunAt) < $checkEverySeconds) {
                    return;
                }

                app(TicketNotificationService::class)->notifyPendingTickets();

                $ttlSeconds = max($checkEverySeconds * 2, 180);
                Cache::put('tickets:notify-pending:fallback-last-run-at', time(), now()->addSeconds($ttlSeconds));
            } finally {
                $lock->release();
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
