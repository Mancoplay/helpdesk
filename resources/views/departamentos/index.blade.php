@extends('layouts.app')

@section('title', 'Departamentos')
@section('header', 'Lista de departamentos')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item active">Departamentos</li>
@endsection

@section('content')
<div class="card mb-3">
    <div class="card-body">
        <button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#nuevoDepartamento">
            <i class="fas fa-plus me-1"></i> Agregar nuevo departamento
        </button>

        <div class="collapse mt-3" id="nuevoDepartamento">
            <form method="POST" action="{{ route('departamentos.store') }}" class="row g-2">
                @csrf
                <div class="col-md-4"><input type="text" name="nombre" class="form-control" placeholder="Nombre" required></div>
                <div class="col-md-6"><input type="text" name="descripcion" class="form-control" placeholder="Descripcion"></div>
                <div class="col-md-2 text-end"><button type="submit" class="btn btn-success">Guardar</button></div>
            </form>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3 class="card-title mb-0">Tabla de Departamentos</h3></div>
    <div class="card-body table-responsive p-0">
        <table class="table table-striped table-hover mb-0">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Descripcion</th>
                    <th>Activo</th>
                    <th style="width:130px;">Accion</th>
                </tr>
            </thead>
            <tbody>
                @forelse($departamentos as $departamento)
                    <tr>
                        <td>{{ $departamento->nombre }}</td>
                        <td>{{ $departamento->descripcion ?? '-' }}</td>
                        <td>
                            <span class="badge text-bg-{{ $departamento->activo ? 'success' : 'secondary' }}">
                                {{ $departamento->activo ? 'Si' : 'No' }}
                            </span>
                        </td>
                        <td>
                            <form method="POST" action="{{ route('departamentos.destroy', $departamento) }}" onsubmit="return confirm('Deseas eliminar este departamento?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
                            </form>
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
