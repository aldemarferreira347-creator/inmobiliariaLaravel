@php($reserva = $pago->reserva)

@component('emails.layout', [
    'titulo' => 'Comprobante de pago',
    'accion' => ['texto' => 'Ver mi reserva', 'url' => route('reservas.show', $reserva)],
])
    <p style="margin:0 0 14px 0;font-size:15px;line-height:1.6;color:#334155;">
        Hola {{ $reserva->cliente->nombre }}, confirmamos la recepción de tu pago. Tu reserva ya está confirmada.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
        style="border-collapse:collapse;margin:8px 0 16px 0;font-size:14px;">
        @foreach ([
            'Reserva' => $reserva->codigo_reserva,
            'Inmueble' => $reserva->inmueble->titulo,
            'Monto' => $pago->monto_formateado,
            'Método' => $pago->metodo_pago->etiqueta(),
            'Referencia' => $pago->referencia ?: '—',
            'Fecha' => $pago->revisado_en?->format('d/m/Y H:i'),
        ] as $etiqueta => $valor)
            <tr>
                <td style="padding:8px 0;color:#64748b;border-bottom:1px solid #eef1f8;">{{ $etiqueta }}</td>
                <td
                    style="padding:8px 0;text-align:right;font-weight:bold;color:#0f1e4a;border-bottom:1px solid #eef1f8;">
                    {{ $valor }}
                </td>
            </tr>
        @endforeach
    </table>

    <p style="margin:0 0 14px 0;font-size:15px;line-height:1.6;color:#334155;">
        Conserva este comprobante. Nos pondremos en contacto contigo para los siguientes pasos.
    </p>
@endcomponent
