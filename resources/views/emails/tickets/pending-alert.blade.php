<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ticket pendiente</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.5;">
    <h2 style="margin-bottom: 8px;">
        {{ $isReminder ? 'Recordatorio de ticket pendiente' : 'Nuevo ticket pendiente' }}
    </h2>

    <p style="margin-top: 0;">
        Se registro un ticket para el departamento
        <strong>{{ $ticket->departamento->nombre ?? 'Sin departamento' }}</strong>.
    </p>

    <ul>
        <li><strong>Codigo:</strong> {{ $ticket->codigo }}</li>
        <li><strong>Asunto:</strong> {{ $ticket->asunto }}</li>
        <li><strong>Estado:</strong> {{ str_replace('_', ' ', $ticket->estado) }}</li>
        <li><strong>Cliente:</strong> {{ $ticket->cliente->email ?? '-' }}</li>
        <li><strong>Fecha:</strong> {{ $ticket->created_at?->format('Y-m-d H:i') }}</li>
    </ul>

    @if($isReminder)
        <p>Este es un recordatorio automatico porque el ticket sigue pendiente.</p>
    @else
        <p>Este es el aviso inicial del ticket.</p>
    @endif
</body>
</html>
