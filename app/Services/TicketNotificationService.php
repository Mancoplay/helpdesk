<?php

namespace App\Services;

use App\Mail\PendingTicketAlertMail;
use App\Models\Empleado;
use App\Models\Ticket;
use App\Notifications\PendingTicketDatabaseNotification;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Throwable;

class TicketNotificationService
{
    public function notifyTicketCreated(Ticket $ticket): int
    {
        return $this->sendPendingTicketAlert($ticket, false);
    }

    public function notifyPendingTickets(): int
    {
        $intervalMinutes = max(1, (int) config('helpdesk.pending_ticket_reminders.interval_minutes', 5));
        $threshold = now()->subMinutes($intervalMinutes);

        $tickets = Ticket::query()
            ->where('estado', 'pendiente')
            ->where(function (Builder $query) use ($threshold): void {
                $query->whereNull('last_notified_at')
                    ->orWhere('last_notified_at', '<=', $threshold);
            })
            ->with(['departamento', 'cliente'])
            ->get();

        $sentTo = 0;
        foreach ($tickets as $ticket) {
            $sentTo += $this->sendPendingTicketAlert($ticket, true);
        }

        return $sentTo;
    }

    private function sendPendingTicketAlert(Ticket $ticket, bool $isReminder): int
    {
        if ($ticket->estado !== 'pendiente') {
            return 0;
        }

        $recipientEmails = $this->departmentRecipientEmails((int) $ticket->departamento_id);
        if ($recipientEmails->isEmpty()) {
            return 0;
        }

        $ticket->loadMissing(['departamento', 'cliente']);

        $databaseRecipientCount = $this->sendDatabaseNotifications($ticket, $isReminder);
        $mailWasSent = false;

        try {
            Mail::to($recipientEmails->all())->send(new PendingTicketAlertMail($ticket, $isReminder));
            $mailWasSent = true;
        } catch (Throwable $exception) {
            report($exception);
        }

        if (!$mailWasSent && $databaseRecipientCount === 0) {
            return 0;
        }

        $ticket->forceFill([
            'last_notified_at' => now(),
        ])->save();

        return $mailWasSent
            ? $recipientEmails->count()
            : $databaseRecipientCount;
    }

    private function departmentRecipientEmails(int $departmentId): Collection
    {
        return Empleado::query()
            ->where('activo', true)
            ->where(function ($query) use ($departmentId): void {
                $query->where('departamento_id', $departmentId)
                    ->orWhereHas('departamentos', function ($departmentQuery) use ($departmentId): void {
                        $departmentQuery->where('departamentos.id', $departmentId);
                    });
            })
            ->pluck('email')
            ->filter(fn ($email) => is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL))
            ->map(fn (string $email) => mb_strtolower(trim($email)))
            ->unique()
            ->values();
    }

    private function sendDatabaseNotifications(Ticket $ticket, bool $isReminder): int
    {
        $recipientUsers = Empleado::query()
            ->where('activo', true)
            ->whereNotNull('user_id')
            ->where(function (Builder $query) use ($ticket): void {
                $query->where('departamento_id', (int) $ticket->departamento_id)
                    ->orWhereHas('departamentos', function (Builder $departmentQuery) use ($ticket): void {
                        $departmentQuery->where('departamentos.id', (int) $ticket->departamento_id);
                    });
            })
            ->with('user:id,name,email')
            ->get()
            ->pluck('user')
            ->filter()
            ->unique('id')
            ->values();

        if ($recipientUsers->isEmpty()) {
            return 0;
        }

        Notification::send($recipientUsers, new PendingTicketDatabaseNotification($ticket, $isReminder));

        return $recipientUsers->count();
    }
}
