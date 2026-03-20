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
    Route::get('/usuarios/{user}/edit', [HomeController::class, 'editUsuario'])->name('usuarios.edit');
    Route::put('/usuarios/{user}', [HomeController::class, 'updateUsuario'])->name('usuarios.update');
    Route::delete('/usuarios/{user}', [HomeController::class, 'destroyUsuario'])->name('usuarios.destroy');

    Route::get('/clientes', [HomeController::class, 'clientes'])->name('clientes.index');
    Route::post('/clientes', [HomeController::class, 'storeCliente'])->name('clientes.store');
    Route::get('/clientes/{cliente}/edit', [HomeController::class, 'editCliente'])->name('clientes.edit');
    Route::put('/clientes/{cliente}', [HomeController::class, 'updateCliente'])->name('clientes.update');
    Route::delete('/clientes/{cliente}', [HomeController::class, 'destroyCliente'])->name('clientes.destroy');

    Route::get('/empleados', [HomeController::class, 'empleados'])->name('empleados.index');
    Route::post('/empleados', [HomeController::class, 'storeEmpleado'])->name('empleados.store');
    Route::get('/empleados/{empleado}/edit', [HomeController::class, 'editEmpleado'])->name('empleados.edit');
    Route::put('/empleados/{empleado}', [HomeController::class, 'updateEmpleado'])->name('empleados.update');
    Route::delete('/empleados/{empleado}', [HomeController::class, 'destroyEmpleado'])->name('empleados.destroy');

    Route::get('/departamentos', [HomeController::class, 'departamentos'])->name('departamentos.index');
    Route::post('/departamentos', [HomeController::class, 'storeDepartamento'])->name('departamentos.store');
    Route::get('/departamentos/{departamento}/edit', [HomeController::class, 'editDepartamento'])->name('departamentos.edit');
    Route::put('/departamentos/{departamento}', [HomeController::class, 'updateDepartamento'])->name('departamentos.update');
    Route::delete('/departamentos/{departamento}', [HomeController::class, 'destroyDepartamento'])->name('departamentos.destroy');

    Route::get('/tickets', [HomeController::class, 'tickets'])->name('tickets.index');
    Route::post('/tickets', [HomeController::class, 'storeTicket'])->name('tickets.store');
    Route::get('/tickets/{ticket}/edit', [HomeController::class, 'editTicket'])->name('tickets.edit');
    Route::put('/tickets/{ticket}', [HomeController::class, 'updateTicket'])->name('tickets.update');
    Route::delete('/tickets/{ticket}', [HomeController::class, 'destroyTicket'])->name('tickets.destroy');

    Route::view('/dashboard-livewire', 'dashboard')->name('dashboard.livewire');
});
