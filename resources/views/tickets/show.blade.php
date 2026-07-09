@extends('layouts.app')

@section('title', 'Ver ticket')
@section('header', 'Ver ticket')
@section('show_back_button', '1')
@section('back_url', route('tickets.index'))
@push('styles')
    @vite('resources/sass/pages/ticket-show.scss')
@endpush

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item"><a href="{{ route('tickets.index') }}">Tickets</a></li>
    <li class="breadcrumb-item active">{{ $ticket->codigo }}</li>
@endsection

@section('content')
@php
    $currentEmployee = \App\Models\Empleado::query()
        ->whereKey(auth()->id())
        ->orWhere('email', auth()->user()->email)
        ->first();
    $currentEmployeeId = (int) ($currentEmployee->id ?? 0);
    $additionalAssignedIds = collect($ticket->assigned_employee_ids ?? [])
        ->map(fn ($employeeId) => (int) $employeeId);
    $isAdmin = auth()->user()->hasRole('Administrador');
    $isAssignedEmployee = auth()->user()->hasRole('Empleado')
        && (
            (int) ($ticket->empleado_id ?? 0) === $currentEmployeeId
            || $additionalAssignedIds->contains($currentEmployeeId)
        );
    $assignedEmployeeDisplay = ($assignedEmployeeNames ?? collect())->isNotEmpty()
        ? $assignedEmployeeNames->implode(', ')
        : ($ticket->empleado->nombre_completo ?? 'Sin asignar');
