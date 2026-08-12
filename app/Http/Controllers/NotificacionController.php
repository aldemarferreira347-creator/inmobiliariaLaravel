<?php

namespace App\Http\Controllers;

use App\Models\Notificacion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Centro de notificaciones del usuario autenticado (HU-15).
 *
 * Modelo principal: {@see Notificacion}, siempre acotado al usuario que
 * consulta o marca como leídas sus propias notificaciones.
 */
class NotificacionController extends Controller
{
    /** Lista las últimas 100 notificaciones del usuario autenticado, con su conteo de no leídas. */
    public function index(Request $request): View
    {
        $notificaciones = $request->user()->notificaciones()->recientes()->limit(100)->get();

        return view('notificaciones.index', [
            'notificaciones' => $notificaciones,
            'sinLeer' => $notificaciones->whereNull('leida_en')->count(),
        ]);
    }

    /** Marca una notificación propia como leída y la retira de la lista. */
    // Filtrar por usuario evita que nadie marque notificaciones ajenas
    public function marcarLeida(Request $request, Notificacion $notificacion): RedirectResponse
    {
        abort_unless($notificacion->usuario_id === $request->user()->id, 403);

        $notificacion->delete();

        return back();
    }

    /** Marca todas las notificaciones pendientes del usuario autenticado como leídas y las retira de la lista. */
    public function marcarTodas(Request $request): RedirectResponse
    {
        $request->user()->notificaciones()->sinLeer()->delete();

        return back()->with(['mensaje' => 'Todas tus notificaciones quedaron marcadas como leídas.', 'tipo' => 'success']);
    }
}
