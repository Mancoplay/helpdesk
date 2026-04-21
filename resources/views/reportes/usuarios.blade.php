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
        <form method="GET" action="{{ route('reportes.usuarios') }}" class="row g-2 align-items-end js-report-filters">
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

@include('reportes.partials.usuarios-results')

@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/reportes-usuarios-print.css') }}?v=20260419-1">
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('form.js-report-filters').forEach((form) => {
        const searchInput = form.querySelector('input[name="q"]');
        const periodoSelect = form.querySelector('.js-periodo-select');
        const customFields = form.querySelectorAll('.js-periodo-custom');
        let resultsWrapper = document.querySelector('.js-report-results');
        let searchTimer = null;
        let activeController = null;

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

        const loadReportResults = (targetUrl = null) => {
            if (!resultsWrapper) {
                form.submit();
                return;
            }

            const params = new URLSearchParams(new FormData(form));
            params.delete('page');

            if (targetUrl) {
                const parsedTargetUrl = new URL(targetUrl, window.location.origin);
                const page = parsedTargetUrl.searchParams.get('page');
                if (page) {
                    params.set('page', page);
                }
            }

            const requestUrl = `${form.action}?${params.toString()}`;

            if (activeController) {
                activeController.abort();
            }

            activeController = new AbortController();

            fetch(requestUrl, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
                signal: activeController.signal,
            })
                .then((response) => response.text())
                .then((html) => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const newResultsWrapper = doc.querySelector('.js-report-results');

                    if (!newResultsWrapper || !resultsWrapper) {
                        window.location.href = requestUrl;
                        return;
                    }

                    resultsWrapper.replaceWith(newResultsWrapper);
                    resultsWrapper = newResultsWrapper;
                    history.replaceState({}, '', requestUrl);
                })
                .catch((error) => {
                    if (error.name === 'AbortError') {
                        return;
                    }

                    window.location.href = requestUrl;
                });
        };

        const scheduleResultsLoad = (delayMs = 700) => {
            if (searchTimer) {
                clearTimeout(searchTimer);
            }

            searchTimer = setTimeout(() => loadReportResults(), delayMs);
        };

        periodoSelect.addEventListener('change', toggleCustomRange);
        if (searchInput) {
            searchInput.addEventListener('input', () => {
                const value = searchInput.value.trim();
                const currentDetail = document.querySelector('.js-user-ticket-detail');

                if (currentDetail && value === '') {
                    currentDetail.classList.add('d-none');
                }

                if (value.length === 1) {
                    return;
                }

                scheduleResultsLoad(700);
            });

            searchInput.addEventListener('keydown', (event) => {
                if (event.key !== 'Enter') {
                    return;
                }

                event.preventDefault();
                if (searchTimer) {
                    clearTimeout(searchTimer);
                }
                loadReportResults();
            });
        }

        form.querySelectorAll('select[name], input[type="date"][name]').forEach((field) => {
            field.addEventListener('change', () => {
                if (searchTimer) {
                    clearTimeout(searchTimer);
                }
                loadReportResults();
            });
        });

        document.addEventListener('click', (event) => {
            const paginationLink = event.target.closest('.js-report-results .pagination a[href]');
            if (!paginationLink) {
                return;
            }

            event.preventDefault();
            if (searchTimer) {
                clearTimeout(searchTimer);
            }
            loadReportResults(paginationLink.href);
        });

        if (searchInput && searchInput.value.trim() === '') {
            const currentDetail = document.querySelector('.js-user-ticket-detail');
            if (currentDetail) {
                currentDetail.classList.add('d-none');
            }
        }

        toggleCustomRange();
    });
});
</script>
@endpush
