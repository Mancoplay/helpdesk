<?php

use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return redirect()->route('dashboard');
})->name('home');

Auth::routes(['verify' => true]);
Route::pattern('ticket', '[0-9]+');
Route::pattern('remoteSession', '[0-9]+');

Route::middleware(['auth', 'permission:ver dashboard'])->group(function () {
    Route::get('/dashboard', [HomeController::class, 'index'])->name('dashboard');
});

Route::middleware(['auth', 'role:Administrador'])->group(function () {

    Route::get('/clientes', [HomeController::class, 'clientes'])->name('clientes.index');
    Route::get('/clientes/{cliente}/revisar', [HomeController::class, 'reviewCliente'])->name('clientes.review');
    Route::post('/clientes', [HomeController::class, 'storeCliente'])->name('clientes.store');
    Route::put('/clientes/{cliente}', [HomeController::class, 'updateCliente'])->name('clientes.update');
    Route::delete('/clientes/{cliente}', [HomeController::class, 'destroyCliente'])->name('clientes.destroy');

    Route::get('/empleados', [HomeController::class, 'empleados'])->name('empleados.index');
    Route::get('/empleados/{empleado}/revisar', [HomeController::class, 'reviewEmpleado'])->name('empleados.review');
    Route::post('/empleados', [HomeController::class, 'storeEmpleado'])->name('empleados.store');
    Route::put('/empleados/{empleado}', [HomeController::class, 'updateEmpleado'])->name('empleados.update');
    Route::delete('/empleados/{empleado}', [HomeController::class, 'destroyEmpleado'])->name('empleados.destroy');

    Route::get('/departamentos', [HomeController::class, 'departamentos'])->name('departamentos.index');
    Route::post('/departamentos', [HomeController::class, 'storeDepartamento'])->name('departamentos.store');
    Route::put('/departamentos/{departamento}', [HomeController::class, 'updateDepartamento'])->name('departamentos.update');
    Route::delete('/departamentos/{departamento}', [HomeController::class, 'destroyDepartamento'])->name('departamentos.destroy');
});

Route::middleware(['auth', 'permission:ver tickets'])->group(function () {
    Route::get('/tickets', [HomeController::class, 'tickets'])->name('tickets.index');
    Route::get('/tickets/{ticket}', [HomeController::class, 'showTicket'])->name('tickets.show');
    Route::post('/tickets/{ticket}/mensajes', [HomeController::class, 'storeTicketMessage'])->name('tickets.messages.store');
    Route::post('/tickets/{ticket}/remote/request', [HomeController::class, 'requestRemoteSession'])->name('tickets.remote.request');
    Route::patch('/tickets/{ticket}/remote/{remoteSession}', [HomeController::class, 'updateRemoteSession'])->name('tickets.remote.update');
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
    Route::put('/tickets/{ticket}', [HomeController::class, 'updateTicket'])->name('tickets.update');
});

Route::middleware(['auth', 'permission:ver tickets'])->group(function () {
    Route::delete('/tickets/{ticket}', [HomeController::class, 'destroyTicket'])->name('tickets.destroy');
});

Route::middleware('auth')->group(function () {
    Route::redirect('/settings', '/settings/profile');

    Volt::route('/settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('/settings/password', 'settings.password')->name('settings.password');
    Volt::route('/settings/appearance', 'settings.appearance')->name('settings.appearance');
});

Route::view('/dashboard-livewire', 'dashboard')->name('dashboard.livewire')->middleware('auth');

