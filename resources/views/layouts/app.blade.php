<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('adminlte.app_name', config('app.name', 'Help Desk')))</title>

    @vite(['resources/sass/app.scss', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('css/postal-theme.css') }}?v=20260406-3">
    @livewireStyles
    @stack('styles')
</head>
<body class="layout-fixed layout-navbar-fixed sidebar-expand-lg sidebar-mini bg-body-tertiary">
    @php
        $menuItems = config('adminlte.menu', []);
        $menuBadges = $menuBadges ?? [];
    @endphp

    @auth
        @php
            $notificationSummary = app(\App\Services\NotificationSummaryService::class)->forUser(Auth::user());
            $unreadNotifications = collect($notificationSummary['items'] ?? []);
            $unreadNotificationsCount = (int) ($notificationSummary['count'] ?? 0);
        @endphp
        <div class="app-wrapper">
            <nav class="app-header navbar navbar-expand bg-dark navbar-dark py-1">
                <div class="container-fluid">
                    <ul class="navbar-nav">
                        <li class="nav-item">
                            <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button">
                                <i class="fas fa-bars"></i>
                            </a>
                        </li>
                    </ul>

                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item dropdown">
                            <a
                                class="nav-link position-relative"
                                data-bs-toggle="dropdown"
                                href="#"
                                role="button"
                                title="Notificaciones"
                                id="notificationsBellButton"
                            >
                                <i class="fas fa-bell"></i>
                                <span
                                    id="notificationsUnreadBadge"
                                    class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-danger {{ $unreadNotificationsCount > 0 ? '' : 'd-none' }}"
                                >
                                    {{ $unreadNotificationsCount > 99 ? '99+' : $unreadNotificationsCount }}
                                </span>
                            </a>
                            <div class="dropdown-menu dropdown-menu-end p-0" style="min-width: 330px;">
                                <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                                    <strong>Notificaciones</strong>
                                    <a href="{{ route('notifications.index') }}" class="small text-decoration-none">Ver todas</a>
                                </div>

                                        <div id="notificationsUnreadList">
                                            @if($unreadNotifications->isEmpty())
                                                <div id="notificationsEmptyState" class="px-3 py-3 text-muted small">No tienes notificaciones nuevas.</div>
                                            @else
                                                @foreach($unreadNotifications as $notification)
                                                    <a href="{{ $notification['open_url'] ?? '#' }}" class="dropdown-item py-2 js-notification-item">
                                                        <div class="fw-semibold">{{ $notification['title'] ?? 'Notificacion' }}</div>
                                                        <div class="small text-muted">{{ $notification['message'] ?? '' }}</div>
                                                        <div class="small text-muted">
                                                            {{ $notification['created_human'] ?? '' }}
                                                        </div>
                                                    </a>
                                        @endforeach
                                    @endif
                                </div>

                                <div class="border-top px-3 py-2">
                                    <form action="{{ route('notifications.mark-all-read') }}" method="POST">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-primary w-100">
                                            Marcar todas como leídas
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link" data-bs-toggle="dropdown" href="#" role="button">
                                <i class="fas fa-user"></i>
                                <span class="ms-2">{{ Auth::user()->name }}</span>
                            </a>
                            <div class="dropdown-menu dropdown-menu-end">
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="dropdown-item">
                                        <i class="fas fa-sign-out-alt me-2"></i> Cerrar sesión
                                    </button>
                                </form>
                            </div>
                        </li>
                    </ul>
                </div>
            </nav>

            <aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
                <div class="sidebar-brand">
                    <a href="{{ route('dashboard') }}" class="brand-link">
                        <span class="brand-text fw-light">{{ auth()->user()->nombre_completo }}</span>
                    </a>
                </div>

                <div class="sidebar-wrapper">
                    <nav class="mt-2">
                        <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="menu">
                            @foreach($menuItems as $item)
                                @php
                                    $allowedRoles = $item['roles'] ?? [];
                                    if (!empty($allowedRoles) && !auth()->user()->hasAnyRole($allowedRoles)) {
                                        continue;
                                    }

                                    $routeName = $item['route'] ?? 'dashboard';
                                    $active = request()->routeIs($routeName) ? 'active' : '';
                                    $badgeCount = null;

                                    if (!empty($item['badge_key'])) {
                                        $badgeCount = $menuBadges[$item['badge_key']] ?? 0;
                                    }
                                @endphp
                                <li class="nav-item">
                                    <a href="{{ route($routeName) }}" class="nav-link {{ $active }}">
                                        <i class="nav-icon {{ $item['icon'] ?? 'fas fa-circle' }}"></i>
                                        <p>
                                            {{ $item['label'] ?? 'Item' }}
                                            @if(!is_null($badgeCount))
                                                <span class="nav-badge badge text-bg-{{ $item['badge_type'] ?? 'secondary' }} ms-2">{{ $badgeCount }}</span>
                                            @endif
                                        </p>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </nav>
                </div>
            </aside>

            <main class="app-main">
                <div class="app-content-header">
                    <div class="container-fluid">
                        <div class="row">
                            <div class="col-sm-6">
                                @php
                                    $showBackButton = filter_var(trim($__env->yieldContent('show_back_button', '0')), FILTER_VALIDATE_BOOLEAN);
                                    $backUrl = trim($__env->yieldContent('back_url', ''));
                                @endphp
                                <div class="page-header-title-row">
                                    @if($showBackButton)
                                        <button
                                            type="button"
                                            class="btn btn-outline-secondary page-back-btn"
                                            onclick="window.location.href='{{ $backUrl !== '' ? $backUrl : route('dashboard') }}';"
                                        >
                                            <i class="fas fa-arrow-left me-1"></i> Volver
                                        </button>
                                    @endif
                                    <h3 class="mb-0">@yield('header', 'Dashboard')</h3>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <ol class="breadcrumb float-sm-end">
                                    @yield('breadcrumb')
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="app-content">
                    <div class="container-fluid">
                        @if(session('success'))
                            <div class="alert alert-success alert-dismissible fade show js-auto-dismiss-alert" role="alert" data-auto-dismiss="2200">
                                {{ session('success') }}
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        @endif
                        @if(session('error'))
                            <div class="alert alert-warning alert-dismissible fade show js-auto-dismiss-alert" role="alert" data-auto-dismiss="2800">
                                {{ session('error') }}
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        @endif
                        @if($errors->any())
                            <div class="alert alert-danger alert-dismissible fade show js-auto-dismiss-alert" role="alert" data-auto-dismiss="3200">
                                <strong>Revisa los campos del formulario.</strong>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        @endif

                        @yield('content')
                        {{ $slot ?? '' }}
                    </div>
                </div>
            </main>

            <footer class="app-footer">
                <div class="float-end d-none d-sm-inline">Version 1.0</div>
                <strong>Version {{ date('Y') }} {{ config('adminlte.app_name') }}</strong>
                {{ config('adminlte.footer_text') }}.
            </footer>
        </div>
    @else
        <div class="login-page bg-body-secondary" style="min-height:100vh; display:flex; align-items:center; justify-content:center; padding:20px;">
            @yield('content')
            {{ $slot ?? '' }}
        </div>
    @endauth

    @livewireScripts
    <div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1080;">
        <div id="notificationsToast" class="toast align-items-center text-bg-primary border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body" id="notificationsToastBody">Tienes nuevas notificaciones.</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Cerrar"></button>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const authUserId = @json((int) auth()->id());
            const notificationsState = {
                lastCount: parseInt(document.getElementById('notificationsUnreadBadge')?.textContent || '0', 10) || 0,
            };

            const playNotificationBeep = function () {
                try {
                    const AudioCtx = window.AudioContext || window.webkitAudioContext;
                    if (!AudioCtx) {
                        return;
                    }

                    const audioContext = new AudioCtx();
                    const oscillator = audioContext.createOscillator();
                    const gainNode = audioContext.createGain();

                    oscillator.type = 'sine';
                    oscillator.frequency.setValueAtTime(880, audioContext.currentTime);
                    gainNode.gain.setValueAtTime(0.0001, audioContext.currentTime);
                    gainNode.gain.exponentialRampToValueAtTime(0.15, audioContext.currentTime + 0.02);
                    gainNode.gain.exponentialRampToValueAtTime(0.0001, audioContext.currentTime + 0.22);

                    oscillator.connect(gainNode);
                    gainNode.connect(audioContext.destination);
                    oscillator.start();
                    oscillator.stop(audioContext.currentTime + 0.22);
                } catch (error) {
                    // Audio can be blocked on some browsers until user interaction.
                }
            };

            const showBrowserNotification = function (count) {
                if (!('Notification' in window) || Notification.permission !== 'granted') {
                    return;
                }

                const body = count === 1
                    ? 'Tienes 1 notificación nueva en Help Desk.'
                    : `Tienes ${count} notificaciones sin leer en Help Desk.`;

                new Notification('Help Desk', {
                    body,
                    tag: 'helpdesk-unread-notifications',
                    renotify: true,
                });
            };

            const renderNotificationDropdown = function (payload) {
                const badgeElement = document.getElementById('notificationsUnreadBadge');
                const listElement = document.getElementById('notificationsUnreadList');

                if (!badgeElement || !listElement || !payload || typeof payload.count !== 'number') {
                    return;
                }

                const unreadCount = payload.count;
                if (unreadCount > 0) {
                    badgeElement.classList.remove('d-none');
                    badgeElement.textContent = unreadCount > 99 ? '99+' : String(unreadCount);
                } else {
                    badgeElement.classList.add('d-none');
                    badgeElement.textContent = '';
                }

                const items = Array.isArray(payload.items) ? payload.items : [];
                if (items.length === 0) {
                    listElement.innerHTML = '<div id="notificationsEmptyState" class="px-3 py-3 text-muted small">No tienes notificaciones nuevas.</div>';
                } else {
                    const escapeHtml = function (value) {
                        return String(value)
                            .replace(/&/g, '&amp;')
                            .replace(/</g, '&lt;')
                            .replace(/>/g, '&gt;')
                            .replace(/"/g, '&quot;')
                            .replace(/'/g, '&#039;');
                    };

                    listElement.innerHTML = items.map(function (item) {
                        const title = escapeHtml(item.title || 'Notificación');
                        const message = escapeHtml(item.message || '');
                        const created = escapeHtml(item.created_human || '');
                        const openUrl = escapeHtml(item.open_url || '#');
                        return `
                            <a href="${openUrl}" class="dropdown-item py-2 js-notification-item">
                                <div class="fw-semibold">${title}</div>
                                <div class="small text-muted">${message}</div>
                                <div class="small text-muted">${created}</div>
                            </a>
                        `;
                    }).join('');
                }

                if (unreadCount > notificationsState.lastCount) {
                    playNotificationBeep();
                    showBrowserNotification(unreadCount);

                    if (window.bootstrap && window.bootstrap.Toast) {
                        const toastEl = document.getElementById('notificationsToast');
                        const toastBody = document.getElementById('notificationsToastBody');

                        if (toastEl && toastBody) {
                            toastBody.textContent = unreadCount === 1
                                ? 'Tienes 1 notificación nueva.'
                                : `Tienes ${unreadCount} notificaciones sin leer.`;
                            window.bootstrap.Toast.getOrCreateInstance(toastEl, { delay: 2500 }).show();
                        }
                    }
                }

                notificationsState.lastCount = unreadCount;
            };

            const refreshNotificationSummary = function () {
                if (document.hidden) {
                    return;
                }

                fetch('{{ route('notifications.summary') }}', {
                    credentials: 'same-origin',
                    cache: 'no-store',
                })
                    .then(function (response) {
                        if (!response.ok) {
                            return null;
                        }
                        return response.json();
                    })
                    .then(function (payload) {
                        if (payload) {
                            renderNotificationDropdown(payload);
                        }
                    })
                    .catch(function () {
                        // Ignore transient network errors silently.
                    });
            };

            document.querySelectorAll('.js-auto-dismiss-alert').forEach(function (alertElement) {
                const timeoutValue = parseInt(alertElement.getAttribute('data-auto-dismiss') || '5000', 10);

                window.setTimeout(function () {
                    if (!alertElement.isConnected) {
                        return;
                    }

                    if (window.bootstrap && window.bootstrap.Alert) {
                        window.bootstrap.Alert.getOrCreateInstance(alertElement).close();
                        return;
                    }

                    alertElement.classList.remove('show');
                    window.setTimeout(function () {
                        alertElement.remove();
                    }, 250);
                }, timeoutValue);
            });

            document.querySelectorAll('form.js-table-filters').forEach(function (form) {
                const searchInput = form.querySelector('input[name="q"]');
                const filterSelects = form.querySelectorAll('select');
                const dateInputs = form.querySelectorAll('input[type="date"]');
                let resultsCard = document.querySelector('.js-table-results');
                let searchTimer = null;
                let activeController = null;

                const loadFilteredTable = function (targetUrl = null) {
                    if (!resultsCard) {
                        form.submit();
                        return;
                    }

                    const params = new URLSearchParams(new FormData(form));
                    params.delete('page');

                    if (targetUrl) {
                        const parsedTargetUrl = new URL(targetUrl, window.location.origin);
                        const page = parsedTargetUrl.searchParams.get('page');
                        if (page) {
                            params.set('page', page);
                        }
                    }

                    const requestUrl = `${form.action}?${params.toString()}`;

                    if (activeController) {
                        activeController.abort();
                    }

                    activeController = new AbortController();

                    fetch(requestUrl, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        signal: activeController.signal,
                    })
                        .then(function (response) {
                            return response.text();
                        })
                        .then(function (html) {
                            const parser = new DOMParser();
                            const doc = parser.parseFromString(html, 'text/html');
                            const newResultsCard = doc.querySelector('.js-table-results');

                            if (!newResultsCard || !resultsCard) {
                                window.location.href = requestUrl;
                                return;
                            }

                            resultsCard.replaceWith(newResultsCard);
                            resultsCard = newResultsCard;
                            history.replaceState({}, '', requestUrl);
                        })
                        .catch(function (error) {
                            if (error.name === 'AbortError') {
                                return;
                            }

                            window.location.href = requestUrl;
                        });
                };

                if (searchInput) {
                    searchInput.addEventListener('input', function () {
                        clearTimeout(searchTimer);
                        searchTimer = setTimeout(function () {
                            loadFilteredTable();
                        }, 500);
                    });

                    searchInput.addEventListener('search', function () {
                        loadFilteredTable();
                    });
                }

                filterSelects.forEach(function (select) {
                    select.addEventListener('change', function () {
                        loadFilteredTable();
                    });
                });

                dateInputs.forEach(function (dateInput) {
                    dateInput.addEventListener('change', function () {
                        loadFilteredTable();
                    });
                });

                document.addEventListener('click', function (event) {
                    const paginationLink = event.target.closest('.js-table-results .pagination a[href]');

                    if (!paginationLink) {
                        return;
                    }

                    event.preventDefault();
                    loadFilteredTable(paginationLink.href);
                });
            });

            document.querySelectorAll('form').forEach(function (form) {
                const checkpointButton = form.querySelector('.checkpoint-switch');

                if (!checkpointButton) {
                    return;
                }

                form.addEventListener('submit', function (event) {
                    if (form.dataset.checkpointSubmitting === '1') {
                        return;
                    }

                    event.preventDefault();
                    form.dataset.checkpointSubmitting = '1';

                    const nextIsOn = checkpointButton.classList.contains('is-off');
                    checkpointButton.classList.toggle('is-on', nextIsOn);
                    checkpointButton.classList.toggle('is-off', !nextIsOn);
                    checkpointButton.title = nextIsOn ? 'Habilitado' : 'Deshabilitado';

                    const labelElement = checkpointButton.querySelector('.checkpoint-switch__label');
                    if (labelElement) {
                        labelElement.textContent = nextIsOn ? 'ON' : 'OFF';
                    }

                    const syncTarget = form.getAttribute('data-sync-active-target');
                    if (syncTarget) {
                        const activeRow = document.querySelector(`[data-active-row="${syncTarget}"]`);
                        const activeBadge = activeRow ? activeRow.querySelector('[data-active-badge]') : null;
                        const activeSelect = document.querySelector(`[data-edit-active-select="${syncTarget}"]`);

                        if (activeBadge) {
                            activeBadge.textContent = nextIsOn ? 'Si' : 'No';
                            activeBadge.classList.toggle('text-bg-success', nextIsOn);
                            activeBadge.classList.toggle('text-bg-secondary', !nextIsOn);
                        }

                        if (activeSelect) {
                            activeSelect.value = nextIsOn ? '1' : '0';
                        }
                    }

                    checkpointButton.disabled = true;

                    window.setTimeout(function () {
                        form.submit();
                    }, 180);
                });
            });

            refreshNotificationSummary();
            const hasNotificationSocket = Boolean(window.Echo && typeof window.Echo.private === 'function');

            if (hasNotificationSocket && authUserId > 0) {
                window.Echo.private(`users.${authUserId}.notifications`)
                    .listen('.notifications.updated', function () {
                        refreshNotificationSummary();
                    });
            }

            window.setInterval(refreshNotificationSummary, hasNotificationSocket ? 180000 : 60000);

            const bellButton = document.getElementById('notificationsBellButton');
            if (bellButton && 'Notification' in window) {
                bellButton.addEventListener('click', function () {
                    if (Notification.permission === 'default') {
                        Notification.requestPermission();
                    }
                });
            }
        });
    </script>
    @stack('scripts')
</body>
</html>
