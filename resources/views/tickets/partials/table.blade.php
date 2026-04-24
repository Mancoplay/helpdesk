@php
    $isAdmin = auth()->user()->hasRole('Administrador');
    $activeRemoteIds = collect($activeRemoteTicketIds ?? []);
    $pendingRemoteIds = collect($pendingRemoteTicketIds ?? []);
@endphp

<div class="card js-table-results">
    <div class="card-header"><h3 class="card-title mb-0">Tabla de Tickets</h3></div>
    <div class="card-body table-responsive p-0">
        <table class="table table-striped table-hover mb-0">
            <thead>
                <tr>
                    <th>Codigo</th>
                    <th>Asunto</th>
                    <th>Usuario</th>
                    <th>Empleado</th>
                    <th>Estado</th>
                    <th style="width: 290px;">Accion</th>
                </tr>
            </thead>
            <tbody>
                @forelse($tickets as $ticket)
                    @php
                        $stateMap = config('adminlte.ticket_states');
                        $isDisabled = $ticket->trashed();
                        $badgeType = $isDisabled ? 'secondary' : ($stateMap[$ticket->estado]['badge'] ?? 'secondary');
                        $stateLabel = $isDisabled ? 'Deshabilitado' : str_replace('_', ' ', $ticket->estado);
                        $isEmployeeOwner = auth()->user()->hasRole('Empleado') && (int) $ticket->empleado_id === (int) ($currentEmployeeId ?? 0);
                        $isClientOwner = auth()->user()->hasAnyRole(['Cliente', 'Usuario'])
                            && (
                                (int) ($ticket->cliente->id ?? 0) === (int) auth()->id()
                                || (($ticket->cliente->email ?? null) === auth()->user()->email)
                            );
                        $canManageTicket = auth()->user()->hasRole('Administrador') || $isEmployeeOwner || $isClientOwner;
                        $isRemoteActive = (string) $ticket->estado === 'en_proceso'
                            && (
                                ($isAdmin && $activeRemoteIds->contains((int) $ticket->id))
                                || (!$isAdmin && !empty($activeRemoteTicketId) && (int) $ticket->id === (int) $activeRemoteTicketId)
                            );
                        $isRemotePending = !$isRemoteActive
                            && (string) $ticket->estado === 'en_proceso'
                            && (
                                ($isAdmin && $pendingRemoteIds->contains((int) $ticket->id))
                                || (!$isAdmin && !empty($pendingRemoteTicketId) && (int) $ticket->id === (int) $pendingRemoteTicketId)
                            );
                    @endphp
                    <tr class="{{ $isRemoteActive ? 'ticket-row--remote-active' : ($isRemotePending ? 'ticket-row--remote-pending' : '') }}">
                        <td>{{ $ticket->codigo }}</td>
                        <td>{{ $ticket->asunto }}</td>
                        <td>{{ $ticket->cliente->nombre_completo ?? '-' }}</td>
                        <td>{{ $ticket->empleado->nombre_completo ?? 'Sin asignar' }}</td>
                        <td><span class="badge text-bg-{{ $badgeType }}">{{ $stateLabel }}</span></td>
                        <td class="text-nowrap">
                            <div class="d-flex flex-nowrap align-items-center gap-1">
                            @if(!$isDisabled)
                                <a href="{{ route('tickets.show', $ticket) }}" class="btn btn-secondary btn-sm">Ver</a>
                            @endif

                            @if($canManageTicket && !$isDisabled && $ticket->estado === 'pendiente')
                                <a href="{{ route('tickets.edit', $ticket) }}" class="btn btn-warning btn-sm">Editar</a>
                            @endif

                            @can('atender tickets')
                                @if(!$isDisabled && $ticket->estado === 'pendiente')
                                    <form
                                        class="d-inline mb-0 js-ticket-inline-form"
                                        method="POST"
                                        action="{{ route('tickets.attend', $ticket) }}"
                                        data-success-message="Ticket atendido correctamente."
                                    >
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn btn-info btn-sm">Atender</button>
                                    </form>
                                @endif
                            @endcan

                            @if(auth()->user()->hasRole('Administrador'))
                                <form class="d-inline mb-0 ms-auto" method="POST" action="{{ route('tickets.checkpoint', $ticket->id) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="checkpoint-switch {{ $isDisabled ? 'is-off' : 'is-on' }}" title="{{ $isDisabled ? 'Deshabilitado' : 'Habilitado' }}">
                                        <span class="checkpoint-switch__label">{{ $isDisabled ? 'OFF' : 'ON' }}</span>
                                        <span class="checkpoint-switch__knob"></span>
                                    </button>
                                </form>
                            @elseif(!$isDisabled && $canManageTicket && $ticket->estado === 'finalizado')
                                <form
                                    class="d-inline js-ticket-inline-form"
                                    method="POST"
                                    action="{{ route('tickets.destroy', $ticket) }}"
                                    data-confirm="Estas seguro de que quieres eliminar este ticket?"
                                    data-success-message="Ticket eliminado correctamente."
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
                                </form>
                            @endif
                            </div>
                        </td>
                    </tr>

                @empty
                    <tr><td colspan="6" class="text-center text-muted">Sin datos</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer compact-pagination">
        {{ $tickets->links() }}
    </div>
</div>
