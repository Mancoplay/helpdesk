<?php

use App\Models\Departamento;
use App\Models\Ticket;
use App\Models\TicketRemoteSession;
use App\Models\Empleado;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Ticket Detalle - Help Desk')] class extends Component
{
    public function render()
    {
        $ticket = request()->route('ticket');
        if (!$ticket) {
            abort(404);
        }

        if (!$this->canAccessTicket($ticket)) {
            abort(403);
        }

        $ticket->load(['cliente', 'empleado', 'departamento']);
        $messages = $ticket->mensajes()
            ->with('user')
            ->latest()
            ->limit(100)
            ->get()
            ->sortBy('created_at')
            ->values();

        $remoteEnabled = Schema::hasTable('ticket_remote_sessions');
        $remoteSession = $remoteEnabled
            ? $ticket->remoteSessions()->latest('id')->first()
            : null;

        return view('tickets.show', [
            'ticket' => $ticket,
            'messages' => $messages,
            'remoteEnabled' => $remoteEnabled,
            'remoteSession' => $remoteSession,
            'departamentos' => Departamento::orderBy('nombre')->get(),
            'menuBadges' => ['pendientes' => Ticket::where('estado', 'pendiente')->count()],
        ]);
    }

    private function canAccessTicket(Ticket $ticket): bool
    {
        if (auth()->user()->hasRole('Administrador')) {
            return true;
        }

        $query = Ticket::query();

        if (auth()->check() && auth()->user()->hasRole('Usuario')) {
            $query->whereHas('cliente', function ($q): void {
                $q->where('email', auth()->user()->email);
            });
        }

        if (auth()->check() && auth()->user()->hasRole('Empleado')) {
            $employee = Empleado::with('departamentos')->where('user_id', auth()->id())
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
                return false;
            }
        }

        return $query->whereKey($ticket->id)->exists();
    }
};
