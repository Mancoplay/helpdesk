@extends('layouts.app')

@section('title', 'Dashboard Help Desk')
@section('header', 'Dashboard')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item active">Dashboard</li>
@endsection

@section('content')
<livewire:dashboard-overview
    :stats="$stats"
    :chart-labels="$chartLabels"
    :chart-values="$chartValues"
    :chart-colors="$chartColors"
    :is-admin="auth()->user()->hasRole('Administrador')"
/>
@endsection
