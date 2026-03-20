<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Auth::routes();

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [HomeController::class, 'index'])->name('dashboard');
    Route::get('/clientes', [HomeController::class, 'clientes'])->name('clientes.index');
    Route::get('/empleados', [HomeController::class, 'empleados'])->name('empleados.index');
    Route::get('/departamentos', [HomeController::class, 'departamentos'])->name('departamentos.index');
    Route::get('/tickets', [HomeController::class, 'tickets'])->name('tickets.index');

    Route::view('/dashboard-livewire', 'dashboard')->name('dashboard.livewire');
});
