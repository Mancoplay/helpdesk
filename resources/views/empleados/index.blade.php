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
        <button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#nuevoEmpleado">
            <i class="fas fa-plus me-1"></i> Agregar nuevo empleado
        </button>

        <div class="collapse mt-3" id="nuevoEmpleado">
            <form method="POST" action="{{ route('empleados.store') }}" class="row g-2">
                @csrf
                <div class="col-md-3"><input type="text" name="nombres" class="form-control" placeholder="Nombres" required></div>
                <div class="col-md-3"><input type="text" name="apellidos" class="form-control" placeholder="Apellidos" required></div>
                <div class="col-md-3"><input type="email" name="email" class="form-control" placeholder="Correo" required></div>
                <div class="col-md-3"><input type="text" name="telefono" class="form-control" placeholder="Contacto"></div>
                <div class="col-md-4"><input type="text" name="cargo" class="form-control" placeholder="Cargo"></div>
                <div class="col-md-4">
                    <select name="departamento_id" class="form-select" required>
                        <option value="">Departamento</option>
                        @foreach($departamentos as $departamento)
                            <option value="{{ $departamento->id }}">{{ $departamento->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 text-end"><button type="submit" class="btn btn-success">Guardar</button></div>
            </form>
        </div>
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
                    <th style="width:170px;">Accion</th>
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
                            <a href="{{ route('empleados.edit', $empleado) }}" class="btn btn-warning btn-sm me-1">Editar</a>
                            <form class="d-inline" method="POST" action="{{ route('empleados.destroy', $empleado) }}" onsubmit="return confirm('Deseas eliminar este empleado?');">
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


