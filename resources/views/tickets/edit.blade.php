@extends('layouts.app')

@section('title', 'Editar Ticket')
@section('header', 'Ticket')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item"><a href="{{ route('tickets.index') }}">Tickets</a></li>
    <li class="breadcrumb-item active">Editar</li>
@endsection

@section('content')
@php
    $isAdmin = auth()->user()->hasRole('Administrador');
    $requestTypeLabels = [
        'change_employee' => 'Cambio de empleado',
        'add_employees' => 'Asignacion de empleados',
    ];
    $primarySelectedEmployeeId = (int) old('empleado_id', $ticket->empleado_id);
    $assignmentRequestedById = (int) ($ticket->assignment_request_by_id ?? 0);
    $selectedAssignedEmployeeIds = collect(old('assigned_employee_ids', $assignedEmployeeIds ?? []))
        ->map(fn ($employeeId) => (int) $employeeId)
        ->filter(fn (int $employeeId) => $employeeId > 0 && $employeeId !== $primarySelectedEmployeeId)
        ->reject(fn (int $employeeId) => $ticket->assignment_request_type === 'change_employee' && $employeeId === $assignmentRequestedById)
        ->unique()
        ->values();
@endphp
<div class="row justify-content-center">
    <div class="col-12 col-xl-10 col-xxl-9">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <h3 class="card-title mb-0">Editar ticket</h3>
                    </div>
                    <span class="badge text-bg-light border">#{{ old('codigo', $ticket->codigo) }}</span>
                </div>
            </div>
            <div class="card-body p-4">
                @if($isAdmin && $ticket->assignment_request_type)
                    <div class="alert alert-warning">
                        <strong>{{ $requestTypeLabels[$ticket->assignment_request_type] ?? 'Solicitud de asignacion' }}:</strong>
                        {{ $ticket->assignmentRequestBy->nombre_completo ?? $ticket->assignmentRequestBy->name ?? 'Un empleado' }}
                        solicito revisar la asignacion de este ticket.
                    </div>
                @endif

                <form method="POST" action="{{ route('tickets.update', $ticket) }}" class="row g-4">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="return_url" value="{{ $returnUrl ?? route('tickets.show', $ticket) }}">

                    <div class="col-12">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Codigo</label>
                                <input type="text" name="codigo" class="form-control" value="{{ old('codigo', $ticket->codigo) }}" readonly>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Estado</label>
                                @if($isAdmin)
                                    <select name="estado" class="form-select" required>
                                        <option value="pendiente" @selected(old('estado', $ticket->estado) == 'pendiente')>Pendiente</option>
                                        <option value="en_proceso" @selected(old('estado', $ticket->estado) == 'en_proceso')>En proceso</option>
                                        <option value="finalizado" @selected(old('estado', $ticket->estado) == 'finalizado')>Finalizado</option>
                                        <option value="cerrado" @selected(old('estado', $ticket->estado) == 'cerrado')>Cerrado</option>
                                    </select>
                                @else
                                    <input type="text" class="form-control" value="{{ str_replace('_', ' ', old('estado', $ticket->estado)) }}" readonly>
                                @endif
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Departamento</label>
                                @php
                                    $selectedDepartment = $departamentos->firstWhere('id', old('departamento_id', $ticket->departamento_id));
                                @endphp
                                @if($isAdmin)
                                    <input type="hidden" name="departamento_id" value="{{ old('departamento_id', $ticket->departamento_id) }}">
                                @endif
                                <input type="text" class="form-control" value="{{ $selectedDepartment?->nombre ?? ($ticket->departamento->nombre ?? '-') }}" readonly>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="border rounded-3 p-3 p-md-4 bg-light-subtle">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Usuario</label>
                                    @php
                                        $selectedClient = $clientes->firstWhere('id', old('cliente_id', $ticket->cliente_id));
                                    @endphp
                                    @if($isAdmin)
                                        <input type="hidden" name="cliente_id" value="{{ old('cliente_id', $ticket->cliente_id) }}">
                                    @endif
                                    <input type="text" class="form-control" value="{{ $selectedClient?->nombre_completo ?? ($ticket->cliente->nombre_completo ?? '-') }}" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Empleado</label>
                                    @if($isAdmin)
                                        <select name="empleado_id" class="form-select">
                                            <option value="">Sin asignar</option>
                                            @foreach($empleados as $empleado)
                                                <option value="{{ $empleado->id }}" @selected(old('empleado_id', $ticket->empleado_id) == $empleado->id)>{{ $empleado->nombre_completo }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        @php
                                            $selectedEmployee = $empleados->firstWhere('id', old('empleado_id', $ticket->empleado_id));
                                        @endphp
                                        <input type="text" class="form-control" value="{{ $selectedEmployee?->nombre_completo ?? ($ticket->empleado->nombre_completo ?? 'Sin asignar') }}" readonly>
                                    @endif
                                </div>
                                @if($isAdmin)
                                    <div class="col-12">
                                        <label class="form-label">Empleados adicionales</label>
                                        <div class="row g-2">
                                            <div class="col-md-8">
                                                <select id="additionalEmployeeSelect" class="form-select">
                                                    <option value="">Seleccione un empleado</option>
                                                    @foreach($empleados as $empleado)
                                                        <option value="{{ $empleado->id }}">{{ $empleado->nombre_completo }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <button type="button" id="addAdditionalEmployeeBtn" class="btn btn-outline-primary w-100">Agregar</button>
                                            </div>
                                        </div>
                                        <div id="additionalEmployeesList" class="border rounded p-2 mt-2 bg-white" data-empty-text="Sin empleados adicionales.">
                                            @foreach($selectedAssignedEmployeeIds as $selectedAssignedEmployeeId)
                                                @php
                                                    $selectedAdditionalEmployee = $empleados->firstWhere('id', $selectedAssignedEmployeeId);
                                                @endphp
                                                @if($selectedAdditionalEmployee)
                                                    <span class="badge text-bg-light border text-dark me-1 mb-1 p-2 js-additional-employee-chip" data-employee-id="{{ $selectedAdditionalEmployee->id }}">
                                                        {{ $selectedAdditionalEmployee->nombre_completo }}
                                                        <button type="button" class="btn-close btn-close-sm ms-2 js-remove-additional-employee" aria-label="Quitar"></button>
                                                        <input type="hidden" name="assigned_employee_ids[]" value="{{ $selectedAdditionalEmployee->id }}">
                                                    </span>
                                                @endif
                                            @endforeach
                                        </div>
                                        @error('assigned_employee_ids')
                                            <div class="text-danger small mt-1">{{ $message }}</div>
                                        @enderror
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Asunto</label>
                                <input
                                    type="text"
                                    name="asunto"
                                    class="form-control"
                                    value="{{ old('asunto', $ticket->asunto) }}"
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
                                    rows="4"
                                    minlength="3"
                                    required
                                    oninvalid="this.setCustomValidity('Debe ingresar minimo 3 caracteres.')"
                                    oninput="this.setCustomValidity('')"
                                >{{ old('descripcion', $ticket->descripcion) }}</textarea>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="d-flex flex-wrap justify-content-end gap-2 pt-2">
                            <a href="{{ $returnUrl ?? route('tickets.show', $ticket) }}" class="btn btn-secondary px-4">Cancelar</a>
                            <button type="submit" class="btn btn-primary px-4">Guardar cambios</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@if($isAdmin)
@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const primaryEmployeeSelect = document.querySelector('select[name="empleado_id"]');
        const additionalSelect = document.getElementById('additionalEmployeeSelect');
        const addButton = document.getElementById('addAdditionalEmployeeBtn');
        const list = document.getElementById('additionalEmployeesList');

        if (!additionalSelect || !addButton || !list) {
            return;
        }

        const renderEmptyState = function () {
            let emptyState = list.querySelector('.js-additional-empty');
            const hasEmployees = Boolean(list.querySelector('.js-additional-employee-chip'));

            if (hasEmployees && emptyState) {
                emptyState.remove();
                return;
            }

            if (!hasEmployees && !emptyState) {
                emptyState = document.createElement('span');
                emptyState.className = 'text-muted small js-additional-empty';
                emptyState.textContent = list.dataset.emptyText || 'Sin empleados adicionales.';
                list.appendChild(emptyState);
            }
        };

        const selectedAdditionalIds = function () {
            return Array.from(list.querySelectorAll('input[name="assigned_employee_ids[]"]'))
                .map(function (input) {
                    return String(input.value);
                });
        };

        const syncOptions = function () {
            const selectedIds = selectedAdditionalIds();
            const primaryId = String(primaryEmployeeSelect?.value || '');

            additionalSelect.querySelectorAll('option').forEach(function (option) {
                if (!option.value) {
                    return;
                }

                option.disabled = selectedIds.includes(String(option.value)) || option.value === primaryId;
            });

            if (additionalSelect.value && additionalSelect.selectedOptions[0]?.disabled) {
                additionalSelect.value = '';
            }
        };

        const addSelectedEmployee = function () {
            const option = additionalSelect.selectedOptions[0];
            const employeeId = String(additionalSelect.value || '');

            if (!option || !employeeId || option.disabled || selectedAdditionalIds().includes(employeeId)) {
                return;
            }

            const chip = document.createElement('span');
            chip.className = 'badge text-bg-light border text-dark me-1 mb-1 p-2 js-additional-employee-chip';
            chip.dataset.employeeId = employeeId;
            chip.append(document.createTextNode(option.textContent.trim() + ' '));

            const removeButton = document.createElement('button');
            removeButton.type = 'button';
            removeButton.className = 'btn-close btn-close-sm ms-2 js-remove-additional-employee';
            removeButton.setAttribute('aria-label', 'Quitar');
            chip.appendChild(removeButton);

            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'assigned_employee_ids[]';
            hiddenInput.value = employeeId;
            chip.appendChild(hiddenInput);

            list.appendChild(chip);
            additionalSelect.value = '';
            renderEmptyState();
            syncOptions();
        };

        addButton.addEventListener('click', addSelectedEmployee);

        list.addEventListener('click', function (event) {
            const button = event.target.closest('.js-remove-additional-employee');
            if (!button) {
                return;
            }

            button.closest('.js-additional-employee-chip')?.remove();
            renderEmptyState();
            syncOptions();
        });

        primaryEmployeeSelect?.addEventListener('change', function () {
            const primaryId = String(primaryEmployeeSelect.value || '');

            list.querySelectorAll('.js-additional-employee-chip').forEach(function (chip) {
                if (String(chip.dataset.employeeId || '') === primaryId) {
                    chip.remove();
                }
            });

            renderEmptyState();
            syncOptions();
        });

        renderEmptyState();
        syncOptions();
    });
</script>
@endpush
@endif
@endsection
