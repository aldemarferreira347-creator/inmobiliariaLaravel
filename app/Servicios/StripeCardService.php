<?php

namespace App\Servicios;

use App\Enumerados\EstadoPago;
use App\Enumerados\MetodoPago;
use App\Enumerados\PasarelaPago;
use App\Enumerados\RolUsuario;
use App\Enumerados\TipoNotificacion;
use App\Models\MetodoPagoGuardado;
use App\Models\Pago;
use App\Models\Reserva;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Stripe\Exception\ApiConnectionException;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\CardException;
use Stripe\PaymentIntent;
use Stripe\StripeClient;
use Throwable;

/**
 * Tarjetas guardadas y pago con tarjeta (HU-20 / HU-23.1).
 *
 * El número de tarjeta, el CVV y la fecha de expiración completa nunca pasan
 * por el servidor: Stripe.js/Elements tokeniza en el navegador del cliente y
 * aquí solo se re-consulta el PaymentMethod por su id ante Stripe, para no
 * confiar nunca en datos de tarjeta enviados por el propio cliente.
 */
class StripeCardService
{
    /** Códigos de Stripe que indican datos de tarjeta mal escritos (HU-23.4) */
    private const CODIGOS_DATOS_INVALIDOS = [
        'incorrect_number',
        'invalid_number',
        'invalid_expiry_month',
        'invalid_expiry_year',
        'invalid_cvc',
        'incorrect_cvc',
    ];

    private readonly StripeClient $stripe;

    public function __construct(
        private readonly PagoService $pagos,
        private readonly NotificacionService $notificaciones,
    ) {
        $this->stripe = new StripeClient(config('services.stripe.secret'));
    }

    // Crea (o reutiliza) el Customer del cliente y abre un SetupIntent para
    // tokenizar una tarjeta nueva desde el frontend.
    public function crearSetupIntent(User $cliente): array
    {
        $customerId = $this->obtenerOcrearCustomer($cliente);

        try {
            $setupIntent = $this->stripe->setupIntents->create([
                'customer' => $customerId,
                'payment_method_types' => ['card'],
                'usage' => 'off_session',
            ]);
        } catch (ApiErrorException $e) {
            throw ValidationException::withMessages([
                'tarjeta' => 'No se pudo iniciar el registro de la tarjeta: '.$e->getMessage(),
            ]);
        }

        return ['client_secret' => $setupIntent->client_secret, 'customer_id' => $customerId];
    }

    /**
     * Confirma el guardado de la tarjeta ya tokenizada por Stripe.js/Elements.
     *
     * @throws ValidationException
     */
    public function guardarTarjeta(User $cliente, string $paymentMethodId, string $customerId): MetodoPagoGuardado
    {
        if (MetodoPagoGuardado::query()->where('pasarela', PasarelaPago::Stripe)->where('token_pasarela', $paymentMethodId)->exists()) {
            throw ValidationException::withMessages(['tarjeta' => 'Esta tarjeta ya está guardada en tu cuenta.']);
        }

        try {
            $paymentMethod = $this->stripe->paymentMethods->retrieve($paymentMethodId);
        } catch (ApiErrorException $e) {
            throw ValidationException::withMessages(['tarjeta' => 'No se pudo verificar la tarjeta con Stripe: '.$e->getMessage()]);
        }

        if ($paymentMethod->customer !== $customerId) {
            throw ValidationException::withMessages(['tarjeta' => 'La tarjeta no corresponde a tu cuenta de pago.']);
        }

        if (! $paymentMethod->card) {
            throw ValidationException::withMessages(['tarjeta' => 'El método de pago recibido no es una tarjeta válida.']);
        }

        return MetodoPagoGuardado::create([
            'cliente_id' => $cliente->id,
            'pasarela' => PasarelaPago::Stripe,
            'token_pasarela' => $paymentMethod->id,
            'cliente_pasarela_id' => $customerId,
            'marca' => ucfirst((string) $paymentMethod->card->brand),
            'ultimos_4' => $paymentMethod->card->last4,
            'nombre_titular' => $paymentMethod->billing_details->name ?? null,
            'mes_expiracion' => $paymentMethod->card->exp_month,
            'anio_expiracion' => $paymentMethod->card->exp_year,
            'predeterminado' => ! $cliente->metodosPago()->activos()->exists(),
        ]);
    }

