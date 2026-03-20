<?php

use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Auth::routes();

Route::middleware(['auth', 'permission:ver dashboard'])->group(function () {
    Route::get('/dashboard', [HomeController::class, 'index'])->name('dashboard');
});

Route::middleware(['auth', 'role:Administrador'])->group(function () {
    Route::get('/clientes', [HomeController::class, 'clientes'])->name('clientes.index');
    Route::post('/clientes', [HomeController::class, 'storeCliente'])->name('clientes.store');
    Route::put('/clientes/{cliente}', [HomeController::class, 'updateCliente'])->name('clientes.update');
    Route::delete('/clientes/{cliente}', [HomeController::class, 'destroyCliente'])->name('clientes.destroy');

    Route::get('/empleados', [HomeController::class, 'empleados'])->name('empleados.index');
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
});

Route::middleware(['auth', 'permission:crear tickets'])->group(function () {
    Route::post('/tickets', [HomeController::class, 'storeTicket'])->name('tickets.store');
});

Route::middleware(['auth', 'permission:atender tickets'])->group(function () {
    Route::patch('/tickets/{ticket}/atender', [HomeController::class, 'attendTicket'])->name('tickets.attend');
});

Route::middleware(['auth', 'role:Administrador'])->group(function () {
    Route::put('/tickets/{ticket}', [HomeController::class, 'updateTicket'])->name('tickets.update');
    Route::delete('/tickets/{ticket}', [HomeController::class, 'destroyTicket'])->name('tickets.destroy');
});

Route::view('/dashboard-livewire', 'dashboard')->name('dashboard.livewire')->middleware('auth');
