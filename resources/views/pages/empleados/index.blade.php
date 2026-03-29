<?php

use App\Models\Departamento;
use App\Models\Empleado;
use App\Models\Ticket;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Empleados - Help Desk')] class extends Component
{
    public function render()
    {
        $search = trim((string) request()->get('q', request()->get('search', '')));
        $perPage = (int) request()->get('per_page', 10);
        if (!in_array($perPage, [10, 15], true)) {
            $perPage = 10;
        }

        $query = Empleado::with(['departamento', 'departamentos'])->latest();

        if ($search !== '') {
            $query->where('nombres', 'like', '%' . $search . '%')
                ->orWhere('apellidos', 'like', '%' . $search . '%')
                ->orWhere('email', 'like', '%' . $search . '%')
                ->orWhere('telefono', 'like', '%' . $search . '%')
                ->orWhere('cargo', 'like', '%' . $search . '%');
        }

        return view('empleados.index', [
            'empleados' => $query->paginate($perPage)->withQueryString(),
            'departamentos' => Departamento::orderBy('nombre')->get(),
            'searchQuery' => $search,
            'perPage' => $perPage,
            'menuBadges' => ['pendientes' => Ticket::where('estado', 'pendiente')->count()],
        ]);
    }
};
