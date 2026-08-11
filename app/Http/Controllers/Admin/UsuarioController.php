<?php

namespace App\Http\Controllers\Admin;

use App\Enumerados\EstadoUsuario;
use App\Enumerados\RolUsuario;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUsuarioRequest;
use App\Models\Auditoria;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Administración de usuarios y roles (HU-16 / HU-26).
 *
 * Modelo principal: {@see User}. Los cambios de estado quedan registrados
 * en {@see Auditoria} para conservar el histórico de la cuenta.
 */
class UsuarioController extends Controller
{
    /** Lista todos los usuarios del sistema, con el total por rol. */
    public function index(): View
    {
        return view('admin.usuarios.index', [
            'usuarios' => User::orderByDesc('creado_en')->get(),
            'totalesPorRol' => User::query()
                ->selectRaw('rol, COUNT(*) as total')
                ->groupBy('rol')
                ->pluck('total', 'rol'),
        ]);
    }

    /** Crea un usuario nuevo con el rol indicado. */
    public function store(StoreUsuarioRequest $request): RedirectResponse
    {
        $usuario = User::create($request->validated());

        return $this->volverAlListado(
            "Usuario «{$usuario->nombre}» creado correctamente con rol {$usuario->rol->etiqueta()}."
        );
    }

    /** Reasigna el rol de un usuario existente (HU-26.1). */
    // HU-26.1: reasignación del rol de un usuario existente
    public function cambiarRol(Request $request, User $usuario): RedirectResponse
    {
        $this->authorize('cambiarRol', $usuario);

        $datos = $request->validate(['rol' => ['required', Rule::enum(RolUsuario::class)]]);
        $usuario->update($datos);

        return $this->volverAlListado("Rol de «{$usuario->nombre}» actualizado a {$usuario->rol->etiqueta()}.");
    }

    /**
     * HU-26.3: activa o desactiva la cuenta conservando su histórico.
     * El cambio queda registrado en auditoría.
     */
    public function cambiarEstado(Request $request, User $usuario): RedirectResponse
    {
        $this->authorize('cambiarEstado', $usuario);

        $nuevoEstado = $usuario->estado->opuesto();

        DB::transaction(function () use ($usuario, $nuevoEstado, $request) {
            $usuario->update([
                'estado' => $nuevoEstado,
                'desactivado_en' => $nuevoEstado === EstadoUsuario::Inactivo ? now() : null,
                'desactivado_por' => $nuevoEstado === EstadoUsuario::Inactivo ? $request->user()->id : null,
            ]);

            Auditoria::registrar(
                'usuario',
                $usuario->id,
                'cambiar_estado',
                "La cuenta de {$usuario->email} pasó a estado {$nuevoEstado->etiqueta()}."
            );
        });

        return $this->volverAlListado("Usuario «{$usuario->nombre}» marcado como {$nuevoEstado->etiqueta()}.");
    }

    /** Elimina un usuario; se bloquea si tiene historial (reservas, etc.) asociado. */
    // Un usuario con reservas asociadas no se elimina: se desactiva para no perder el histórico
    public function destroy(User $usuario): RedirectResponse
    {
        $this->authorize('delete', $usuario);

        if ($usuario->tieneHistorial()) {
            return $this->volverAlListado(
                'No se puede eliminar: el usuario tiene reservas asociadas. Desactívalo en su lugar.',
                'error'
            );
        }

        $nombre = $usuario->nombre;
        $usuario->delete();

        return $this->volverAlListado("Usuario «{$nombre}» eliminado correctamente.");
    }

    /** Redirige al listado de usuarios con un mensaje flash de éxito o error. */
    private function volverAlListado(string $mensaje, string $tipo = 'success'): RedirectResponse
    {
        return redirect()
            ->route('admin.usuarios.index')
            ->with(['mensaje' => $mensaje, 'tipo' => $tipo]);
    }
}
