<?php

namespace App\Jobs;

use App\Models\Ticket;
use App\Services\TicketNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class NotifyTicketAttended implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(
        public readonly int $ticketId,
        public readonly string $attendedByName,
    ) {
        $this->afterCommit();

        if (config('queue.default') === 'sync') {
            $this->onConnection('database');
        }
    }

    public function handle(TicketNotificationService $ticketNotificationService): void
    {
        $ticket = Ticket::query()->find($this->ticketId);

        if (!$ticket) {
            return;
        }

        try {
            $ticketNotificationService->notifyTicketAttended($ticket, $this->attendedByName);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
