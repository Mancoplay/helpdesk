@extends('layouts.app')

@section('title', 'Usuarios')
@section('header', 'Lista de usuarios')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item active">Usuarios</li>
@endsection

@section('content')
<div class="card mb-3">
    <div class="card-body">
        <button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#nuevoUsuario">
            <i class="fas fa-plus me-1"></i> Agregar nuevo usuario
        </button>

        <div class="collapse mt-3" id="nuevoUsuario">
            <form method="POST" action="{{ route('usuarios.store') }}" class="row g-2">
                @csrf
                <div class="col-md-4"><input type="text" name="name" class="form-control" placeholder="Nombre" required></div>
                <div class="col-md-4"><input type="email" name="email" class="form-control" placeholder="Correo" required></div>
                <div class="col-md-3"><input type="password" name="password" class="form-control" placeholder="Contrasena" required></div>
                <div class="col-md-1 text-end"><button type="submit" class="btn btn-success">Guardar</button></div>
            </form>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3 class="card-title mb-0">Tabla de Usuarios</h3></div>
    <div class="card-body table-responsive p-0">
        <table class="table table-striped table-hover mb-0">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Email</th>
                    <th>Creado</th>
                    <th style="width:130px;">Accion</th>
                </tr>
            </thead>
            <tbody>
                @forelse($usuarios as $usuario)
                    <tr>
                        <td>{{ $usuario->name }}</td>
                        <td>{{ $usuario->email }}</td>
                        <td>{{ $usuario->created_at?->format('d/m/Y H:i') }}</td>
                        <td>
                            @if(auth()->id() === $usuario->id)
                                <span class="badge text-bg-secondary">Actual</span>
                            @else
                                <form method="POST" action="{{ route('usuarios.destroy', $usuario) }}" onsubmit="return confirm('Deseas eliminar este usuario?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted">Sin datos</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
