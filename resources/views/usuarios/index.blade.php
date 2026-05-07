@extends('layouts.app')

@section('title', 'Usuarios')
@section('header', 'Gestion de usuarios')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item active">Usuarios</li>
@endsection

@section('content')
<div class="card mb-3">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUsuarioModal">
                <i class="fas fa-plus me-1"></i> Agregar nuevo usuario
            </button>
            <a href="{{ route('reportes.usuarios') }}" class="btn btn-outline-primary">
                <i class="fas fa-file-lines me-1"></i> Generar reporte
            </a>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('usuarios.index') }}" class="row g-2 align-items-end js-table-filters">
            <div class="col-md-6">
                <label class="form-label mb-1">Buscar</label>
                <input type="text" name="q" class="form-control" value="{{ $searchQuery ?? '' }}" placeholder="Ejemplo: nombre, correo o telefono...">
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1">Rol</label>
                <select name="rol" class="form-select">
                    <option value="">Todos</option>
                    @foreach($rolesDisponibles as $rol)
                        <option value="{{ $rol }}" @selected(($selectedRole ?? '') === $rol)>{{ $rol }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1">Registros</label>
                <select name="per_page" class="form-select">
                    @foreach([10, 15] as $size)
                        <option value="{{ $size }}" @selected(($perPage ?? 10) == $size)>{{ $size }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <a href="{{ route('usuarios.index') }}" class="btn btn-outline-secondary w-100">Limpiar</a>
            </div>
        </form>
    </div>
</div>

@include('usuarios.partials.table')

<div class="modal fade" id="createUsuarioModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('usuarios.store') }}" id="createUsuarioForm">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Nuevo usuario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label">Nombre(s)</label>
                            <input type="text" name="nombres" class="form-control" required autocomplete="off">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Apellidos</label>
                            <input type="text" name="apellidos" class="form-control" required autocomplete="off">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Correo</label>
                            <input type="email" name="email" class="form-control" required maxlength="255" pattern="^[^\s@]+@[^\s@]+\.[^\s@]+$" autocomplete="off">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Contacto</label>
                            <input type="text" name="telefono" class="form-control" inputmode="numeric" maxlength="8" pattern="(?:[67][0-9]{7}|[234][0-9]{6})" placeholder="Opcional" oninput="this.value=this.value.replace(/[^0-9]/g,'');">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Departamento</label>
                            <select name="departamento_id" class="form-select" required>
                                <option value="" selected disabled>Selecciona un departamento</option>
                                @foreach($departamentosBolivia as $departamento)
                                    <option value="{{ $departamento->id }}">{{ $departamento->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Area de trabajo</label>
                            <select name="area_trabajo_id" class="form-select" required>
                                <option value="" selected disabled>Selecciona un area de trabajo</option>
                                @foreach($areasTrabajoActivas as $area)
                                    <option value="{{ $area->id }}">{{ $area->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Rol</label>
                            <select name="rol" class="form-select" required>
                                <option value="" selected disabled>Selecciona un rol</option>
                                @foreach($rolesDisponibles as $rol)
                                    <option value="{{ $rol }}">{{ $rol }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Contraseña</label>
                            <div class="input-group">
                                <input type="password" name="password" class="form-control js-password-input" required minlength="8" autocomplete="new-password">
                                <button type="button" class="btn btn-outline-secondary js-password-toggle" aria-label="Mostrar contraseña">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Confirmar contraseña</label>
                            <div class="input-group">
                                <input type="password" name="password_confirmation" class="form-control js-password-input" required minlength="8" autocomplete="new-password">
                                <button type="button" class="btn btn-outline-secondary js-password-toggle" aria-label="Mostrar contraseña">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
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

@push('styles')
<style>
    .js-password-input::-ms-reveal,
    .js-password-input::-ms-clear {
        display: none;
    }
</style>
@endpush

@push('scripts')
<script>
    function setupPasswordToggles(root = document) {
        root.querySelectorAll('.js-password-toggle').forEach((button) => {
            if (button.dataset.bound === '1') {
                return;
            }

            button.dataset.bound = '1';
            button.addEventListener('click', () => {
                const inputGroup = button.closest('.input-group');
                const input = inputGroup ? inputGroup.querySelector('input') : null;
                const icon = button.querySelector('i');

                if (!input) {
                    return;
                }

                const showPassword = input.type === 'password';
                input.type = showPassword ? 'text' : 'password';
                button.setAttribute('aria-label', showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña');

                if (icon) {
                    icon.classList.toggle('fa-eye', !showPassword);
                    icon.classList.toggle('fa-eye-slash', showPassword);
                }
            });
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        setupPasswordToggles();

        const createUsuarioModal = document.getElementById('createUsuarioModal');
        const createUsuarioForm = document.getElementById('createUsuarioForm');

        if (createUsuarioModal && createUsuarioForm) {
            createUsuarioModal.addEventListener('hidden.bs.modal', () => {
                createUsuarioForm.reset();

                createUsuarioForm.querySelectorAll('.js-password-input').forEach((input) => {
                    input.type = 'password';
                });

                createUsuarioForm.querySelectorAll('.js-password-toggle').forEach((button) => {
                    button.setAttribute('aria-label', 'Mostrar contraseña');
                    const icon = button.querySelector('i');

                    if (icon) {
                        icon.classList.add('fa-eye');
                        icon.classList.remove('fa-eye-slash');
                    }
                });
            });
        }
    });

    document.addEventListener('shown.bs.modal', (event) => {
        setupPasswordToggles(event.target);
    });
</script>
@endpush
