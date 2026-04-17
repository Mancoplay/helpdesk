@extends('layouts.app')

@section('title', 'Empleados')
@section('header', 'Lista de empleados')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item active">Empleados</li>
@endsection

@section('content')
<div class="card mb-3">
    <div class="card-body">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createEmpleadoModal">
            <i class="fas fa-plus me-1"></i> Agregar nuevo empleado
        </button>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('empleados.index') }}" class="row g-2 align-items-end js-table-filters">
            <div class="col-md-8">
                <label class="form-label mb-1">Buscar</label>
                <input type="text" name="q" class="form-control" value="{{ $searchQuery ?? '' }}" placeholder="Ejemplo: red, soporte, correo...">
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
                <a href="{{ route('empleados.index') }}" class="btn btn-outline-secondary w-100">Limpiar</a>
            </div>
        </form>
    </div>
</div>
<div class="card js-table-results">
    <div class="card-header"><h3 class="card-title mb-0">Tabla de Empleados</h3></div>
    <div class="card-body table-responsive p-0">
        <table class="table table-striped table-hover mb-0">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Departamentos</th>
                    <th>Contacto</th>
                    <th>Correo</th>
                    <th style="width:300px;">Accion</th>
                </tr>
            </thead>
            <tbody>
                @forelse($empleados as $empleado)
                    <tr>
                        <td>{{ $empleado->nombre_completo }}</td>
                        <td>
                            @if($empleado->departamentos->isNotEmpty())
                                {{ $empleado->departamentos->pluck('nombre')->implode(', ') }}
                            @else
                                {{ $empleado->departamento->nombre ?? '-' }}
                            @endif
                        </td>
                        <td>{{ $empleado->telefono ?? '-' }}</td>
                        <td>{{ $empleado->email }}</td>
                        <td class="text-nowrap">
                            <div class="d-flex flex-nowrap align-items-center gap-2">
                                <a href="{{ route('empleados.review', ['empleado' => $empleado, 'period' => 'month']) }}" class="btn btn-secondary btn-sm">Revisar</a>
                                <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editEmpleadoModal{{ $empleado->id }}">Editar</button>
                                <form class="d-inline mb-0" method="POST" action="{{ route('empleados.checkpoint', $empleado) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="checkpoint-switch {{ $empleado->activo ? 'is-on' : 'is-off' }}" title="{{ $empleado->activo ? 'Habilitado' : 'Deshabilitado' }}">
                                        <span class="checkpoint-switch__label">{{ $empleado->activo ? 'ON' : 'OFF' }}</span>
                                        <span class="checkpoint-switch__knob"></span>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>

                    <div class="modal fade" id="editEmpleadoModal{{ $empleado->id }}" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-xl modal-dialog-centered">
                            <div class="modal-content">
                                <form method="POST" action="{{ route('empleados.update', $empleado) }}">
                                    @csrf
                                    @method('PUT')
                                    <div class="modal-header">
                                        <h5 class="modal-title">Empleado</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <h6 class="fw-bold">Informacion Personal</h6>
                                                <label class="form-label">Nombre(s)</label>
                                                <input type="text" name="nombres" class="form-control" value="{{ $empleado->nombres }}" required>
                                                <label class="form-label mt-2">Apellidos</label>
                                                <input type="text" name="apellidos" class="form-control" value="{{ $empleado->apellidos }}" required>
                                                <label class="form-label mt-2">Contacto</label>
                                                <input type="text" name="telefono" class="form-control" value="{{ $empleado->telefono }}" inputmode="numeric" maxlength="8" pattern="(?:[67][0-9]{7}|[234][0-9]{6})" title="Ingresa un numero boliviano valido: celular de 8 digitos (6 o 7) o fijo de 7 digitos (2, 3 o 4)." placeholder="Ej: 71234567 o 2345678" oninput="this.value=this.value.replace(/[^0-9]/g,'');">
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
                                                        @foreach($departamentosActivos as $departamento)
                                                            <div class="form-check mb-2">
                                                                <input
                                                                    class="form-check-input department-checkbox"
                                                                    type="checkbox"
                                                                    name="departamento_ids[]"
                                                                    value="{{ $departamento->id }}"
                                                                    id="edit-{{ $empleado->id }}-dep-{{ $departamento->id }}"
                                                                    @checked($empleado->departamentos->contains('id', $departamento->id) || $empleado->departamento_id == $departamento->id)
                                                                >
                                                                <label class="form-check-label" for="edit-{{ $empleado->id }}-dep-{{ $departamento->id }}">
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
                                                <input type="text" name="cargo" class="form-control" value="{{ $empleado->cargo }}">
                                                <label class="form-label mt-2">Correo</label>
                                                <input type="email" name="email" class="form-control" value="{{ $empleado->email }}" required maxlength="255" pattern="^[^\s@]+@[^\s@]+\.[^\s@]+$" title="Ingresa un correo valido, por ejemplo usuario@dominio.com">
                                                <label class="form-label mt-2">Contrasena (opcional)</label>
                                                <div class="input-group">
                                                    <input type="password" name="password" class="form-control js-password-input" autocomplete="new-password" placeholder="Escribe una nueva contrasena para cambiarla">
                                                    <button type="button" class="btn btn-outline-secondary js-password-toggle" aria-label="Mostrar contrasena">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                </div>
                                                <label class="form-label mt-2">Confirmar Contrasena</label>
                                                <div class="input-group">
                                                    <input type="password" name="password_confirmation" class="form-control js-password-input" autocomplete="new-password" placeholder="Repite la nueva contrasena">
                                                    <button type="button" class="btn btn-outline-secondary js-password-toggle" aria-label="Mostrar contrasena">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                </div>
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
                    <tr><td colspan="5" class="text-center text-muted">Sin datos</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer compact-pagination">
        {{ $empleados->links() }}
    </div>
