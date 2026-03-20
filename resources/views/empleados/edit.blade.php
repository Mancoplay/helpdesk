@extends('layouts.app')

@section('title', 'Editar Empleado')
@section('header', 'Empleado')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item"><a href="{{ route('empleados.index') }}">Empleados</a></li>
    <li class="breadcrumb-item active">Editar</li>
@endsection

@section('content')
<div class="card">
    <div class="card-header"><h3 class="card-title mb-0">Editar empleado</h3></div>
    <div class="card-body">
        <form method="POST" action="{{ route('empleados.update', $empleado) }}">
            @csrf
            @method('PUT')
            <div class="row g-3">
                <div class="col-md-6">
                    <h6 class="fw-bold">Informacion Personal</h6>
                    <label class="form-label">Nombre</label>
                    <input type="text" name="nombres" class="form-control" value="{{ old('nombres', $empleado->nombres) }}" required>

                    <label class="form-label mt-2">Segundo Nombre</label>
                    <input type="text" name="segundo_nombre" class="form-control" value="{{ old('segundo_nombre', $empleado->segundo_nombre) }}">

                    <label class="form-label mt-2">Apellido</label>
                    <input type="text" name="apellidos" class="form-control" value="{{ old('apellidos', $empleado->apellidos) }}" required>

                    <label class="form-label mt-2">Contacto</label>
                    <input type="text" name="telefono" class="form-control" value="{{ old('telefono', $empleado->telefono) }}">

                    <label class="form-label mt-2">Direccion</label>
                    <textarea name="direccion" class="form-control" rows="4">{{ old('direccion', $empleado->direccion) }}</textarea>
                </div>

                <div class="col-md-6">
                    <h6 class="fw-bold">Credenciales del Sistema</h6>
                    <label class="form-label">Departamento</label>
                    <select name="departamento_id" class="form-select" required>
                        <option value="">Selecciona aqui</option>
                        @foreach($departamentos as $departamento)
                            <option value="{{ $departamento->id }}" @selected(old('departamento_id', $empleado->departamento_id) == $departamento->id)>{{ $departamento->nombre }}</option>
                        @endforeach
                    </select>

                    <label class="form-label mt-2">Cargo</label>
                    <input type="text" name="cargo" class="form-control" value="{{ old('cargo', $empleado->cargo) }}">

                    <label class="form-label mt-2">Correo</label>
                    <input type="email" name="email" class="form-control" value="{{ old('email', $empleado->email) }}" required>

                    <label class="form-label mt-2">Contrasena</label>
                    <input type="password" class="form-control" placeholder="Opcional" disabled>

                    <label class="form-label mt-2">Confirmar Contrasena</label>
                    <input type="password" class="form-control" placeholder="Opcional" disabled>
                    <small class="text-muted">La contrasena se gestiona desde Usuarios.</small>
                </div>
            </div>

            @if($errors->any())
                <div class="alert alert-danger mt-3 mb-0">Revisa los campos ingresados.</div>
            @endif

            <div class="mt-3 text-end">
                <a href="{{ route('empleados.index') }}" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">Guardar cambios</button>
            </div>
        </form>
    </div>
</div>
@endsection
