@extends('layouts.app')

@section('title', 'Ver ticket')
@section('header', 'Ver ticket')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item"><a href="{{ route('tickets.index') }}">Tickets</a></li>
    <li class="breadcrumb-item active">{{ $ticket->codigo }}</li>
@endsection

@section('content')
<div class="row g-3">
    <div class="col-lg-5">
        <div class="card border-success">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">Ticket</h3>
                <div>
                    @php
                        $stateMap = config('adminlte.ticket_states');
                        $badgeType = $stateMap[$ticket->estado]['badge'] ?? 'secondary';
                    @endphp
                    <span class="badge text-bg-{{ $badgeType }}">{{ str_replace('_', ' ', $ticket->estado) }}</span>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <strong>Asunto</strong>
                        <div>{{ $ticket->asunto }}</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Departamento</strong>
                        <div>{{ $ticket->departamento->nombre ?? '-' }}</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Cliente</strong>
                        <div>{{ $ticket->cliente->nombre_completo ?? '-' }}</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Empleado</strong>
                        <div>{{ $ticket->empleado->nombre_completo ?? 'Sin asignar' }}</div>
                    </div>
                </div>
                <hr>
                <div>
                    <strong>Descripcion</strong>
                    <p class="mb-0">{{ $ticket->descripcion }}</p>
                </div>
            </div>

            @can('atender tickets')
                @if($ticket->estado === 'pendiente')
                    <div class="card-footer">
                        <form method="POST" action="{{ route('tickets.attend', $ticket) }}" onsubmit="return confirm('Estas seguro de que quieres atender este ticket? El estado cambiara a \"En proceso\" y se asignara a ti.');">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="btn btn-info">Atender ticket</button>
                        </form>
                    </div>
                @endif

                @if(
                    auth()->user()->hasRole('Empleado')
                    && in_array($ticket->estado, ['pendiente', 'en_proceso'], true)
                    && (int) ($ticket->empleado_id ?? 0) === (int) (optional(auth()->user()->empleado)->id ?? 0)
                )
                    <div class="card-footer">
                        <form method="POST" action="{{ route('tickets.finalize', $ticket) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="btn btn-success fs-5">Finalizar ticket</button>
                        </form>
                    </div>
                @endif
            @endcan

            @php
                $isClientOwner = auth()->user()->hasAnyRole(['Cliente', 'Usuario'])
                    && (($ticket->cliente->email ?? null) === auth()->user()->email);
                $isAssignedEmployee = auth()->user()->hasRole('Empleado')
                    && (int) ($ticket->empleado_id ?? 0) === (int) (optional(auth()->user()->empleado)->id ?? 0);
            @endphp

            @if($isClientOwner || $isAssignedEmployee)
                <div class="card-footer border-top">
                    <h6 class="mb-2">Soporte remoto (simulado)</h6>

                    @if(!$remoteEnabled)
                        <div class="alert alert-secondary py-2 mb-0">
                            Funcionalidad remota no disponible todavia. Falta ejecutar migraciones de base de datos.
                        </div>
                    @elseif($ticket->estado === 'pendiente')
                        <div class="alert alert-warning py-2 mb-0">
                            La conexion remota estara disponible cuando el ticket sea atendido y pase a <strong>En proceso</strong>.
                        </div>
                    @elseif($ticket->estado === 'finalizado')
                        <div class="alert alert-secondary py-2 mb-0">
                            Ticket finalizado: la conexion remota esta bloqueada.
                        </div>
                    @elseif(!$remoteSession || in_array($remoteSession->status, ['rejected', 'cancelled', 'ended'], true))
                        @if($isAssignedEmployee)
                            <form method="POST" action="{{ route('tickets.remote.request', $ticket) }}">
                                @csrf
                                <button type="submit" class="btn btn-outline-primary w-100">Conectar</button>
                            </form>
                            <small class="text-muted d-block mt-2">El cliente recibira una solicitud para autorizar el control remoto.</small>
                        @else
                            <small class="text-muted">Aun no hay solicitud activa de soporte remoto.</small>
                        @endif
                    @elseif($remoteSession->status === 'pending')
                        <div class="alert alert-warning py-2 mb-2">
                            Solicitud pendiente de aprobacion del cliente.
                        </div>

                        @if($isClientOwner)
                            <div class="row g-2">
                                <div class="col-6">
                                    <form id="remote-accept-form" method="POST" action="{{ route('tickets.remote.update', [$ticket, $remoteSession]) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="action" value="accept">
                                        <button type="submit" class="btn btn-success w-100">Aceptar</button>
                                    </form>
                                </div>
                                <div class="col-6">
                                    <form method="POST" action="{{ route('tickets.remote.update', [$ticket, $remoteSession]) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="action" value="reject">
                                        <button type="submit" class="btn btn-danger w-100">Rechazar</button>
                                    </form>
                                </div>
                            </div>
                        @endif
                    @elseif($remoteSession->status === 'accepted')
                        <div class="alert alert-success py-2 mb-2">
                            Conexion autorizada por el cliente.
                        </div>
                        <button type="button" class="btn btn-primary w-100 mb-2" data-bs-toggle="modal" data-bs-target="#remoteSupportModal">
                            Abrir ventana de conexion
                        </button>
                        @if($isAssignedEmployee)
                            <form id="endRemoteSessionForm" method="POST" action="{{ route('tickets.remote.update', [$ticket, $remoteSession]) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="action" value="end">
                                <button type="button" id="endRemoteSessionBtn" class="btn btn-outline-dark w-100">Finalizar conexion</button>
                            </form>
                        @endif
                    @endif
                </div>
            @endif
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card border-primary">
            <div class="card-header">
                <h3 class="card-title mb-0">Comunicacion</h3>
            </div>
            <div class="card-body">
                <div class="border rounded p-2 mb-3" style="max-height: 360px; overflow-y: auto;">
                    @php
                        $chatTimezone = config('app.timezone', 'America/La_Paz');
                    @endphp
                    @forelse($messages as $mensaje)
                        @php
                            $tipoBadge = match($mensaje->tipo) {
                                'creacion' => 'primary',
                                'atencion' => 'info',
                                default => 'secondary',
                            };
                        @endphp
                        <div class="mb-3 pb-2 border-bottom">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <strong>{{ $mensaje->user->name ?? 'Sistema' }}</strong>
                                    <span class="badge text-bg-{{ $tipoBadge }}">{{ $mensaje->tipo }}</span>
                                </div>
                                <small class="text-muted">
                                    {{ $mensaje->created_at?->copy()->setTimezone($chatTimezone)->format('d/m/Y H:i') }} (UTC-04)
                                </small>
                            </div>
                            @if(!empty($mensaje->mensaje))
                                <p class="mb-1">{{ $mensaje->mensaje }}</p>
                            @endif
                            @if($mensaje->imagen_path)
                                <a href="{{ asset('storage/' . $mensaje->imagen_path) }}" target="_blank" rel="noopener">
                                    <img
                                        src="{{ asset('storage/' . $mensaje->imagen_path) }}"
                                        alt="Adjunto"
                                        class="img-fluid rounded border"
                                        style="max-height: 180px;"
                                    >
                                </a>
                            @endif
                        </div>
                    @empty
                        <p class="text-muted mb-0">Sin mensajes por el momento.</p>
                    @endforelse
                </div>

                @php
                    $mustAttendFirst = auth()->user()->hasRole('Empleado') && $ticket->estado === 'pendiente';
                @endphp

                @if($mustAttendFirst)
                    <div class="alert alert-warning mb-0">
                        Este ticket aun no esta siendo atendido. Presiona <strong>Atender ticket</strong> para habilitar el chat y la carga de imagenes.
                    </div>
                @elseif($ticket->estado === 'finalizado')
                    <div class="alert alert-secondary mb-0">
                        Ticket finalizado. El chat esta bloqueado y ya no se permiten comentarios.
                    </div>
                @else
                    <form method="POST" action="{{ route('tickets.messages.store', $ticket) }}" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-2">
                            <label class="form-label">Nuevo comentario</label>
                            <textarea name="mensaje" class="form-control" rows="3" placeholder="Escribe un mensaje...">{{ old('mensaje') }}</textarea>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Adjuntar imagen (opcional)</label>
                            <input type="file" name="imagen" class="form-control" accept=".jpg,.jpeg,.png,.webp,image/*">
                            <small class="text-muted">Maximo 4 MB. Formatos: JPG, PNG, WEBP.</small>
                        </div>
                        <div class="text-center">
                            <button type="submit" class="btn btn-primary btn-sm fs-4 w-100">Enviar</button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>

