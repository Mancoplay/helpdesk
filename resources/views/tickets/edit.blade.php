@extends('layouts.app')

@section('title', 'Editar Ticket')
@section('header', 'Ticket')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item"><a href="{{ route('tickets.index') }}">Tickets</a></li>
    <li class="breadcrumb-item active">Editar</li>
@endsection

@section('content')
<div class="card">
    <div class="card-header"><h3 class="card-title mb-0">Editar ticket</h3></div>
    <div class="card-body">
        <form method="POST" action="{{ route('tickets.update', $ticket) }}" class="row g-3">
            @csrf
            @method('PUT')
            <div class="col-md-3">
                <label class="form-label">Codigo</label>
                <input type="text" name="codigo" class="form-control" value="{{ old('codigo', $ticket->codigo) }}" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Cliente</label>
                <select name="cliente_id" class="form-select" required>
                    @foreach($clientes as $cliente)
                        <option value="{{ $cliente->id }}" @selected(old('cliente_id', $ticket->cliente_id) == $cliente->id)>{{ $cliente->nombre_completo }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Empleado</label>
                <select name="empleado_id" class="form-select">
                    <option value="">Sin asignar</option>
                    @foreach($empleados as $empleado)
                        <option value="{{ $empleado->id }}" @selected(old('empleado_id', $ticket->empleado_id) == $empleado->id)>{{ $empleado->nombre_completo }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Departamento</label>
                <select name="departamento_id" class="form-select" required>
                    @foreach($departamentos as $departamento)
                        <option value="{{ $departamento->id }}" @selected(old('departamento_id', $ticket->departamento_id) == $departamento->id)>{{ $departamento->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Asunto</label>
                <input type="text" name="asunto" class="form-control" value="{{ old('asunto', $ticket->asunto) }}" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Descripcion</label>
                <input type="text" name="descripcion" class="form-control" value="{{ old('descripcion', $ticket->descripcion) }}" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Estado</label>
                <select name="estado" class="form-select" required>
                    <option value="pendiente" @selected(old('estado', $ticket->estado) == 'pendiente')>Pendiente</option>
                    <option value="en_proceso" @selected(old('estado', $ticket->estado) == 'en_proceso')>En proceso</option>
                    <option value="finalizado" @selected(old('estado', $ticket->estado) == 'finalizado')>Finalizado</option>
                    <option value="cerrado" @selected(old('estado', $ticket->estado) == 'cerrado')>Cerrado</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Prioridad</label>
                <select name="prioridad" class="form-select" required>
                    <option value="baja" @selected(old('prioridad', $ticket->prioridad) == 'baja')>Baja</option>
                    <option value="media" @selected(old('prioridad', $ticket->prioridad) == 'media')>Media</option>
                    <option value="alta" @selected(old('prioridad', $ticket->prioridad) == 'alta')>Alta</option>
                </select>
            </div>
            <div class="col-12 text-end">
                <a href="{{ route('tickets.index') }}" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">Guardar cambios</button>
            </div>
        </form>
    </div>
</div>
@endsection
