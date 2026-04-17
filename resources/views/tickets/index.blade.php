@extends('layouts.app')

@section('title', 'Tickets')
@section('header', 'Lista de tickets')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item active">Tickets</li>
@endsection

@section('content')
@if(auth()->user()->can('crear tickets'))
<div class="card mb-3">
    <div class="card-body">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createTicketModal">
            <i class="fas fa-plus me-1"></i> Agregar nuevo ticket
        </button>
    </div>
</div>
@endif

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('tickets.index') }}" class="row g-2 align-items-end js-table-filters">
            <div class="col-md-8">
                <label class="form-label mb-1">Buscar</label>
                <input type="text" name="q" class="form-control" value="{{ $searchQuery ?? '' }}" placeholder="Ejemplo: red, TCK-0001, pendiente...">
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1">Registros</label>
                <select name="per_page" class="form-select">
                    @foreach([10, 15] as $size)
                        <option value="{{ $size }}" @selected(($perPage ?? 10) == $size)>{{ $size }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <a href="{{ route('tickets.index') }}" class="btn btn-outline-secondary w-100">Limpiar</a>
            </div>
        </form>
    </div>
</div>

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
                        $isAdmin = auth()->user()->hasRole('Administrador');
                        $activeRemoteIds = collect($activeRemoteTicketIds ?? []);
                        $pendingRemoteIds = collect($pendingRemoteTicketIds ?? []);
                        $isDisabled = $ticket->trashed();
                        $badgeType = $isDisabled ? 'secondary' : ($stateMap[$ticket->estado]['badge'] ?? 'secondary');
                        $stateLabel = $isDisabled ? 'Deshabilitado' : str_replace('_', ' ', $ticket->estado);
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

                            @can('atender tickets')
                                @if(!$isDisabled && $ticket->estado === 'pendiente')
                                    <form class="d-inline mb-0" method="POST" action="{{ route('tickets.attend', $ticket) }}" onsubmit="return confirm('Estas seguro de que quieres atender este ticket? El estado cambiara a \"En proceso\" y se asignara a ti.');">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn btn-info btn-sm">Atender</button>
                                    </form>
                                @endif
                            @endcan

                            @if(auth()->user()->hasRole('Administrador') && !$isDisabled)
                                <a href="{{ route('tickets.edit', $ticket) }}" class="btn btn-warning btn-sm">Editar</a>
                            @endif

                            @if(auth()->user()->hasRole('Administrador'))
                                <form class="d-inline mb-0 ms-auto" method="POST" action="{{ route('tickets.checkpoint', $ticket->id) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="checkpoint-switch {{ $isDisabled ? 'is-off' : 'is-on' }}" title="{{ $isDisabled ? 'Deshabilitado' : 'Habilitado' }}">
                                        <span class="checkpoint-switch__label">{{ $isDisabled ? 'OFF' : 'ON' }}</span>
                                        <span class="checkpoint-switch__knob"></span>
                                    </button>
                                </form>
                            @elseif(
                                !$isDisabled
                                && (
                                    (auth()->user()->hasRole('Empleado') && (int) $ticket->empleado_id === (int) ($currentEmployeeId ?? 0))
                                    || (
                                        auth()->user()->hasAnyRole(['Cliente', 'Usuario'])
                                        && (
                                            (int) ($ticket->cliente->id ?? 0) === (int) auth()->id()
                                            || (($ticket->cliente->email ?? null) === auth()->user()->email)
                                        )
                                    )
                                )
                            )
                                <form class="d-inline" method="POST" action="{{ route('tickets.destroy', $ticket) }}">
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

@if(auth()->user()->can('crear tickets'))
<div class="modal fade" id="createTicketModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('tickets.store') }}" id="createTicketForm">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Nuevo ticket</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Codigo</label>
                            <input type="text" name="codigo" id="codigoTicket" class="form-control" value="{{ old('codigo', $nextTicketCode) }}" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Departamento</label>
                            <select name="departamento_id" class="form-select" required>
                                <option value="">Departamento</option>
                                @foreach($departamentosActivos as $departamento)
                                    <option value="{{ $departamento->id }}">{{ $departamento->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Asunto</label>
                            <input type="text" name="asunto" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Descripcion</label>
                            <textarea name="descripcion" class="form-control" rows="3" required></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" @disabled($departamentosActivos->isEmpty())>Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const createTicketModal = document.getElementById('createTicketModal');

        if (createTicketModal) {
            createTicketModal.addEventListener('show.bs.modal', function () {
                fetch("{{ route('tickets.next-code') }}")
                    .then(function (response) {
                        return response.json();
                    })
                    .then(function (data) {
                        const codeInput = createTicketModal.querySelector('input[name="codigo"]');
                        if (codeInput && data && data.codigo) {
                            codeInput.value = data.codigo;
                        }
                    })
                    .catch(function (error) {
                        console.error('No se pudo obtener el siguiente codigo de ticket:', error);
                    });
            });
        }

        const refreshTableResults = function () {
            if (document.querySelector('.modal.show')) {
                return;
            }

            const tableContainer = document.querySelector('.js-table-results');
            if (!tableContainer) {
                return;
            }

            const queryString = window.location.search || '';
            const url = "{{ route('tickets.index') }}" + queryString;

            fetch(url, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(function (response) {
                    return response.text();
                })
                .then(function (html) {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const freshTable = doc.querySelector('.js-table-results');

                    if (!freshTable) {
                        return;
                    }

                    tableContainer.innerHTML = freshTable.innerHTML;
                })
                .catch(function (error) {
                    console.error('No se pudo actualizar la tabla de tickets:', error);
                });
        };

        const searchParams = new URLSearchParams(window.location.search || '');
        const hasActiveSearch = (searchParams.get('q') || '').trim() !== '';

        if (!hasActiveSearch) {
            setInterval(function () {
                if (!document.hidden) {
                    refreshTableResults();
                }
            }, 15000);
        }

        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) {
                refreshTableResults();
            }
        });

        document.addEventListener('hidden.bs.modal', function () {
            if (!document.hidden) {
                refreshTableResults();
            }
        });
    });
</script>
@endpush

@endsection