    // HU-20.2
    public function listar(User $cliente): Collection
    {
        return $cliente->metodosPago()->activos()->get();
    }

    // HU-20.3: baja lógica local + desvinculación en Stripe (best-effort)
    public function eliminar(MetodoPagoGuardado $tarjeta): void
    {
        try {
            $this->stripe->paymentMethods->detach($tarjeta->token_pasarela);
        } catch (ApiErrorException $e) {
            // No bloquea la baja local: si Stripe ya no la tiene o falla la red,
            // igual queremos que el cliente deje de verla/usarla en el sistema.
            report($e);
        }

        $tarjeta->update(['activo' => false]);
    }

    /**
     * HU-20.4 / HU-23.1: paga la reserva con una tarjeta guardada, sin pedir
     * datos de nuevo. Si Stripe exige autenticación adicional (3DS), el pago
     * queda «Procesando» y el webhook lo confirma cuando el cliente la supere.
     *
     * `estado`: succeeded, requires_action, rechazado, datos_invalidos, error_conexion o error.
     *
     * @return array{estado: string, client_secret?: string}
     *
     * @throws ValidationException
     */
    public function pagarConTarjeta(Reserva $reserva, MetodoPagoGuardado $tarjeta): array
    {
        // El pago queda creado (y la reserva bloqueada para otros intentos)
        // antes de llamar a Stripe: un doble clic no puede cobrar dos veces.
        $pago = $this->pagoParaIntentar($reserva, $tarjeta);

        try {
            $intent = $this->stripe->paymentIntents->create([
                // COP no es zero-decimal en Stripe: unit_amount va en centavos.
                'amount' => (int) round((float) $reserva->monto_reserva) * 100,
                'currency' => 'cop',
                'customer' => $tarjeta->cliente_pasarela_id,
                'payment_method' => $tarjeta->token_pasarela,
                'off_session' => false,
                'confirm' => true,
                'description' => "Reserva {$reserva->codigo_reserva}",
                'metadata' => ['reserva_id' => (string) $reserva->id, 'pago_id' => (string) $pago->id],
            ], ['idempotency_key' => "pago-{$pago->id}"]);
        } catch (CardException $e) {
            // HU-23.4: datos mal escritos no son un rechazo del banco ni llegan a cobrarse
            if (in_array($e->getStripeCode(), self::CODIGOS_DATOS_INVALIDOS, true)) {
                $this->pagos->descartar($pago, 'Datos de tarjeta inválidos: no se realizó ningún cobro.');

                return ['estado' => 'datos_invalidos'];
            }

            // HU-23.2: rechazo de la entidad bancaria
            $this->pagos->rechazar($pago, null, $e->getError()->message ?? 'Tarjeta rechazada.');

            return ['estado' => 'rechazado'];
        } catch (ApiConnectionException $e) {
            // HU-23.3: sin comunicación con la pasarela; el detalle técnico solo va al registro
            report($e);
            $this->pagos->rechazar($pago, null, 'No hubo comunicación con la pasarela de pago.');

            // Stripe pudo confirmar el cobro antes de caerse la conexión: el reintento
            // con esta misma tarjeta reutiliza el intento (misma clave de idempotencia)
            Cache::put(self::claveDeReintento($reserva, $tarjeta), $pago->id, now()->addHours(23));

            // Si el cliente no reintenta, nadie más conciliaría un cobro que Stripe sí confirmó
            try {
                $this->notificaciones->paraRol(
                    RolUsuario::Administrador,
                    'Pago con tarjeta sin confirmar',
                    "No hubo respuesta de Stripe al cobrar la reserva {$reserva->codigo_reserva} (pago #{$pago->id}, clave de idempotencia pago-{$pago->id}). Verifique en el panel de Stripe si el cobro se realizó.",
                    TipoNotificacion::Aviso,
                );
            } catch (Throwable $notificacion) {
                // Avisar es de mejor esfuerzo: nunca debe impedir la respuesta «Reintentar pago»
                report($notificacion);
            }

            return ['estado' => 'error_conexion'];
        } catch (Throwable $e) {
            // HU-23.5: cualquier otra falla
            report($e);
            $this->pagos->rechazar($pago, null, 'Error interno al procesar el pago.');

            return ['estado' => 'error'];
        }

        $pago->update(['referencia_pasarela' => $intent->id]);

        if ($intent->status === PaymentIntent::STATUS_SUCCEEDED) {
            $this->pagos->aprobar($pago);

            return ['estado' => 'succeeded'];
        }

        if ($intent->status === PaymentIntent::STATUS_REQUIRES_ACTION) {
            return ['estado' => 'requires_action', 'client_secret' => $intent->client_secret];
        }

        $this->pagos->rechazar($pago, null, "El pago no pudo completarse (estado: {$intent->status}).");

        return ['estado' => 'error'];
    }

