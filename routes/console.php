<?php

use App\Services\TicketNotificationService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Artisan::command('tickets:notify-pending', function (TicketNotificationService $ticketNotificationService) {
    $sent = $ticketNotificationService->notifyPendingTickets();
    $this->info("Notificaciones enviadas a {$sent} destinatarios.");
})->purpose('Envia recordatorios por correo para tickets pendientes');

// Ejecutar cada minuto y filtrar internamente por 5 minutos evita retrasos (ej: 10-15 min).
Schedule::command('tickets:notify-pending')->everyMinute();
