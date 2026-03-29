<?php

use App\Models\Departamento;
use App\Models\Ticket;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Departamentos - Help Desk')] class extends Component
{
    public function render()
    {
        $search = trim((string) request()->get('q', request()->get('search', '')));
        $perPage = (int) request()->get('per_page', 10);
        if (!in_array($perPage, [10, 15], true)) {
            $perPage = 10;
        }

        $query = Departamento::latest();

        if ($search !== '') {
            $query->where('nombre', 'like', '%' . $search . '%')
                ->orWhere('descripcion', 'like', '%' . $search . '%');
        }

        return view('departamentos.index', [
            'departamentos' => $query->paginate($perPage)->withQueryString(),
            'searchQuery' => $search,
            'perPage' => $perPage,
            'menuBadges' => ['pendientes' => Ticket::where('estado', 'pendiente')->count()],
        ]);
    }
};
