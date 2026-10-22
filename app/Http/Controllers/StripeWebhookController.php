<?php

namespace App\Http\Controllers;

use App\Enumerados\EstadoPago;
use App\Enumerados\TipoNotificacion;
use App\Models\Pago;
use App\Models\WebhookEvento;
use App\Servicios\NotificacionService;
use App\Servicios\PagoService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\PaymentIntent;
use Stripe\Webhook;
use UnexpectedValueException;

/**
 * Recibe los eventos de webhook de Stripe (HU-20.4 / HU-23.1).
 *
 * No requiere sesión ni CSRF: la autenticidad se valida con la firma
 * Stripe-Signature contra STRIPE_WEBHOOK_SECRET, no con la sesión del
 * navegador — quien llama es el servidor de Stripe, nunca el cliente.
 */
class StripeWebhookController extends Controller
{
    public function __construct(
        private readonly PagoService $pagos,
        private readonly NotificacionService $notificaciones,
    ) {}

    /** Punto de entrada del webhook: valida la firma Stripe y despacha el evento si es nuevo. */
    public function recibir(Request $request): Response
    {
        try {
            $evento = Webhook::constructEvent(
                $request->getContent(),
                (string) $request->header('Stripe-Signature'),
                (string) config('services.stripe.webhook_secret'),
            );
        } catch (UnexpectedValueException|SignatureVerificationException $e) {
            report($e);

            return response(['error' => 'Webhook inválido.'], 400);
        }

        // Un mismo evento reenviado por Stripe (reintento de red) no debe aplicarse dos veces
        WebhookEvento::procesarUnaVez('Stripe', $evento->id, $evento->type, fn () => $this->procesar($evento));

        return response(['recibido' => true], 200);
    }

    /** Enruta el evento de Stripe al manejador correspondiente según su tipo. */
    private function procesar(Event $evento): void
    {
        match ($evento->type) {
            'payment_intent.succeeded' => $this->procesarPagoExitoso($evento->data->object),
            'payment_intent.payment_failed' => $this->procesarPagoFallido($evento->data->object),
            default => null,
        };
    }

    /** Aprueba en el sistema un {@see Pago} que Stripe confirmó como exitoso. */
    // HU-20.4: confirma pagos con tarjeta guardada que requirieron un paso de
    // autenticación adicional (3DS) tras la respuesta síncrona inicial
    private function procesarPagoExitoso(PaymentIntent $intent): void
    {
        $pago = Pago::where('referencia_pasarela', $intent->id)->first();

        if ($pago === null || $pago->estado === EstadoPago::Pagado) {
            return;
        }

        try {
            $this->pagos->aprobar($pago);
        } catch (ValidationException) {
            // Stripe cobró, pero el pago ya no estaba en revisión (p. ej. la
            // reserva se canceló mientras el cliente superaba el 3DS). No hay
            // nada que confirmar: el dinero debe devolverse a mano.
            $this->notificaciones->paraStaff(
                'Cobro que requiere reembolso',
                "Stripe confirmó el cobro {$intent->id} ({$pago->monto_formateado}) de la reserva "
                    ."{$pago->reserva->codigo_reserva}, que ya no esperaba ese pago. Revisa el reembolso.",
                TipoNotificacion::Error,
                'reserva',
                $pago->reserva_id,
            );
        }
    }

    /** Rechaza en el sistema un {@see Pago} que Stripe reportó como fallido. */
    private function procesarPagoFallido(PaymentIntent $intent): void
    {
        $pago = Pago::where('referencia_pasarela', $intent->id)->first();

        if ($pago === null || $pago->estado !== EstadoPago::Procesando) {
            return;
        }

        $motivo = $intent->last_payment_error->message ?? 'Pago rechazado por la pasarela.';

        try {
            $this->pagos->rechazar($pago, null, $motivo);
        } catch (ValidationException) {
            // La reserva ya se cerró por otra vía: no queda nada que rechazar
        }
    }
}
