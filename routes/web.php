<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Auth::routes();

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [HomeController::class, 'index'])->name('dashboard');

    Route::get('/usuarios', [HomeController::class, 'usuarios'])->name('usuarios.index');
    Route::post('/usuarios', [HomeController::class, 'storeUsuario'])->name('usuarios.store');
    Route::delete('/usuarios/{user}', [HomeController::class, 'destroyUsuario'])->name('usuarios.destroy');

    Route::get('/clientes', [HomeController::class, 'clientes'])->name('clientes.index');
    Route::post('/clientes', [HomeController::class, 'storeCliente'])->name('clientes.store');
    Route::delete('/clientes/{cliente}', [HomeController::class, 'destroyCliente'])->name('clientes.destroy');

    Route::get('/empleados', [HomeController::class, 'empleados'])->name('empleados.index');
    Route::post('/empleados', [HomeController::class, 'storeEmpleado'])->name('empleados.store');
    Route::delete('/empleados/{empleado}', [HomeController::class, 'destroyEmpleado'])->name('empleados.destroy');

    Route::get('/departamentos', [HomeController::class, 'departamentos'])->name('departamentos.index');
    Route::post('/departamentos', [HomeController::class, 'storeDepartamento'])->name('departamentos.store');
    Route::delete('/departamentos/{departamento}', [HomeController::class, 'destroyDepartamento'])->name('departamentos.destroy');

    Route::get('/tickets', [HomeController::class, 'tickets'])->name('tickets.index');
    Route::post('/tickets', [HomeController::class, 'storeTicket'])->name('tickets.store');
    Route::delete('/tickets/{ticket}', [HomeController::class, 'destroyTicket'])->name('tickets.destroy');

    Route::view('/dashboard-livewire', 'dashboard')->name('dashboard.livewire');
});
