<?php

use App\Services\TicketNotificationService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Artisan::command('tickets:notify-pending', function (TicketNotificationService $ticketNotificationService) {
    $sent = $ticketNotificationService->notifyPendingTickets();
    $this->info("Notificaciones enviadas a {$sent} destinatarios.");
})->purpose('Envia recordatorios por correo para tickets pendientes cuando estan habilitados');

Artisan::command('notifications:prune-old {--days=30}', function () {
    $days = max(1, (int) $this->option('days'));
    $threshold = now()->subDays($days);

    $deleted = DB::table('notifications')
        ->where('created_at', '<', $threshold)
        ->delete();

    $this->info("Notificaciones eliminadas: {$deleted} (anteriores a {$threshold->format('Y-m-d H:i:s')}).");
})->purpose('Elimina notificaciones antiguas para evitar crecimiento de la base de datos');

if (config('helpdesk.pending_ticket_reminders.enabled', false)) {
    Schedule::command('tickets:notify-pending')->everyMinute();
}

Schedule::command('notifications:prune-old --days=' . max(1, (int) config('helpdesk.notifications.retention_days', 30)))
    ->dailyAt('02:00');
