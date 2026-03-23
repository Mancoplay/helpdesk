@extends('layouts.app')

@section('title', 'Ver ticket')
@section('header', 'Ver ticket')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item"><a href="{{ route('tickets.index') }}">Tickets</a></li>
    <li class="breadcrumb-item active">{{ $ticket->codigo }}</li>
@endsection

@section('content')
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card border-success">
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
                        <strong>Cliente</strong>
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
                        <form method="POST" action="{{ route('tickets.attend', $ticket) }}" onsubmit="return confirm('Estas seguro de que quieres atender este ticket? El estado cambiara a "En proceso" y se asignara a ti.');">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="btn btn-info">Atender ticket</button>
                        </form>
                    </div>
                @endif

                @if(
                    auth()->user()->hasRole('Empleado')
                    && in_array($ticket->estado, ['pendiente', 'en_proceso'], true)
                    && (int) ($ticket->empleado_id ?? 0) === (int) (optional(auth()->user()->empleado)->id ?? 0)
                )
                    <div class="card-footer">
                        <form method="POST" action="{{ route('tickets.finalize', $ticket) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="btn btn-success">Finalizar ticket</button>
                        </form>
                    </div>
                @endif
            @endcan
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border-primary">
            <div class="card-header">
                <h3 class="card-title mb-0">Comunicacion</h3>
            </div>
            <div class="card-body">
                <div class="border rounded p-2 mb-3" style="max-height: 360px; overflow-y: auto;">
                    @forelse($messages as $mensaje)
                        @php
                            $tipoBadge = match($mensaje->tipo) {
                                'creacion' => 'primary',
                                'atencion' => 'info',
                                default => 'secondary',
                            };
                        @endphp
                        <div class="mb-3 pb-2 border-bottom">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <strong>{{ $mensaje->user->name ?? 'Sistema' }}</strong>
                                    <span class="badge text-bg-{{ $tipoBadge }}">{{ $mensaje->tipo }}</span>
                                </div>
                                <small class="text-muted">{{ $mensaje->created_at?->format('d/m/Y H:i') }}</small>
                            </div>
                            @if(!empty($mensaje->mensaje))
                                <p class="mb-1">{{ $mensaje->mensaje }}</p>
                            @endif
                            @if($mensaje->imagen_path)
                                <a href="{{ asset('storage/' . $mensaje->imagen_path) }}" target="_blank" rel="noopener">
                                    <img
                                        src="{{ asset('storage/' . $mensaje->imagen_path) }}"
                                        alt="Adjunto"
                                        class="img-fluid rounded border"
                                        style="max-height: 180px;"
                                    >
                                </a>
                            @endif
                        </div>
                    @empty
                        <p class="text-muted mb-0">Sin mensajes por el momento.</p>
                    @endforelse
                </div>

                @php
                    $mustAttendFirst = auth()->user()->hasRole('Empleado') && $ticket->estado === 'pendiente';
                @endphp

                @if($mustAttendFirst)
                    <div class="alert alert-warning mb-0">
                        Este ticket aun no esta siendo atendido. Presiona <strong>Atender ticket</strong> para habilitar el chat y la carga de imagenes.
                    </div>
                @elseif($ticket->estado === 'finalizado')
                    <div class="alert alert-secondary mb-0">
                        Ticket finalizado. El chat esta bloqueado y ya no se permiten comentarios.
                    </div>
                @else
                    <form method="POST" action="{{ route('tickets.messages.store', $ticket) }}" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-2">
                            <label class="form-label">Nuevo comentario</label>
                            <textarea name="mensaje" class="form-control" rows="3" placeholder="Escribe un mensaje...">{{ old('mensaje') }}</textarea>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Adjuntar imagen (opcional)</label>
                            <input type="file" name="imagen" class="form-control" accept=".jpg,.jpeg,.png,.webp,image/*">
                            <small class="text-muted">Maximo 4 MB. Formatos: JPG, PNG, WEBP.</small>
                        </div>
                        <div class="text-end">
                            <button type="submit" class="btn btn-primary btn-sm">Enviar</button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
