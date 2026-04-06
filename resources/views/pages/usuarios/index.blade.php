<?php

use App\Models\Cliente;
use App\Models\Ticket;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Usuarios - Help Desk')] class extends Component
{
    public function render()
    {
        $search = trim((string) request()->get('q', request()->get('search', '')));
        $perPage = (int) request()->get('per_page', 10);
        if (!in_array($perPage, [10, 15], true)) {
            $perPage = 10;
        }

        $query = Cliente::latest();

        if ($search !== '') {
            $query->where('nombres', 'like', '%' . $search . '%')
                ->orWhere('apellidos', 'like', '%' . $search . '%')
                ->orWhere('email', 'like', '%' . $search . '%')
                ->orWhere('telefono', 'like', '%' . $search . '%')
                ->orWhere('empresa', 'like', '%' . $search . '%');
        }

        return view('usuarios.index', [
            'clientes' => $query->paginate($perPage)->withQueryString(),
            'searchQuery' => $search,
            'perPage' => $perPage,
            'menuBadges' => ['pendientes' => Ticket::where('estado', 'pendiente')->count()],
        ]);
    }
};
