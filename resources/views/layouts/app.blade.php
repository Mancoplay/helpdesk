<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('adminlte.app_name', config('app.name', 'Help Desk')))</title>

    @vite(['resources/sass/app.scss', 'resources/js/app.js'])
    @livewireStyles

    <style>
        :root {
            --lte-sidebar-width: 280px;
        }
        .dashboard-stat .icon {
            width: 45px;
            height: 45px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1.1rem;
            margin-right: 10px;
        }
        .dashboard-stat .label {
            color: #6c757d;
            font-size: 0.92rem;
        }
        .dashboard-stat .value {
            font-size: 1.1rem;
            font-weight: 700;
            margin: 0;
        }
        .card-graph .card-header {
            background: #00264d;
            color: #fff;
        }
        .app-sidebar .sidebar-menu .nav-link {
            border-radius: 8px;
            margin: 0.2rem 0.5rem;
            background-color: rgba(255, 255, 255, 0.08);
            color: #fff;
            font-size: 1.05rem;
        }
        .app-sidebar .sidebar-menu .nav-link:hover,
        .app-sidebar .sidebar-menu .nav-link.active {
            background-color: rgba(255, 255, 255, 0.18);
            color: #fff;
        }
        .app-sidebar .sidebar-menu .nav-link .nav-badge {
            border-radius: 10px;
            font-weight: 700;
            min-width: 26px;
        }
        .compact-pagination .pagination {
            margin-bottom: 0;
        }
        .compact-pagination .page-link {
            padding: 0.2rem 0.45rem;
            font-size: 0.8rem;
            line-height: 1.1;
        }
        .compact-pagination .page-item.active .page-link {
            font-weight: 600;
        }
        .compact-pagination .page-link svg {
            width: 12px;
            height: 12px;
        }
    </style>
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
                                <h3 class="mb-0">@yield('header', 'Dashboard')</h3>
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
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                {{ session('success') }}
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        @endif
                        @if(session('error'))
                            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                                {{ session('error') }}
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        @endif
                        @if($errors->any())
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
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
