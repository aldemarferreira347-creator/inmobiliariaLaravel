{{-- Plantilla base de los correos; estilos en línea porque los clientes de correo no cargan hojas externas --}}
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $titulo }}</title>
</head>

<body style="margin:0;padding:0;background-color:#f8f9fc;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
        style="background-color:#f8f9fc;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                    style="max-width:560px;background-color:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 2px 12px rgba(15,30,74,0.08);">
                    <tr>
                        <td style="background:linear-gradient(135deg,#0f1e4a,#1e3177);padding:24px;text-align:center;">
                            <span style="color:#ffffff;font-size:18px;font-weight:bold;letter-spacing:0.3px;">
                                Inmobiliaria García
                            </span>
                            <div style="height:3px;background-color:#f5a623;margin-top:16px;border-radius:2px;"></div>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:28px 28px 8px 28px;">
                            <h1 style="margin:0 0 16px 0;font-size:20px;color:#0f1e4a;">{{ $titulo }}</h1>
                            {{ $slot }}
                        </td>
                    </tr>

                    @isset($accion)
                        <tr>
                            <td style="padding:8px 28px 28px 28px;">
                                <a href="{{ $accion['url'] }}"
                                    style="display:inline-block;background-color:#f5a623;color:#0f1e4a;text-decoration:none;font-weight:bold;padding:12px 22px;border-radius:999px;">
                                    {{ $accion['texto'] }}
                                </a>
                            </td>
                        </tr>
                    @endisset

                    <tr>
                        <td
                            style="background-color:#eef1f8;padding:18px 28px;text-align:center;font-size:12px;color:#64748b;">
                            Inmobiliaria García — Neiva, Huila<br>
                            Este es un mensaje automático; no respondas a este correo.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>

</html>
