<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('adminlte.app_name', config('app.name', 'Help Desk')))</title>

    @vite(['resources/sass/app.scss', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('css/postal-theme.css') }}?v=20260329-1">
    @livewireStyles
    @stack('styles')
</head>
<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
    @php
        $menuItems = config('adminlte.menu', []);
        $menuBadges = $menuBadges ?? [];
    @endphp

    @auth
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
                            <a class="nav-link" data-bs-toggle="dropdown" href="#" role="button">
                                <i class="fas fa-user"></i>
                                <span class="ms-2">{{ Auth::user()->name }}</span>
                            </a>
                            <div class="dropdown-menu dropdown-menu-end">
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="dropdown-item">
                                        <i class="fas fa-sign-out-alt me-2"></i> Cerrar sesion
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
                                @endphp
                                <div class="page-header-title-row">
                                    @if($showBackButton)
                                        <button
                                            type="button"
                                            class="btn btn-outline-secondary page-back-btn"
                                            onclick="if (window.history.length > 1) { window.history.back(); } else { window.location.href='{{ route('dashboard') }}'; }"
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
    <script>
        document.addEventListener('DOMContentLoaded', function () {
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
                const perPageSelect = form.querySelector('select[name="per_page"]');
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

                if (perPageSelect) {
                    perPageSelect.addEventListener('change', function () {
                        loadFilteredTable();
                    });
                }

                document.addEventListener('click', function (event) {
                    const paginationLink = event.target.closest('.js-table-results .pagination a[href]');

                    if (!paginationLink) {
                        return;
                    }

                    event.preventDefault();
                    loadFilteredTable(paginationLink.href);
                });
            });
        });
    </script>
    @stack('scripts')
</body>
</html>
