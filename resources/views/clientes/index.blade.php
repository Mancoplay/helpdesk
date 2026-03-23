@extends('layouts.app')

@section('title', 'Clientes')
@section('header', 'Lista de clientes')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item active">Clientes</li>
@endsection

@section('content')
<div class="card mb-3">
    <div class="card-body">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createClienteModal"><i class="fas fa-plus me-1"></i> Agregar nuevo cliente</button>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('clientes.index') }}" class="row g-2 align-items-end js-table-filters">
            <div class="col-md-8">
                <label class="form-label mb-1">Buscar</label>
                <input type="text" name="q" class="form-control" value="{{ $searchQuery ?? '' }}" placeholder="Ejemplo: red, empresa, telefono...">
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1">Registros</label>
                <select name="per_page" class="form-select">
                    @foreach([10, 15] as $size)
                        <option value="{{ $size }}" @selected(($perPage ?? 5) == $size)>{{ $size }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <a href="{{ route('clientes.index') }}" class="btn btn-outline-secondary w-100">Limpiar</a>
            </div>
        </form>
    </div>
</div>
<div class="card js-table-results">
    <div class="card-header"><h3 class="card-title mb-0">Tabla de Clientes</h3></div>
    <div class="card-body table-responsive p-0">
        <table class="table table-striped table-hover mb-0">
            <thead><tr><th>Nombre</th><th>Email</th><th>Telefono</th><th>Empresa</th><th style="width:220px;">Accion</th></tr></thead>
            <tbody>
            @forelse($clientes as $cliente)
                <tr>
                    <td>{{ $cliente->nombre_completo }}</td>
                    <td>{{ $cliente->email }}</td>
                    <td>{{ $cliente->telefono ?? '-' }}</td>
                    <td>{{ $cliente->empresa ?? '-' }}</td>
                    <td>
                        <button class="btn btn-warning btn-sm me-1" data-bs-toggle="modal" data-bs-target="#editClienteModal{{ $cliente->id }}">Editar</button>
                        <form class="d-inline" method="POST" action="{{ route('clientes.destroy', $cliente) }}" onsubmit="return confirm('Deseas eliminar este cliente?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
                        </form>
                    </td>
                </tr>

                <div class="modal fade" id="editClienteModal{{ $cliente->id }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content">
                        <form method="POST" action="{{ route('clientes.update', $cliente) }}">
                            @csrf
                            @method('PUT')
                            <div class="modal-header"><h5 class="modal-title">Editar cliente</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                            <div class="modal-body"><div class="row g-2">
                                <div class="col-md-6"><label class="form-label">Nombre</label><input type="text" name="nombres" class="form-control" value="{{ $cliente->nombres }}" required></div>
                                <div class="col-md-6"><label class="form-label">Segundo nombre</label><input type="text" name="segundo_nombre" class="form-control" value="{{ $cliente->segundo_nombre }}"></div>
                                <div class="col-md-6"><label class="form-label">Apellido</label><input type="text" name="apellidos" class="form-control" value="{{ $cliente->apellidos }}" required></div>
                                <div class="col-md-6"><label class="form-label">Correo</label><input type="email" name="email" class="form-control" value="{{ $cliente->email }}" required></div>
                                <div class="col-md-6"><label class="form-label">Contrasena</label><input type="password" name="password" class="form-control" required autocomplete="new-password"></div>
                                <div class="col-md-6"><label class="form-label">Confirmar contrasena</label><input type="password" name="password_confirmation" class="form-control" required autocomplete="new-password"></div>
                                <div class="col-md-6"><label class="form-label">Contacto</label><input type="text" name="telefono" class="form-control" value="{{ $cliente->telefono }}"></div>
                                <div class="col-md-6"><label class="form-label">Empresa</label><input type="text" name="empresa" class="form-control" value="{{ $cliente->empresa }}"></div>
                                <div class="col-12"><label class="form-label">Direccion</label><textarea name="direccion" class="form-control" rows="3">{{ $cliente->direccion }}</textarea></div>
                            </div></div>
                            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Guardar</button></div>
                        </form>
                    </div></div>
                </div>
            @empty
                <tr><td colspan="5" class="text-center text-muted">Sin datos</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer compact-pagination">
        {{ $clientes->links() }}
    </div>
</div>

<div class="modal fade" id="createClienteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content">
        <form method="POST" action="{{ route('clientes.store') }}">
            @csrf
            <div class="modal-header"><h5 class="modal-title">Nuevo cliente</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body"><div class="row g-2">
                <div class="col-md-6"><label class="form-label">Nombre</label><input type="text" name="nombres" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label">Segundo nombre</label><input type="text" name="segundo_nombre" class="form-control"></div>
                <div class="col-md-6"><label class="form-label">Apellido</label><input type="text" name="apellidos" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label">Correo</label><input type="email" name="email" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label">Contrasena</label><input type="password" name="password" class="form-control" required autocomplete="new-password"></div>
                <div class="col-md-6"><label class="form-label">Confirmar contrasena</label><input type="password" name="password_confirmation" class="form-control" required autocomplete="new-password"></div>
                <div class="col-md-6"><label class="form-label">Contacto</label><input type="text" name="telefono" class="form-control"></div>
                <div class="col-md-6"><label class="form-label">Empresa</label><input type="text" name="empresa" class="form-control"></div>
                <div class="col-12"><label class="form-label">Direccion</label><textarea name="direccion" class="form-control" rows="3"></textarea></div>
            </div></div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Guardar</button></div>
        </form>
    </div></div>
</div>
@endsection
