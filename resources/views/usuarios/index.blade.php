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
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUsuarioModal">
            <i class="fas fa-plus me-1"></i> Agregar nuevo usuario
        </button>
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
                    <th style="width:220px;">Accion</th>
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
                                <button class="btn btn-warning btn-sm me-1" data-bs-toggle="modal" data-bs-target="#editUsuarioModal{{ $usuario->id }}">Editar</button>
                                <form class="d-inline" method="POST" action="{{ route('usuarios.destroy', $usuario) }}" onsubmit="return confirm('Deseas eliminar este usuario?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
                                </form>
                            @endif
                        </td>
                    </tr>

                    <div class="modal fade" id="editUsuarioModal{{ $usuario->id }}" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-lg modal-dialog-centered">
                            <div class="modal-content">
                                <form method="POST" action="{{ route('usuarios.update', $usuario) }}">
                                    @csrf
                                    @method('PUT')
                                    <div class="modal-header">
                                        <h5 class="modal-title">Editar usuario</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label">Nombre</label>
                                                <input type="text" name="name" class="form-control" value="{{ $usuario->name }}" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Correo</label>
                                                <input type="email" name="email" class="form-control" value="{{ $usuario->email }}" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Contrasena (opcional)</label>
                                                <input type="password" name="password" class="form-control" autocomplete="new-password">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Confirmar contrasena</label>
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
                    <tr><td colspan="4" class="text-center text-muted">Sin datos</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="createUsuarioModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('usuarios.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Nuevo usuario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nombre</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Correo</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Contrasena</label>
                            <input type="password" name="password" class="form-control" required autocomplete="new-password">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Confirmar contrasena</label>
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
