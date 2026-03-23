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

<div class="card">
    <div class="card-header"><h3 class="card-title mb-0">Tabla de Tickets</h3></div>
    <div class="card-body table-responsive p-0">
        <table class="table table-striped table-hover mb-0">
            <thead>
                <tr>
                    <th>Codigo</th>
                    <th>Asunto</th>
                    <th>Cliente</th>
                    <th>Empleado</th>
                    <th>Estado</th>
                    <th style="width: 320px;">Accion</th>
                </tr>
            </thead>
            <tbody>
            @forelse($tickets as $ticket)
                @php
                    $stateMap = config('adminlte.ticket_states');
                    $badgeType = $stateMap[$ticket->estado]['badge'] ?? 'secondary';
                @endphp
                <tr>
                    <td>{{ $ticket->codigo }}</td>
                    <td>{{ $ticket->asunto }}</td>
                    <td>{{ $ticket->cliente->nombre_completo ?? '-' }}</td>
                    <td>{{ $ticket->empleado->nombre_completo ?? 'Sin asignar' }}</td>
                    <td><span class="badge text-bg-{{ $badgeType }}">{{ str_replace('_', ' ', $ticket->estado) }}</span></td>
                    <td>
                        <a href="{{ route('tickets.show', $ticket) }}" class="btn btn-secondary btn-sm me-1">Ver</a>

                        @can('atender tickets')
                            @if($ticket->estado === 'pendiente')
                                <a href="{{ route('tickets.show', $ticket) }}" class="btn btn-info btn-sm me-1">Atender</a>
                            @endif
                        @endcan

                        @if(auth()->user()->hasRole('Administrador'))
                            <button class="btn btn-warning btn-sm me-1" data-bs-toggle="modal" data-bs-target="#editTicketModal{{ $ticket->id }}">Editar</button>
                        @endif

                        @if(
                            auth()->user()->hasRole('Administrador')
                            || (auth()->user()->hasRole('Empleado') && (int) $ticket->empleado_id === (int) ($currentEmployeeId ?? 0))
                            || (auth()->user()->hasRole('Usuario') && (($ticket->cliente->email ?? null) === auth()->user()->email))
                        )
                            <form class="d-inline" method="POST" action="{{ route('tickets.destroy', $ticket) }}" onsubmit="return confirm('Deseas eliminar este ticket?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
                            </form>
                        @endif
                    </td>
                </tr>

                @if(auth()->user()->hasRole('Administrador'))
                <div class="modal fade" id="editTicketModal{{ $ticket->id }}" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-centered"><div class="modal-content"><form method="POST" action="{{ route('tickets.update', $ticket) }}">@csrf @method('PUT')<div class="modal-header"><h5 class="modal-title">Editar ticket</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="row g-2"><div class="col-md-3"><label class="form-label">Codigo</label><input type="text" name="codigo" class="form-control" value="{{ $ticket->codigo }}" required></div><div class="col-md-3"><label class="form-label">Cliente</label><select name="cliente_id" class="form-select" required>@foreach($clientes as $cliente)<option value="{{ $cliente->id }}" @selected($ticket->cliente_id == $cliente->id)>{{ $cliente->nombre_completo }}</option>@endforeach</select></div><div class="col-md-3"><label class="form-label">Empleado</label><select name="empleado_id" class="form-select"><option value="">Sin asignar</option>@foreach($empleados as $empleado)<option value="{{ $empleado->id }}" @selected($ticket->empleado_id == $empleado->id)>{{ $empleado->nombre_completo }}</option>@endforeach</select></div><div class="col-md-3"><label class="form-label">Departamento</label><select name="departamento_id" class="form-select" required>@foreach($departamentos as $departamento)<option value="{{ $departamento->id }}" @selected($ticket->departamento_id == $departamento->id)>{{ $departamento->nombre }}</option>@endforeach</select></div><div class="col-md-4"><label class="form-label">Asunto</label><input type="text" name="asunto" class="form-control" value="{{ $ticket->asunto }}" required></div><div class="col-md-4"><label class="form-label">Descripcion</label><input type="text" name="descripcion" class="form-control" value="{{ $ticket->descripcion }}" required></div><div class="col-md-2"><label class="form-label">Estado</label><select name="estado" class="form-select" required><option value="pendiente" @selected($ticket->estado=='pendiente')>Pendiente</option><option value="en_proceso" @selected($ticket->estado=='en_proceso')>En proceso</option><option value="finalizado" @selected($ticket->estado=='finalizado')>Finalizado</option><option value="cerrado" @selected($ticket->estado=='cerrado')>Cerrado</option></select></div><div class="col-md-2"><label class="form-label">Prioridad</label><select name="prioridad" class="form-select" required><option value="baja" @selected($ticket->prioridad=='baja')>Baja</option><option value="media" @selected($ticket->prioridad=='media')>Media</option><option value="alta" @selected($ticket->prioridad=='alta')>Alta</option></select></div></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Guardar</button></div></form></div></div></div>
                @endif
            @empty
                <tr><td colspan="6" class="text-center text-muted">Sin datos</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@if(auth()->user()->can('crear tickets'))
<div class="modal fade" id="createTicketModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-centered"><div class="modal-content"><form method="POST" action="{{ route('tickets.store') }}">@csrf<div class="modal-header"><h5 class="modal-title">Nuevo ticket</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="row g-2"><div class="col-md-3"><label class="form-label">Codigo</label><input type="text" name="codigo" class="form-control" placeholder="Opcional"></div><div class="col-md-3"><label class="form-label">Departamento</label><select name="departamento_id" class="form-select" required><option value="">Departamento</option>@foreach($departamentosActivos as $departamento)<option value="{{ $departamento->id }}">{{ $departamento->nombre }}</option>@endforeach</select></div><div class="col-md-4"><label class="form-label">Asunto</label><input type="text" name="asunto" class="form-control" required></div><div class="col-md-4"><label class="form-label">Descripcion</label><input type="text" name="descripcion" class="form-control" required></div><div class="col-md-2"><label class="form-label">Prioridad</label><select name="prioridad" class="form-select" required><option value="baja">Baja</option><option value="media" selected>Media</option><option value="alta">Alta</option></select></div></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary" @disabled($departamentosActivos->isEmpty())>Guardar</button></div></form></div></div></div>
@endif
@endsection
