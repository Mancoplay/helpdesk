<?php

namespace App\Mail;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PendingTicketAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Ticket $ticket,
        public bool $isReminder = false,
    ) {
    }

    public function envelope(): Envelope
    {
        $fromAddress = config('mail.from.address') ?: 'noreply@helpdesk.local';
        $fromName = config('mail.from.name') ?: config('app.name', 'Helpdesk');

        $subjectPrefix = $this->isReminder ? '[Recordatorio] ' : '';

        return new Envelope(
            from: new Address($fromAddress, $fromName),
            subject: $subjectPrefix . 'Ticket pendiente #' . $this->ticket->codigo . ' - ' . $this->ticket->asunto,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.tickets.pending-alert',
            with: [
                'ticket' => $this->ticket,
                'isReminder' => $this->isReminder,
            ],
        );
    }
}
