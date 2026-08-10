<?php

namespace App\Http\Controllers;

use App\Http\Requests\GuardarTarjetaRequest;
use App\Models\MetodoPagoGuardado;
use App\Servicios\StripeCardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

// HU-20: tarjetas guardadas del cliente autenticado
class MetodoPagoController extends Controller
{
    public function __construct(private readonly StripeCardService $tarjetas) {}

    public function index(Request $request): View
    {
        return view('perfil.tarjetas.index', [
            'usuario' => $request->user(),
            'tarjetas' => $this->tarjetas->listar($request->user()),
            'stripeKey' => config('services.stripe.key'),
        ]);
    }

    // Abre un SetupIntent para que Stripe.js tokenice la tarjeta en el navegador
    public function setupIntent(Request $request): JsonResponse
    {
        return response()->json($this->tarjetas->crearSetupIntent($request->user()));
    }

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

    public function destroy(Request $request, MetodoPagoGuardado $tarjeta): RedirectResponse
    {
        abort_unless($tarjeta->cliente_id === $request->user()->id, 404);

        $this->tarjetas->eliminar($tarjeta);

        return redirect()->route('perfil.tarjetas.index')->with(['mensaje' => 'Tarjeta eliminada correctamente.', 'tipo' => 'success']);
    }
}
