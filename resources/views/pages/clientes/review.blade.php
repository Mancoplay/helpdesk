<?php

use App\Models\Ticket;
use Carbon\Carbon;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Revisar Cliente - Help Desk')] class extends Component
{
    public function render()
    {
        $cliente = request()->route('cliente');
        if (!$cliente) {
            abort(404);
        }

        $period = (string) request()->get('period', 'month');
        $allowedPeriods = ['week', 'month', 'previous_month', 'year', 'custom'];
        if (!in_array($period, $allowedPeriods, true)) {
            $period = 'month';
        }

        $fromInput = (string) request()->get('from', '');
        $toInput = (string) request()->get('to', '');
        $now = Carbon::now();

        if ($period === 'week') {
            $fromDate = $now->copy()->startOfWeek();
            $toDate = $now->copy()->endOfWeek();
        } elseif ($period === 'previous_month') {
            $fromDate = $now->copy()->subMonthNoOverflow()->startOfMonth();
            $toDate = $now->copy()->subMonthNoOverflow()->endOfMonth();
        } elseif ($period === 'year') {
            $fromDate = $now->copy()->startOfYear();
            $toDate = $now->copy()->endOfYear();
        } elseif ($period === 'custom') {
            $fromDate = $this->safeParseDate($fromInput) ?? $now->copy()->startOfMonth();
            $toDate = $this->safeParseDate($toInput) ?? $now->copy()->endOfMonth();
            if ($fromDate->gt($toDate)) {
                [$fromDate, $toDate] = [$toDate, $fromDate];
            }
        } else {
            $fromDate = $now->copy()->startOfMonth();
            $toDate = $now->copy()->endOfMonth();
            $period = 'month';
        }

        if ($period !== 'custom') {
            $fromInput = $fromDate->toDateString();
            $toInput = $toDate->toDateString();
        }

        $perPage = (int) request()->get('per_page', 10);
        if (!in_array($perPage, [10, 15], true)) {
            $perPage = 10;
        }

        $baseQuery = Ticket::withTrashed()
            ->with(['empleado', 'departamento'])
            ->where('cliente_id', $cliente->id)
            ->whereBetween('created_at', [$fromDate->copy()->startOfDay(), $toDate->copy()->endOfDay()]);

        $tickets = (clone $baseQuery)
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        $summary = [
            'total_tickets' => (clone $baseQuery)->count(),
            'empleados_distintos' => (clone $baseQuery)
                ->whereNotNull('empleado_id')
                ->distinct('empleado_id')
                ->count('empleado_id'),
            'tickets_cerrados' => (clone $baseQuery)->where('estado', 'finalizado')->count(),
            'tickets_eliminados' => (clone $baseQuery)->onlyTrashed()->count(),
        ];

        return view('clientes.review', [
            'cliente' => $cliente,
            'tickets' => $tickets,
            'summary' => $summary,
            'period' => $period,
            'fromInput' => $fromInput,
            'toInput' => $toInput,
            'menuBadges' => ['pendientes' => Ticket::where('estado', 'pendiente')->count()],
        ]);
    }

    private function safeParseDate(string $value): ?Carbon
    {
        if (trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
};