</div>

<div class="modal fade" id="createEmpleadoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('empleados.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Empleado</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <h6 class="fw-bold">Informacion Personal</h6>
                            <label class="form-label">Nombre(s)</label>
                            <input type="text" name="nombres" class="form-control" required>
                            <label class="form-label mt-2">Apellidos</label>
                            <input type="text" name="apellidos" class="form-control" required>
                            <label class="form-label mt-2">Contacto</label>
                            <input type="text" name="telefono" class="form-control" inputmode="numeric" maxlength="8" pattern="(?:[67][0-9]{7}|[234][0-9]{6})" title="Ingresa un numero boliviano valido: celular de 8 digitos (6 o 7) o fijo de 7 digitos (2, 3 o 4)." placeholder="Ej: 71234567 o 2345678" oninput="this.value=this.value.replace(/[^0-9]/g,'');">
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
                                    @foreach($departamentosActivos as $departamento)
                                        <div class="form-check mb-2">
                                            <input
                                                class="form-check-input department-checkbox"
                                                type="checkbox"
                                                name="departamento_ids[]"
                                                value="{{ $departamento->id }}"
                                                id="create-dep-{{ $departamento->id }}"
                                            >
                                            <label class="form-check-label" for="create-dep-{{ $departamento->id }}">
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
                            <input type="text" name="cargo" class="form-control">
                            <label class="form-label mt-2">Correo</label>
                            <input type="email" name="email" class="form-control" required maxlength="255" pattern="^[^\s@]+@[^\s@]+\.[^\s@]+$" title="Ingresa un correo valido, por ejemplo usuario@dominio.com">
                            <label class="form-label mt-2">Contrasena</label>
                            <div class="input-group">
                                <input type="password" name="password" class="form-control js-password-input" required autocomplete="new-password">
                                <button type="button" class="btn btn-outline-secondary js-password-toggle" aria-label="Mostrar contrasena">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <label class="form-label mt-2">Confirmar Contrasena</label>
                            <div class="input-group">
                                <input type="password" name="password_confirmation" class="form-control js-password-input" required autocomplete="new-password">
                                <button type="button" class="btn btn-outline-secondary js-password-toggle" aria-label="Mostrar contrasena">
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

    document.addEventListener('shown.bs.modal', (event) => {
        event.target.querySelectorAll('.department-picker').forEach(renderDepartmentPicklist);
        setupPasswordToggles(event.target);
    });
</script>
@endpush

@endsection

