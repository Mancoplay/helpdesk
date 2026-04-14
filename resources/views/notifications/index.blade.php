@extends('layouts.app')

@section('title', 'Notificaciones')
@section('header', 'Notificaciones')

@section('content')
    <div class="card shadow-sm">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h3 class="card-title mb-0">Historial de notificaciones</h3>
            <form action="{{ route('notifications.mark-all-read') }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-primary">
                    Marcar todas como leidas
                </button>
            </form>
        </div>

        <div class="card-body p-0">
            @if($notifications->isEmpty())
                <div class="p-4 text-muted">No tienes notificaciones por ahora.</div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead>
                            <tr>
                                <th style="width: 220px;">Fecha</th>
                                <th>Titulo</th>
                                <th>Detalle</th>
                                <th style="width: 120px;">Estado</th>
                                <th style="width: 140px;" class="text-end">Accion</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($notifications as $notification)
                                @php
                                    $data = $notification->data;
                                    $isUnread = is_null($notification->read_at);
                                @endphp
                                <tr class="{{ $isUnread ? 'table-warning' : '' }}">
                                    <td>{{ optional($notification->created_at)->format('d/m/Y H:i') }}</td>
                                    <td>{{ $data['title'] ?? 'Notificacion' }}</td>
                                    <td>
                                        {{ $data['message'] ?? '' }}
                                        @if(!empty($data['ticket_subject']))
                                            <div class="small text-muted">
                                                {{ $data['ticket_code'] ?? '-' }} | {{ $data['ticket_subject'] }}
                                            </div>
                                        @endif
                                    </td>
                                    <td>
                                        @if($isUnread)
                                            <span class="badge text-bg-warning">No leida</span>
                                        @else
                                            <span class="badge text-bg-secondary">Leida</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('notifications.open', $notification->id) }}" class="btn btn-sm btn-outline-dark">
                                            Abrir
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        @if($notifications->hasPages())
            <div class="card-footer">
                {{ $notifications->links() }}
            </div>
        @endif
    </div>
@endsection
