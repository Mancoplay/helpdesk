<?php

use App\Models\Empleado;
use App\Models\Ticket;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('users.{id}.notifications', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('users.{id}.tickets', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('tickets.admins', function ($user) {
    return $user->hasRole('Administrador');
});

Broadcast::channel('tickets.employees', function ($user) {
    return $user->hasRole('Empleado');
});

Broadcast::channel('tickets.{ticketId}', function ($user, $ticketId) {
    if ($user->hasRole('Administrador')) {
        return true;
    }

    $ticket = Ticket::query()
        ->with('cliente')
        ->find($ticketId);

    if (!$ticket) {
        return false;
    }

    if ($user->hasRole('Empleado')) {
        $employee = Empleado::query()
            ->whereKey($user->id)
            ->orWhere('email', $user->email)
            ->first();

        return $employee && (int) $ticket->empleado_id === (int) $employee->id;
    }

    if (!$user->hasAnyRole(['Cliente', 'Usuario']) || !$ticket->cliente) {
        return false;
    }

    return (int) ($ticket->cliente->id ?? 0) === (int) $user->id
        || ($ticket->cliente->email ?? null) === $user->email;
});
