<?php

namespace App\Http\Middleware;

use App\Enumerados\RolUsuario;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe una ruta a los roles indicados: ->middleware('rol:administrador,asesor').
 *
 * Reemplaza el patrón anterior (cada controller de Admin/Asesor sobreescribía
 * callAction() y llamaba requerirRol() a mano). Al vivir en la ruta, la
 * protección es visible en routes/web.php y no depende de que cada controller
 * nuevo recuerde aplicarla.
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $rolesPermitidos = array_map(
            fn (string $rol) => RolUsuario::from($rol),
            $roles
        );

        abort_unless(
            $request->user()?->tieneRol(...$rolesPermitidos),
            403,
            'No tienes permisos para acceder a esta sección.'
        );

        return $next($request);
    }
}
