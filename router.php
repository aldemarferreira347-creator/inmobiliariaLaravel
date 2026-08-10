<?php

// Router para `php -S` (equivalente al server.php que Laravel ya no incluye
// por defecto en versiones nuevas). Sirve archivos estáticos existentes y
// delega todo lo demás al front controller.
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

if ($uri !== '/' && file_exists(__DIR__.'/public'.$uri)) {
    return false;
}

require_once __DIR__.'/public/index.php';
