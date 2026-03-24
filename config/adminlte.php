<?php

return [
    'app_name' => env('ADMINLTE_APP_NAME', 'Helpdesk'),
    'topbar_title' => env('ADMINLTE_TOPBAR_TITLE', 'Sistema de Soporte Tecnico / Help Desk'),
    'logo_text' => env('ADMINLTE_LOGO_TEXT', 'helpdesk'),
    'footer_text' => env('ADMINLTE_FOOTER_TEXT', 'Todos los derechos reservados'),

    'menu' => [
        [
            'label' => 'Dashboard',
            'route' => 'dashboard',
            'icon' => 'fas fa-gauge-high',
            'roles' => ['Administrador', 'Empleado', 'Cliente'],
        ],
        [
            'label' => 'Clientes',
            'route' => 'clientes.index',
            'icon' => 'fas fa-users',
            'roles' => ['Administrador'],
        ],
        [
            'label' => 'Empleados',
            'route' => 'empleados.index',
            'icon' => 'fas fa-user-tie',
            'roles' => ['Administrador'],
        ],
        [
            'label' => 'Departamentos',
            'route' => 'departamentos.index',
            'icon' => 'fas fa-building',
            'roles' => ['Administrador'],
        ],
        [
            'label' => 'Tickets',
            'route' => 'tickets.index',
            'icon' => 'fas fa-ticket-alt',
            'badge_type' => 'warning',
            'badge_key' => 'pendientes',
            'roles' => ['Administrador', 'Empleado', 'Cliente'],
        ],
    ],

    'ticket_states' => [
        'pendiente' => [
            'label' => 'Tickets pendientes',
            'color' => '#f39c12',
            'badge' => 'warning',
        ],
        'en_proceso' => [
            'label' => 'Tickets en proceso',
            'color' => '#6f42c1',
            'badge' => 'primary',
        ],
        'finalizado' => [
            'label' => 'Tickets finalizados',
            'color' => '#28a745',
            'badge' => 'success',
        ],
        'cerrado' => [
            'label' => 'Tickets cerrados',
            'color' => '#dc3545',
            'badge' => 'danger',
        ],
    ],
];


