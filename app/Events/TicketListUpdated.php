<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketListUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $ticketId,
        public readonly ?int $clientUserId = null,
    ) {
    }

    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('tickets.admins'),
            new PrivateChannel('tickets.employees'),
        ];

        if (($this->clientUserId ?? 0) > 0) {
            $channels[] = new PrivateChannel('users.' . $this->clientUserId . '.tickets');
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'ticket.list.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'ticket_id' => $this->ticketId,
            'client_user_id' => $this->clientUserId,
            'occurred_at' => now()->toIso8601String(),
        ];
    }
}
