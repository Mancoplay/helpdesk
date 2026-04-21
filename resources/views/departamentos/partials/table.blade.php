<div class="card js-table-results">
    <div class="card-header"><h3 class="card-title mb-0">Tabla de areas de trabajo</h3></div>
    <div class="card-body table-responsive p-0">
        <table class="table table-striped table-hover mb-0">
            <thead><tr><th>Nombre</th><th>Descripcion</th><th>Activo</th><th style="width:220px;">Accion</th></tr></thead>
            <tbody>
            @forelse($departamentos as $departamento)
                <tr data-active-row="{{ $departamento->id }}">
                    <td>{{ $departamento->nombre }}</td>
                    <td>{{ $departamento->descripcion ?? '-' }}</td>
                    <td>
                        <span
                            class="badge text-bg-{{ $departamento->activo ? 'success' : 'secondary' }}"
                            data-active-badge
                        >
                            {{ $departamento->activo ? 'Si' : 'No' }}
                        </span>
                    </td>
                    <td>
                        <button class="btn btn-warning btn-sm me-1" data-bs-toggle="modal" data-bs-target="#editDepartamentoModal{{ $departamento->id }}">Editar</button>
                        <form class="d-inline" method="POST" action="{{ route('departamentos.checkpoint', $departamento) }}" data-sync-active-target="{{ $departamento->id }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="checkpoint-switch {{ $departamento->activo ? 'is-on' : 'is-off' }}" title="{{ $departamento->activo ? 'Habilitado' : 'Deshabilitado' }}">
                                <span class="checkpoint-switch__label">{{ $departamento->activo ? 'ON' : 'OFF' }}</span>
                                <span class="checkpoint-switch__knob"></span>
                            </button>
                        </form>
                    </td>
                </tr>

                <div class="modal fade" id="editDepartamentoModal{{ $departamento->id }}" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content"><form method="POST" action="{{ route('departamentos.update', $departamento) }}">@csrf @method('PUT')<div class="modal-header"><h5 class="modal-title">Editar area de trabajo</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="row g-2"><div class="col-md-6"><label class="form-label">Nombre</label><input type="text" name="nombre" class="form-control" value="{{ $departamento->nombre }}" required></div><div class="col-md-6"><label class="form-label">Descripcion</label><input type="text" name="descripcion" class="form-control" value="{{ $departamento->descripcion }}"></div><div class="col-md-6"><label class="form-label">Activo</label><select name="activo" class="form-select" data-edit-active-select="{{ $departamento->id }}"><option value="1" @selected($departamento->activo)>Si</option><option value="0" @selected(!$departamento->activo)>No</option></select></div></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Guardar</button></div></form></div></div></div>
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