@if($remoteEnabled && $remoteSession && $remoteSession->status === 'accepted')
<div class="modal fade" id="remoteSupportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Conexion remota del ticket {{ $ticket->codigo }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info mb-3">
                    Sesion autorizada por el cliente en <strong>AnyDesk</strong>.
                </div>

                <div class="row g-2 align-items-end">
                    <div class="col-md-8">
                        <label class="form-label mb-1">Codigo de AnyDesk</label>
                        @if($isClientOwner)
                            <form method="POST" action="{{ route('tickets.remote.update', [$ticket, $remoteSession]) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="action" value="share_code">
                                <div class="input-group">
                                    <input
                                        type="text"
                                        id="remoteSupportCode"
                                        name="support_code"
                                        class="form-control"
                                        value="{{ old('support_code', $remoteSession->support_code) }}"
                                        maxlength="40"
                                        placeholder="Ej: 123 456 789"
                                        required
                                    >
                                    <button type="submit" class="btn btn-primary">Enviar codigo</button>
                                </div>
                            </form>
                        @else
                            <div class="input-group">
                                <input type="text" id="remoteSupportCode" class="form-control" value="{{ $remoteSession->support_code }}" readonly>
                                @if($isAssignedEmployee)
                                    <button type="button" class="btn btn-outline-primary" id="copyRemoteCodeBtn">Copiar</button>
                                @endif
                            </div>
                        @endif
                    </div>
                    <div class="col-md-4">
                        <a href="anydesk:{{ $remoteSession->support_code }}" class="btn btn-outline-dark w-100">Abrir AnyDesk</a>
                    </div>
                </div>

                @if($isClientOwner || $isAssignedEmployee)
                    <form class="mt-2" method="POST" action="{{ route('tickets.remote.update', [$ticket, $remoteSession]) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="action" value="signal_closed">
                        <button type="submit" class="btn btn-outline-secondary w-100">Ya se cerro AnyDesk</button>
                    </form>
                @endif
                <hr>
                <p class="mb-1"><strong>Pasos rapidos</strong></p>
                <ol class="mb-0">
                    <li>El cliente comparte su codigo de AnyDesk.</li>
                    <li>El empleado abre AnyDesk y pega el codigo.</li>
                    <li>El cliente confirma el permiso de control remoto.</li>
                </ol>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const copyButton = document.getElementById('copyRemoteCodeBtn');
        const codeElement = document.getElementById('remoteSupportCode');
        const endButton = document.getElementById('endRemoteSessionBtn');
        const endForm = document.getElementById('endRemoteSessionForm');

        if (copyButton && codeElement && navigator.clipboard) {
            copyButton.addEventListener('click', function () {
                const code = codeElement.value.trim();
                if (!code) {
                    return;
                }

                navigator.clipboard.writeText(code).then(function () {
                    copyButton.textContent = 'Copiado';
                    setTimeout(function () {
                        copyButton.textContent = 'Copiar';
                    }, 1500);
                });
            });
        }

        if (endButton && endForm) {
            endButton.addEventListener('click', function () {
                if (!confirm('Confirma que deseas finalizar la conexion remota.')) {
                    return;
                }

                window.location.href = 'anydesk:';
                setTimeout(function () {
                    endForm.submit();
                }, 300);
            });
        }
    });
</script>
@endpush
@endif
@endsection


