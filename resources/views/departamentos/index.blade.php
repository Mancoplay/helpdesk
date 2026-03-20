@extends('layouts.app')

@section('title', 'Departamentos')
@section('header', 'Departamentos')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item active">Departamentos</li>
@endsection

@section('content')
<div class="row">
    <div class="col-12">
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
</div>
@endsection
