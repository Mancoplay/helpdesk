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
        $statusCounts = $this->ticketStatusCounts();

        $chartLabels = [];
        $chartValues = [];
        $chartColors = [];

        foreach (array_keys($statusConfig) as $state) {
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
            'menuBadges' => $this->menuBadges($statusCounts),
        ]);
    }

    public function clientes()
    {
        return view('clientes.index', [
            'clientes' => Cliente::latest()->get(),
            'menuBadges' => $this->menuBadges(),
        ]);
    }

    public function empleados()
    {
        return view('empleados.index', [
            'empleados' => Empleado::with('departamento')->latest()->get(),
            'menuBadges' => $this->menuBadges(),
        ]);
    }

    public function departamentos()
    {
        return view('departamentos.index', [
            'departamentos' => Departamento::latest()->get(),
            'menuBadges' => $this->menuBadges(),
        ]);
    }

    public function tickets()
    {
        return view('tickets.index', [
            'tickets' => Ticket::with(['cliente', 'empleado', 'departamento'])->latest()->get(),
            'menuBadges' => $this->menuBadges(),
        ]);
    }

    private function ticketStatusCounts()
    {
        return Ticket::query()
            ->selectRaw('estado, COUNT(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado');
    }

    private function menuBadges($statusCounts = null): array
    {
        $counts = $statusCounts ?? $this->ticketStatusCounts();

        return [
            'pendientes' => (int) ($counts['pendiente'] ?? 0),
        ];
    }
}
