<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Red de seguridad para el hallazgo "autorización por rol no estandar" del
 * QA: toda ruta bajo /admin o /asesor debe declarar el middleware 'rol', así
 * una ruta nueva registrada ahí sin ese middleware falla el test en vez de
 * quedar accesible para cualquier usuario autenticado.
 */
class RutasProtegidasTest extends TestCase
{
    public function test_toda_ruta_admin_o_asesor_declara_middleware_de_rol(): void
    {
        $sinProteger = [];

        foreach (Route::getRoutes() as $ruta) {
            $uri = $ruta->uri();

            if (! str_starts_with($uri, 'admin/') && ! str_starts_with($uri, 'asesor/')) {
                continue;
            }

            $tieneRol = collect($ruta->gatherMiddleware())
                ->contains(fn (string $middleware) => str_starts_with($middleware, 'rol:'));

            if (! $tieneRol) {
                $sinProteger[] = $ruta->getName() ?? $uri;
            }
        }

        $this->assertEmpty(
            $sinProteger,
            'Rutas sin middleware de rol: '.implode(', ', $sinProteger)
        );
    }
}
