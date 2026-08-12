<?php

namespace App\Http\Controllers;

use App\Http\Requests\GuardarTarjetaRequest;
use App\Models\MetodoPagoGuardado;
use App\Servicios\StripeCardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Tarjetas guardadas del cliente autenticado (HU-20).
 *
 * No maneja datos de tarjeta directamente: toda la tokenización y el cobro
 * los resuelve Stripe.js en el navegador y {@see StripeCardService} en el
 * servidor, sobre el modelo {@see \App\Models\MetodoPagoGuardado}.
 */
class MetodoPagoController extends Controller
{
    public function __construct(private readonly StripeCardService $tarjetas) {}

    /** Lista las tarjetas guardadas del cliente autenticado. */
    public function index(Request $request): View
    {
        return view('perfil.tarjetas.index', [
            'usuario' => $request->user(),
            'tarjetas' => $this->tarjetas->listar($request->user()),
            'stripeKey' => config('services.stripe.key'),
        ]);
    }

    /** Crea el SetupIntent que Stripe.js necesita para tokenizar la tarjeta en el navegador. */
    // Abre un SetupIntent para que Stripe.js tokenice la tarjeta en el navegador
    public function setupIntent(Request $request): JsonResponse
    {
        return response()->json($this->tarjetas->crearSetupIntent($request->user()));
    }

    /** Guarda en el sistema la tarjeta ya tokenizada por Stripe.js. */
    public function store(GuardarTarjetaRequest $request): JsonResponse
    {
        $tarjeta = $this->tarjetas->guardarTarjeta(
            $request->user(),
            $request->string('payment_method_id')->toString(),
            $request->string('customer_id')->toString(),
        );

        return response()->json([
            'tarjeta' => [
                'id' => $tarjeta->id,
                'descripcion' => $tarjeta->descripcion,
            ],
        ]);
    }

    /** Elimina una tarjeta guardada del cliente autenticado. */
    public function destroy(Request $request, MetodoPagoGuardado $tarjeta): RedirectResponse
    {
        abort_unless($tarjeta->cliente_id === $request->user()->id, 404);

        $this->tarjetas->eliminar($tarjeta);

        return redirect()->route('perfil.edit')
            ->with(['mensaje' => 'Tarjeta eliminada correctamente.', 'tipo' => 'success', 'reabrirModal' => 'modal-tarjetas']);
    }
}
