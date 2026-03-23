@extends('layouts.app')

@section('title', 'Departamentos')
@section('header', 'Lista de departamentos')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item active">Departamentos</li>
@endsection

@section('content')
<div class="card mb-3"><div class="card-body"><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createDepartamentoModal"><i class="fas fa-plus me-1"></i> Agregar nuevo departamento</button></div></div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('departamentos.index') }}" class="row g-2 align-items-end js-table-filters">
            <div class="col-md-8">
                <label class="form-label mb-1">Buscar</label>
                <input type="text" name="q" class="form-control" value="{{ $searchQuery ?? '' }}" placeholder="Ejemplo: red, sistemas, soporte...">
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1">Registros</label>
                <select name="per_page" class="form-select">
                    @foreach([10, 15, 20] as $size)
                        <option value="{{ $size }}" @selected(($perPage ?? 10) == $size)>{{ $size }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <a href="{{ route('departamentos.index') }}" class="btn btn-outline-secondary w-100">Limpiar</a>
            </div>
        </form>
    </div>
</div>
<div class="card">
    <div class="card-header"><h3 class="card-title mb-0">Tabla de Departamentos</h3></div>
    <div class="card-body table-responsive p-0">
        <table class="table table-striped table-hover mb-0">
            <thead><tr><th>Nombre</th><th>Descripcion</th><th>Activo</th><th style="width:220px;">Accion</th></tr></thead>
            <tbody>
            @forelse($departamentos as $departamento)
                <tr>
                    <td>{{ $departamento->nombre }}</td>
                    <td>{{ $departamento->descripcion ?? '-' }}</td>
                    <td><span class="badge text-bg-{{ $departamento->activo ? 'success' : 'secondary' }}">{{ $departamento->activo ? 'Si' : 'No' }}</span></td>
                    <td>
                        <button class="btn btn-warning btn-sm me-1" data-bs-toggle="modal" data-bs-target="#editDepartamentoModal{{ $departamento->id }}">Editar</button>
                        <form class="d-inline" method="POST" action="{{ route('departamentos.destroy', $departamento) }}" onsubmit="return confirm('Deseas eliminar este departamento?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
                        </form>
                    </td>
                </tr>

                <div class="modal fade" id="editDepartamentoModal{{ $departamento->id }}" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content"><form method="POST" action="{{ route('departamentos.update', $departamento) }}">@csrf @method('PUT')<div class="modal-header"><h5 class="modal-title">Editar departamento</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="row g-2"><div class="col-md-6"><label class="form-label">Nombre</label><input type="text" name="nombre" class="form-control" value="{{ $departamento->nombre }}" required></div><div class="col-md-6"><label class="form-label">Descripcion</label><input type="text" name="descripcion" class="form-control" value="{{ $departamento->descripcion }}"></div><div class="col-md-6"><label class="form-label">Activo</label><select name="activo" class="form-select"><option value="1" @selected($departamento->activo)>Si</option><option value="0" @selected(!$departamento->activo)>No</option></select></div></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Guardar</button></div></form></div></div></div>
            @empty
                <tr><td colspan="4" class="text-center text-muted">Sin datos</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer compact-pagination">
        {{ $departamentos->links() }}
    </div>
</div>

<div class="modal fade" id="createDepartamentoModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content"><form method="POST" action="{{ route('departamentos.store') }}">@csrf<div class="modal-header"><h5 class="modal-title">Nuevo departamento</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="row g-2"><div class="col-md-6"><label class="form-label">Nombre</label><input type="text" name="nombre" class="form-control" required></div><div class="col-md-6"><label class="form-label">Descripcion</label><input type="text" name="descripcion" class="form-control"></div></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Guardar</button></div></form></div></div></div>
@endsection
