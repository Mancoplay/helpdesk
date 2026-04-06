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
<div class="row g-3 ticket-detail-page">
    <div class="col-lg-5">
        <div class="card border-success ticket-panel ticket-panel-detail">
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
                        <strong>Usuario</strong>
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
                    (
                        auth()->user()->hasRole('Administrador')
                        && in_array($ticket->estado, ['pendiente', 'en_proceso'], true)
                    )
                    || (
                        auth()->user()->hasRole('Empleado')
                        && in_array($ticket->estado, ['pendiente', 'en_proceso'], true)
                        && (int) ($ticket->empleado_id ?? 0) === (int) (optional(auth()->user()->empleado)->id ?? 0)
                    )
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
                $isAdmin = auth()->user()->hasRole('Administrador');
                $isClientOwner = auth()->user()->hasAnyRole(['Cliente', 'Usuario'])
                    && (($ticket->cliente->email ?? null) === auth()->user()->email);
                $isAssignedEmployee = auth()->user()->hasRole('Empleado')
                    && (int) ($ticket->empleado_id ?? 0) === (int) (optional(auth()->user()->empleado)->id ?? 0);
                $canManageRemoteAsClient = $isClientOwner || $isAdmin;
                $canManageRemoteAsEmployee = $isAssignedEmployee || $isAdmin;
            @endphp

            @if($isClientOwner || $isAssignedEmployee || $isAdmin)
                <div class="card-footer border-top">
                    <h6 class="mb-2">Soporte remoto</h6>

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
                        @if($canManageRemoteAsEmployee)
                            <form method="POST" action="{{ route('tickets.remote.request', $ticket) }}">
                                @csrf
                                <button type="submit" class="btn btn-outline-primary w-100">Conectar</button>
                            </form>
                            <small class="text-muted d-block mt-2">El usuario recibira una solicitud para autorizar el control remoto.</small>
                        @else
                            <small class="text-muted">Aun no hay solicitud activa de soporte remoto.</small>
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
                            Conexion autorizada por el usuario.
                        </div>
                        <button type="button" class="btn btn-primary w-100 mb-2" data-bs-toggle="modal" data-bs-target="#remoteSupportModal">
                            Abrir panel de conexion
                        </button>
                        @if($canManageRemoteAsEmployee)
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
        <div class="card border-primary ticket-panel ticket-panel-chat">
            <div class="card-header">
                <h3 class="card-title mb-0">Comunicacion</h3>
            </div>
            <div class="card-body d-flex flex-column ticket-chat-card-body">
                <div id="ticketChatScroll" class="ticket-chat-scroll border rounded p-3 mb-2">
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
                            $isOwnMessage = (int) ($mensaje->user_id ?? 0) === (int) auth()->id();
                        @endphp
                        <div class="ticket-chat-message {{ $isOwnMessage ? 'mine' : '' }}">
                            <div class="ticket-chat-bubble">
                                <div class="d-flex justify-content-between align-items-center gap-2 mb-1">
                                    <div>
                                        <strong>{{ $mensaje->user->name ?? 'Sistema' }}</strong>
                                        <span class="badge text-bg-{{ $tipoBadge }}">{{ $mensaje->tipo }}</span>
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

                @php
                    $mustAttendFirst = auth()->user()->hasRole('Empleado') && $ticket->estado === 'pendiente';
                @endphp

                <div class="ticket-chat-composer">
                    @if($mustAttendFirst)
                        <div class="alert alert-warning mb-0">
                            Este ticket aun no esta siendo atendido. Presiona <strong>Atender ticket</strong> para habilitar el chat y la carga de imagenes.
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
                            <small class="text-muted d-block mb-2">Adjunta imagen, documento o archivo. Maximo 5 archivos por mensaje (12 MB c/u).</small>
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
                    Sesion autorizada por el usuario en <strong>AnyDesk</strong>.
                </div>
                <div class="row g-2 align-items-end">
                    <div class="col-12">
                        <label class="form-label mb-1">Codigo de AnyDesk</label>
                        @if($canManageRemoteAsClient)
                            <form id="remoteShareCodeForm" method="POST" action="{{ route('tickets.remote.update', [$ticket, $remoteSession]) }}">
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
                                    <button type="submit" id="sendSupportCodeBtn" class="btn btn-success">Enviar codigo</button>
                                </div>
                            </form>
                        @else
                            <div class="input-group">
                                <input type="text" id="remoteSupportCode" class="form-control" value="{{ $remoteSession->support_code }}" readonly>
                            </div>
                        @endif
                    </div>
                    <div class="col-md-6">
                        <button
                            type="button"
                            id="openCopyAnyDeskBtn"
                            class="btn btn-outline-dark w-100"
                            {{ blank($remoteSession->support_code) && !$canManageRemoteAsClient ? 'disabled' : '' }}
                        >
                            Abrir y copiar codigo de AnyDesk
                        </button>
                    </div>
                    @if($canManageRemoteAsClient)
                        <div class="col-md-6">
                            <form id="closeAnyDeskForm" method="POST" action="{{ route('tickets.remote.update', [$ticket, $remoteSession]) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="action" value="signal_closed">
                                <button type="button" id="closeAnyDeskBtn" class="btn btn-danger w-100">Cerrar sesion remota</button>
                            </form>
                        </div>
                    @endif
                </div>
                <hr>
                <p class="mb-1"><strong>Pasos rapidos</strong></p>
                <ol class="mb-0">
                    <li>Usa "Abrir y copiar codigo de AnyDesk" para abrir la app y copiar el codigo.</li>
                    <li>Comparte o pega el codigo para iniciar la conexion remota.</li>
                    <li>Usa "Finalizar conexion" para cortar la sesion remota del ticket.</li>
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
        const codeElement = document.getElementById('remoteSupportCode');
        const openCopyAnyDeskBtn = document.getElementById('openCopyAnyDeskBtn');
        const shareCodeForm = document.getElementById('remoteShareCodeForm');
        const sendSupportCodeBtn = document.getElementById('sendSupportCodeBtn');
        const endRemoteSessionBtn = document.getElementById('endRemoteSessionBtn');
        const endRemoteSessionForm = document.getElementById('endRemoteSessionForm');
        const closeAnyDeskBtn = document.getElementById('closeAnyDeskBtn');
        const closeAnyDeskForm = document.getElementById('closeAnyDeskForm');
        const shouldSyncSupportCode = {{ $canManageRemoteAsClient ? 'false' : 'true' }};

        const openAnyDesk = function (code) {
            const rawCode = (code || '').trim();
            const cleanCode = rawCode.replace(/\s+/g, '');
            window.location.href = cleanCode ? `anydesk:${cleanCode}` : 'anydesk:';
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
            openCopyAnyDeskBtn.addEventListener('click', function () {
                const code = codeElement.value.trim();

                if (!code) {
                    openAnyDesk('');
                    return;
                }

                copyText(code).finally(function () {
                    openAnyDesk(code);
                });
            });
        }

        if (shareCodeForm && sendSupportCodeBtn) {
            shareCodeForm.addEventListener('submit', function () {
                sendSupportCodeBtn.disabled = true;
                sendSupportCodeBtn.textContent = 'Enviando...';
            });
        }

        if (shouldSyncSupportCode && codeElement) {
            setInterval(function () {
                if (document.hidden) {
                    return;
                }

                fetch("{{ route('tickets.show', $ticket) }}", {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                    .then(function (response) {
                        return response.text();
                    })
                    .then(function (html) {
                        const doc = new DOMParser().parseFromString(html, 'text/html');
                        const freshCodeInput = doc.getElementById('remoteSupportCode');
                        if (!freshCodeInput) {
                            return;
                        }

                        const newCode = (freshCodeInput.value || '').trim();
                        codeElement.value = newCode;

                        if (openCopyAnyDeskBtn) {
                            openCopyAnyDeskBtn.disabled = newCode === '';
                        }
                    })
                    .catch(function () {
                        // Ignorar errores intermitentes de red.
                    });
            }, 8000);
        }

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
    });
</script>
@endpush
@endsection
