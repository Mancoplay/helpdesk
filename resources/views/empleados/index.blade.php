@extends('layouts.app')

@section('title', 'Empleados')
@section('header', 'Empleados')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item active">Empleados</li>
@endsection

@section('content')
<div class="row">
    <div class="col-12">
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
</div>
@endsection
