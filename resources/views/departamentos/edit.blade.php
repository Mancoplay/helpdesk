@extends('layouts.app')

@section('title', 'Editar Departamento')
@section('header', 'Departamento')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item"><a href="{{ route('departamentos.index') }}">Departamentos</a></li>
    <li class="breadcrumb-item active">Editar</li>
@endsection

@section('content')
<div class="card">
    <div class="card-header"><h3 class="card-title mb-0">Editar departamento</h3></div>
    <div class="card-body">
        <form method="POST" action="{{ route('departamentos.update', $departamento) }}" class="row g-3">
            @csrf
            @method('PUT')
            <div class="col-md-4">
                <label class="form-label">Nombre</label>
                <input type="text" name="nombre" class="form-control" value="{{ old('nombre', $departamento->nombre) }}" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Descripcion</label>
                <input type="text" name="descripcion" class="form-control" value="{{ old('descripcion', $departamento->descripcion) }}">
            </div>
            <div class="col-md-2">
                <label class="form-label">Activo</label>
                <select name="activo" class="form-select">
                    <option value="1" @selected(old('activo', $departamento->activo) == 1)>Si</option>
                    <option value="0" @selected(old('activo', $departamento->activo) == 0)>No</option>
                </select>
            </div>
            <div class="col-12 text-end">
                <a href="{{ route('departamentos.index') }}" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">Guardar cambios</button>
            </div>
        </form>
    </div>
</div>
@endsection
