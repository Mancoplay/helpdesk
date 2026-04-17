@extends('layouts.app')

@section('title', 'Editar Usuario')
@section('header', 'Usuario')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item"><a href="{{ route('usuarios.index') }}">Usuarios</a></li>
    <li class="breadcrumb-item active">Editar</li>
@endsection

@section('content')
<div class="card">
    <div class="card-header"><h3 class="card-title mb-0">Editar usuario</h3></div>
    <div class="card-body">
        <form method="POST" action="{{ route('usuarios.update', $cliente) }}">
            @csrf
            @method('PUT')
            <div class="row g-3">
                <div class="col-md-6">
                    <h6 class="fw-bold">Informacion Personal</h6>
                    <label class="form-label">Nombre(s)</label>
                    <input type="text" name="nombres" class="form-control" value="{{ old('nombres', $cliente->nombres) }}" required>

                    <label class="form-label mt-2">Apellidos</label>
                    <input type="text" name="apellidos" class="form-control" value="{{ old('apellidos', $cliente->apellidos) }}" required>

                    <label class="form-label mt-2">Contacto</label>
                    <input type="text" name="telefono" class="form-control" value="{{ old('telefono', $cliente->telefono) }}">
                </div>

                <div class="col-md-6">
                    <h6 class="fw-bold">Credenciales del Sistema</h6>
                    <label class="form-label">Correo</label>
                    <input type="email" name="email" class="form-control" value="{{ old('email', $cliente->email) }}" required>

                    <label class="form-label mt-2">Contrasena (opcional)</label>
                    <div class="input-group">
                        <input type="password" name="password" class="form-control" placeholder="Nueva contrasena" autocomplete="new-password">
                        <button type="button" class="btn btn-outline-secondary js-password-toggle" aria-label="Mostrar contrasena">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>

                    <label class="form-label mt-2">Confirmar Contrasena</label>
                    <div class="input-group">
                        <input type="password" name="password_confirmation" class="form-control" placeholder="Repite la nueva contrasena" autocomplete="new-password">
                        <button type="button" class="btn btn-outline-secondary js-password-toggle" aria-label="Mostrar contrasena">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <small class="text-muted">Si dejas los campos en blanco, se mantiene la contrasena actual.</small>
                </div>
            </div>

            @if($errors->any())
                <div class="alert alert-danger mt-3 mb-0">Revisa los campos ingresados.</div>
            @endif

            <div class="mt-3 text-end">
                <a href="{{ route('usuarios.index') }}" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">Guardar cambios</button>
            </div>
        </form>
    </div>
</div>
@endsection

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
                button.setAttribute('aria-label', showPassword ? 'Ocultar contrasena' : 'Mostrar contrasena');

                if (icon) {
                    icon.classList.toggle('fa-eye', !showPassword);
                    icon.classList.toggle('fa-eye-slash', showPassword);
                }
            });
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        setupPasswordToggles();
    });
</script>
@endpush
