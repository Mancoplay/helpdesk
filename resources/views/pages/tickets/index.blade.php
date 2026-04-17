<?php

use App\Models\Cliente;
use App\Models\Departamento;
use App\Models\Empleado;
use App\Models\Ticket;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Tickets - Help Desk')] class extends Component
{
    public function render()
    {
        $search = trim((string) request()->get('q', request()->get('search', '')));
        $perPage = (int) request()->get('per_page', 10);
        if (!in_array($perPage, [10, 15], true)) {
            $perPage = 10;
        }

        $query = Ticket::query();

        if (auth()->check() && auth()->user()->hasRole('Usuario')) {
            $query->whereHas('cliente', function ($q): void {
                $q->whereKey(auth()->id())
                    ->orWhere('email', auth()->user()->email);
            });
        }

        if (auth()->check() && auth()->user()->hasRole('Empleado')) {
            $employee = Empleado::with('departamentos')->whereKey(auth()->id())
                ->orWhere('email', auth()->user()->email)
                ->first();

            if ($employee) {
                $departmentIds = $employee->departamentos->pluck('id')->toArray();
                $query->where(function ($q) use ($employee, $departmentIds): void {
                    $q->where('empleado_id', $employee->id)
                        ->orWhere(function ($q2) use ($departmentIds): void {
                            $q2->whereNull('empleado_id')
                                ->whereIn('departamento_id', $departmentIds)
                                ->where('estado', 'pendiente');
                        });
                });
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('codigo', 'like', '%' . $search . '%')
                    ->orWhere('asunto', 'like', '%' . $search . '%')
                    ->orWhere('descripcion', 'like', '%' . $search . '%')
                    ->orWhereHas('cliente', function ($q2) use ($search) {
                        $q2->where('nombres', 'like', '%' . $search . '%')
                            ->orWhere('apellidos', 'like', '%' . $search . '%')
                            ->orWhere('email', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('empleado', function ($q2) use ($search) {
                        $q2->where('nombres', 'like', '%' . $search . '%')
                            ->orWhere('apellidos', 'like', '%' . $search . '%')
                            ->orWhere('email', 'like', '%' . $search . '%');
                    });
            });
        }

        $tickets = $query->with(['cliente', 'empleado', 'departamento'])
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        $currentEmployee = null;
        if (auth()->user()->hasRole('Empleado')) {
            $currentEmployee = Empleado::whereKey(auth()->id())
                ->orWhere('email', auth()->user()->email)
                ->first();
        }

        return view('tickets.index', [
            'tickets' => $tickets,
            'clientes' => Cliente::orderBy('nombres')->orderBy('apellidos')->get(),
            'empleados' => Empleado::orderBy('nombres')->orderBy('apellidos')->get(),
            'departamentos' => Departamento::orderBy('nombre')->get(),
            'departamentosActivos' => Departamento::where('activo', true)->orderBy('nombre')->get(),
            'currentEmployeeId' => $currentEmployee?->id,
            'nextTicketCode' => 'TCK-' . str_pad((string) ((int) Ticket::max('id') + 1), 4, '0', STR_PAD_LEFT),
            'searchQuery' => $search,
            'perPage' => $perPage,
            'activeRemoteTicketId' => null,
            'pendingRemoteTicketId' => null,
            'menuBadges' => ['pendientes' => Ticket::where('estado', 'pendiente')->count()],
        ]);
    }
};
