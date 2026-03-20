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
        .topbar-system {
            background: #e53935;
            color: #fff;
            font-weight: 600;
            font-size: 0.95rem;
        }
        .topbar-system .system-title {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
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
    </style>
</head>
<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
    @php
        $menuItems = config('adminlte.menu', []);
        $menuBadges = $menuBadges ?? [];
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
                    <li class="nav-item d-none d-md-inline-flex align-items-center text-white-50 fw-semibold">
                        <i class="fas fa-circle text-primary me-2"></i>
                        {{ config('adminlte.logo_text') }}
                    </li>
                </ul>

                <ul class="navbar-nav ms-auto">
                    @auth
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
                    @else
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('login') }}">
                                <i class="fas fa-sign-in-alt"></i>
                                <span class="ms-2">Iniciar sesion</span>
                            </a>
                        </li>
                    @endauth
                </ul>
            </div>
        </nav>

        <div class="topbar-system py-2 px-3 d-flex align-items-center">
            <i class="fas fa-server me-2"></i>
            <span class="system-title">{{ config('adminlte.topbar_title') }}</span>
        </div>

        @auth
            <aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
                <div class="sidebar-brand">
                    <a href="{{ route('dashboard') }}" class="brand-link">
                        <span class="brand-text fw-light">{{ config('adminlte.app_name') }}</span>
                    </a>
                </div>

                <div class="sidebar-wrapper">
                    <nav class="mt-2">
                        <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="menu">
                            @foreach($menuItems as $item)
                                @php
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
        @endauth

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
                    @yield('content')
                    {{ $slot ?? '' }}
                </div>
            </div>
        </main>

        <footer class="app-footer">
            <div class="float-end d-none d-sm-inline">Version 1.0</div>
            <strong>Copyright {{ date('Y') }} {{ config('adminlte.app_name') }}</strong>
            {{ config('adminlte.footer_text') }}.
        </footer>
    </div>

    @livewireScripts
    @stack('scripts')
</body>
</html>
