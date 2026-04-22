@extends('layouts.app')

@section('title', 'Area de trabajo')
@section('header', 'Lista de areas de trabajo')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item active">Area de trabajo</li>
@endsection

@section('content')
<div class="card mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-lg-auto">
                <button class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#createDepartamentoModal">
                    <i class="fas fa-plus me-1"></i> Agregar nueva area de trabajo
                </button>
            </div>
            <div class="col-lg">
                <form method="POST" action="{{ route('departamentos.notification-email.update') }}" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-md-8">
                        <label class="form-label mb-1">Correo para notificaciones</label>
                        <input
                            type="email"
                            name="notification_email"
                            class="form-control"
                            value="{{ old('notification_email', $notificationEmail ?? '') }}"
                            maxlength="255"
                            placeholder="correo@ejemplo.com"
                            pattern="^[^\s@]+@[^\s@]+\.[^\s@]+$"
                            title="Ingresa un correo valido, por ejemplo admin@dominio.com"
                        >
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-outline-primary w-100">Guardar correo</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

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
                    @foreach([10, 15] as $size)
                        <option value="{{ $size }}" @selected(($perPage ?? 5) == $size)>{{ $size }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <a href="{{ route('departamentos.index') }}" class="btn btn-outline-secondary w-100">Limpiar</a>
            </div>
        </form>
    </div>
</div>
@include('departamentos.partials.table')

<div class="modal fade" id="createDepartamentoModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content"><form method="POST" action="{{ route('departamentos.store') }}">@csrf<div class="modal-header"><h5 class="modal-title">Nueva area de trabajo</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="row g-2"><div class="col-md-6"><label class="form-label">Nombre</label><input type="text" name="nombre" class="form-control" required></div><div class="col-md-6"><label class="form-label">Descripcion</label><input type="text" name="descripcion" class="form-control"></div></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Guardar</button></div></form></div></div></div>
@endsection


