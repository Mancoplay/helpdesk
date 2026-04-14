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

        if ($request->ajax()) {
            return false;
        }

        return true;
    }

    private function runSafely(): void
    {
        try {
            $lock = Cache::lock('tickets:notify-pending:fallback-lock', 20);

            if (!$lock->get()) {
                return;
            }

            try {
                $lastRunAt = (int) Cache::get('tickets:notify-pending:fallback-last-run-at', 0);
                if ($lastRunAt > 0 && (time() - $lastRunAt) < 60) {
                    return;
                }

                app(TicketNotificationService::class)->notifyPendingTickets();

                Cache::put('tickets:notify-pending:fallback-last-run-at', time(), now()->addMinutes(5));
            } finally {
                $lock->release();
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