    // Reabre el intento que falló por conexión, si lo hay; si no, crea uno nuevo
    private function pagoParaIntentar(Reserva $reserva, MetodoPagoGuardado $tarjeta): Pago
    {
        $clave = self::claveDeReintento($reserva, $tarjeta);
        $previo = Cache::get($clave);
        $pago = $previo ? Pago::where('reserva_id', $reserva->id)->whereKey($previo)->first() : null;

        if ($pago !== null && $pago->estado === EstadoPago::Rechazado && $pago->referencia_pasarela === null) {
            $reabierto = $this->pagos->reabrir($pago);
            Cache::forget($clave);

            return $reabierto;
        }

        // El pago queda creado (y la reserva bloqueada para otros intentos)
        // antes de llamar a Stripe: un doble clic no puede cobrar dos veces.
        return $this->pagos->iniciar(
            $reserva,
            MetodoPago::TarjetaCredito,
            $tarjeta->descripcion,
            "Pago iniciado con tarjeta guardada ({$tarjeta->descripcion}).",
        );
    }

    private static function claveDeReintento(Reserva $reserva, MetodoPagoGuardado $tarjeta): string
    {
        return "pago:reintento:{$reserva->id}:{$tarjeta->id}";
    }

    /**
     * Secreto del PaymentIntent de un pago pendiente de verificación 3DS.
     * Se pide a Stripe en el momento en vez de guardarlo o enviarlo por la sesión.
     *
     * @throws ValidationException
     */
    public function clientSecretDe(Pago $pago): string
    {
        return $this->consultarIntento($pago)->client_secret;
    }

    /**
     * Aplica el resultado que Stripe tiene del pago. Es idempotente frente al
     * webhook: si ya se aplicó, no hace nada.
     *
     * @return string 'succeeded', 'rechazado' o el estado de Stripe que sigue en curso
     *
     * @throws ValidationException
     */
    public function sincronizarPago(Pago $pago): string
    {
        $intento = $this->consultarIntento($pago);

        try {
            if ($intento->status === PaymentIntent::STATUS_SUCCEEDED) {
                $this->pagos->aprobar($pago);

                return 'succeeded';
            }

            if (in_array($intento->status, [PaymentIntent::STATUS_REQUIRES_PAYMENT_METHOD, PaymentIntent::STATUS_CANCELED], true)) {
                $this->pagos->rechazar($pago, null, $intento->last_payment_error->message ?? 'La verificación del pago no se completó.');

                return 'rechazado';
            }
        } catch (ValidationException) {
            // El webhook (o una cancelación) ya resolvió este pago: nada que aplicar
            return $pago->refresh()->estado === EstadoPago::Pagado ? 'succeeded' : 'rechazado';
        }

        return $intento->status;
    }

    /** @throws ValidationException */
    private function consultarIntento(Pago $pago): PaymentIntent
    {
        try {
            return $this->stripe->paymentIntents->retrieve($pago->referencia_pasarela);
        } catch (ApiErrorException $e) {
            report($e);

            throw ValidationException::withMessages(['tarjeta' => 'No se pudo consultar el pago con Stripe. Inténtalo de nuevo en unos minutos.']);
        }
    }

    private function obtenerOcrearCustomer(User $cliente): string
    {
        $existente = $cliente->metodosPago()
            ->where('pasarela', PasarelaPago::Stripe)
            ->whereNotNull('cliente_pasarela_id')
            ->value('cliente_pasarela_id');

        if ($existente !== null) {
            return $existente;
        }

        try {
            $customer = $this->stripe->customers->create([
                'email' => $cliente->email,
                'name' => $cliente->nombre,
                'metadata' => ['cliente_id' => (string) $cliente->id],
            ]);
        } catch (ApiErrorException $e) {
            throw ValidationException::withMessages(['tarjeta' => 'No se pudo crear el perfil de pago en Stripe: '.$e->getMessage()]);
        }

        return $customer->id;
    }
}
