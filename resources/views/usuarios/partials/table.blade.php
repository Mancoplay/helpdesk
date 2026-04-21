<div class="card js-table-results">
    <div class="card-header">
        <h3 class="card-title mb-0">Tabla de usuarios</h3>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-striped table-hover mb-0">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Correo</th>
                    <th>Departamento</th>
                    <th>Area de trabajo</th>
                    <th>Rol</th>
                    <th style="width:220px;">Accion</th>
                </tr>
            </thead>
            <tbody>
            @forelse($usuarios as $usuario)
                @php
                    $rolActual = $usuario->getRoleNames()->first() ?? '-';
                @endphp
                <tr>
                    <td>{{ $usuario->nombre_completo }}</td>
                    <td>{{ $usuario->email }}</td>
                    <td>{{ $usuario->departamento->nombre ?? '-' }}</td>
                    <td>{{ $usuario->areaTrabajo->nombre ?? '-' }}</td>
                    <td>
                        <span class="badge text-bg-{{ $rolActual === 'Administrador' ? 'danger' : ($rolActual === 'Empleado' ? 'primary' : 'secondary') }}">
                            {{ $rolActual }}
                        </span>
                    </td>
                    <td class="text-nowrap">
                        <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editUsuarioModal{{ $usuario->id }}">Editar</button>
                        <form class="d-inline mb-0" method="POST" action="{{ route('usuarios.checkpoint', $usuario) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="checkpoint-switch {{ $usuario->activo ? 'is-on' : 'is-off' }}" title="{{ $usuario->activo ? 'Habilitado' : 'Deshabilitado' }}">
                                <span class="checkpoint-switch__label">{{ $usuario->activo ? 'ON' : 'OFF' }}</span>
                                <span class="checkpoint-switch__knob"></span>
                            </button>
                        </form>
                    </td>
                </tr>

                <div class="modal fade" id="editUsuarioModal{{ $usuario->id }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-centered">
                        <div class="modal-content">
                            <form method="POST" action="{{ route('usuarios.update', $usuario) }}">
                                @csrf
                                @method('PUT')
                                <div class="modal-header">
                                    <h5 class="modal-title">Editar usuario</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="row g-2">
                                        <div class="col-md-6">
                                            <label class="form-label">Nombre(s)</label>
                                            <input type="text" name="nombres" class="form-control" value="{{ $usuario->nombres }}" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Apellidos</label>
                                            <input type="text" name="apellidos" class="form-control" value="{{ $usuario->apellidos }}" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Correo</label>
                                            <input type="email" name="email" class="form-control" value="{{ $usuario->email }}" required maxlength="255" pattern="^[^\s@]+@[^\s@]+\.[^\s@]+$">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Contacto</label>
                                            <input type="text" name="telefono" class="form-control" value="{{ $usuario->telefono }}" inputmode="numeric" maxlength="8" pattern="(?:[67][0-9]{7}|[234][0-9]{6})" oninput="this.value=this.value.replace(/[^0-9]/g,'');">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Departamento</label>
                                            <select name="departamento_id" class="form-select" required>
                                                @foreach($departamentosBolivia as $departamento)
                                                    <option value="{{ $departamento->id }}" @selected((int) $usuario->departamento_id === (int) $departamento->id)>{{ $departamento->nombre }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Area de trabajo</label>
                                            <select name="area_trabajo_id" class="form-select" required>
                                                @foreach($areasTrabajoActivas as $area)
                                                    <option value="{{ $area->id }}" @selected((int) $usuario->area_trabajo_id === (int) $area->id)>{{ $area->nombre }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Rol</label>
                                            <select name="rol" class="form-select" required>
                                                @foreach($rolesDisponibles as $rol)
                                                    <option value="{{ $rol }}" @selected($rolActual === $rol)>{{ $rol }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-12">
                                            <label class="form-label">Contrasena (opcional)</label>
                                            <div class="input-group">
                                                <input type="password" name="password" class="form-control js-password-input" autocomplete="new-password">
                                                <button type="button" class="btn btn-outline-secondary js-password-toggle" aria-label="Mostrar contrasena">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="col-md-12">
                                            <label class="form-label">Confirmar contrasena</label>
                                            <div class="input-group">
                                                <input type="password" name="password_confirmation" class="form-control js-password-input" autocomplete="new-password">
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
            @empty
                <tr><td colspan="6" class="text-center text-muted">Sin datos</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer compact-pagination">
        {{ $usuarios->links() }}
    </div>
</div>
