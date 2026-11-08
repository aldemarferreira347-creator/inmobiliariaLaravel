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
    /** Notificaciones por página del centro */
    private const POR_PAGINA = 30;

    /** Lista las notificaciones del usuario (leídas y no leídas), con el total de no leídas. */
    public function index(Request $request): View
    {
        $notificaciones = $request->user()->notificaciones()->recientes()->paginate(self::POR_PAGINA);

        return view('notificaciones.index', [
            'notificaciones' => $notificaciones,
            'sinLeer' => $request->user()->notificacionesSinLeer(),
        ]);
    }

    /** Marca una notificación propia como leída; se conserva en el centro (HU-15.4). */
    // Filtrar por usuario evita que nadie marque notificaciones ajenas
    public function marcarLeida(Request $request, Notificacion $notificacion): RedirectResponse
    {
        abort_unless($notificacion->usuario_id === $request->user()->id, 403);

        if (! $notificacion->estaLeida()) {
            $notificacion->update(['leida_en' => now()]);
            $request->user()->olvidarConteoNotificaciones();
        }

        return back();
    }

    /** Marca todas las notificaciones pendientes del usuario autenticado como leídas, sin borrarlas. */
    public function marcarTodas(Request $request): RedirectResponse
    {
        $request->user()->notificaciones()->sinLeer()->update(['leida_en' => now()]);
        $request->user()->olvidarConteoNotificaciones();

        return back()->with(['mensaje' => 'Todas tus notificaciones quedaron marcadas como leídas.', 'tipo' => 'success']);
    }
}