@endphp
<div class="row g-3 ticket-detail-page">
    <div class="col-lg-5">
        <div class="card border-success ticket-panel ticket-panel-detail">
            <div class="card-header ticket-summary-header d-flex align-items-center position-relative">
                <h3 class="card-title mb-0">Ticket</h3>
                @php
                    $stateMap = config('adminlte.ticket_states');
                    $badgeType = $stateMap[$ticket->estado]['badge'] ?? 'secondary';
                @endphp
                <div class="ticket-state-center position-absolute top-50 start-50 translate-middle">
                    <span id="ticketStateBadge" class="badge text-bg-{{ $badgeType }}">{{ str_replace('_', ' ', $ticket->estado) }}</span>
                </div>
                <div class="ticket-header-actions ms-auto">
                    @if($isAssignedEmployee && in_array($ticket->estado, ['pendiente', 'en_proceso'], true))
                        <button type="button" class="btn btn-warning btn-sm ticket-assignment-request-btn" data-bs-toggle="modal" data-bs-target="#assignmentRequestModal">
                            <i class="fas fa-paper-plane me-1"></i>
                            Solicitar
                        </button>
                    @endif
                </div>
            </div>
            <div class="card-body">
                <div class="row ticket-detail-fields">
                    <div class="col-md-6 mb-3">
                        <strong>Asunto</strong>
                        <div>{{ $ticket->asunto }}</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Departamento</strong>
                        <div>{{ $ticket->departamento->nombre ?? '-' }}</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Usuario</strong>
                        <div>{{ $ticket->cliente->nombre_completo ?? '-' }}</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Empleado</strong>
                        <div id="ticketAssignedEmployee">{{ $assignedEmployeeDisplay }}</div>
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
                        <form method="POST" action="{{ route('tickets.attend', $ticket) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="btn btn-info">Atender ticket</button>
                        </form>
                    </div>
                @endif
            @endcan

            @php
                $currentEmployee = \App\Models\Empleado::query()
                    ->whereKey(auth()->id())
                    ->orWhere('email', auth()->user()->email)
                    ->first();
                $currentEmployeeId = (int) ($currentEmployee->id ?? 0);
                $isAdmin = auth()->user()->hasRole('Administrador');
                $isClientOwner = auth()->user()->hasAnyRole(['Cliente', 'Usuario'])
                    && (
                        (int) ($ticket->cliente->id ?? 0) === (int) auth()->id()
                        || (($ticket->cliente->email ?? null) === auth()->user()->email)
                    );
                $isAssignedEmployee = auth()->user()->hasRole('Empleado')
                    && (
                        (int) ($ticket->empleado_id ?? 0) === $currentEmployeeId
                        || collect($ticket->assigned_employee_ids ?? [])->map(fn ($employeeId) => (int) $employeeId)->contains($currentEmployeeId)
                    );
                $canManageRemoteAsClient = $isClientOwner || $isAdmin;
                $canManageRemoteAsEmployee = $isAssignedEmployee || $isAdmin;
                $canFinalizeTicketHere = (
                    $isAdmin
                    && in_array($ticket->estado, ['pendiente', 'en_proceso'], true)
                ) || (
                    auth()->user()->hasRole('Empleado')
                    && in_array($ticket->estado, ['pendiente', 'en_proceso'], true)
                    && (int) ($ticket->empleado_id ?? 0) === $currentEmployeeId
                );
            @endphp

            @if($ticket->estado === 'finalizado' && $isClientOwner && (int) ($ticket->empleado_id ?? 0) > 0)
                @if(!is_null($ticket->atencion_puntuacion))
                    <div class="card-footer border-top">
                        <div class="alert alert-success mb-0">
                            Calificaste esta atencion con <strong>{{ (int) $ticket->atencion_puntuacion }}/5</strong>.
                        </div>
                    </div>
                @endif
            @endif

            @if($isClientOwner || $isAssignedEmployee || $isAdmin)
                <div class="card-footer border-top">
                    <h6 class="mb-2">Soporte remoto</h6>

                    @if(!$remoteEnabled)
                        <div class="alert alert-secondary py-2 mb-0">
                            Funcionalidad remota no disponible todavía. Falta ejecutar migraciones de base de datos.
                        </div>
                    @elseif($ticket->estado === 'pendiente')
                        <div class="alert alert-warning py-2 mb-0">
                            La conexión remota estará disponible cuando el ticket sea atendido y pase a <strong>En proceso</strong>.
                        </div>
                    @elseif($ticket->estado === 'finalizado')
                        <div class="alert alert-secondary py-2 mb-0">
                            Ticket finalizado: la conexión remota está bloqueada.
                        </div>
                    @elseif(!$remoteSession || in_array($remoteSession->status, ['rejected', 'cancelled', 'ended'], true))
                        @if($canManageRemoteAsEmployee)
                            <form method="POST" action="{{ route('tickets.remote.request', $ticket) }}">
                                @csrf
                                <button type="submit" class="btn btn-outline-primary w-100">Conectar</button>
                            </form>
                            <small class="text-muted d-block mt-2">El usuario recibira una solicitud para autorizar el control remoto.</small>
                        @else
                            <small class="text-muted">Aún no hay solicitud activa de soporte remoto.</small>
                        @endif
                    @elseif($remoteSession->status === 'pending')
                        <div class="alert alert-warning py-2 mb-2">
                            Solicitud pendiente de aprobacion del usuario.
                        </div>

                        @if($canManageRemoteAsClient)
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
                            Conexión autorizada por el usuario.
                        </div>
                        <button type="button" class="btn btn-primary w-100 mb-2" data-bs-toggle="modal" data-bs-target="#remoteSupportModal">
                            Abrir panel de conexión
                        </button>
                        @if($canManageRemoteAsEmployee)
                            <form id="endRemoteSessionForm" method="POST" action="{{ route('tickets.remote.update', [$ticket, $remoteSession]) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="action" value="end">
                                <button type="button" id="endRemoteSessionBtn" class="btn btn-outline-dark w-100">Finalizar conexión</button>
                            </form>
                        @endif
                    @endif
                </div>
            @endif

            @can('atender tickets')
                @if($canFinalizeTicketHere)
                    <div class="card-footer border-top">
                        <form method="POST" action="{{ route('tickets.finalize', $ticket) }}" id="finalizeTicketForm">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="btn btn-success fs-5 w-100">Finalizar ticket</button>
                        </form>
                    </div>
                @endif
            @endcan
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card border-primary ticket-panel ticket-panel-chat">
            <div class="card-header">
                <h3 class="card-title mb-0">Comunicacion</h3>
            </div>
            <div class="card-body d-flex flex-column ticket-chat-card-body">
                <div class="ticket-chat-scroll-wrap mb-2">
                    <div id="ticketChatScroll" class="ticket-chat-scroll border rounded p-3" data-last-message-id="{{ (int) ($messages->max('id') ?? 0) }}">
                        @php
                            $chatTimezone = config('app.timezone', 'America/La_Paz');
                        @endphp
                        @forelse($messages as $mensaje)
                            @php
                                $isOwnMessage = (int) ($mensaje->user_id ?? 0) === (int) auth()->id();
                            @endphp
                            <div class="ticket-chat-message {{ $isOwnMessage ? 'mine' : '' }}">
                                <div class="ticket-chat-bubble">
                                    <div class="d-flex justify-content-between align-items-center gap-2 mb-1">
                                        <div>
                                            <strong>{{ $mensaje->user->name ?? 'Sistema' }}</strong>
                                        </div>
                                        <span class="ticket-chat-meta">
                                            {{ $mensaje->created_at?->copy()->setTimezone($chatTimezone)->format('d/m/Y H:i') }}
                                        </span>
                                    </div>
                                    @if(!empty($mensaje->mensaje))
                                        <p class="mb-1">{{ $mensaje->mensaje }}</p>
                                    @endif
                                    @if($mensaje->imagen_path)
                                        @php
                                            $attachmentUrl = route('tickets.attachments.show', [$ticket, $mensaje]);
                                            $isImageAttachment = str_starts_with((string) ($mensaje->imagen_mime ?? ''), 'image/');
                                        @endphp
                                        @if($isImageAttachment)
                                            <a href="{{ $attachmentUrl }}" target="_blank" rel="noopener">
                                                <img
                                                    src="{{ $attachmentUrl }}"
                                                    alt="Adjunto"
                                                    class="img-fluid rounded border ticket-chat-image"
                                                >
                                            </a>
                                        @else
                                            <a href="{{ $attachmentUrl }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">
                                                <i class="fas fa-paperclip me-1"></i>
                                                {{ $mensaje->imagen_nombre ?? 'Descargar archivo' }}
                                            </a>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        @empty
                            <p class="text-muted mb-0">Sin mensajes por el momento.</p>
                        @endforelse
                    </div>
                    <button type="button" id="ticketChatUnreadBadge" class="ticket-chat-unread-badge" hidden aria-live="polite" aria-label="Ver mensajes nuevos">
                        <span id="ticketChatUnreadCount">0</span>
                    </button>
                </div>

                @php
                    $mustAttendFirst = auth()->user()->hasRole('Empleado') && $ticket->estado === 'pendiente';
                @endphp

                <div class="ticket-chat-composer">
                    @if($mustAttendFirst)
                        <div class="alert alert-warning mb-0">
                            Este ticket aún no está siendo atendido. Presiona <strong>Atender ticket</strong> para habilitar el chat y la carga de imágenes.
                        </div>
                    @elseif($ticket->estado === 'finalizado')
                        <div class="alert alert-secondary mb-0">
                            Ticket finalizado. El chat esta bloqueado y ya no se permiten comentarios.
                        </div>
                    @else
                        <form id="chatComposerForm" method="POST" action="{{ route('tickets.messages.store', $ticket) }}" enctype="multipart/form-data">
                            @csrf
                            <div class="mb-2">
                                <div class="chat-composer-header">
                                    <label class="form-label mb-0">Nuevo comentario</label>
                                    <button type="button" class="btn btn-outline-secondary chat-plus-btn" id="chatAttachmentBtn" title="Adjuntar archivo">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                                <textarea id="chatMessageInput" name="mensaje" class="form-control" rows="3" placeholder="Escribe un mensaje...">{{ old('mensaje') }}</textarea>
                                <input
                                    id="chatAttachmentInput"
                                    type="file"
                                    name="adjuntos[]"
                                    class="d-none"
                                    multiple
                                    accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip,.rar,.7z,.csv,image/*,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation,text/plain,application/zip,application/x-rar-compressed"
                                >
                                <div id="chatSelectedFiles" class="chat-selected-files"></div>
                            </div>
                            <small class="text-muted d-block mb-2">Adjunta imagen, documento o archivo.</small>
                            <div class="text-center">
                                <button type="submit" class="btn btn-primary btn-sm fs-4 w-100">Enviar</button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@if($isAssignedEmployee && in_array($ticket->estado, ['pendiente', 'en_proceso'], true))
<div class="modal fade" id="assignmentRequestModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('tickets.assignment-request', $ticket) }}">
                @csrf
                @method('PATCH')
                <div class="modal-header">
                    <h5 class="modal-title">Solicitud para administracion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="d-grid gap-2">
                        <input type="radio" class="btn-check" name="request_type" id="requestChangeEmployee" value="change_employee" required>
                        <label class="btn btn-outline-primary text-start" for="requestChangeEmployee">
                            Cambiar de empleado
                        </label>

                        <input type="radio" class="btn-check" name="request_type" id="requestAddEmployees" value="add_employees" required>
                        <label class="btn btn-outline-primary text-start" for="requestAddEmployees">
                            Asignar mas empleados
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning">Enviar solicitud</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@if($ticket->estado === 'finalizado' && $isClientOwner && (int) ($ticket->empleado_id ?? 0) > 0 && is_null($ticket->atencion_puntuacion))
<div class="modal fade" id="rateTicketModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('tickets.rate', $ticket) }}" id="rateTicketForm">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Calificar atencion</h5>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-3">
                        Selecciona una puntuacion para la atencion recibida por {{ $ticket->empleado->nombre_completo ?? 'el empleado' }}.
                    </p>
                    <div class="row g-2">
                        @php
                            $ratingLabels = [
                                1 => 'Mala',
                                2 => 'Regular',
                                3 => 'Aceptable',
                                4 => 'Buena',
                                5 => 'Excelente',
                            ];
                        @endphp
                        @foreach($ratingLabels as $score => $ratingLabel)
                            <div class="col">
                                <input class="btn-check" type="radio" name="puntuacion" id="ticketRating{{ $score }}" value="{{ $score }}" required>
                                <label class="btn btn-outline-warning w-100 py-3 ticket-rating-option" for="ticketRating{{ $score }}">
                                    <span class="d-block fs-4 fw-bold">{{ $score }}</span>
                                    <span class="d-block small text-dark">{{ $ratingLabel }}</span>
                                </label>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Enviar</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@if($ticket->estado === 'finalizado' && $isClientOwner && (int) ($ticket->empleado_id ?? 0) > 0 && is_null($ticket->atencion_puntuacion))
@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const modalElement = document.getElementById('rateTicketModal');
        if (!modalElement) {
            return;
        }

        if (window.bootstrap?.Modal) {
            window.bootstrap.Modal.getOrCreateInstance(modalElement, {
                backdrop: 'static',
                keyboard: false,
            }).show();
            return;
        }

        modalElement.classList.add('show');
        modalElement.removeAttribute('aria-hidden');
        modalElement.setAttribute('aria-modal', 'true');
        modalElement.setAttribute('role', 'dialog');
        modalElement.style.display = 'block';
        document.body.classList.add('modal-open');

        if (!document.querySelector('.modal-backdrop')) {
            const backdrop = document.createElement('div');
            backdrop.className = 'modal-backdrop fade show';
            document.body.appendChild(backdrop);
        }
    });
</script>
@endpush
@endif

@if($remoteEnabled && $remoteSession && $remoteSession->status === 'accepted')
<div class="modal fade remote-support-modal" id="remoteSupportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Conexión remota del ticket {{ $ticket->codigo }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info mb-3">
                    Sesión autorizada por el usuario. Puedes usar <strong>AnyDesk</strong> o <strong>RustDesk</strong>.
                </div>
                <div class="remote-support-tools">
                    <div class="remote-support-tool">
                        <label class="form-label mb-1">Código de AnyDesk</label>
                        <div class="remote-code-entry">
                        @if($canManageRemoteAsClient || $canManageRemoteAsEmployee)
                            <form id="remoteShareCodeForm" method="POST" action="{{ route('tickets.remote.update', [$ticket, $remoteSession]) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="action" value="share_code">
                                <div class="input-group remote-code-group">
                                    <input
                                        type="text"
                                        id="remoteSupportCode"
                                        name="support_code"
                                        class="form-control"
                                        value="{{ old('support_code', $remoteSupportCode ?? $remoteSession->support_code) }}"
                                        maxlength="40"
                                        placeholder="Ej: 123456789"
                                        inputmode="numeric"
                                        pattern="[0-9]+"
                                    >
                                    @if(!$canManageRemoteAsEmployee || $canManageRemoteAsClient)
                                        <button type="submit" id="sendSupportCodeBtn" class="btn btn-success">Enviar código</button>
                                    @endif
                                </div>
                                <small id="remoteSupportCodeStatus" class="d-block mt-2" style="min-height: 1.25rem;"></small>
                            </form>
                        @else
                            <div class="input-group remote-code-group">
                                <input
                                    type="text"
                                    id="remoteSupportCode"
                                    class="form-control"
                                    value="{{ $remoteSupportCode ?? $remoteSession->support_code }}"
                                    readonly
                                    maxlength="40"
                                    placeholder="Ej: 123456789"
                                    inputmode="numeric"
                                    pattern="[0-9]+"
                                >
                            </div>
                        @endif
                        </div>
                        <button
                            type="button"
                            id="openCopyAnyDeskBtn"
                            class="btn btn-outline-dark w-100 mt-3"
                            {{ blank($remoteSupportCode ?? $remoteSession->support_code) && !$canManageRemoteAsClient && !$canManageRemoteAsEmployee ? 'disabled' : '' }}
                        >
                            Abrir AnyDesk
                        </button>
                    </div>
                    <div class="remote-support-tool">
                        <label class="form-label mb-1">Código de RustDesk</label>
                        <div class="remote-code-entry">
                        @if($canManageRemoteAsClient || $canManageRemoteAsEmployee)
                            <form id="remoteRustDeskShareCodeForm" method="POST" action="{{ route('tickets.remote.update', [$ticket, $remoteSession]) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="action" value="share_code">
                                <div class="input-group remote-code-group">
                                    <input
                                        type="text"
                                        id="remoteRustDeskCode"
                                        name="rustdesk_code"
                                        class="form-control"
                                        value="{{ old('rustdesk_code', $remoteRustDeskCode ?? $remoteSession->rustdesk_code) }}"
                                        maxlength="80"
                                        placeholder="Ej: 123456789"
                                        autocomplete="off"
                                    >
                                    @if(!$canManageRemoteAsEmployee || $canManageRemoteAsClient)
                                        <button type="submit" id="sendRustDeskCodeBtn" class="btn btn-success">Enviar código</button>
                                    @endif
                                </div>
                            </form>
                        @else
                            <div class="input-group remote-code-group">
                                <input
                                    type="text"
                                    id="remoteRustDeskCode"
                                    class="form-control"
                                    value="{{ $remoteRustDeskCode ?? $remoteSession->rustdesk_code }}"
                                    readonly
                                    maxlength="80"
                                    placeholder="Ej: 123456789"
                                >
                            </div>
                        @endif
                        </div>
                        <button
                            type="button"
                            id="openCopyRustDeskBtn"
                            class="btn btn-outline-dark w-100 mt-3"
                            {{ blank($remoteRustDeskCode ?? $remoteSession->rustdesk_code) && !$canManageRemoteAsClient && !$canManageRemoteAsEmployee ? 'disabled' : '' }}
                        >
                            Abrir RustDesk
                        </button>
                    </div>
                    @if($canManageRemoteAsClient)
                        <div class="remote-support-tool remote-support-tool--full">
                            <form id="closeAnyDeskForm" method="POST" action="{{ route('tickets.remote.update', [$ticket, $remoteSession]) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="action" value="signal_closed">
                                <!-- <button type="button" id="closeAnyDeskBtn" class="btn btn-danger w-100">Cerrar sesión remota</button> -->
                            </form>
                        </div>
                    @endif
                </div>
                <hr>
                <p class="mb-1"><strong>Pasos rápidos</strong></p>
                <ol class="mb-0">
                    <li>Usa el botón de AnyDesk o RustDesk para abrir la app y copiar el código.</li>
                    <li>Comparte o pega el código para iniciar la conexión remota.</li>
                </ol>
                <p class="mt-3 mb-0 d-flex flex-wrap gap-3">
                    <a href="https://anydesk.com/es/downloads/windows" target="_blank" rel="noopener noreferrer">
                        Descarga AnyDesk aquí
                    </a>
                    <a href="https://rustdesk.com/" target="_blank" rel="noopener noreferrer">
                        Descarga RustDesk aquí
                    </a>
                </p>
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
        const codeElement = document.getElementById('remoteSupportCode');
        const rustDeskCodeElement = document.getElementById('remoteRustDeskCode');
        const openCopyAnyDeskBtn = document.getElementById('openCopyAnyDeskBtn');
        const openCopyRustDeskBtn = document.getElementById('openCopyRustDeskBtn');
        const shareCodeForm = document.getElementById('remoteShareCodeForm');
        const rustDeskShareCodeForm = document.getElementById('remoteRustDeskShareCodeForm');
        const supportCodeStatus = document.getElementById('remoteSupportCodeStatus');
        const sendSupportCodeBtn = document.getElementById('sendSupportCodeBtn');
        const sendRustDeskCodeBtn = document.getElementById('sendRustDeskCodeBtn');
        const endRemoteSessionBtn = document.getElementById('endRemoteSessionBtn');
        const endRemoteSessionForm = document.getElementById('endRemoteSessionForm');
        const closeAnyDeskBtn = document.getElementById('closeAnyDeskBtn');
        const closeAnyDeskForm = document.getElementById('closeAnyDeskForm');
        const ticketId = {{ (int) $ticket->id }};
        const currentRemoteSessionId = {{ (int) $remoteSession->id }};
        const remoteLiveUrl = @json(route('tickets.live', $ticket));
        const canManageRemoteAsClient = @json($canManageRemoteAsClient);
        const canManageRemoteAsEmployee = @json($canManageRemoteAsEmployee);
        const canEditRemoteCode = @json($canManageRemoteAsClient || $canManageRemoteAsEmployee);
        const shouldOpenRemoteAppOnly = canManageRemoteAsClient && !canManageRemoteAsEmployee;
        let saveTimer = null;
        let saveRequestController = null;
        let remoteSyncInFlight = false;
        let lastSavedCode = codeElement ? String(codeElement.value || '').replace(/\D+/g, '').trim() : '';
        let lastSavedRustDeskCode = rustDeskCodeElement ? String(rustDeskCodeElement.value || '').replace(/[^A-Za-z0-9_-]+/g, '').trim() : '';
        const hasManualSubmitButton = Boolean(sendSupportCodeBtn);
        const hasManualRustDeskSubmitButton = Boolean(sendRustDeskCodeBtn);

        const openAnyDesk = function (code) {
            const rawCode = (code || '').trim();
            const cleanCode = rawCode.replace(/\s+/g, '');
            window.location.href = cleanCode ? `anydesk:${cleanCode}` : 'anydesk:';
        };

        const openRustDesk = function (code) {
            const rawCode = (code || '').trim();
            const cleanCode = rawCode.replace(/\s+/g, '');
            window.location.href = cleanCode ? `rustdesk:${cleanCode}` : 'rustdesk:';
        };

        const copyText = function (text) {
            const value = (text || '').trim();
            if (!value) {
                return Promise.resolve(false);
            }

            if (navigator.clipboard && window.isSecureContext) {
                return navigator.clipboard.writeText(value).then(function () {
                    return true;
                }).catch(function () {
                    return false;
                });
            }

            const helper = document.createElement('textarea');
            helper.value = value;
            helper.setAttribute('readonly', '');
            helper.style.position = 'fixed';
            helper.style.left = '-9999px';
            document.body.appendChild(helper);
            helper.select();
            const copied = document.execCommand('copy');
            document.body.removeChild(helper);
            return Promise.resolve(copied);
        };

        if (openCopyAnyDeskBtn && codeElement) {
            codeElement.addEventListener('input', function () {
                const digitsOnly = String(codeElement.value || '').replace(/\D+/g, '');
                if (codeElement.value !== digitsOnly) {
                    codeElement.value = digitsOnly;
                }
            });

            openCopyAnyDeskBtn.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();

                if (typeof codeElement.setCustomValidity === 'function') {
                    codeElement.setCustomValidity('');
                }

                const code = codeElement.value.trim();

                if (shouldOpenRemoteAppOnly) {
                    openAnyDesk('');
                    return;
                }

                if (!code) {
                    openAnyDesk('');
                    return;
                }

                copyText(code).finally(function () {
                    openAnyDesk(code);
                });
            });
        }

        if (openCopyRustDeskBtn && rustDeskCodeElement) {
            rustDeskCodeElement.addEventListener('input', function () {
                const cleanValue = String(rustDeskCodeElement.value || '').replace(/[^A-Za-z0-9_-]+/g, '');
                if (rustDeskCodeElement.value !== cleanValue) {
                    rustDeskCodeElement.value = cleanValue;
                }
            });

            openCopyRustDeskBtn.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();

                const code = rustDeskCodeElement.value.trim();

                if (shouldOpenRemoteAppOnly) {
                    openRustDesk('');
                    return;
                }

                if (!code) {
                    openRustDesk('');
                    return;
                }

                copyText(code).finally(function () {
                    openRustDesk(code);
                });
            });
        }

        const setSupportCodeStatus = function (message, tone) {
            if (!supportCodeStatus) {
                return;
            }

            supportCodeStatus.textContent = message;
            supportCodeStatus.classList.remove('text-muted', 'text-success', 'text-danger');
            if (message) {
                supportCodeStatus.classList.add(tone || 'text-muted');
            }
        };

        const getFriendlySupportCodeError = function (error) {
            const rawMessage = String(error?.message || '').trim();
            const normalizedMessage = rawMessage.toLowerCase();

            if (
                normalizedMessage.includes('htmlinputelement')
                || normalizedMessage.includes('could not be found')
                || normalizedMessage.includes('route ')
            ) {
                return '';
            }

            if (rawMessage !== '') {
                return rawMessage;
            }

            return '';
        };

        const syncRemoteCodeFields = function (remoteData) {
            if (!remoteData || Number(remoteData.id || 0) !== Number(currentRemoteSessionId || 0)) {
                return;
            }

            const supportCode = String(remoteData.support_code || '').trim();
            const rustDeskCode = String(remoteData.rustdesk_code || '').trim();
            const isEditingAnyDesk = codeElement && document.activeElement === codeElement;
            const isEditingRustDesk = rustDeskCodeElement && document.activeElement === rustDeskCodeElement;

            lastSavedCode = supportCode;
            lastSavedRustDeskCode = rustDeskCode;

            if (codeElement && !isEditingAnyDesk && String(codeElement.value || '').trim() !== supportCode) {
                codeElement.value = supportCode;
            }

            if (rustDeskCodeElement && !isEditingRustDesk && String(rustDeskCodeElement.value || '').trim() !== rustDeskCode) {
                rustDeskCodeElement.value = rustDeskCode;
            }

            if (openCopyAnyDeskBtn) {
                openCopyAnyDeskBtn.disabled = supportCode === '' && !canEditRemoteCode;
            }

            if (openCopyRustDeskBtn) {
                openCopyRustDeskBtn.disabled = rustDeskCode === '' && !canEditRemoteCode;
            }
        };

        window.__ticketRemoteCodeSyncByTicket = window.__ticketRemoteCodeSyncByTicket || {};
        window.__ticketRemoteCodeSyncByTicket[ticketId] = syncRemoteCodeFields;

        window.addEventListener('helpdesk:remote-code-updated', function (event) {
            if (Number(event.detail?.ticketId || 0) !== Number(ticketId || 0)) {
                return;
            }

            syncRemoteCodeFields(event.detail?.remote || null);
        });

        const pollRemoteCodes = function () {
            if (document.hidden || remoteSyncInFlight) {
                return;
            }

            remoteSyncInFlight = true;

            fetch(`${remoteLiveUrl}?since_message_id=0&t=${Date.now()}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('remote-live-failed');
                    }

                    return response.json();
                })
                .then(function (payload) {
                    if (payload && payload.ok === true) {
                        syncRemoteCodeFields(payload.remote || null);
                    }
                })
                .catch(function () {
                    // El polling global tambien reintentara; aqui evitamos molestar al usuario.
                })
                .finally(function () {
                    remoteSyncInFlight = false;
                });
        };

        const saveSupportCode = function () {
            if (!shareCodeForm || !codeElement) {
                return;
            }

            const code = String(codeElement.value || '').replace(/\D+/g, '').trim();
            codeElement.value = code;

            if (!code) {
                setSupportCodeStatus('Escribe un código numérico para compartirlo.', 'text-muted');
                return;
            }

            if (code === lastSavedCode) {
                setSupportCodeStatus('Código sincronizado.', 'text-success');
                return;
            }

            if (saveRequestController) {
                saveRequestController.abort();
            }

            saveRequestController = new AbortController();
            setSupportCodeStatus('Guardando código...', 'text-muted');

            const payload = new FormData(shareCodeForm);
            payload.set('support_code', code);

            fetch(shareCodeForm.action, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: payload,
                signal: saveRequestController.signal
            })
                .then(function (response) {
                    return response.json().then(function (payload) {
                        if (!response.ok) {
                            throw new Error(payload.message || 'No se pudo guardar el código.');
                        }

                        return payload;
                    });
                })
                .then(function (payload) {
                    const savedCode = String(payload?.remote?.support_code || code).trim();
                    lastSavedCode = savedCode;
                    codeElement.value = savedCode;
                    setSupportCodeStatus('Código sincronizado.', 'text-success');
                })
                .catch(function (error) {
                    if (error.name === 'AbortError') {
                        return;
                    }

                    setSupportCodeStatus(getFriendlySupportCodeError(error), 'text-danger');
                })
                .finally(function () {
                    saveRequestController = null;
                });
        };

        const saveRustDeskCode = function () {
            if (!rustDeskShareCodeForm || !rustDeskCodeElement) {
                return;
            }

            const code = String(rustDeskCodeElement.value || '').replace(/[^A-Za-z0-9_-]+/g, '').trim();
            rustDeskCodeElement.value = code;

            if (!code) {
                setSupportCodeStatus('Escribe un código de RustDesk para compartirlo.', 'text-muted');
                return;
            }

            if (code === lastSavedRustDeskCode) {
                setSupportCodeStatus('Código de RustDesk sincronizado.', 'text-success');
                return;
            }

            setSupportCodeStatus('Guardando código de RustDesk...', 'text-muted');

            const payload = new FormData(rustDeskShareCodeForm);
            payload.set('rustdesk_code', code);

            fetch(rustDeskShareCodeForm.action, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: payload
            })
                .then(function (response) {
                    return response.json().then(function (payload) {
                        if (!response.ok) {
                            throw new Error(payload.message || 'No se pudo guardar el código de RustDesk.');
                        }

                        return payload;
                    });
                })
                .then(function (payload) {
                    const savedCode = String(payload?.remote?.rustdesk_code || code).trim();
                    lastSavedRustDeskCode = savedCode;
                    rustDeskCodeElement.value = savedCode;
                    setSupportCodeStatus('Código de RustDesk sincronizado.', 'text-success');
                })
                .catch(function (error) {
                    setSupportCodeStatus(getFriendlySupportCodeError(error), 'text-danger');
                });
        };

        if (shareCodeForm && codeElement) {
            codeElement.addEventListener('input', function () {
                const digitsOnly = String(codeElement.value || '').replace(/\D+/g, '');
                if (codeElement.value !== digitsOnly) {
                    codeElement.value = digitsOnly;
                }

                if (typeof codeElement.setCustomValidity === 'function') {
                    codeElement.setCustomValidity('');
                }

                if (!hasManualSubmitButton) {
                    setSupportCodeStatus('Guardando cambios...', 'text-muted');
                    window.clearTimeout(saveTimer);
                    saveTimer = window.setTimeout(saveSupportCode, 500);
                }
            });

            if (!hasManualSubmitButton) {
                codeElement.addEventListener('blur', function () {
                    window.clearTimeout(saveTimer);
                    saveSupportCode();
                });
            }

            shareCodeForm.addEventListener('submit', function (event) {
                const code = String(codeElement.value || '').replace(/\D+/g, '').trim();
                codeElement.value = code;

                if (!code) {
                    event.preventDefault();
                    setSupportCodeStatus('Debes ingresar un código de AnyDesk.', 'text-danger');
                    if (typeof codeElement.setCustomValidity === 'function') {
                        codeElement.setCustomValidity('Rellena este campo.');
                    }
                    if (typeof codeElement.reportValidity === 'function') {
                        codeElement.reportValidity();
                    }
                    codeElement.focus();
                    return;
                }

                if (typeof codeElement.setCustomValidity === 'function') {
                    codeElement.setCustomValidity('');
                }

                if (hasManualSubmitButton) {
                    setSupportCodeStatus('Enviando código...', 'text-muted');
                    sendSupportCodeBtn.disabled = true;
                    sendSupportCodeBtn.textContent = 'Enviando...';
                    return;
                }

                event.preventDefault();
                window.clearTimeout(saveTimer);
                saveSupportCode();
            });
        }

        if (rustDeskShareCodeForm && rustDeskCodeElement) {
            rustDeskCodeElement.addEventListener('input', function () {
                const cleanValue = String(rustDeskCodeElement.value || '').replace(/[^A-Za-z0-9_-]+/g, '');
                if (rustDeskCodeElement.value !== cleanValue) {
                    rustDeskCodeElement.value = cleanValue;
                }

                if (!hasManualRustDeskSubmitButton) {
                    setSupportCodeStatus('Guardando cambios...', 'text-muted');
                    window.clearTimeout(saveTimer);
                    saveTimer = window.setTimeout(saveRustDeskCode, 500);
                }
            });

            if (!hasManualRustDeskSubmitButton) {
                rustDeskCodeElement.addEventListener('blur', function () {
                    window.clearTimeout(saveTimer);
                    saveRustDeskCode();
                });
            }

            rustDeskShareCodeForm.addEventListener('submit', function (event) {
                const code = String(rustDeskCodeElement.value || '').replace(/[^A-Za-z0-9_-]+/g, '').trim();
                rustDeskCodeElement.value = code;

                if (!code) {
                    event.preventDefault();
                    setSupportCodeStatus('Debes ingresar un código de RustDesk.', 'text-danger');
                    rustDeskCodeElement.focus();
                    return;
                }

                if (hasManualRustDeskSubmitButton) {
                    setSupportCodeStatus('Enviando código de RustDesk...', 'text-muted');
                    sendRustDeskCodeBtn.disabled = true;
                    sendRustDeskCodeBtn.textContent = 'Enviando...';
                    return;
                }

                event.preventDefault();
                window.clearTimeout(saveTimer);
                saveRustDeskCode();
            });
        }

        // La sincronizacion de codigo y estado se gestiona desde el bloque live polling global.

        if (endRemoteSessionBtn && endRemoteSessionForm) {
            endRemoteSessionBtn.addEventListener('click', function () {
endRemoteSessionBtn.disabled = true;
                endRemoteSessionBtn.textContent = 'Finalizando...';
                endRemoteSessionForm.submit();
            });
        }

        if (closeAnyDeskBtn && closeAnyDeskForm) {
            closeAnyDeskBtn.addEventListener('click', function () {
closeAnyDeskBtn.disabled = true;
                closeAnyDeskBtn.textContent = 'Cerrando...';
                closeAnyDeskForm.submit();
            });
        }

        pollRemoteCodes();
        window.setInterval(pollRemoteCodes, 2500);
        window.addEventListener('focus', pollRemoteCodes);
        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) {
                pollRemoteCodes();
            }
        });
    });
</script>
@endpush
@endif

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const chatScroll = document.getElementById('ticketChatScroll');
        const form = document.getElementById('chatComposerForm');
        const attachmentBtn = document.getElementById('chatAttachmentBtn');
        const attachmentInput = document.getElementById('chatAttachmentInput');
        const selectedFilesWrap = document.getElementById('chatSelectedFiles');
        const messageInput = document.getElementById('chatMessageInput');

        const maxFiles = 5;
        let selectedFiles = [];
        let objectUrls = [];

        if (chatScroll) {
            chatScroll.scrollTop = chatScroll.scrollHeight;
        }

        if (!form || !attachmentInput || !selectedFilesWrap) {
            return;
        }

        const clearObjectUrls = function () {
            objectUrls.forEach(function (url) {
                URL.revokeObjectURL(url);
            });
            objectUrls = [];
        };

        const syncFilesToInput = function () {
            const transfer = new DataTransfer();
            selectedFiles.forEach(function (file) {
                transfer.items.add(file);
            });
            attachmentInput.files = transfer.files;
        };

        const renderFiles = function () {
            selectedFilesWrap.innerHTML = '';
            clearObjectUrls();

            if (selectedFiles.length === 0) {
                selectedFilesWrap.classList.remove('show');
                syncFilesToInput();
                return;
            }

            selectedFilesWrap.classList.add('show');

            selectedFiles.forEach(function (file, index) {
                const chip = document.createElement('div');
                chip.className = 'chat-file-chip';

                const isImage = (file.type || '').startsWith('image/');
                if (isImage) {
                    const thumb = document.createElement('img');
                    thumb.className = 'chat-file-chip-thumb';
                    const url = URL.createObjectURL(file);
                    objectUrls.push(url);
                    thumb.src = url;
                    chip.appendChild(thumb);
                } else {
                    const icon = document.createElement('span');
                    icon.className = 'chat-file-chip-thumb d-inline-flex align-items-center justify-content-center';
                    icon.innerHTML = '<i class="fas fa-file"></i>';
                    chip.appendChild(icon);
                }

                const name = document.createElement('span');
                name.className = 'chat-file-chip-name';
                name.textContent = file.name;
                chip.appendChild(name);

                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'chat-file-chip-remove';
                removeBtn.textContent = 'X';
                removeBtn.addEventListener('click', function () {
                    selectedFiles.splice(index, 1);
                    renderFiles();
                });
                chip.appendChild(removeBtn);

                selectedFilesWrap.appendChild(chip);
            });

            syncFilesToInput();
        };

        const addFiles = function (files, imagesOnly) {
            const incoming = Array.from(files || []);
            const filtered = imagesOnly
                ? incoming.filter(function (file) {
                    return (file.type || '').startsWith('image/');
                })
                : incoming;

            filtered.forEach(function (file) {
                if (selectedFiles.length >= maxFiles) {
                    return;
                }
                selectedFiles.push(file);
            });

            renderFiles();
        };

        attachmentBtn.addEventListener('click', function () {
            attachmentInput.click();
        });

        attachmentInput.addEventListener('change', function () {
            addFiles(attachmentInput.files, false);
            attachmentInput.value = '';
        });

        if (messageInput) {
            messageInput.addEventListener('keydown', function (event) {
                if (event.key !== 'Enter' || event.shiftKey || event.isComposing) {
                    return;
                }

                event.preventDefault();

                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit();
                    return;
                }

                form.dispatchEvent(new Event('submit', {
                    bubbles: true,
                    cancelable: true,
                }));
            });

            messageInput.addEventListener('paste', function (event) {
                if (!event.clipboardData || !event.clipboardData.items) {
                    return;
                }

                const pastedFiles = [];
                Array.from(event.clipboardData.items).forEach(function (item) {
                    if (item.kind !== 'file' || !(item.type || '').startsWith('image/')) {
                        return;
                    }
                    const file = item.getAsFile();
                    if (!file) {
                        return;
                    }
                    const extension = (file.type || '').split('/')[1] || 'png';
                    const stampedName = `pegado-${Date.now()}-${pastedFiles.length + 1}.${extension}`;
                    pastedFiles.push(new File([file], stampedName, { type: file.type || 'image/png' }));
                });

                if (pastedFiles.length > 0) {
                    addFiles(pastedFiles, true);
                }
            });
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();

            if (form.dataset.sending === '1') {
                return;
            }

            const messageText = messageInput ? messageInput.value.trim() : '';
            if (messageText === '' && selectedFiles.length === 0) {
                if (messageInput) {
                    messageInput.focus();
                }
                return;
            }

            form.dataset.sending = '1';
            const submitButton = form.querySelector('button[type="submit"]');
            const originalLabel = submitButton ? submitButton.textContent : 'Enviar';

            if (submitButton) {
                submitButton.disabled = true;
                submitButton.textContent = 'Enviando...';
            }

            fetch(form.action, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new FormData(form)
            })
                .then(function (response) {
                    return response.json().then(function (payload) {
                        if (!response.ok) {
                            throw new Error(payload.message || 'No se pudo enviar el mensaje.');
                        }
                        return payload;
                    });
                })
                .then(function (payload) {
                    form.reset();
                    selectedFiles = [];
                    renderFiles();

                    if (messageInput) {
                        messageInput.value = '';
                        messageInput.focus();
                    }

                    const latestMessageId = Number(payload && payload.latest_message_id ? payload.latest_message_id : 0);
                    if (latestMessageId > 0) {
                        chatScroll.dataset.lastMessageId = String(Math.max(
                            Number(chatScroll.dataset.lastMessageId || 0),
                            latestMessageId - 1
                        ));

                        window.__ticketForceScrollAfterOwnMessageByTicket = window.__ticketForceScrollAfterOwnMessageByTicket || {};
                        window.__ticketForceScrollAfterOwnMessageByTicket[{{ (int) $ticket->id }}] = true;
                    }

                    const livePollFn = window.__ticketLivePollByTicket && window.__ticketLivePollByTicket[{{ (int) $ticket->id }}];
                    if (typeof livePollFn === 'function') {
                        livePollFn();
                    }
                })
                .catch(function (error) {
                    alert(error.message || 'No se pudo enviar el mensaje.');
                })
                .finally(function () {
                    form.dataset.sending = '0';
                    if (submitButton) {
                        submitButton.disabled = false;
                        submitButton.textContent = originalLabel;
                    }
                });
        });
    });
</script>
@endpush

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const chatScroll = document.getElementById('ticketChatScroll');
        if (!chatScroll) {
            return;
        }

        const unreadBadge = document.getElementById('ticketChatUnreadBadge');
        const unreadCountLabel = document.getElementById('ticketChatUnreadCount');
        const ticketId = {{ (int) $ticket->id }};
        const liveUrl = @json(route('tickets.live', $ticket));
        const badgeMap = @json(collect(config('adminlte.ticket_states', []))->mapWithKeys(fn ($state, $key) => [$key => $state['badge'] ?? 'secondary']));
        const stateBadge = document.getElementById('ticketStateBadge');
        const assignedEmployee = document.getElementById('ticketAssignedEmployee');
        const remoteCodeInput = document.getElementById('remoteSupportCode');
        const remoteRustDeskCodeInput = document.getElementById('remoteRustDeskCode');
        const shareCodeForm = document.getElementById('remoteShareCodeForm');
        const openCopyAnyDeskBtn = document.getElementById('openCopyAnyDeskBtn');
        const openCopyRustDeskBtn = document.getElementById('openCopyRustDeskBtn');
        const finalizeTicketForm = document.getElementById('finalizeTicketForm');
        const rateTicketForm = document.getElementById('rateTicketForm');
        const rateTicketModal = document.getElementById('rateTicketModal');
        const canManageRemoteAsClient = @json($canManageRemoteAsClient);
        const canManageRemoteAsEmployee = @json($canManageRemoteAsEmployee);
        const canEditRemoteCode = canManageRemoteAsClient || canManageRemoteAsEmployee;

        let lastMessageId = Number(chatScroll.dataset.lastMessageId || 0);
        let currentState = @json((string) $ticket->estado);
        let currentRemoteId = {{ (int) ($remoteSession->id ?? 0) }};
        let currentRemoteStatus = @json((string) ($remoteSession->status ?? ''));
        let lastSyncedRemoteCode = @json((string) ($remoteSupportCode ?? $remoteSession->support_code ?? ''));
        let lastSyncedRustDeskCode = @json((string) ($remoteRustDeskCode ?? $remoteSession->rustdesk_code ?? ''));
        let remoteCodeDirty = false;
        let rustDeskCodeDirty = false;
        let inFlight = false;
        let unreadMessages = 0;

        if (rateTicketModal && window.bootstrap?.Modal) {
            window.bootstrap.Modal.getOrCreateInstance(rateTicketModal, {
                backdrop: 'static',
                keyboard: false,
            }).show();
        }

        const escapeHtml = function (value) {
            return String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        };

        const buildMessageHtml = function (message) {
            const bubbleClass = message.is_own ? 'ticket-chat-message mine' : 'ticket-chat-message';
            const userName = escapeHtml(message.user_name || 'Sistema');
            const createdAt = escapeHtml(message.created_at || '');
            const textHtml = message.mensaje ? `<p class="mb-1">${escapeHtml(message.mensaje)}</p>` : '';

            let attachmentHtml = '';
            if (message.attachment && message.attachment.url) {
                const url = escapeHtml(message.attachment.url);
                const name = escapeHtml(message.attachment.name || 'Descargar archivo');
                if (message.attachment.is_image) {
                    attachmentHtml = `
                        <a href="${url}" target="_blank" rel="noopener">
                            <img src="${url}" alt="Adjunto" class="img-fluid rounded border ticket-chat-image">
                        </a>
                    `;
                } else {
                    attachmentHtml = `
                        <a href="${url}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-paperclip me-1"></i>${name}
                        </a>
                    `;
                }
            }

            return `
                <div class="${bubbleClass}">
                    <div class="ticket-chat-bubble">
                        <div class="d-flex justify-content-between align-items-center gap-2 mb-1">
                            <div>
                                <strong>${userName}</strong>
                            </div>
                            <span class="ticket-chat-meta">${createdAt}</span>
                        </div>
                        ${textHtml}
                        ${attachmentHtml}
                    </div>
                </div>
            `;
        };

        if (finalizeTicketForm) {
            finalizeTicketForm.addEventListener('submit', function (event) {
                const submitButton = finalizeTicketForm.querySelector('button[type="submit"]');
                if (finalizeTicketForm.dataset.submitting === '1') {
                    event.preventDefault();
                    return;
                }

                finalizeTicketForm.dataset.submitting = '1';

                if (submitButton) {
                    submitButton.disabled = true;
                    submitButton.textContent = 'Finalizando...';
                }
            });
        }

        if (rateTicketForm) {
            rateTicketForm.addEventListener('submit', function (event) {
                const submitButton = rateTicketForm.querySelector('button[type="submit"]');
                if (rateTicketForm.dataset.submitting === '1') {
                    event.preventDefault();
                    return;
                }

                rateTicketForm.dataset.submitting = '1';

                if (submitButton) {
                    submitButton.disabled = true;
                    submitButton.textContent = 'Enviando...';
                }
            });
        }

        const shouldStickToBottom = function () {
            const gap = chatScroll.scrollHeight - chatScroll.scrollTop - chatScroll.clientHeight;
            return gap < 100;
        };

        const updateUnreadBadge = function () {
            if (!unreadBadge || !unreadCountLabel) {
                return;
            }

            if (unreadMessages <= 0 || shouldStickToBottom()) {
                unreadMessages = 0;
                unreadBadge.hidden = true;
                unreadCountLabel.textContent = '0';
                return;
            }

            unreadCountLabel.textContent = unreadMessages > 99 ? '99+' : String(unreadMessages);
            unreadBadge.hidden = false;
        };

        const scrollChatToBottom = function () {
            chatScroll.scrollTop = chatScroll.scrollHeight;
            unreadMessages = 0;
            updateUnreadBadge();
        };

        if (unreadBadge) {
            unreadBadge.addEventListener('click', scrollChatToBottom);
        }

        chatScroll.addEventListener('scroll', function () {
            if (shouldStickToBottom()) {
                unreadMessages = 0;
            }

            updateUnreadBadge();
        }, { passive: true });

        const syncRemoteSupportCode = function (remoteData) {
            if (!remoteData) {
                return;
            }

            const newCode = String(remoteData.support_code || '').trim();
            const newRustDeskCode = String(remoteData.rustdesk_code || '').trim();
            const isEditingRemoteCode = remoteCodeInput && document.activeElement === remoteCodeInput;
            const isEditingRustDeskCode = remoteRustDeskCodeInput && document.activeElement === remoteRustDeskCodeInput;

            if (remoteCodeInput && !isEditingRemoteCode && !remoteCodeDirty) {
                remoteCodeInput.value = newCode;
                lastSyncedRemoteCode = newCode;
            }

            if (remoteRustDeskCodeInput && !isEditingRustDeskCode && !rustDeskCodeDirty) {
                remoteRustDeskCodeInput.value = newRustDeskCode;
                lastSyncedRustDeskCode = newRustDeskCode;
            }

            if (openCopyAnyDeskBtn) {
                openCopyAnyDeskBtn.disabled = newCode === '' && !canEditRemoteCode;
            }
            if (openCopyRustDeskBtn) {
                openCopyRustDeskBtn.disabled = newRustDeskCode === '' && !canEditRemoteCode;
            }

            window.dispatchEvent(new CustomEvent('helpdesk:remote-code-updated', {
                detail: {
                    ticketId,
                    remote: remoteData,
                },
            }));
        };

        if (remoteCodeInput) {
            remoteCodeInput.addEventListener('input', function () {
                const currentInputValue = String(remoteCodeInput.value || '').trim();
                remoteCodeDirty = currentInputValue !== lastSyncedRemoteCode;
            });
        }

        if (shareCodeForm) {
            shareCodeForm.addEventListener('submit', function () {
                remoteCodeDirty = false;
                lastSyncedRemoteCode = String(remoteCodeInput?.value || '').trim();
            });
        }

        if (remoteRustDeskCodeInput) {
            remoteRustDeskCodeInput.addEventListener('input', function () {
                const currentInputValue = String(remoteRustDeskCodeInput.value || '').trim();
                rustDeskCodeDirty = currentInputValue !== lastSyncedRustDeskCode;
            });
        }

        const rustDeskShareCodeForm = document.getElementById('remoteRustDeskShareCodeForm');
        if (rustDeskShareCodeForm) {
            rustDeskShareCodeForm.addEventListener('submit', function () {
                rustDeskCodeDirty = false;
                lastSyncedRustDeskCode = String(remoteRustDeskCodeInput?.value || '').trim();
            });
        }

        const applyLiveData = function (data) {
            if (!data || data.ok !== true) {
                return;
            }

            const ticketData = data.ticket || {};
            const remoteData = data.remote || {};
            const incomingMessages = Array.isArray(data.messages) ? data.messages : [];

            const requiresReload =
                String(ticketData.estado || '') !== String(currentState || '') ||
                Number(remoteData.id || 0) !== Number(currentRemoteId || 0) ||
                String(remoteData.status || '') !== String(currentRemoteStatus || '');

            if (requiresReload) {
                window.location.reload();
                return;
            }

            currentState = String(ticketData.estado || currentState || '');
            currentRemoteId = Number(remoteData.id || currentRemoteId || 0);
            currentRemoteStatus = String(remoteData.status || currentRemoteStatus || '');

            if (assignedEmployee && ticketData.empleado_nombre) {
                assignedEmployee.textContent = ticketData.empleado_nombre;
            }

            if (stateBadge && ticketData.estado) {
                const nextBadge = badgeMap[ticketData.estado] || 'secondary';
                stateBadge.className = `badge text-bg-${nextBadge}`;
                stateBadge.textContent = String(ticketData.estado).replace('_', ' ');
            }

            syncRemoteSupportCode(remoteData);

            if (incomingMessages.length === 0) {
                return;
            }

            const keepBottom = shouldStickToBottom();
            const shouldForceOwnScroll = Boolean(window.__ticketForceScrollAfterOwnMessageByTicket?.[ticketId]);
            const emptyState = chatScroll.querySelector('p.text-muted.mb-0');
            if (emptyState) {
                emptyState.remove();
            }

            let appendedIncomingMessages = 0;

            incomingMessages.forEach(function (message) {
                if (Number(message.id || 0) <= lastMessageId) {
                    return;
                }

                chatScroll.insertAdjacentHTML('beforeend', buildMessageHtml(message));
                lastMessageId = Number(message.id || lastMessageId);

                if (!message.is_own) {
                    appendedIncomingMessages++;
                }
            });

            chatScroll.dataset.lastMessageId = String(lastMessageId);
            if (keepBottom || shouldForceOwnScroll) {
                if (window.__ticketForceScrollAfterOwnMessageByTicket) {
                    window.__ticketForceScrollAfterOwnMessageByTicket[ticketId] = false;
                }

                scrollChatToBottom();
                return;
            }

            unreadMessages += appendedIncomingMessages;
            updateUnreadBadge();
        };

        const runLivePoll = function () {
            if (document.hidden || inFlight) {
                return;
            }

            inFlight = true;

            fetch(`${liveUrl}?since_message_id=${encodeURIComponent(lastMessageId)}&t=${Date.now()}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('live-failed');
                    }
                    return response.json();
                })
                .then(applyLiveData)
                .catch(function () {
                    // Ignorar fallas intermitentes y reintentar en el siguiente ciclo.
                })
                .finally(function () {
                    inFlight = false;
                });
        };

        window.__ticketLivePollByTicket = window.__ticketLivePollByTicket || {};
        window.__ticketLivePollByTicket[ticketId] = runLivePoll;

        const ticketSocket = {
            enabled: Boolean(window.Echo && typeof window.Echo.private === 'function'),
            connected: false,
            channel: null,
            pollTimer: null,
        };

        const resolveTicketPollInterval = function () {
            return ticketSocket.connected ? 10000 : 3000;
        };

        const scheduleTicketPolling = function () {
            const intervalMs = resolveTicketPollInterval();

            if (ticketSocket.pollTimer) {
                window.clearInterval(ticketSocket.pollTimer);
            }

            ticketSocket.pollTimer = window.setInterval(runLivePoll, intervalMs);
        };

        const bindTicketSocket = function () {
            if (!ticketSocket.enabled) {
                scheduleTicketPolling();
                return;
            }

            const pusherConnection = window.Echo?.connector?.pusher?.connection;
            ticketSocket.connected = pusherConnection?.state === 'connected';

            if (pusherConnection && typeof pusherConnection.bind === 'function') {
                pusherConnection.bind('connected', function () {
                    ticketSocket.connected = true;
                    scheduleTicketPolling();
                    runLivePoll();
                });

                pusherConnection.bind('disconnected', function () {
                    ticketSocket.connected = false;
                    scheduleTicketPolling();
                });

                pusherConnection.bind('unavailable', function () {
                    ticketSocket.connected = false;
                    scheduleTicketPolling();
                });

                pusherConnection.bind('failed', function () {
                    ticketSocket.connected = false;
                    scheduleTicketPolling();
                });
            }

            ticketSocket.channel = window.Echo.private(`tickets.${ticketId}`)
                .listen('.ticket.stream.updated', function (eventPayload) {
                    const remoteData = eventPayload && typeof eventPayload === 'object'
                        ? eventPayload.remote || null
                        : null;

                    if (
                        remoteData
                        && Number(remoteData.id || 0) === Number(currentRemoteId || 0)
                        && String(remoteData.status || '') === String(currentRemoteStatus || '')
                    ) {
                        syncRemoteSupportCode(remoteData);
                    }

                    runLivePoll();
                });

            scheduleTicketPolling();
        };

        runLivePoll();
        bindTicketSocket();

        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) {
                runLivePoll();
            }
        });

        window.addEventListener('focus', function () {
            runLivePoll();
        });
    });
</script>
@endpush
@endsection
