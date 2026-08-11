<?php

namespace App\Http\Controllers;

use App\Http\Requests\EnviarMensajeRequest;
use App\Models\Conversacion;
use App\Models\Inmueble;
use App\Models\Mensaje;
use App\Servicios\MensajeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Bandeja de mensajes (HU-13).
 *
 * La misma pantalla sirve al cliente, al asesor y al administrador: lo único
 * que cambia son los hilos que cada uno alcanza.
 */
class MensajeController extends Controller
{
    /** Mensajes que se cargan de entrada y en cada «cargar más» */
    private const POR_PAGINA = 20;

    public function __construct(private readonly MensajeService $mensajes) {}

    /** Bandeja de conversaciones del usuario autenticado, sin ningún hilo abierto todavía. */
    public function index(Request $request): View
    {
        return view($this->vistaPara($request), [
            'conversaciones' => $this->conversacionesDe($request),
            'conversacion' => null,
            'mensajes' => collect(),
        ]);
    }

    /** Abre una conversación concreta y marca sus mensajes como leídos por el usuario autenticado. */
    public function show(Request $request, Conversacion $conversacion): View
    {
        $this->authorize('view', $conversacion);

        // El administrador supervisa sin consumir los no leídos del asesor
        if ($conversacion->participa($request->user())) {
            $this->mensajes->marcarLeidos($conversacion, $request->user());
        }

        return view($this->vistaPara($request), [
            'conversaciones' => $this->conversacionesDe($request),
            'conversacion' => $conversacion->load('cliente', 'asesor', 'inmueble'),
            'mensajes' => $this->pagina($conversacion),
        ]);
    }

    /** Envía un mensaje (con adjunto opcional) dentro de una conversación existente. */
    public function store(EnviarMensajeRequest $request, Conversacion $conversacion): RedirectResponse
    {
        $this->mensajes->enviar(
            $conversacion,
            $request->user(),
            $request->input('contenido'),
            $request->file('adjunto'),
        );

        return redirect()->route('mensajes.show', $conversacion);
    }

    /** Abre (o reutiliza) la conversación sobre un inmueble y redirige a ella. */
    // Primer contacto desde la ficha del inmueble
    public function iniciar(Request $request, Inmueble $inmueble): RedirectResponse
    {
        $conversacion = $this->mensajes->abrirConversacion($request->user(), $inmueble);

        return redirect()->route('mensajes.show', $conversacion);
    }

    /** Tramo anterior del hilo, para el botón «cargar más» */
    public function anteriores(Request $request, Conversacion $conversacion): JsonResponse
    {
        $this->authorize('view', $conversacion);

        $mensajes = $this->pagina($conversacion, $request->integer('antes_de'));

        return response()->json([
            'mensajes' => $this->serializar($mensajes, $request),
            'hay_mas' => $mensajes->isNotEmpty()
                && $conversacion->mensajes()->where('id', '<', $mensajes->first()->id)->exists(),
        ]);
    }

    /** Mensajes llegados después del último visto; alimenta el sondeo del chat */
    public function nuevos(Request $request, Conversacion $conversacion): JsonResponse
    {
        $this->authorize('view', $conversacion);

        $mensajes = $conversacion->mensajes()
            ->with('emisor')
            ->where('id', '>', $request->integer('desde'))
            ->oldest('id')
            ->get();

        if ($conversacion->participa($request->user())) {
            $this->mensajes->marcarLeidos($conversacion, $request->user());
        }

        return response()->json(['mensajes' => $this->serializar($mensajes, $request)]);
    }

    /** Contador global para el distintivo de la barra de navegación */
    public function sinLeer(Request $request): JsonResponse
    {
        $usuario = $request->user();

        $total = Mensaje::query()
            ->whereNull('leido_en')
            ->where('emisor_id', '!=', $usuario->id)
            ->whereIn('conversacion_id', $usuario->conversaciones()->select('id'))
            ->count();

        return response()->json([
            'mensajes' => $total,
            'notificaciones' => $usuario->notificacionesSinLeer(),
        ]);
    }

    // ----------------------------------------------------------------
    // Apoyo
    // ----------------------------------------------------------------

    /** Vista de bandeja según el rol: panel para el equipo interno, área propia para el cliente. */
    // El equipo interno ve la bandeja dentro del panel; el cliente, en su área
    private function vistaPara(Request $request): string
    {
        return $request->user()->rol->usaPanel() ? 'mensajes.panel' : 'mensajes.index';
    }

    /** Conversaciones visibles para el usuario: todas si es administrador, solo las propias si no. */
    private function conversacionesDe(Request $request)
    {
        $usuario = $request->user();

        return Conversacion::query()
            ->with('cliente', 'asesor', 'inmueble', 'ultimoMensaje')
            // El administrador ve todos los hilos; el resto solo los suyos
            ->when(! $usuario->esAdministrador(), fn ($q) => $q->de($usuario))
            ->recientes()
            ->get();
    }

    /** @return Collection<int, Mensaje> */
    private function pagina(Conversacion $conversacion, ?int $antesDe = null)
    {
        return $conversacion->mensajes()
            ->with('emisor')
            ->when($antesDe, fn ($q) => $q->where('id', '<', $antesDe))
            ->latest('id')
            ->limit(self::POR_PAGINA)
            ->get()
            ->reverse()
            ->values();
    }

    /** Convierte los mensajes del modelo al arreglo plano que consume la vista y el JSON del sondeo. */
    private function serializar($mensajes, Request $request): array
    {
        return $mensajes->map(fn (Mensaje $mensaje) => [
            'id' => $mensaje->id,
            'contenido' => $mensaje->contenido,
            'adjunto' => $mensaje->adjunto_publico,
            'hora' => $mensaje->creado_en->format('H:i'),
            'fecha' => $mensaje->creado_en->translatedFormat('d \d\e F Y'),
            'propio' => $mensaje->emisor_id === $request->user()->id,
            'emisor' => $mensaje->emisor->nombre,
        ])->all();
    }
}
