<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeceras de seguridad básicas en todas las respuestas web.
 *
 * Evita que el sitio se incruste en un iframe ajeno (clickjacking), que el
 * navegador reinterprete el tipo de los archivos subidos (sniffing) y que se
 * filtre la URL completa como referer. No se fija una Content-Security-Policy
 * porque el sitio carga Stripe.js y las fuentes de Google desde terceros.
 */
class AgregarCabecerasSeguridad
{
    public function handle(Request $request, Closure $next): Response
    {
        $respuesta = $next($request);

        $respuesta->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $respuesta->headers->set('X-Content-Type-Options', 'nosniff');
        $respuesta->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $respuesta->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        if ($request->isSecure()) {
            $respuesta->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        return $respuesta;
    }
}
