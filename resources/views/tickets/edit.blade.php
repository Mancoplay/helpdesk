@extends('layouts.app')

@section('title', 'Editar Ticket')
@section('header', 'Ticket')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item"><a href="{{ route('tickets.index') }}">Tickets</a></li>
    <li class="breadcrumb-item active">Editar</li>
@endsection

@section('content')
@php
    $isAdmin = auth()->user()->hasRole('Administrador');
@endphp
<div class="row justify-content-center">
    <div class="col-12 col-xl-10 col-xxl-9">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <h3 class="card-title mb-0">Editar ticket</h3>
                    </div>
                    <span class="badge text-bg-light border">#{{ old('codigo', $ticket->codigo) }}</span>
                </div>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="{{ route('tickets.update', $ticket) }}" class="row g-4">
                    @csrf
                    @method('PUT')

                    <div class="col-12">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Codigo</label>
                                <input type="text" name="codigo" class="form-control" value="{{ old('codigo', $ticket->codigo) }}" readonly>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Estado</label>
                                @if($isAdmin)
                                    <select name="estado" class="form-select" required>
                                        <option value="pendiente" @selected(old('estado', $ticket->estado) == 'pendiente')>Pendiente</option>
                                        <option value="en_proceso" @selected(old('estado', $ticket->estado) == 'en_proceso')>En proceso</option>
                                        <option value="finalizado" @selected(old('estado', $ticket->estado) == 'finalizado')>Finalizado</option>
                                        <option value="cerrado" @selected(old('estado', $ticket->estado) == 'cerrado')>Cerrado</option>
                                    </select>
                                @else
                                    <input type="text" class="form-control" value="{{ str_replace('_', ' ', old('estado', $ticket->estado)) }}" readonly>
                                @endif
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Departamento</label>
                                @php
                                    $selectedDepartment = $departamentos->firstWhere('id', old('departamento_id', $ticket->departamento_id));
                                @endphp
                                @if($isAdmin)
                                    <input type="hidden" name="departamento_id" value="{{ old('departamento_id', $ticket->departamento_id) }}">
                                @endif
                                <input type="text" class="form-control" value="{{ $selectedDepartment?->nombre ?? ($ticket->departamento->nombre ?? '-') }}" readonly>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="border rounded-3 p-3 p-md-4 bg-light-subtle">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Usuario</label>
                                    @php
                                        $selectedClient = $clientes->firstWhere('id', old('cliente_id', $ticket->cliente_id));
                                    @endphp
                                    @if($isAdmin)
                                        <input type="hidden" name="cliente_id" value="{{ old('cliente_id', $ticket->cliente_id) }}">
                                    @endif
                                    <input type="text" class="form-control" value="{{ $selectedClient?->nombre_completo ?? ($ticket->cliente->nombre_completo ?? '-') }}" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Empleado</label>
                                    @if($isAdmin)
                                        <select name="empleado_id" class="form-select">
                                            <option value="">Sin asignar</option>
                                            @foreach($empleados as $empleado)
                                                <option value="{{ $empleado->id }}" @selected(old('empleado_id', $ticket->empleado_id) == $empleado->id)>{{ $empleado->nombre_completo }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        @php
                                            $selectedEmployee = $empleados->firstWhere('id', old('empleado_id', $ticket->empleado_id));
                                        @endphp
                                        <input type="text" class="form-control" value="{{ $selectedEmployee?->nombre_completo ?? ($ticket->empleado->nombre_completo ?? 'Sin asignar') }}" readonly>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Asunto</label>
                                <input
                                    type="text"
                                    name="asunto"
                                    class="form-control"
                                    value="{{ old('asunto', $ticket->asunto) }}"
                                    minlength="3"
                                    required
                                    oninvalid="this.setCustomValidity('Debe ingresar minimo 3 caracteres.')"
                                    oninput="this.setCustomValidity('')"
                                >
                            </div>
                            <div class="col-12">
                                <label class="form-label">Descripcion</label>
                                <textarea
                                    name="descripcion"
                                    class="form-control"
                                    rows="4"
                                    minlength="3"
                                    required
                                    oninvalid="this.setCustomValidity('Debe ingresar minimo 3 caracteres.')"
                                    oninput="this.setCustomValidity('')"
                                >{{ old('descripcion', $ticket->descripcion) }}</textarea>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="d-flex flex-wrap justify-content-end gap-2 pt-2">
                            <a href="{{ route('tickets.index') }}" class="btn btn-secondary px-4">Cancelar</a>
                            <button type="submit" class="btn btn-primary px-4">Guardar cambios</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
