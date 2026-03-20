@extends('layouts.app')

@section('title', 'Empleados')
@section('header', 'Lista de empleados')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item active">Empleados</li>
@endsection

@section('content')
<div class="card mb-3">
    <div class="card-body">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createEmpleadoModal">
            <i class="fas fa-plus me-1"></i> Agregar nuevo empleado
        </button>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3 class="card-title mb-0">Tabla de Empleados</h3></div>
    <div class="card-body table-responsive p-0">
        <table class="table table-striped table-hover mb-0">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Departamento</th>
                    <th>Contacto</th>
                    <th>Correo</th>
                    <th style="width:220px;">Accion</th>
                </tr>
            </thead>
            <tbody>
                @forelse($empleados as $empleado)
                    <tr>
                        <td>{{ $empleado->nombre_completo }}</td>
                        <td>{{ $empleado->departamento->nombre ?? '-' }}</td>
                        <td>{{ $empleado->telefono ?? '-' }}</td>
                        <td>{{ $empleado->email }}</td>
                        <td>
                            <button class="btn btn-warning btn-sm me-1" data-bs-toggle="modal" data-bs-target="#editEmpleadoModal{{ $empleado->id }}">Editar</button>
                            <form class="d-inline" method="POST" action="{{ route('empleados.destroy', $empleado) }}" onsubmit="return confirm('Deseas eliminar este empleado?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
                            </form>
                        </td>
                    </tr>

                    <div class="modal fade" id="editEmpleadoModal{{ $empleado->id }}" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-xl modal-dialog-centered">
                            <div class="modal-content">
                                <form method="POST" action="{{ route('empleados.update', $empleado) }}">
                                    @csrf
                                    @method('PUT')
                                    <div class="modal-header">
                                        <h5 class="modal-title">Empleado</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <h6 class="fw-bold">Informacion Personal</h6>
                                                <label class="form-label">Nombre</label>
                                                <input type="text" name="nombres" class="form-control" value="{{ $empleado->nombres }}" required>
                                                <label class="form-label mt-2">Segundo Nombre</label>
                                                <input type="text" name="segundo_nombre" class="form-control" value="{{ $empleado->segundo_nombre }}">
                                                <label class="form-label mt-2">Apellido</label>
                                                <input type="text" name="apellidos" class="form-control" value="{{ $empleado->apellidos }}" required>
                                                <label class="form-label mt-2">Contacto</label>
                                                <input type="text" name="telefono" class="form-control" value="{{ $empleado->telefono }}">
                                                <label class="form-label mt-2">Direccion</label>
                                                <textarea name="direccion" class="form-control" rows="3">{{ $empleado->direccion }}</textarea>
                                            </div>
                                            <div class="col-md-6">
                                                <h6 class="fw-bold">Credenciales del Sistema</h6>
                                                <label class="form-label">Departamento</label>
                                                <select name="departamento_id" class="form-select" required>
                                                    @foreach($departamentos as $departamento)
                                                        <option value="{{ $departamento->id }}" @selected($empleado->departamento_id == $departamento->id)>{{ $departamento->nombre }}</option>
                                                    @endforeach
                                                </select>
                                                <label class="form-label mt-2">Cargo</label>
                                                <input type="text" name="cargo" class="form-control" value="{{ $empleado->cargo }}">
                                                <label class="form-label mt-2">Correo</label>
                                                <input type="email" name="email" class="form-control" value="{{ $empleado->email }}" required>
                                                <label class="form-label mt-2">Contrasena (opcional)</label>
                                                <input type="password" name="password" class="form-control" autocomplete="new-password">
                                                <label class="form-label mt-2">Confirmar Contrasena</label>
                                                <input type="password" name="password_confirmation" class="form-control" autocomplete="new-password">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                        <button type="submit" class="btn btn-primary">Guardar cambios</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                @empty
                    <tr><td colspan="5" class="text-center text-muted">Sin datos</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="createEmpleadoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('empleados.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Empleado</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <h6 class="fw-bold">Informacion Personal</h6>
                            <label class="form-label">Nombre</label>
                            <input type="text" name="nombres" class="form-control" required>
                            <label class="form-label mt-2">Segundo Nombre</label>
                            <input type="text" name="segundo_nombre" class="form-control">
                            <label class="form-label mt-2">Apellido</label>
                            <input type="text" name="apellidos" class="form-control" required>
                            <label class="form-label mt-2">Contacto</label>
                            <input type="text" name="telefono" class="form-control">
                            <label class="form-label mt-2">Direccion</label>
                            <textarea name="direccion" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="col-md-6">
                            <h6 class="fw-bold">Credenciales del Sistema</h6>
                            <label class="form-label">Departamento</label>
                            <select name="departamento_id" class="form-select" required>
                                <option value="">Selecciona aqui</option>
                                @foreach($departamentos as $departamento)
                                    <option value="{{ $departamento->id }}">{{ $departamento->nombre }}</option>
                                @endforeach
                            </select>
                            <label class="form-label mt-2">Cargo</label>
                            <input type="text" name="cargo" class="form-control">
                            <label class="form-label mt-2">Correo</label>
                            <input type="email" name="email" class="form-control" required>
                            <label class="form-label mt-2">Contrasena</label>
                            <input type="password" name="password" class="form-control" required autocomplete="new-password">
                            <label class="form-label mt-2">Confirmar Contrasena</label>
                            <input type="password" name="password_confirmation" class="form-control" required autocomplete="new-password">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
