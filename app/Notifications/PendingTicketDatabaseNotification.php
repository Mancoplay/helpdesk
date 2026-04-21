<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PendingTicketDatabaseNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Ticket $ticket,
        private readonly bool $isReminder = false,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $departmentName = $this->ticket->departamento?->nombre ?? 'Sin departamento';
        $title = $this->isReminder ? 'Recordatorio de ticket pendiente' : 'Nuevo ticket pendiente';
        $message = $this->isReminder
            ? "El ticket #{$this->ticket->codigo} sigue pendiente."
            : "Se creó el ticket #{$this->ticket->codigo} y requiere atención.";

        return [
            'kind' => 'ticket_pending_alert',
            'is_reminder' => $this->isReminder,
            'ticket_id' => $this->ticket->id,
            'ticket_code' => $this->ticket->codigo,
            'ticket_subject' => $this->ticket->asunto,
            'ticket_status' => $this->ticket->estado,
            'department_name' => $departmentName,
            'title' => $title,
            'message' => $message,
            'url' => route('tickets.show', $this->ticket),
            'created_at' => now()->toIso8601String(),
        ];
    }
}
