@extends('layouts.app')

@section('title', 'Tickets')
@section('header', 'Lista de tickets')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item active">Tickets</li>
@endsection

@section('content')
@if(auth()->user()->can('crear tickets'))
<div class="card mb-3">
    <div class="card-body">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createTicketModal">
            <i class="fas fa-plus me-1"></i> Agregar nuevo ticket
        </button>
    </div>
</div>
@endif

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('tickets.index') }}" class="row g-2 align-items-end js-table-filters">
            <div class="col-md-8">
                <label class="form-label mb-1">Buscar</label>
                <input type="text" name="q" class="form-control" value="{{ $searchQuery ?? '' }}" placeholder="Ejemplo: red, TCK-0001, pendiente...">
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1">Registros</label>
                <select name="per_page" class="form-select">
                    @foreach([10, 15] as $size)
                        <option value="{{ $size }}" @selected(($perPage ?? 10) == $size)>{{ $size }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <a href="{{ route('tickets.index') }}" class="btn btn-outline-secondary w-100">Limpiar</a>
            </div>
        </form>
    </div>
</div>

@include('tickets.partials.table')

@if(auth()->user()->can('crear tickets'))
<div class="modal fade" id="createTicketModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('tickets.store') }}" id="createTicketForm">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Nuevo ticket</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Codigo</label>
                            <input type="text" name="codigo" id="codigoTicket" class="form-control" value="{{ old('codigo', $nextTicketCode) }}" readonly>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Asunto</label>
                            <input
                                type="text"
                                name="asunto"
                                class="form-control"
                                value="{{ old('asunto') }}"
                                minlength="3"
                                required
                                oninvalid="this.setCustomValidity('Debe ingresar minimo 3 caracteres.')"
                                oninput="this.setCustomValidity('')"
                            >
                        </div>
                        <div class="col-12">
                            <label class="form-label">Descripcion</label>
                            <textarea
                                name="descripcion"
                                class="form-control"
                                rows="3"
                                minlength="3"
                                required
                                oninvalid="this.setCustomValidity('Debe ingresar minimo 3 caracteres.')"
                                oninput="this.setCustomValidity('')"
                            >{{ old('descripcion') }}</textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const createTicketModal = document.getElementById('createTicketModal');

        if (createTicketModal) {
            createTicketModal.addEventListener('show.bs.modal', function () {
                fetch("{{ route('tickets.next-code') }}")
                    .then(function (response) {
                        return response.json();
                    })
                    .then(function (data) {
                        const codeInput = createTicketModal.querySelector('input[name="codigo"]');
                        if (codeInput && data && data.codigo) {
                            codeInput.value = data.codigo;
                        }
                    })
                    .catch(function (error) {
                        console.error('No se pudo obtener el siguiente codigo de ticket:', error);
                    });
            });
        }

        const refreshTableResults = function () {
            if (document.querySelector('.modal.show')) {
                return;
            }

            const tableContainer = document.querySelector('.js-table-results');
            if (!tableContainer) {
                return;
            }

            const queryString = window.location.search || '';
            const url = "{{ route('tickets.index') }}" + queryString;

            fetch(url, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(function (response) {
                    return response.text();
                })
                .then(function (html) {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const freshTable = doc.querySelector('.js-table-results');

                    if (!freshTable) {
                        return;
                    }

                    tableContainer.innerHTML = freshTable.innerHTML;
                })
                .catch(function (error) {
                    console.error('No se pudo actualizar la tabla de tickets:', error);
                });
        };

        const searchParams = new URLSearchParams(window.location.search || '');
        const hasActiveSearch = (searchParams.get('q') || '').trim() !== '';

        if (!hasActiveSearch) {
            setInterval(function () {
                if (!document.hidden) {
                    refreshTableResults();
                }
            }, 45000);
        }

        document.addEventListener('hidden.bs.modal', function () {
            if (!document.hidden) {
                refreshTableResults();
            }
        });
    });
</script>
@endpush

@endsection
