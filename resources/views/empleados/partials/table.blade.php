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
                    <th>Puntuacion</th>
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
                        <td>
                            @if((int) ($empleado->puntuaciones_count ?? 0) > 0)
                                <span class="badge text-bg-warning text-dark">{{ number_format((float) $empleado->puntuacion_promedio, 2) }}/5</span>
                                <small class="text-muted d-block">{{ (int) $empleado->puntuaciones_count }} calif.</small>
                            @else
                                <span class="text-muted">Sin calificar</span>
                            @endif
                        </td>
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
                    <tr><td colspan="6" class="text-center text-muted">Sin datos</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer compact-pagination">
        {{ $empleados->links() }}
    </div>
</div>
