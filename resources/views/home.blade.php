@extends('layouts.app')

@section('title', 'Dashboard Help Desk')
@section('header', 'Dashboard')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item active">Dashboard</li>
@endsection

@section('content')
@php
    $isAdmin = auth()->user()->hasRole('Administrador');
@endphp

@if($isAdmin)
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
                <div class="icon" style="background-color:#0d6efd;"><i class="fas fa-ticket-alt"></i></div>
                <div>
                    <div class="label">Total Tickets</div>
                    <p class="value">{{ $stats['total_tickets'] }}</p>
                </div>
            </div>
        </div>
    </div>
</div>
@else
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
    <div class="col-lg-3 col-md-6">
        <div class="card dashboard-stat h-100">
            <div class="card-body d-flex align-items-center">
                <div class="icon" style="background-color:#0d6efd;"><i class="fas fa-ticket-alt"></i></div>
                <div>
                    <div class="label">Total Tickets</div>
                    <p class="value">{{ $stats['total_tickets'] }}</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endif

<div class="row g-3 mb-3">
    @if($isAdmin)
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
    @endif


<div class="row g-3">
    <div class="col-lg-8 mx-auto">
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
