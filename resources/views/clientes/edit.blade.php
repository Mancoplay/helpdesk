@extends('layouts.app')

@section('title', 'Editar Cliente')
@section('header', 'Cliente')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item"><a href="{{ route('clientes.index') }}">Clientes</a></li>
    <li class="breadcrumb-item active">Editar</li>
@endsection

@section('content')
<div class="card">
    <div class="card-header"><h3 class="card-title mb-0">Editar cliente</h3></div>
    <div class="card-body">
        <form method="POST" action="{{ route('clientes.update', $cliente) }}">
            @csrf
            @method('PUT')
            <div class="row g-3">
                <div class="col-md-6">
                    <h6 class="fw-bold">Informacion Personal</h6>
                    <label class="form-label">Nombre</label>
                    <input type="text" name="nombres" class="form-control" value="{{ old('nombres', $cliente->nombres) }}" required>

                    <label class="form-label mt-2">Segundo Nombre</label>
                    <input type="text" name="segundo_nombre" class="form-control" value="{{ old('segundo_nombre', $cliente->segundo_nombre) }}">

                    <label class="form-label mt-2">Apellido</label>
                    <input type="text" name="apellidos" class="form-control" value="{{ old('apellidos', $cliente->apellidos) }}" required>

                    <label class="form-label mt-2">Contacto</label>
                    <input type="text" name="telefono" class="form-control" value="{{ old('telefono', $cliente->telefono) }}">
                </div>

                <div class="col-md-6">
                    <h6 class="fw-bold">Credenciales del Sistema</h6>
                    <label class="form-label">Correo</label>
                    <input type="email" name="email" class="form-control" value="{{ old('email', $cliente->email) }}" required>

                    <label class="form-label mt-2">Contrasena</label>
                    <input type="password" class="form-control" placeholder="Opcional" disabled>

                    <label class="form-label mt-2">Confirmar Contrasena</label>
                    <input type="password" class="form-control" placeholder="Opcional" disabled>
                    <small class="text-muted">La contrasena se gestiona desde el formulario de cliente.</small>
                </div>
            </div>

            @if($errors->any())
                <div class="alert alert-danger mt-3 mb-0">Revisa los campos ingresados.</div>
            @endif

            <div class="mt-3 text-end">
                <a href="{{ route('clientes.index') }}" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">Guardar cambios</button>
            </div>
        </form>
    </div>
</div>
@endsection

