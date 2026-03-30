<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Codigo de verificacion</title>
</head>
<body style="margin:0;padding:24px;background:#f6f8fb;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px;margin:0 auto;">
        <tr>
            <td style="background:#ffffff;border-radius:14px;padding:28px;border:1px solid #e5e7eb;">
                <h1 style="margin:0 0 10px 0;font-size:22px;color:#0f172a;">Recuperacion de contrasena</h1>
                <p style="margin:0 0 18px 0;font-size:15px;line-height:1.55;color:#334155;">
                    Recibimos una solicitud para cambiar tu contrasena. Usa este codigo de verificacion:
                </p>

                <div style="background:#f1f5f9;border:1px dashed #94a3b8;border-radius:10px;padding:16px;text-align:center;margin:0 0 18px 0;">
                    <span style="font-size:34px;letter-spacing:8px;font-weight:700;color:#0f172a;">{{ $code }}</span>
                </div>

                <p style="margin:0 0 8px 0;font-size:14px;color:#475569;">
                    Este codigo vence en {{ $expiresIn }} minutos.
                </p>
                <p style="margin:0;font-size:14px;color:#475569;">
                    Si no solicitaste este cambio, puedes ignorar este correo.
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
