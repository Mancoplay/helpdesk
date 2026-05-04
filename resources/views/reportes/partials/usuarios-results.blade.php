<div
    class="js-report-results"
    data-print-tickets-label="{{ $printSummary['tickets_label'] ?? 'Total de tickets' }}"
    data-print-tickets-total="{{ $printSummary['tickets_total'] ?? 0 }}"
    data-print-period-from="{{ $selectedFechaDesde ?? '' }}"
    data-print-period-to="{{ $selectedFechaHasta ?? '' }}"
>
@php($showEmployeeRatingColumn = ($selectedRole ?? '') === 'Empleado')
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
                    @if($showEmployeeRatingColumn)
                        <th>Puntuacion</th>
                    @endif
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
                        @if($showEmployeeRatingColumn)
                            <td>
                                @if((int) ($usuario->puntuaciones_count ?? 0) > 0)
                                    <span class="badge text-bg-warning text-dark">{{ number_format((float) $usuario->puntuacion_promedio, 2) }}/5</span>
                                    <small class="text-muted d-block">{{ (int) $usuario->puntuaciones_count }} calif.</small>
                                @else
                                    <span class="text-muted">Sin calificar</span>
                                @endif
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="{{ $showEmployeeRatingColumn ? 6 : 5 }}" class="text-center text-muted">Sin resultados para los filtros seleccionados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if(!empty($usuarioDetalle))
@php($showDetailRatingColumn = ($selectedRole ?? '') === 'Empleado')
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
                    @if($showDetailRatingColumn)
                        <th>Puntuacion</th>
                    @endif
                    <th>Fecha</th>
                </tr>
            </thead>
            <tbody>
                @forelse($detalleTicketsUsuario as $ticketDetalle)
                    <tr>
                        <td>{{ $ticketDetalle['codigo'] }}</td>
                        <td>{{ $ticketDetalle['asunto'] }}</td>
                        <td>{{ $ticketDetalle['departamento'] }}</td>
                        <td>{{ ucfirst(str_replace('_', ' ', $ticketDetalle['estado'])) }}</td>
                        <td>{{ $ticketDetalle['relacion'] }}</td>
                        @if($showDetailRatingColumn)
                            <td>
                                @if(!is_null($ticketDetalle['puntuacion']))
                                    <span class="badge text-bg-warning text-dark">{{ (int) $ticketDetalle['puntuacion'] }}/5</span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                        @endif
                        <td>{{ $ticketDetalle['fecha'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $showDetailRatingColumn ? 7 : 6 }}" class="text-center text-muted">Sin tickets para el periodo seleccionado.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endif
</div>
