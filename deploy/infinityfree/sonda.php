<?php
/**
 * Sonda del servidor (InfinityFree). PHP plano: sin Laravel ni Composer.
 *
 * Uso: editar las constantes, subir a htdocs/sonda.php, abrir
 * https://<dominio>/sonda.php?clave=<CLAVE>, copiar el resultado y BORRAR el archivo.
 * Las credenciales de MySQL son las del panel (no se lee ningun .env).
 */

const CLAVE = 'CAMBIAR_CLAVE';
const DB_HOST = 'sqlXXX.infinityfree.com';
const DB_NAME = 'if0_XXXXXXXX_nombre';
const DB_USER = 'if0_XXXXXXXX';
const DB_PASS = 'CAMBIAR_PASSWORD';

header('Content-Type: text/plain; charset=utf-8');

$clave = $_GET['clave'] ?? '';
if (CLAVE === 'CAMBIAR_CLAVE' || ! is_string($clave) || ! hash_equals(CLAVE, $clave)) {
    http_response_code(403);
    echo "Acceso denegado.\n";
    exit;
}

function linea(string $clave, mixed $valor): void
{
    if (is_bool($valor)) {
        $valor = $valor ? 'si' : 'no';
    }
    echo str_pad($clave, 26).' = '.$valor."\n";
}

function seccion(string $titulo): void
{
    echo "\n== {$titulo} ==\n";
}

function comprobar(string $nombre, callable $fn): void
{
    try {
        $fn();
    } catch (Throwable $e) {
        linea($nombre, 'ERROR: '.$e->getMessage());
    }
}

seccion('PHP');
comprobar('php', function () {
    linea('php_version', PHP_VERSION);
    linea('php_int_size', PHP_INT_SIZE);
    linea('php_sapi', php_sapi_name());
});

seccion('Extensiones');
$faltantes = [];
comprobar('extensiones', function () use (&$faltantes) {
    foreach (['pdo_mysql', 'mbstring', 'intl', 'gd', 'zip', 'bcmath', 'fileinfo', 'openssl', 'curl', 'dom', 'xml', 'xmlwriter', 'iconv', 'ctype', 'tokenizer', 'sodium'] as $ext) {
        $ok = extension_loaded($ext);
        linea('ext_'.$ext, $ok);
        if (! $ok) {
            $faltantes[] = $ext;
        }
    }
});
linea('extensiones_faltantes', $faltantes === [] ? '(ninguna)' : implode(', ', $faltantes));

seccion('ini');
comprobar('ini', function () {
    foreach (['memory_limit', 'max_execution_time', 'upload_max_filesize', 'post_max_size', 'max_file_uploads', 'disable_functions', 'open_basedir'] as $k) {
        $v = ini_get($k);
        linea($k, $v === false ? '(no disponible)' : ($v === '' ? '(vacio)' : $v));
    }
});

seccion('Funciones');
comprobar('funciones', function () {
    foreach (['symlink', 'putenv', 'proc_open', 'exec', 'fastcgi_finish_request', 'password_hash'] as $f) {
        linea($f, function_exists($f));
    }
    linea('argon2id', defined('PASSWORD_ARGON2ID'));
});

seccion('MySQL');
comprobar('mysql', function () {
    $pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4', DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    comprobar('mysql_version', fn () => linea('mysql_version', $pdo->query('SELECT VERSION()')->fetchColumn()));
    comprobar('motor_por_defecto', function () use ($pdo) {
        $f = $pdo->query("SHOW VARIABLES LIKE 'default_storage_engine'")->fetch(PDO::FETCH_NUM);
        linea('motor_por_defecto', $f[1] ?? '(desconocido)');
    });
    comprobar('engines', function () use ($pdo) {
        foreach ($pdo->query('SHOW ENGINES')->fetchAll(PDO::FETCH_NUM) as $f) {
            linea('engine_'.$f[0], $f[1].' (transacciones: '.$f[3].', fk: '.$f[4].')');
        }
    });
    comprobar('grants', function () use ($pdo) {
        foreach ($pdo->query('SHOW GRANTS')->fetchAll(PDO::FETCH_NUM) as $f) {
            linea('grant', $f[0]);
        }
    });

    $limpiar = function () use ($pdo) {
        foreach (['DROP VIEW IF EXISTS sonda_v', 'DROP TABLE IF EXISTS sonda_fk_hijo', 'DROP TABLE IF EXISTS sonda_fk_padre'] as $sql) {
            try {
                $pdo->exec($sql);
            } catch (Throwable $e) {
                linea('limpieza', 'ERROR en "'.$sql.'": '.$e->getMessage());
            }
        }
    };

    comprobar('fk_soportadas', function () use ($pdo, $limpiar) {
        $limpiar();
        try {
            $pdo->exec('CREATE TABLE sonda_fk_padre (id INT PRIMARY KEY) ENGINE=InnoDB');
            $pdo->exec('CREATE TABLE sonda_fk_hijo (id INT PRIMARY KEY, padre_id INT NOT NULL, FOREIGN KEY (padre_id) REFERENCES sonda_fk_padre(id) ON DELETE CASCADE) ENGINE=InnoDB');
            $pdo->exec('INSERT INTO sonda_fk_padre (id) VALUES (1)');
            $pdo->exec('INSERT INTO sonda_fk_hijo (id, padre_id) VALUES (1, 1)');
            $pdo->exec('DELETE FROM sonda_fk_padre WHERE id = 1');
            $quedan = (int) $pdo->query('SELECT COUNT(*) FROM sonda_fk_hijo')->fetchColumn();
            $st = $pdo->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?');
            $st->execute([DB_NAME, 'sonda_fk_hijo']);
            linea('motor_real_tabla_hijo', $st->fetchColumn() ?: '(desconocido)');
            linea('fk_soportadas', $quedan === 0);
            linea('hijo_tras_borrar_padre', $quedan.' fila(s)');
        } finally {
            $limpiar();
        }
    });

    comprobar('create_view', function () use ($pdo, $limpiar) {
        try {
            $pdo->exec('CREATE VIEW sonda_v AS SELECT 1 AS uno');
            linea('create_view', true);
        } catch (Throwable $e) {
            linea('create_view', 'no: '.$e->getMessage());
        } finally {
            $limpiar();
        }
    });
});

seccion('SMTP (salida 587)');
foreach (['brevo' => 'smtp-relay.brevo.com', 'gmail' => 'smtp.gmail.com'] as $nombre => $host) {
    comprobar('smtp_587_'.$nombre, function () use ($nombre, $host) {
        $errno = 0;
        $errstr = '';
        $s = @fsockopen($host, 587, $errno, $errstr, 5);
        if ($s) {
            fclose($s);
            linea('smtp_587_'.$nombre, 'abierto');
        } else {
            linea('smtp_587_'.$nombre, "cerrado ({$errno}: {$errstr})");
        }
    });
}

seccion('HTTPS saliente (Stripe)');
comprobar('stripe_https', function () {
    if (! function_exists('curl_init')) {
        linea('stripe_https', 'sin curl');

        return;
    }
    $ch = curl_init('https://api.stripe.com/v1');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_CONNECTTIMEOUT => 10]);
    curl_exec($ch);
    $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    linea('stripe_https', $codigo.($error !== '' ? " (error: {$error})" : '').($codigo > 0 ? ' (hay conectividad HTTPS; 0 = sin salida)' : ' (SIN conectividad)'));
});

echo "\nFin. BORRA este archivo del servidor.\n";
