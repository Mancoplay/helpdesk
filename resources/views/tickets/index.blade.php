@extends('layouts.app')

@section('title', 'Tickets')
@section('header', 'Lista de tickets')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item active">Tickets</li>
@endsection

@section('content')
<div class="card mb-3">
    <div class="card-body">
        <button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#nuevoTicket">
            <i class="fas fa-plus me-1"></i> Agregar nuevo ticket
        </button>

        <div class="collapse mt-3" id="nuevoTicket">
            <form method="POST" action="{{ route('tickets.store') }}" class="row g-2">
                @csrf
                <div class="col-md-3"><input type="text" name="codigo" class="form-control" placeholder="Codigo (opcional)"></div>
                <div class="col-md-3">
                    <select name="cliente_id" class="form-select" required>
                        <option value="">Cliente</option>
                        @foreach($clientes as $cliente)
                            <option value="{{ $cliente->id }}">{{ $cliente->nombre_completo }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="empleado_id" class="form-select">
                        <option value="">Empleado (opcional)</option>
                        @foreach($empleados as $empleado)
                            <option value="{{ $empleado->id }}">{{ $empleado->nombre_completo }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="departamento_id" class="form-select" required>
                        <option value="">Departamento</option>
                        @foreach($departamentos as $departamento)
                            <option value="{{ $departamento->id }}">{{ $departamento->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4"><input type="text" name="asunto" class="form-control" placeholder="Asunto" required></div>
                <div class="col-md-4"><input type="text" name="descripcion" class="form-control" placeholder="Descripcion" required></div>
                <div class="col-md-2">
                    <select name="estado" class="form-select" required>
                        <option value="pendiente">Pendiente</option>
                        <option value="en_proceso">En proceso</option>
                        <option value="finalizado">Finalizado</option>
                        <option value="cerrado">Cerrado</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="prioridad" class="form-select" required>
                        <option value="baja">Baja</option>
                        <option value="media" selected>Media</option>
                        <option value="alta">Alta</option>
                    </select>
                </div>
                <div class="col-md-12 text-end"><button type="submit" class="btn btn-success">Guardar</button></div>
            </form>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3 class="card-title mb-0">Tabla de Tickets</h3></div>
    <div class="card-body table-responsive p-0">
        <table class="table table-striped table-hover mb-0">
            <thead>
                <tr>
                    <th>Codigo</th>
                    <th>Asunto</th>
                    <th>Cliente</th>
                    <th>Estado</th>
                    <th style="width:130px;">Accion</th>
                </tr>
            </thead>
            <tbody>
                @forelse($tickets as $ticket)
                    <tr>
                        <td>{{ $ticket->codigo }}</td>
                        <td>{{ $ticket->asunto }}</td>
                        <td>{{ $ticket->cliente->nombre_completo ?? '-' }}</td>
                        <td>
                            @php
                                $stateMap = config('adminlte.ticket_states');
                                $badgeType = $stateMap[$ticket->estado]['badge'] ?? 'secondary';
                            @endphp
                            <span class="badge text-bg-{{ $badgeType }}">{{ str_replace('_', ' ', $ticket->estado) }}</span>
                        </td>
                        <td>
                            <form method="POST" action="{{ route('tickets.destroy', $ticket) }}" onsubmit="return confirm('Deseas eliminar este ticket?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted">Sin datos</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
