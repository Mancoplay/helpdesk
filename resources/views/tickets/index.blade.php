@extends('layouts.app')

@section('title', 'Tickets')
@section('header', 'Lista de tickets')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item active">Tickets</li>
@endsection

@section('content')
@php
    $ticketSubjectOptions = [
        'Problema con el sistema de correspondencia',
        'Falla en equipo de computación',
        'Problema con impresora o escaner',
        'Problema de conexion a internet o red',
        'Acceso bloqueado o recuperacion de cuenta',
        'Error al registrar o actualizar envios',
        'Problema con reportes o consultas internas',
        'Solicitud de soporte para ventanilla',
        'Solicitud de soporte para clasificación o distribución',
        'Solicitud de mantenimiento o revisión técnica',
    ];

    $oldSubject = old('asunto');
    $isCustomSubject = filled($oldSubject) && ! in_array($oldSubject, $ticketSubjectOptions, true);
@endphp

@if(auth()->user()->can('crear tickets'))
<div class="card mb-3">
    <div class="card-body">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createTicketModal">
            <i class="fas fa-plus me-1"></i> Agregar nuevo ticket
        </button>
    </div>
</div>
@endif

<div class="js-ticket-page-feedback"></div>

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
                    <div class="js-create-ticket-feedback"></div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Código</label>
                            <input type="text" name="codigo" id="codigoTicket" class="form-control" value="{{ old('codigo', $nextTicketCode) }}" readonly>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Asunto</label>
                            <input type="hidden" name="asunto" id="ticketSubjectValue" value="{{ $oldSubject }}">
                            <select
                                id="ticketSubjectSelect"
                                class="form-select"
                                required
                                data-other-value="__other__"
                                oninvalid="this.setCustomValidity('Debe seleccionar un asunto.')"
                                onchange="this.setCustomValidity('')"
                            >
                                <option value="">Seleccione un asunto</option>
                                @foreach($ticketSubjectOptions as $subjectOption)
                                    <option value="{{ $subjectOption }}" @selected($oldSubject === $subjectOption)>{{ $subjectOption }}</option>
                                @endforeach
                                <option value="__other__" @selected($isCustomSubject)>Otro asunto</option>
                            </select>
                            <input
                                type="text"
                                id="ticketSubjectOther"
                                class="form-control mt-2 {{ $isCustomSubject ? '' : 'd-none' }}"
                                value="{{ $isCustomSubject ? $oldSubject : '' }}"
                                minlength="3"
                                maxlength="180"
                                placeholder="Escriba el asunto"
                                @disabled(! $isCustomSubject)
                                oninvalid="this.setCustomValidity('Debe ingresar minimo 3 caracteres.')"
                                oninput="this.setCustomValidity('')"
                            >
                        </div>
                        <div class="col-12">
                            <label class="form-label">Descripción</label>
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
        const authUserId = {{ (int) auth()->id() }};
        const isAdmin = @json(auth()->user()->hasRole('Administrador'));
        const isEmployee = @json(auth()->user()->hasRole('Empleado'));
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const createTicketModal = document.getElementById('createTicketModal');
        const createTicketForm = document.getElementById('createTicketForm');
        const pageFeedback = document.querySelector('.js-ticket-page-feedback');
        const ticketSubjectSelect = document.getElementById('ticketSubjectSelect');
        const ticketSubjectValue = document.getElementById('ticketSubjectValue');
        const ticketSubjectOther = document.getElementById('ticketSubjectOther');
        let tableRefreshController = null;
        let createTicketRequest = null;
        let nextTicketCodeCache = @json($nextTicketCode ?? null);

        const syncTicketSubject = function () {
            if (!ticketSubjectSelect || !ticketSubjectValue || !ticketSubjectOther) {
                return;
            }

            const otherValue = ticketSubjectSelect.dataset.otherValue || '__other__';
            const isOtherSubject = ticketSubjectSelect.value === otherValue;

            ticketSubjectOther.classList.toggle('d-none', !isOtherSubject);
            ticketSubjectOther.disabled = !isOtherSubject;
            ticketSubjectOther.required = isOtherSubject;

            if (isOtherSubject) {
                ticketSubjectValue.value = ticketSubjectOther.value.trim();
                return;
            }

            ticketSubjectOther.value = '';
            ticketSubjectValue.value = ticketSubjectSelect.value;
        };

        if (ticketSubjectSelect) {
            ticketSubjectSelect.addEventListener('change', syncTicketSubject);
        }

        if (ticketSubjectOther) {
            ticketSubjectOther.addEventListener('input', syncTicketSubject);
        }

        const showFeedback = function (container, type, message) {
            if (!container || !message) {
                return;
            }

            container.innerHTML = '<div class="alert alert-' + type + ' alert-dismissible fade show" role="alert">'
                + message
                + '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>'
                + '</div>';

            window.setTimeout(function () {
                const alertElement = container.querySelector('.alert');
                if (!alertElement) {
                    return;
                }

                if (window.bootstrap && window.bootstrap.Alert) {
                    window.bootstrap.Alert.getOrCreateInstance(alertElement).close();
                    return;
                }

                container.innerHTML = '';
            }, 5000);
        };

        const extractErrorMessage = function (error) {
            const payload = error?.response?.data;
            const validationErrors = payload?.errors ? Object.values(payload.errors).flat() : [];

            if (validationErrors.length) {
                return validationErrors[0];
            }

            return payload?.message || 'No se pudo completar la solicitud.';
        };

        const fetchNextTicketCode = function () {
            if (!createTicketModal) {
                return Promise.resolve();
            }

            return window.axios.get("{{ route('tickets.next-code') }}")
                .then(function (response) {
                    const codeInput = createTicketModal.querySelector('input[name="codigo"]');
                    const nextCode = response?.data?.codigo;

                    if (codeInput && nextCode) {
                        codeInput.value = nextCode;
                    }

                     if (nextCode) {
                        nextTicketCodeCache = nextCode;
                    }
                })
                .catch(function (error) {
                    console.error('No se pudo obtener el siguiente codigo de ticket:', error);
                });
        };

        const closeModal = function (modalElement) {
            if (!modalElement) {
                return;
            }

            if (window.bootstrap?.Modal) {
                window.bootstrap.Modal.getOrCreateInstance(modalElement).hide();
                return;
            }

            modalElement.classList.remove('show');
            modalElement.setAttribute('aria-hidden', 'true');
            modalElement.style.display = 'none';
            document.body.classList.remove('modal-open');
            document.body.style.removeProperty('padding-right');
            document.querySelectorAll('.modal-backdrop').forEach(function (backdrop) {
                backdrop.remove();
            });
        };

        if (createTicketModal) {
            createTicketModal.addEventListener('show.bs.modal', function () {
                const codeInput = createTicketModal.querySelector('input[name="codigo"]');

                if (codeInput && nextTicketCodeCache) {
                    codeInput.value = nextTicketCodeCache;
                }

                window.setTimeout(fetchNextTicketCode, 0);
            });

            createTicketModal.addEventListener('hidden.bs.modal', function () {
                const feedbackContainer = createTicketModal.querySelector('.js-create-ticket-feedback');
                if (feedbackContainer) {
                    feedbackContainer.innerHTML = '';
                }

                if (createTicketForm) {
                    createTicketForm.reset();

                    const codeInput = createTicketForm.querySelector('input[name="codigo"]');
                    if (codeInput && nextTicketCodeCache) {
                        codeInput.value = nextTicketCodeCache;
                    }

                    syncTicketSubject();
                }
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

            if (tableRefreshController) {
                tableRefreshController.abort();
            }

            tableRefreshController = new AbortController();

            fetch(url, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                signal: tableRefreshController.signal,
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
                    if (error.name === 'AbortError') {
                        return;
                    }

                    console.error('No se pudo actualizar la tabla de tickets:', error);
                });
        };

        const submitInlineTicketForm = function (form) {
            const confirmMessage = form.getAttribute('data-confirm');
            if (confirmMessage && !window.confirm(confirmMessage)) {
                return;
            }

            const submitButton = form.querySelector('button[type="submit"]');
            if (submitButton) {
                submitButton.disabled = true;
            }

            window.axios({
                method: form.getAttribute('method') || 'POST',
                url: form.getAttribute('action'),
                data: new window.FormData(form),
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
            })
                .then(function (response) {
                    const redirectUrl = response?.data?.redirect_url;

                    if (redirectUrl) {
                        window.location.assign(redirectUrl);
                        return;
                    }

                    showFeedback(pageFeedback, 'success', response?.data?.message || form.getAttribute('data-success-message'));
                    refreshTableResults();
                })
                .catch(function (error) {
                    showFeedback(pageFeedback, 'danger', extractErrorMessage(error));
                })
                .finally(function () {
                    if (submitButton) {
                        submitButton.disabled = false;
                    }
                });
        };

        if (createTicketForm) {
            createTicketForm.addEventListener('submit', function (event) {
                event.preventDefault();
                syncTicketSubject();

                if (!createTicketForm.reportValidity()) {
                    return;
                }

                if (createTicketRequest) {
                    return;
                }

                const feedbackContainer = createTicketModal?.querySelector('.js-create-ticket-feedback');
                const submitButton = createTicketForm.querySelector('button[type="submit"]');

                if (submitButton) {
                    submitButton.disabled = true;
                    submitButton.textContent = 'Guardando...';
                }

                if (feedbackContainer) {
                    feedbackContainer.innerHTML = '';
                }

                createTicketRequest = window.axios.post(createTicketForm.action, new window.FormData(createTicketForm), {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                })
                    .then(function (response) {
                        const nextCode = response?.data?.next_code;

                        if (nextCode) {
                            nextTicketCodeCache = nextCode;
                        }

                        closeModal(createTicketModal);
                        showFeedback(pageFeedback, 'success', response?.data?.message || 'Ticket agregado correctamente.');

                        if (!ticketListSocket.enabled || !ticketListSocket.connected) {
                            window.setTimeout(refreshTableResults, 120);
                        }

                    })
                    .catch(function (error) {
                        showFeedback(feedbackContainer, 'danger', extractErrorMessage(error));
                    })
                    .finally(function () {
                        createTicketRequest = null;

                        if (submitButton) {
                            submitButton.disabled = false;
                            submitButton.textContent = 'Guardar';
                        }
                    });
            });
        }

        document.addEventListener('submit', function (event) {
            const form = event.target;
            if (!(form instanceof HTMLFormElement) || !form.matches('.js-ticket-inline-form')) {
                return;
            }

            event.preventDefault();
            submitInlineTicketForm(form);
        });

        const ticketListSocket = {
            enabled: Boolean(window.Echo && typeof window.Echo.private === 'function'),
            connected: false,
            channels: [],
            pollTimer: null,
        };

        const resolveTicketListPollInterval = function () {
            return ticketListSocket.connected ? 120000 : 25000;
        };

        const scheduleTicketListPolling = function () {
            const intervalMs = resolveTicketListPollInterval();

            if (ticketListSocket.pollTimer) {
                window.clearInterval(ticketListSocket.pollTimer);
            }

            ticketListSocket.pollTimer = window.setInterval(function () {
                if (!document.hidden) {
                    refreshTableResults();
                }
            }, intervalMs);
        };

        const subscribeToChannel = function (channelName) {
            if (!channelName) {
                return;
            }

            ticketListSocket.channels.push(
                window.Echo.private(channelName).listen('.ticket.list.updated', function () {
                    refreshTableResults();
                })
            );
        };

        const bindTicketListSocket = function () {
            if (!ticketListSocket.enabled || authUserId <= 0) {
                scheduleTicketListPolling();
                return;
            }

            const pusherConnection = window.Echo?.connector?.pusher?.connection;
            ticketListSocket.connected = pusherConnection?.state === 'connected';

            if (pusherConnection && typeof pusherConnection.bind === 'function') {
                pusherConnection.bind('connected', function () {
                    ticketListSocket.connected = true;
                    scheduleTicketListPolling();
                    refreshTableResults();
                });

                pusherConnection.bind('disconnected', function () {
                    ticketListSocket.connected = false;
                    scheduleTicketListPolling();
                });

                pusherConnection.bind('unavailable', function () {
                    ticketListSocket.connected = false;
                    scheduleTicketListPolling();
                });

                pusherConnection.bind('failed', function () {
                    ticketListSocket.connected = false;
                    scheduleTicketListPolling();
                });
            }

            subscribeToChannel('users.' + authUserId + '.tickets');

            if (isAdmin) {
                subscribeToChannel('tickets.admins');
            }

            if (isEmployee) {
                subscribeToChannel('tickets.employees');
            }

            scheduleTicketListPolling();
        };

        bindTicketListSocket();
        syncTicketSubject();
        window.setTimeout(fetchNextTicketCode, 0);

        document.addEventListener('hidden.bs.modal', function () {
            if (!document.hidden) {
                refreshTableResults();
            }
        });

        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) {
                refreshTableResults();
            }
        });

        window.addEventListener('focus', function () {
            refreshTableResults();
        });
    });
</script>
@endpush

@endsection
