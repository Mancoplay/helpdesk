@extends('layouts.app')

@section('title', 'Reporte de usuarios')
@section('header', 'Reporte de usuarios')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item"><a href="{{ route('usuarios.index') }}">Usuarios</a></li>
    <li class="breadcrumb-item active">Reporte</li>
@endsection

@section('content')
<div class="card mb-3 report-hide-print">
    <div class="card-body d-flex flex-wrap align-items-center gap-2">
        <a href="{{ route('usuarios.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Volver a usuarios
        </a>
        <div class="d-flex flex-wrap align-items-center justify-content-end gap-2 ms-auto">
            <div class="text-muted small me-2">
                Generado: {{ $generatedAt->format('d/m/Y H:i') }}
            </div>
            <a href="{{ route('reportes.usuarios') }}" class="btn btn-outline-secondary btn-sm">
                Limpiar
            </a>
            <button type="button" class="btn btn-success btn-sm" onclick="window.print()">
                <i class="fas fa-file-pdf me-1"></i> Guardar reporte (PDF)
            </button>
        </div>
    </div>
</div>

<div class="card mb-3 report-hide-print">
    <div class="card-body">
        <form method="GET" action="{{ route('reportes.usuarios') }}" class="row g-2 align-items-end js-table-filters">
            <div class="col-md-3">
                <label class="form-label mb-1">Buscar</label>
                <input type="text" name="q" class="form-control" value="{{ $searchQuery ?? '' }}" placeholder="Nombre, correo o telefono">
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1">Departamento</label>
                <select name="departamento_id" class="form-select">
                    <option value="">Todos</option>
                    @foreach($departamentosBolivia as $departamento)
                        <option value="{{ $departamento->id }}" @selected((string) ($selectedDepartamentoId ?? '') === (string) $departamento->id)>{{ $departamento->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1">Area de trabajo</label>
                <select name="area_trabajo_id" class="form-select">
                    <option value="">Todas</option>
                    @foreach($areasTrabajoActivas as $area)
                        <option value="{{ $area->id }}" @selected((string) ($selectedAreaTrabajoId ?? '') === (string) $area->id)>{{ $area->nombre }}</option>
                    @endforeach
                </select>
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
                <label class="form-label mb-1">Periodo</label>
                <select name="periodo" class="form-select js-periodo-select">
                    <option value="mes_actual" @selected(($selectedPeriodo ?? 'mes_actual') === 'mes_actual')>Mes actual</option>
                    <option value="mes_anterior" @selected(($selectedPeriodo ?? '') === 'mes_anterior')>Mes anterior</option>
                    <option value="personalizado" @selected(($selectedPeriodo ?? '') === 'personalizado')>Desde / hasta</option>
                </select>
            </div>
            <div class="col-md-2 js-periodo-custom {{ ($selectedPeriodo ?? 'mes_actual') === 'personalizado' ? '' : 'd-none' }}">
                <label class="form-label mb-1">Desde</label>
                <input type="date" name="fecha_desde" class="form-control" value="{{ $selectedFechaDesde ?? '' }}">
            </div>
            <div class="col-md-2 js-periodo-custom {{ ($selectedPeriodo ?? 'mes_actual') === 'personalizado' ? '' : 'd-none' }}">
                <label class="form-label mb-1">Hasta</label>
                <input type="date" name="fecha_hasta" class="form-control" value="{{ $selectedFechaHasta ?? '' }}">
            </div>
        </form>
    </div>
</div>

<section class="report-print-header">
    <h1>REPORTE DE USUARIOS</h1>
    <table class="report-print-meta">
        <tbody>
            <tr>
                <th>Fecha y hora</th>
                <td>{{ $generatedAt->format('d/m/Y H:i') }}</td>
            </tr>
            <tr>
                <th>Periodo</th>
                <td>{{ ($selectedFechaDesde ?? '') !== '' ? $selectedFechaDesde : 'Inicio' }} a {{ ($selectedFechaHasta ?? '') !== '' ? $selectedFechaHasta : 'Hoy' }}</td>
            </tr>
            <tr>
                <th>Total de usuarios</th>
                <td>{{ $summary['total'] ?? 0 }}</td>
            </tr>
            <tr>
                <th>Total de tickets</th>
                <td>{{ $summary['tickets_total'] ?? 0 }}</td>
            </tr>
        </tbody>
    </table>
</section>

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
                    <th>Participacion</th>
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
                        <td>{{ $ticketDetalle['participacion'] }}</td>
                        <td>{{ $ticketDetalle['fecha'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

@endsection

@push('styles')
<style>
.report-print-header {
    display: none;
}

@media print {
    @page {
        size: A4 portrait;
        margin: 8mm 6mm 10mm 6mm;
    }

    .app-header,
    .app-sidebar,
    .app-content-header,
    .app-footer,
    .report-hide-print,
    .pagination {
        display: none !important;
    }

    html,
    body {
        font-size: 13px !important;
        background: #fff !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    .app-main,
    .app-content,
    .container-fluid {
        max-width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        background: #fff !important;
    }

    .card,
    .card-body,
    .card-header,
    .card-footer {
        margin: 0 !important;
        border: 0 !important;
        box-shadow: none !important;
        background: #fff !important;
    }

    .report-print-header {
        display: block !important;
        margin: 0 0 6mm 0 !important;
    }

    .report-print-header h1 {
        margin: 0 0 4mm 0 !important;
        text-align: center !important;
        color: #2f5fa7 !important;
        font-size: 30px !important;
        font-weight: 800 !important;
        letter-spacing: 0.6px !important;
    }

    .report-print-meta {
        width: 100% !important;
        border-collapse: collapse !important;
        margin: 0 auto !important;
        font-size: 13px !important;
    }

    .report-print-meta th,
    .report-print-meta td {
        border: 1px solid #bfc8d4 !important;
        padding: 7px 9px !important;
        text-align: left !important;
    }

    .report-print-meta th {
        width: 34% !important;
        background: #2f5fa7 !important;
        color: #fff !important;
        text-transform: uppercase !important;
        font-size: 11px !important;
        letter-spacing: 0.3px !important;
    }

    .js-table-results {
        margin: 0 0 6mm 0 !important;
        border: 1px solid #c6cfdb !important;
    }

    .js-table-results .card-header {
        padding: 8px 10px !important;
        border-bottom: 1px solid #c6cfdb !important;
        background: #eef3fb !important;
    }

    .js-table-results .card-title {
        font-size: 19px !important;
        font-weight: 700 !important;
    }

    .js-table-results .card-body {
        padding: 0 !important;
    }

    .js-table-results table {
        width: 100% !important;
        border-collapse: collapse !important;
        font-size: 14px !important;
    }

    .js-table-results thead th {
        background: #2f5fa7 !important;
        color: #fff !important;
        font-size: 12px !important;
        text-transform: uppercase !important;
        letter-spacing: 0.2px !important;
        padding: 8px 10px !important;
        border: 1px solid #bfc8d4 !important;
    }

    .js-table-results tbody td {
        padding: 8px 10px !important;
        border: 1px solid #d4dbe4 !important;
        vertical-align: middle !important;
    }

    .js-table-results tbody tr:nth-child(even) td {
        background: #f7f9fc !important;
    }

    .report-summary {
        display: block !important;
        font-size: 12px !important;
        color: #333 !important;
    }
}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('form.js-table-filters').forEach((form) => {
        const searchInput = form.querySelector('input[name="q"]');
        const periodoSelect = form.querySelector('.js-periodo-select');
        const customFields = form.querySelectorAll('.js-periodo-custom');
        const detailCard = document.querySelector('.js-user-ticket-detail');

        if (!periodoSelect || customFields.length === 0) {
            return;
        }

        const toggleCustomRange = () => {
            const isCustom = periodoSelect.value === 'personalizado';
            customFields.forEach((field) => {
                field.classList.toggle('d-none', !isCustom);
                const input = field.querySelector('input[type="date"]');
                if (input && !isCustom) {
                    input.value = '';
                }
            });
        };

        periodoSelect.addEventListener('change', toggleCustomRange);
        if (searchInput && detailCard) {
            const syncDetailVisibility = () => {
                detailCard.classList.toggle('d-none', searchInput.value.trim() === '');
            };

            searchInput.addEventListener('input', syncDetailVisibility);
            searchInput.addEventListener('change', syncDetailVisibility);
            syncDetailVisibility();
        }

        toggleCustomRange();
    });
});
</script>
@endpush
