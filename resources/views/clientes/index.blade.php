@extends('layouts.app')

@section('title', 'Clientes')
@section('header', 'Clientes')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item active">Clientes</li>
@endsection

@section('content')
<div class="row">
    <div class="col-12">
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
</div>
@endsection
