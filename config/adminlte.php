<?php

return [
    'app_name' => env('ADMINLTE_APP_NAME', 'Tarea Completo'),
    'topbar_title' => env('ADMINLTE_TOPBAR_TITLE', 'Sistema de Soporte Tecnico / Help Desk'),
    'logo_text' => env('ADMINLTE_LOGO_TEXT', 'Tarea Completo'),
    'footer_text' => env('ADMINLTE_FOOTER_TEXT', 'Todos los derechos reservados'),

    'menu' => [
        [
            'label' => 'Dashboard',
            'route' => 'dashboard',
            'icon' => 'fas fa-gauge-high',
        ],
        [
            'label' => 'Usuarios',
            'route' => 'usuarios.index',
            'icon' => 'fas fa-user-shield',
        ],
        [
            'label' => 'Clientes',
            'route' => 'clientes.index',
            'icon' => 'fas fa-users',
        ],
        [
            'label' => 'Empleados',
            'route' => 'empleados.index',
            'icon' => 'fas fa-user-tie',
        ],
        [
            'label' => 'Departamentos',
            'route' => 'departamentos.index',
            'icon' => 'fas fa-building',
        ],
        [
            'label' => 'Tickets',
            'route' => 'tickets.index',
            'icon' => 'fas fa-ticket-alt',
            'badge_type' => 'warning',
            'badge_key' => 'pendientes',
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
