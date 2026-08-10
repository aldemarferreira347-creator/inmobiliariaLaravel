<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * El cierre de sesión de cuentas desactivadas vive en el middleware
 * EnsureUserIsActive ('activo', global) y la restricción por rol en
 * EnsureUserHasRole ('rol:administrador', declarada en routes/web.php).
 * Ninguna de las dos vive ya en el controlador — ver bootstrap/app.php.
 */
abstract class Controller
{
    // Habilita $this->authorize() en los controladores que aplican policies
    use AuthorizesRequests;
}
