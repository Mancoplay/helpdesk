<?php

use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return redirect()->route('dashboard');
})->name('home');

Auth::routes(['verify' => true]);

Route::post('/password/verify-code', [ResetPasswordController::class, 'verifyCode'])
    ->middleware('guest')
    ->name('password.verify-code');

Route::pattern('ticket', '[0-9]+');
Route::pattern('remoteSession', '[0-9]+');
Route::pattern('mensaje', '[0-9]+');

Route::middleware(['auth', 'permission:ver dashboard'])->group(function () {
    Route::get('/dashboard', [HomeController::class, 'index'])->name('dashboard');
});

Route::middleware(['auth', 'role:Administrador'])->group(function () {
    Route::get('/usuarios', [HomeController::class, 'clientes'])->name('usuarios.index');
    Route::get('/usuarios/{cliente}/revisar', [HomeController::class, 'reviewCliente'])->name('usuarios.review');
    Route::post('/usuarios', [HomeController::class, 'storeCliente'])->name('usuarios.store');
    Route::put('/usuarios/{cliente}', [HomeController::class, 'updateCliente'])->name('usuarios.update');
    Route::patch('/usuarios/{cliente}/checkpoint', [HomeController::class, 'toggleClienteCheckpoint'])->name('usuarios.checkpoint');

    // Legacy aliases to avoid breaking existing links/bookmarks.
    Route::get('/clientes', [HomeController::class, 'clientes'])->name('clientes.index');
    Route::get('/clientes/{cliente}/revisar', [HomeController::class, 'reviewCliente'])->name('clientes.review');
    Route::post('/clientes', [HomeController::class, 'storeCliente'])->name('clientes.store');
    Route::put('/clientes/{cliente}', [HomeController::class, 'updateCliente'])->name('clientes.update');
    Route::patch('/clientes/{cliente}/checkpoint', [HomeController::class, 'toggleClienteCheckpoint'])->name('clientes.checkpoint');

    Route::get('/empleados', [HomeController::class, 'empleados'])->name('empleados.index');
    Route::get('/empleados/{empleado}/revisar', [HomeController::class, 'reviewEmpleado'])->name('empleados.review');
    Route::post('/empleados', [HomeController::class, 'storeEmpleado'])->name('empleados.store');
    Route::put('/empleados/{empleado}', [HomeController::class, 'updateEmpleado'])->name('empleados.update');
    Route::patch('/empleados/{empleado}/checkpoint', [HomeController::class, 'toggleEmpleadoCheckpoint'])->name('empleados.checkpoint');

    Route::get('/departamentos', [HomeController::class, 'departamentos'])->name('departamentos.index');
    Route::post('/departamentos', [HomeController::class, 'storeDepartamento'])->name('departamentos.store');
    Route::put('/departamentos/{departamento}', [HomeController::class, 'updateDepartamento'])->name('departamentos.update');
    Route::post('/departamentos/notificaciones/correo', [HomeController::class, 'updateNotificationEmail'])->name('departamentos.notification-email.update');
    Route::patch('/departamentos/{departamento}/checkpoint', [HomeController::class, 'toggleDepartamentoCheckpoint'])->name('departamentos.checkpoint');
});

Route::middleware(['auth', 'permission:ver tickets'])->group(function () {
    Route::get('/tickets', [HomeController::class, 'tickets'])->name('tickets.index');
    Route::get('/tickets/{ticket}', [HomeController::class, 'showTicket'])->name('tickets.show');
    Route::get('/tickets/{ticket}/live', [HomeController::class, 'ticketLiveData'])->name('tickets.live');
    Route::get('/tickets/{ticket}/adjuntos/{mensaje}', [HomeController::class, 'showTicketAttachment'])->name('tickets.attachments.show');
    Route::post('/tickets/{ticket}/mensajes', [HomeController::class, 'storeTicketMessage'])->name('tickets.messages.store');
    Route::post('/tickets/{ticket}/remote/request', [HomeController::class, 'requestRemoteSession'])->name('tickets.remote.request');
    Route::patch('/tickets/{ticket}/remote/{remoteSession}', [HomeController::class, 'updateRemoteSession'])->name('tickets.remote.update');
    Route::delete('/tickets/{ticket}', [HomeController::class, 'destroyTicket'])->name('tickets.destroy');
});

Route::middleware(['auth', 'permission:crear tickets'])->group(function () {
    Route::post('/tickets', [HomeController::class, 'storeTicket'])->name('tickets.store');
    Route::get('/tickets/next-code', [HomeController::class, 'nextTicketCodeJson'])->name('tickets.next-code');
});

Route::middleware(['auth', 'permission:atender tickets'])->group(function () {
    Route::patch('/tickets/{ticket}/atender', [HomeController::class, 'attendTicket'])->name('tickets.attend');
    Route::patch('/tickets/{ticket}/finalizar', [HomeController::class, 'finalizeTicket'])->name('tickets.finalize');
});

Route::middleware(['auth', 'role:Administrador'])->group(function () {
    Route::get('/tickets/{ticket}/editar', [HomeController::class, 'editTicket'])->name('tickets.edit');
    Route::put('/tickets/{ticket}', [HomeController::class, 'updateTicket'])->name('tickets.update');
    Route::patch('/tickets/{ticket}/checkpoint', [HomeController::class, 'toggleTicketCheckpoint'])->name('tickets.checkpoint');
});

Route::middleware('auth')->group(function () {
    Route::get('/notificaciones', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notificaciones/resumen', [NotificationController::class, 'unreadSummary'])->name('notifications.summary');
    Route::get('/notificaciones/{notificationId}/abrir', [NotificationController::class, 'open'])->name('notifications.open');
    Route::post('/notificaciones/marcar-todas', [NotificationController::class, 'markAllAsRead'])->name('notifications.mark-all-read');

    Route::redirect('/settings', '/settings/profile');

    Volt::route('/settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('/settings/password', 'settings.password')->name('settings.password');
    Volt::route('/settings/appearance', 'settings.appearance')->name('settings.appearance');
});
