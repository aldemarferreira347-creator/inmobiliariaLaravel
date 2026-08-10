<?php

use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

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

        // Stripe llama a esta ruta directamente: se autentica por firma
        // (Stripe-Signature), no por sesión, así que no tiene token CSRF que validar.
        $middleware->validateCsrfTokens(except: ['stripe/webhook']);

        // Corta la sesión de cuentas desactivadas en cualquier ruta (pública o
        // privada), igual que hacía antes Controller::callAction() para todos.
        $middleware->appendToGroup('web', EnsureUserIsActive::class);

        // ->middleware('rol:administrador') / ->middleware('rol:asesor,administrador')
        $middleware->alias(['rol' => EnsureUserHasRole::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
