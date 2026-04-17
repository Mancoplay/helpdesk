@extends('layouts.app')

@section('title', 'Editar Empleado')
@section('header', 'Empleado')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item"><a href="{{ route('empleados.index') }}">Empleados</a></li>
    <li class="breadcrumb-item active">Editar</li>
@endsection

@section('content')
<div class="card">
    <div class="card-header"><h3 class="card-title mb-0">Editar empleado</h3></div>
    <div class="card-body">
        <form method="POST" action="{{ route('empleados.update', $empleado) }}">
            @csrf
            @method('PUT')
            <div class="row g-3">
                <div class="col-md-6">
                    <h6 class="fw-bold">Informacion Personal</h6>
                    <label class="form-label">Nombre(s)</label>
                    <input type="text" name="nombres" class="form-control" value="{{ old('nombres', $empleado->nombres) }}" required>

                    <label class="form-label mt-2">Apellidos</label>
                    <input type="text" name="apellidos" class="form-control" value="{{ old('apellidos', $empleado->apellidos) }}" required>

                    <label class="form-label mt-2">Contacto</label>
                    <input type="text" name="telefono" class="form-control" value="{{ old('telefono', $empleado->telefono) }}">


                </div>

                <div class="col-md-6">
                    <h6 class="fw-bold">Credenciales del Sistema</h6>
                    <label class="form-label">Departamentos</label>
                    <div class="dropdown department-picker">
                        <button
                            class="btn btn-outline-secondary dropdown-toggle w-100 text-start"
                            type="button"
                            data-bs-toggle="dropdown"
                            data-bs-auto-close="outside"
                        >
                            Departamento
                        </button>
                        <div class="dropdown-menu p-3 w-100" style="max-height: 220px; overflow-y: auto;">
                            @foreach($departamentos as $departamento)
                                <div class="form-check mb-2">
                                    <input
                                        class="form-check-input department-checkbox"
                                        type="checkbox"
                                        name="departamento_ids[]"
                                        value="{{ $departamento->id }}"
                                        id="edit-page-dep-{{ $departamento->id }}"
                                        @checked(
                                            collect(old('departamento_ids', $empleado->departamentos->pluck('id')->all() ?: [$empleado->departamento_id]))
                                                ->contains($departamento->id)
                                        )
                                    >
                                    <label class="form-check-label" for="edit-page-dep-{{ $departamento->id }}">
                                        {{ $departamento->nombre }}
                                    </label>
                                </div>
                            @endforeach
                        </div>
                        
                    </div>
                    <small class="text-muted">Puedes seleccionar mas de un departamento.</small>

                    <div class="mt-2">
                        <label class="form-label">Departamentos seleccionados</label>
                        <div class="departments-selected-list" style="min-height: 70px; border: 1px solid #dcdcdc; padding: .5rem; border-radius: .375rem;"></div>
                    </div>

                    <label class="form-label mt-2">Cargo</label>
                    <input type="text" name="cargo" class="form-control" value="{{ old('cargo', $empleado->cargo) }}">

                    <label class="form-label mt-2">Correo</label>
                    <input type="email" name="email" class="form-control" value="{{ old('email', $empleado->email) }}" required>

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
                <a href="{{ route('empleados.index') }}" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">Guardar cambios</button>
            </div>
        </form>
    </div>
</div>

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

    function renderDepartmentPicklist(wrapper) {
        const selectedContainer = wrapper.closest('.col-md-6').querySelector('.departments-selected-list');
        const button = wrapper.querySelector('[data-bs-toggle="dropdown"]');
        const checkedBoxes = Array.from(wrapper.querySelectorAll('.department-checkbox:checked'));        if (!selectedContainer || !button) {
            return;
        }

        const selected = checkedBoxes.map((input) => {
            const label = wrapper.querySelector(`label[for="${input.id}"]`);
            return {
                id: input.value,
                name: label ? label.textContent.trim() : input.value,
            };
        });

        selectedContainer.innerHTML = '';
        button.textContent = selected.length > 0 ? `Departamento (${selected.length})` : 'Departamento';

        if (selected.length === 0) {
            const empty = document.createElement('span');
            empty.className = 'text-muted';
            empty.textContent = 'Ningun departamento seleccionado.';
            selectedContainer.appendChild(empty);
            return;
        }

        selected.forEach((dep) => {
            const badge = document.createElement('span');
            badge.className = 'badge text-bg-primary me-1 mb-1';
            badge.textContent = dep.name;
            selectedContainer.appendChild(badge);
        });
    }

    function setupDepartmentPicker(wrapper) {
        const checkboxes = wrapper.querySelectorAll('.department-checkbox');
        const update = () => renderDepartmentPicklist(wrapper);

        checkboxes.forEach((checkbox) => {
            checkbox.addEventListener('change', update);
        });

        update();
    }

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.department-picker').forEach(setupDepartmentPicker);
        setupPasswordToggles();
    });
</script>
@endpush

@endsection
