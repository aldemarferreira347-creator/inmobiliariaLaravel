<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Middleware\AgregarCabecerasSeguridad;
use App\Http\Middleware\EjecutarTareasPendientes;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Quien ya inició sesión y vuelve a /login o /registro va a su pantalla inicial
        $middleware->redirectUsersTo(
            fn (Request $peticion) => route($peticion->user()->rol->rutaInicio())
        );

        // Invitado que llega a una ruta protegida: va al login. Los avisos por
        // ruta se declaran en $mensajesPorRuta (patrón de ruta => aviso); si
        // antes hubo una sesión (cookie `sesion_iniciada`), manda «Su sesión ha expirado».
        $middleware->redirectGuestsTo(function (Request $peticion) {
            $mensajesPorRuta = [
                'reservas.store' => 'Debe iniciar sesión para reservar un inmueble',
            ];

            $mensaje = null;

            if ($peticion->cookie(AuthenticatedSessionController::COOKIE_SESION)) {
                $mensaje = 'Su sesión ha expirado';
                Cookie::queue(Cookie::forget(AuthenticatedSessionController::COOKIE_SESION));
            } else {
                foreach ($mensajesPorRuta as $patron => $aviso) {
                    if ($peticion->routeIs($patron)) {
                        $mensaje = $aviso;
                        break;
                    }
                }
            }

            if ($mensaje !== null && $peticion->hasSession()) {
                $peticion->session()->flash('mensaje', $mensaje);
                $peticion->session()->flash('tipo', 'warning');
            }

            return route('login');
        });

        // Stripe llama a esta ruta directamente: se autentica por firma
        // (Stripe-Signature), no por sesión, así que no tiene token CSRF que validar.
        $middleware->validateCsrfTokens(except: ['stripe/webhook']);

        // Corta la sesión de cuentas desactivadas en cualquier ruta (pública o
        // privada), igual que hacía antes Controller::callAction() para todos.
        $middleware->appendToGroup('web', EnsureUserIsActive::class);
        $middleware->appendToGroup('web', AgregarCabecerasSeguridad::class);
        $middleware->appendToGroup('web', EjecutarTareasPendientes::class);

        // ->middleware('rol:administrador') / ->middleware('rol:asesor,administrador')
        $middleware->alias(['rol' => EnsureUserHasRole::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
