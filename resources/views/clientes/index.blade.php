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
        <button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#nuevoCliente">
            <i class="fas fa-plus me-1"></i> Agregar nuevo cliente
        </button>

        <div class="collapse mt-3" id="nuevoCliente">
            <form method="POST" action="{{ route('clientes.store') }}" class="row g-2">
                @csrf
                <div class="col-md-3"><input type="text" name="nombres" class="form-control" placeholder="Nombres" required></div>
                <div class="col-md-3"><input type="text" name="apellidos" class="form-control" placeholder="Apellidos" required></div>
                <div class="col-md-3"><input type="email" name="email" class="form-control" placeholder="Correo" required></div>
                <div class="col-md-3"><input type="text" name="telefono" class="form-control" placeholder="Telefono"></div>
                <div class="col-md-6"><input type="text" name="empresa" class="form-control" placeholder="Empresa"></div>
                <div class="col-md-6 text-end"><button type="submit" class="btn btn-success">Guardar</button></div>
            </form>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3 class="card-title mb-0">Tabla de Clientes</h3></div>
    <div class="card-body table-responsive p-0">
        <table class="table table-striped table-hover mb-0">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Email</th>
                    <th>Telefono</th>
                    <th>Empresa</th>
                    <th style="width:130px;">Accion</th>
                </tr>
            </thead>
            <tbody>
                @forelse($clientes as $cliente)
                    <tr>
                        <td>{{ $cliente->nombre_completo }}</td>
                        <td>{{ $cliente->email }}</td>
                        <td>{{ $cliente->telefono ?? '-' }}</td>
                        <td>{{ $cliente->empresa ?? '-' }}</td>
                        <td>
                            <form method="POST" action="{{ route('clientes.destroy', $cliente) }}" onsubmit="return confirm('Deseas eliminar este cliente?');">
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
