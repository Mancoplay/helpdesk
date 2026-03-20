<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Departamento;
use App\Models\Empleado;
use App\Models\Ticket;

class HomeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $statusConfig = config('adminlte.ticket_states', []);
        $statusKeys = array_keys($statusConfig);

        $statusCounts = Ticket::query()
            ->selectRaw('estado, COUNT(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado');

        $chartLabels = [];
        $chartValues = [];
        $chartColors = [];

        foreach ($statusKeys as $state) {
            $chartLabels[] = $statusConfig[$state]['label'];
            $chartValues[] = (int) ($statusCounts[$state] ?? 0);
            $chartColors[] = $statusConfig[$state]['color'];
        }

        $stats = [
            'total_clientes' => Cliente::count(),
            'total_empleados' => Empleado::count(),
            'total_departamentos' => Departamento::count(),
            'total_tickets' => Ticket::count(),
            'pendientes' => (int) ($statusCounts['pendiente'] ?? 0),
            'en_proceso' => (int) ($statusCounts['en_proceso'] ?? 0),
            'finalizado' => (int) ($statusCounts['finalizado'] ?? 0),
            'cerrado' => (int) ($statusCounts['cerrado'] ?? 0),
        ];

        return view('home', [
            'stats' => $stats,
            'chartLabels' => $chartLabels,
            'chartValues' => $chartValues,
            'chartColors' => $chartColors,
            'clientes' => Cliente::latest()->limit(5)->get(),
            'empleados' => Empleado::with('departamento')->latest()->limit(5)->get(),
            'departamentos' => Departamento::latest()->limit(5)->get(),
            'tickets' => Ticket::with(['cliente', 'empleado', 'departamento'])->latest()->limit(8)->get(),
            'menuBadges' => ['pendientes' => (int) ($statusCounts['pendiente'] ?? 0)],
        ]);
    }
}
