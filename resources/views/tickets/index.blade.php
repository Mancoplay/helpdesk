@extends('layouts.app')

@section('title', 'Tickets')
@section('header', 'Tickets')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item active">Tickets</li>
@endsection

@section('content')
<div class="row">
    <div class="col-12">
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
