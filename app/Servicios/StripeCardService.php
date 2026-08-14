<?php

namespace App\Servicios;

use App\Enumerados\EstadoPago;
use App\Enumerados\EstadoReserva;
use App\Enumerados\MetodoPago;
use App\Enumerados\PasarelaPago;
use App\Models\HistorialReserva;
use App\Models\MetodoPagoGuardado;
use App\Models\Pago;
use App\Models\Reserva;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\CardException;
use Stripe\PaymentIntent;
use Stripe\StripeClient;

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
    private readonly StripeClient $stripe;

    public function __construct(private readonly PagoService $pagos)
    {
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
     * @return array{estado: string, client_secret?: string}
     *
     * @throws ValidationException
     */
    public function pagarConTarjeta(Reserva $reserva, MetodoPagoGuardado $tarjeta): array
    {
        if (! $reserva->admiteNuevoPago()) {
            throw ValidationException::withMessages(['reserva' => 'Esta reserva no admite registrar un pago en este momento.']);
        }

        $pago = DB::transaction(function () use ($reserva, $tarjeta) {
            $pago = Pago::create([
                'reserva_id' => $reserva->id,
                'metodo_pago' => MetodoPago::TarjetaCredito,
                'monto' => $reserva->monto_reserva,
                'referencia' => $tarjeta->descripcion,
                'estado' => EstadoPago::Procesando,
            ]);

            $anterior = $reserva->estado;
            $reserva->update(['estado' => EstadoReserva::ProcesandoPago]);

            HistorialReserva::registrar(
                $reserva,
                $anterior->value,
                EstadoReserva::ProcesandoPago->value,
                "Pago iniciado con tarjeta guardada ({$tarjeta->descripcion}).",
                $reserva->usuario_id,
                request()?->ip(),
            );

            return $pago;
        });

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
            ]);
        } catch (CardException $e) {
            $this->pagos->rechazar($pago, null, $e->getError()->message ?? 'Tarjeta rechazada.');
            throw ValidationException::withMessages(['tarjeta' => 'Pago rechazado. Intenta con otro método de pago.']);
        } catch (ApiErrorException $e) {
            $this->pagos->rechazar($pago, null, $e->getMessage());
            throw ValidationException::withMessages(['tarjeta' => 'No se pudo procesar el pago con Stripe: '.$e->getMessage()]);
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
        throw ValidationException::withMessages(['tarjeta' => 'El pago no pudo completarse. Intenta nuevamente.']);
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
