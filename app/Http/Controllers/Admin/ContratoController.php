<?php

namespace App\Http\Controllers\Admin;

use App\Enumerados\EstadoReserva;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DocumentoContratoRequest;
use App\Http\Requests\Admin\StoreContratoRequest;
use App\Models\Contrato;
use App\Models\Reserva;
use App\Servicios\ContratoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Gestión de contratos de arriendo (HU-17).
 *
 * Modelo principal: {@see Contrato}, emitido a partir de una {@see Reserva}
 * confirmada. La creación, el adjunto del PDF firmado y la rescisión se
 * delegan en {@see ContratoService}.
 */
class ContratoController extends Controller
{
    public function __construct(private readonly ContratoService $contratos) {}

    /** Lista todos los contratos emitidos, con su reserva e inmueble. */
    public function index(): View
    {
        $this->authorize('viewAny', Contrato::class);

        return view('admin.contratos.index', [
            'contratos' => Contrato::with('reserva.inmueble', 'reserva.cliente')->latest('id')->get(),
        ]);
    }

    /** Formulario para emitir un contrato nuevo, con las reservas elegibles. */
    public function create(): View
    {
        $this->authorize('create', Contrato::class);

        return view('admin.contratos.create', [
            'reservas' => $this->reservasElegibles(),
        ]);
    }

    /** Emite un contrato nuevo a partir de una reserva confirmada. */
    public function store(StoreContratoRequest $request): RedirectResponse
    {
        $reserva = Reserva::findOrFail($request->integer('reserva_id'));

        $contrato = $this->contratos->crearDesdeReserva($reserva, $request->validated());

        return redirect()
            ->route('admin.contratos.show', $contrato)
            ->with(['mensaje' => "Contrato {$contrato->numero_contrato} emitido correctamente.", 'tipo' => 'success']);
    }

    /** Detalle de un contrato con su reserva e inmueble asociados. */
    public function show(Contrato $contrato): View
    {
        $this->authorize('view', $contrato);

        return view('admin.contratos.show', [
            'contrato' => $contrato->load('reserva.inmueble', 'reserva.cliente'),
        ]);
    }

    /** Adjunta el PDF del contrato ya firmado por el cliente. */
    public function subirDocumento(DocumentoContratoRequest $request, Contrato $contrato): RedirectResponse
    {
        $this->contratos->adjuntarDocumento($contrato, $request->file('documento'));

        return back()->with(['mensaje' => 'Contrato firmado adjuntado correctamente.', 'tipo' => 'success']);
    }

    /** Rescinde (da de baja) un contrato vigente, dejando constancia del motivo. */
    public function rescindir(Request $request, Contrato $contrato): RedirectResponse
    {
        $this->authorize('rescindir', $contrato);

        $datos = $request->validate(['motivo' => ['required', 'string', 'max:255']]);

        $this->contratos->rescindir($contrato, $datos['motivo']);

        return redirect()
            ->route('admin.contratos.index')
            ->with(['mensaje' => "Contrato {$contrato->numero_contrato} rescindido.", 'tipo' => 'success']);
    }

    /**
     * Reservas confirmadas, sin contrato y dentro del plazo de RN-18.
     * Presentar solo estas evita que el formulario ofrezca opciones que el
     * servicio va a rechazar después.
     */
    private function reservasElegibles()
    {
        return Reserva::query()
            ->with('inmueble', 'cliente')
            ->where('estado', EstadoReserva::Confirmada)
            ->whereDoesntHave('contrato')
            ->recientes()
            ->get()
            ->filter(function (Reserva $reserva) {
                $confirmada = $reserva->confirmadaEn();

                return $confirmada === null
                    || now()->lessThanOrEqualTo($confirmada->copy()->addDays(Contrato::DIAS_PARA_EMITIR));
            });
    }
}
