@extends('layouts.app')

@section('title', 'Dashboard Help Desk')
@section('header', 'Dashboard')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item active">Dashboard</li>
@endsection

@section('content')
<div class="row g-3 mb-3">
    <div class="col-lg-3 col-md-6">
        <div class="card dashboard-stat h-100">
            <div class="card-body d-flex align-items-center">
                <div class="icon bg-secondary"><i class="fas fa-users"></i></div>
                <div>
                    <div class="label">Total Clientes</div>
                    <p class="value">{{ $stats['total_clientes'] }}</p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="card dashboard-stat h-100">
            <div class="card-body d-flex align-items-center">
                <div class="icon bg-info"><i class="fas fa-user-tie"></i></div>
                <div>
                    <div class="label">Total Empleados</div>
                    <p class="value">{{ $stats['total_empleados'] }}</p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="card dashboard-stat h-100">
            <div class="card-body d-flex align-items-center">
                <div class="icon bg-primary"><i class="fas fa-building"></i></div>
                <div>
                    <div class="label">Total Departamentos</div>
                    <p class="value">{{ $stats['total_departamentos'] }}</p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="card dashboard-stat h-100">
            <div class="card-body d-flex align-items-center">
                <div class="icon bg-navy"><i class="fas fa-ticket-alt"></i></div>
                <div>
                    <div class="label">Total Tickets</div>
                    <p class="value">{{ $stats['total_tickets'] }}</p>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-3 col-md-6">
        <div class="card dashboard-stat h-100">
            <div class="card-body d-flex align-items-center">
                <div class="icon bg-warning"><i class="fas fa-hourglass-half"></i></div>
                <div>
                    <div class="label">Tickets pendientes</div>
                    <p class="value">{{ $stats['pendientes'] }}</p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="card dashboard-stat h-100">
            <div class="card-body d-flex align-items-center">
                <div class="icon" style="background-color:#6f42c1;"><i class="fas fa-spinner"></i></div>
                <div>
                    <div class="label">Tickets en proceso</div>
                    <p class="value">{{ $stats['en_proceso'] }}</p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="card dashboard-stat h-100">
            <div class="card-body d-flex align-items-center">
                <div class="icon bg-success"><i class="fas fa-check-circle"></i></div>
                <div>
                    <div class="label">Tickets finalizados</div>
                    <p class="value">{{ $stats['finalizado'] }}</p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="card dashboard-stat h-100">
            <div class="card-body d-flex align-items-center">
                <div class="icon bg-danger"><i class="fas fa-times-circle"></i></div>
                <div>
                    <div class="label">Tickets cerrados</div>
                    <p class="value">{{ $stats['cerrado'] }}</p>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-6 mx-auto">
        <div class="card card-graph">
            <div class="card-header">
                <h3 class="card-title mb-0">Grafico de Tickets</h3>
            </div>
            <div class="card-body">
                <canvas id="ticketsChart" height="220"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><h3 class="card-title mb-0">Tabla de Clientes</h3></div>
            <div class="card-body table-responsive p-0">
                <table class="table table-striped table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Email</th>
                            <th>Telefono</th>
                            <th>Empresa</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($clientes as $cliente)
                            <tr>
                                <td>{{ $cliente->nombre_completo }}</td>
                                <td>{{ $cliente->email }}</td>
                                <td>{{ $cliente->telefono ?? '-' }}</td>
                                <td>{{ $cliente->empresa ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted">Sin datos</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><h3 class="card-title mb-0">Tabla de Empleados</h3></div>
            <div class="card-body table-responsive p-0">
                <table class="table table-striped table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Email</th>
                            <th>Cargo</th>
                            <th>Departamento</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($empleados as $empleado)
                            <tr>
                                <td>{{ $empleado->nombre_completo }}</td>
                                <td>{{ $empleado->email }}</td>
                                <td>{{ $empleado->cargo ?? '-' }}</td>
                                <td>{{ $empleado->departamento->nombre ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted">Sin datos</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><h3 class="card-title mb-0">Tabla de Departamentos</h3></div>
            <div class="card-body table-responsive p-0">
                <table class="table table-striped table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Descripcion</th>
                            <th>Activo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($departamentos as $departamento)
                            <tr>
                                <td>{{ $departamento->nombre }}</td>
                                <td>{{ $departamento->descripcion ?? '-' }}</td>
                                <td>
                                    <span class="badge text-bg-{{ $departamento->activo ? 'success' : 'secondary' }}">
                                        {{ $departamento->activo ? 'Si' : 'No' }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-muted">Sin datos</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><h3 class="card-title mb-0">Tabla de Tickets</h3></div>
            <div class="card-body table-responsive p-0">
                <table class="table table-striped table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Codigo</th>
                            <th>Asunto</th>
                            <th>Cliente</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($tickets as $ticket)
                            <tr>
                                <td>{{ $ticket->codigo }}</td>
                                <td>{{ $ticket->asunto }}</td>
                                <td>{{ $ticket->cliente->nombre_completo ?? '-' }}</td>
                                <td>
                                    @php
                                        $stateMap = config('adminlte.ticket_states');
                                        $badgeType = $stateMap[$ticket->estado]['badge'] ?? 'secondary';
                                    @endphp
                                    <span class="badge text-bg-{{ $badgeType }}">{{ str_replace('_', ' ', $ticket->estado) }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted">Sin datos</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const canvas = document.getElementById('ticketsChart');
        if (!canvas) return;

        new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: @json($chartLabels),
                datasets: [{
                    data: @json($chartValues),
                    backgroundColor: @json($chartColors),
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '62%',
                plugins: {
                    legend: {
                        position: 'top'
                    }
                }
            }
        });
    });
</script>
@endpush
