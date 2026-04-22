<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TicketAttendedDatabaseNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Ticket $ticket,
        private readonly string $attendedByName,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'ticket_attended_alert',
            'ticket_id' => $this->ticket->id,
            'ticket_code' => $this->ticket->codigo,
            'ticket_subject' => $this->ticket->asunto,
            'ticket_status' => $this->ticket->estado,
            'title' => 'Ticket atendido',
            'message' => 'Ticket atendido por ' . $this->attendedByName . '.',
            'url' => route('tickets.show', $this->ticket),
            'created_at' => now()->toIso8601String(),
        ];
    }
}
