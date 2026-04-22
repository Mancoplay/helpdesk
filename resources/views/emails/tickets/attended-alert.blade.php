<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ticket atendido</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.5;">
    <h2 style="margin-bottom: 12px;">Ticket atendido</h2>

    <p>
        El ticket <strong>#{{ $ticket->codigo }}</strong> fue atendido por
        <strong>{{ $attendedByName }}</strong>.
    </p>

    <ul>
        <li><strong>Codigo:</strong> {{ $ticket->codigo }}</li>
        <li><strong>Asunto:</strong> {{ $ticket->asunto }}</li>
        <li><strong>Estado:</strong> {{ str_replace('_', ' ', $ticket->estado) }}</li>
        <li><strong>Departamento:</strong> {{ $ticket->departamento->nombre ?? '-' }}</li>
    </ul>

    <p>
        Puedes ingresar al sistema para ver el avance y responder al ticket.
    </p>
</body>
</html>
