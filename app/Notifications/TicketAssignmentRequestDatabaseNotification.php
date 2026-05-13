<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TicketAssignmentRequestDatabaseNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Ticket $ticket,
        private readonly string $requestTypeLabel,
        private readonly string $requestedByName,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'ticket_assignment_request',
            'ticket_id' => $this->ticket->id,
            'ticket_code' => $this->ticket->codigo,
            'ticket_subject' => $this->ticket->asunto,
            'ticket_status' => $this->ticket->estado,
            'title' => 'Solicitud de asignacion',
            'message' => $this->requestedByName . ' solicito ' . mb_strtolower($this->requestTypeLabel) . ' en el ticket #' . $this->ticket->codigo . '.',
            'url' => route('tickets.edit', $this->ticket),
            'created_at' => now()->toIso8601String(),
        ];
    }
}
