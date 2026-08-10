<?php

namespace App\Http\Controllers;

use App\Enumerados\EstadoPago;
use App\Models\Pago;
use App\Models\WebhookEvento;
use App\Servicios\PagoService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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
    public function __construct(private readonly PagoService $pagos) {}

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
        if (WebhookEvento::registrarSiEsNuevo('Stripe', $evento->id, $evento->type)) {
            $this->procesar($evento);
        }

        return response(['recibido' => true], 200);
    }

    private function procesar(Event $evento): void
    {
        match ($evento->type) {
            'payment_intent.succeeded' => $this->procesarPagoExitoso($evento->data->object),
            'payment_intent.payment_failed' => $this->procesarPagoFallido($evento->data->object),
            default => null,
        };
    }

    // HU-20.4: confirma pagos con tarjeta guardada que requirieron un paso de
    // autenticación adicional (3DS) tras la respuesta síncrona inicial
    private function procesarPagoExitoso(PaymentIntent $intent): void
    {
        $pago = Pago::where('referencia_pasarela', $intent->id)->first();

        if ($pago === null || $pago->estado !== EstadoPago::Procesando) {
            return;
        }

        $this->pagos->aprobar($pago);
    }

    private function procesarPagoFallido(PaymentIntent $intent): void
    {
        $pago = Pago::where('referencia_pasarela', $intent->id)->first();

        if ($pago === null || $pago->estado !== EstadoPago::Procesando) {
            return;
        }

        $motivo = $intent->last_payment_error->message ?? 'Pago rechazado por la pasarela.';

        $this->pagos->rechazar($pago, null, $motivo);
    }
}
