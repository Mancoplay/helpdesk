<div class="js-report-results">
<div class="card mb-3 js-table-results">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center">
        <h3 class="card-title mb-0">Resultado del reporte</h3>
        <div class="small text-muted report-summary">
            Total de activos: <strong>{{ $summary['total'] ?? 0 }}</strong> |
            Tickets: <strong>{{ $summary['tickets_total'] ?? 0 }}</strong> |
            Periodo: <strong>{{ ($selectedFechaDesde ?? '') !== '' ? $selectedFechaDesde : 'Inicio' }}</strong>
            a
            <strong>{{ ($selectedFechaHasta ?? '') !== '' ? $selectedFechaHasta : 'Hoy' }}</strong>
        </div>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-striped table-hover mb-0">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Correo</th>
                    <th>Departamento</th>
                    <th>Area de trabajo</th>
                    <th>Tickets</th>
                </tr>
            </thead>
            <tbody>
                @forelse($usuarios as $usuario)
                    <tr>
                        <td>{{ $usuario->nombre_completo }}</td>
                        <td>{{ $usuario->email }}</td>
                        <td>{{ $usuario->departamento->nombre ?? '-' }}</td>
                        <td>{{ $usuario->areaTrabajo->nombre ?? '-' }}</td>
                        <td>{{ (int) ($usuario->tickets_total_count ?? 0) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted">Sin resultados para los filtros seleccionados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if(($detalleTicketsUsuario ?? collect())->isNotEmpty() && !empty($usuarioDetalle))
<div class="card mb-3 js-table-results js-user-ticket-detail">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center">
        <h3 class="card-title mb-0">Detalle de tickets del usuario</h3>
        <div class="small text-muted">
            {{ $usuarioDetalle->nombre_completo }} | Total tickets: <strong>{{ $detalleTicketsUsuario->count() }}</strong>
        </div>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-striped table-hover mb-0">
            <thead>
                <tr>
                    <th>Codigo</th>
                    <th>Asunto</th>
                    <th>Departamento</th>
                    <th>Estado</th>
                    <th>{{ $detalleRelacionLabel ?? 'Participacion' }}</th>
                    <th>Fecha</th>
                </tr>
            </thead>
            <tbody>
                @foreach($detalleTicketsUsuario as $ticketDetalle)
                    <tr>
                        <td>{{ $ticketDetalle['codigo'] }}</td>
                        <td>{{ $ticketDetalle['asunto'] }}</td>
                        <td>{{ $ticketDetalle['departamento'] }}</td>
                        <td>{{ ucfirst(str_replace('_', ' ', $ticketDetalle['estado'])) }}</td>
                        <td>{{ $ticketDetalle['relacion'] }}</td>
                        <td>{{ $ticketDetalle['fecha'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
</div>
