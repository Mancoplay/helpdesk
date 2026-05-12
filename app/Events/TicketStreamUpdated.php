<?php

namespace App\Events;

use App\Models\Ticket;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketStreamUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $ticketId,
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('tickets.' . $this->ticketId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'ticket.stream.updated';
    }

    public function broadcastWith(): array
    {
        $remotePayload = null;
        $ticket = Ticket::query()->with(['remoteSessions' => function ($query): void {
            $query->latest('id')->limit(1);
        }])->find($this->ticketId);

        $remoteSession = $ticket?->remoteSessions->first();
        if ($remoteSession) {
            $remotePayload = [
                'id' => (int) $remoteSession->id,
                'status' => (string) $remoteSession->status,
                'support_code' => (string) ($remoteSession->support_code ?? ''),
                'rustdesk_code' => (string) ($remoteSession->rustdesk_code ?? ''),
            ];
        }

        return [
            'ticket_id' => $this->ticketId,
            'occurred_at' => now()->toIso8601String(),
            'remote' => $remotePayload,
        ];
    }
}
