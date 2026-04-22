<?php

namespace App\Mail;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TicketAttendedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Ticket $ticket,
        public string $attendedByName,
    ) {
    }

    public function envelope(): Envelope
    {
        $fromAddress = config('mail.from.address')
            ?: config('mail.mailers.smtp.username')
            ?: 'noreply@helpdesk.local';
        $fromName = config('mail.from.name') ?: config('app.name', 'Helpdesk');

        return new Envelope(
            from: new Address($fromAddress, $fromName),
            subject: 'Ticket atendido #' . $this->ticket->codigo . ' - ' . $this->ticket->asunto,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.tickets.attended-alert',
            with: [
                'ticket' => $this->ticket,
                'attendedByName' => $this->attendedByName,
            ],
        );
    }
}
