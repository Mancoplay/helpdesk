<?php

namespace App\Events;

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
        return [
            'ticket_id' => $this->ticketId,
            'occurred_at' => now()->toIso8601String(),
        ];
    }
}
