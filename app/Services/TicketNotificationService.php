<?php

namespace App\Services;

use App\Mail\PendingTicketAlertMail;
use App\Models\Empleado;
use App\Models\SystemSetting;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\PendingTicketDatabaseNotification;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
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
            $isReminder = !is_null($ticket->last_notified_at);
            $sentTo += $this->sendPendingTicketAlert($ticket, $isReminder);
        }

        return $sentTo;
    }

    private function sendPendingTicketAlert(Ticket $ticket, bool $isReminder): int
    {
        if ($ticket->estado !== 'pendiente') {
            return 0;
        }

        $recipients = $this->departmentRecipients((int) $ticket->departamento_id);
        $recipientEmails = $recipients['emails'];
        $recipientUsers = $recipients['users'];

        if ($recipientEmails->isEmpty() && $recipientUsers->isEmpty()) {
            return 0;
        }

        $ticket->loadMissing(['departamento', 'cliente']);

        $databaseRecipientCount = $this->sendDatabaseNotifications($recipientUsers, $ticket, $isReminder);
        $mailWasSent = false;

        try {
            if ($recipientEmails->isNotEmpty()) {
                Mail::to($recipientEmails->all())->send(new PendingTicketAlertMail($ticket, $isReminder));
                $mailWasSent = true;
            }
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

    private function departmentRecipients(int $departmentId): array
    {
        $employees = Empleado::query()
            ->where('activo', true)
            ->where(function ($query) use ($departmentId): void {
                $query->where('departamento_id', $departmentId)
                    ->orWhereHas('departamentos', function ($departmentQuery) use ($departmentId): void {
                        $departmentQuery->where('departamentos.id', $departmentId);
                    });
            })
            ->with('user:id,name,email')
            ->get();

        $emails = $employees
            ->pluck('email')
            ->filter(fn ($email) => is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL))
            ->map(fn (string $email) => mb_strtolower(trim($email)))
            ->unique()
            ->values();

        $usersFromRelation = $employees
            ->pluck('user')
            ->filter()
            ->unique('id')
            ->values();

        // Fallback: if an employee was left without user_id, match by email.
        $usersFromEmail = $emails->isEmpty()
            ? collect()
            : User::query()
                ->whereIn('email', $emails->all())
                ->get(['id', 'name', 'email']);

        $emailsFromUsers = $usersFromRelation
            ->concat($usersFromEmail)
            ->pluck('email')
            ->filter(fn ($email) => is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL))
            ->map(fn (string $email) => mb_strtolower(trim($email)))
            ->unique()
            ->values();

        $configuredEmail = $this->configuredNotificationEmail();
        $configuredEmailCollection = $configuredEmail ? collect([$configuredEmail]) : collect();
        $configuredUser = $configuredEmail
            ? User::query()->where('email', $configuredEmail)->first(['id', 'name', 'email'])
            : null;
        $configuredUsers = $configuredUser ? collect([$configuredUser]) : collect();

        return [
            'emails' => $emails
                ->concat($emailsFromUsers)
                ->concat($configuredEmailCollection)
                ->unique()
                ->values(),
            'users' => $usersFromRelation
                ->concat($usersFromEmail)
                ->concat($configuredUsers)
                ->unique('id')
                ->values(),
        ];
    }

    private function sendDatabaseNotifications(Collection $recipientUsers, Ticket $ticket, bool $isReminder): int
    {
        if ($recipientUsers->isEmpty()) {
            return 0;
        }

        Notification::send($recipientUsers, new PendingTicketDatabaseNotification($ticket, $isReminder));

        return $recipientUsers->count();
    }

    private function configuredNotificationEmail(): ?string
    {
        if (!Schema::hasTable('system_settings')) {
            return null;
        }

        $email = SystemSetting::query()
            ->where('key', 'pending_ticket_notification_email')
            ->value('value');

        if (!is_string($email)) {
            return null;
        }

        $email = mb_strtolower(trim($email));

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }
}
