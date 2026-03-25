@extends('layouts.app')

@section('title', 'Revision de cliente')
@section('header', 'Revision de cliente')
@section('show_back_button', '1')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item"><a href="{{ route('clientes.index') }}">Clientes</a></li>
    <li class="breadcrumb-item active">{{ $cliente->nombre_completo }}</li>
@endsection

@section('content')
<div class="card mb-3">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4"><strong>Cliente:</strong> {{ $cliente->nombre_completo }}</div>
            <div class="col-md-4"><strong>Correo:</strong> {{ $cliente->email }}</div>
            <div class="col-md-4"><strong>Empresa:</strong> {{ $cliente->empresa ?? '-' }}</div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('clientes.review', $cliente) }}" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label mb-1">Periodo</label>
                <select name="period" class="form-select" onchange="this.form.submit()">
                    <option value="week" @selected($period === 'week')>Semana actual</option>
                    <option value="month" @selected($period === 'month')>Mes actual</option>
                    <option value="year" @selected($period === 'year')>Ano actual</option>
                    <option value="custom" @selected($period === 'custom')>Rango personalizado</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label mb-1">Desde</label>
                <input type="date" name="from" class="form-control" value="{{ $fromInput }}">
            </div>
            <div class="col-md-3">
                <label class="form-label mb-1">Hasta</label>
                <input type="date" name="to" class="form-control" value="{{ $toInput }}">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100">Filtrar</button>
                <a href="{{ route('clientes.review', $cliente) }}" class="btn btn-outline-secondary">Limpiar</a>
            </div>
        </form>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-3"><div class="card dashboard-stat h-100"><div class="card-body"><div class="label">Tickets del cliente</div><p class="value">{{ $summary['total_tickets'] }}</p></div></div></div>
    <div class="col-md-3"><div class="card dashboard-stat h-100"><div class="card-body"><div class="label">Empleados que atendieron</div><p class="value">{{ $summary['empleados_distintos'] }}</p></div></div></div>
    <div class="col-md-3"><div class="card dashboard-stat h-100"><div class="card-body"><div class="label">Tickeds finalizados</div><p class="value">{{ $summary['tickets_cerrados'] }}</p></div></div></div>
    <div class="col-md-3"><div class="card dashboard-stat h-100"><div class="card-body"><div class="label">Tickets eliminados</div><p class="value">{{ $summary['tickets_eliminados'] }}</p></div></div></div>
</div>

<div class="card">
    <div class="card-header"><h3 class="card-title mb-0">Historial de tickets (incluye eliminados)</h3></div>
    <div class="card-body table-responsive p-0">
        <table class="table table-striped table-hover mb-0">
            <thead>
                <tr>
                    <th>Codigo</th>
                    <th>Asunto</th>
                    <th>Empleado</th>
                    <th>Departamento</th>
                    <th>Estado</th>
                    <th>Creado</th>
                    <th>Cierre</th>
                    <th>Eliminado</th>
                </tr>
            </thead>
            <tbody>
                @forelse($tickets as $ticket)
                    <tr>
                                                <td>
                            @if($ticket->trashed())
                                {{ $ticket->codigo }}
                            @else
                                <a href="{{ route('tickets.show', $ticket->id) }}">{{ $ticket->codigo }}</a>
                            @endif
                        </td>
                        <td>{{ $ticket->asunto }}</td>
                        <td>{{ $ticket->empleado->nombre_completo ?? 'Sin asignar' }}</td>
                        <td>{{ $ticket->departamento->nombre ?? '-' }}</td>
                        <td>{{ str_replace('_', ' ', $ticket->estado) }}</td>
                        <td>{{ $ticket->created_at?->format('d/m/Y H:i') }}</td>
                        <td>{{ $ticket->fecha_cierre?->format('d/m/Y H:i') ?? '-' }}</td>
                        <td>
                            @if($ticket->trashed())
                                <span class="badge text-bg-danger">Si</span>
                            @else
                                <span class="badge text-bg-success">No</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted">Sin registros para el filtro aplicado.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer compact-pagination">
        {{ $tickets->links() }}
    </div>
</div>
@endsection
