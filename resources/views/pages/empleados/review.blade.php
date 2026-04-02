<?php

use App\Services\ReviewRangeService;
use App\Models\Empleado;
use App\Models\Ticket;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Revisar Empleado - Help Desk')] class extends Component
{
    public function render()
    {
        $empleado = request()->route('empleado');
        if (!$empleado) {
            abort(404);
        }

        [$period, $fromInput, $toInput, $fromDate, $toDate] = app(ReviewRangeService::class)
            ->resolveFromRequest(request());

        $perPage = (int) request()->get('per_page', 10);
        if (!in_array($perPage, [10, 15], true)) {
            $perPage = 10;
        }

        $empleado->loadMissing(['departamentos', 'departamento']);

        $baseQuery = Ticket::withTrashed()
            ->with(['cliente', 'departamento'])
            ->where('empleado_id', $empleado->id)
            ->whereBetween('created_at', [$fromDate->copy()->startOfDay(), $toDate->copy()->endOfDay()]);

        $tickets = (clone $baseQuery)
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        $summary = [
            'total_tickets' => (clone $baseQuery)->count(),
            'clientes_atendidos' => (clone $baseQuery)
                ->whereNotNull('cliente_id')
                ->distinct('cliente_id')
                ->count('cliente_id'),
            'tickets_cerrados' => (clone $baseQuery)->where('estado', 'finalizado')->count(),
            'tickets_eliminados' => (clone $baseQuery)->onlyTrashed()->count(),
        ];

        return view('empleados.review', [
            'empleado' => $empleado,
            'tickets' => $tickets,
            'summary' => $summary,
            'period' => $period,
            'fromInput' => $fromInput,
            'toInput' => $toInput,
            'menuBadges' => ['pendientes' => Ticket::where('estado', 'pendiente')->count()],
        ]);
    }

};
