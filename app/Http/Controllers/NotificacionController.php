<?php

namespace App\Http\Controllers;

use App\Models\Notificacion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

// HU-15: centro de notificaciones del usuario
class NotificacionController extends Controller
{
    public function index(Request $request): View
    {
        $notificaciones = $request->user()->notificaciones()->recientes()->limit(100)->get();

        return view('notificaciones.index', [
            'notificaciones' => $notificaciones,
            'sinLeer' => $notificaciones->whereNull('leida_en')->count(),
        ]);
    }

    // Filtrar por usuario evita que nadie marque notificaciones ajenas
    public function marcarLeida(Request $request, Notificacion $notificacion): RedirectResponse
    {
        abort_unless($notificacion->usuario_id === $request->user()->id, 403);

        $notificacion->update(['leida_en' => now()]);

        return back();
    }

    public function marcarTodas(Request $request): RedirectResponse
    {
        $request->user()->notificaciones()->sinLeer()->update(['leida_en' => now()]);

        return back()->with(['mensaje' => 'Todas tus notificaciones quedaron marcadas como leídas.', 'tipo' => 'success']);
    }
}
