# Genera build-infinityfree/bd.sql, importable por phpMyAdmin de InfinityFree.
# Crea una BD local aparte (inmobiliaria_export); no toca la BD de desarrollo.
param(
    [string]$MysqlBin = 'C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin',
    [string]$Servidor = '127.0.0.1',
    [int]$Puerto = 3306,
    [string]$Usuario = 'root',
    [string]$ClaveBd = '',
    [string]$BaseExport = 'inmobiliaria_export'
)
$ErrorActionPreference = 'Stop'

$raiz = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$build = Join-Path $raiz 'build-infinityfree'
$salida = Join-Path $build 'bd.sql'
$credenciales = Join-Path $build 'CREDENCIALES.txt'
$mysql = Join-Path $MysqlBin 'mysql.exe'
$mysqldump = Join-Path $MysqlBin 'mysqldump.exe'
foreach ($exe in @($mysql, $mysqldump)) {
    if (-not (Test-Path $exe)) { throw "No se encontró $exe (use -MysqlBin)" }
}
if ($BaseExport -ne 'inmobiliaria_export') { throw 'Por seguridad solo se admite la base inmobiliaria_export' }

function Verificar-Salida([string]$paso) {
    if ($LASTEXITCODE -ne 0) { throw "Falló: $paso (código $LASTEXITCODE)" }
}

Write-Host 'PHP en uso:'; php -v | Select-Object -First 1
New-Item -ItemType Directory -Path $build -Force | Out-Null

# Contraseñas iniciales aleatorias (solo se escriben en CREDENCIALES.txt)
$mayus = 'ABCDEFGHJKLMNPQRSTUVWXYZ'; $minus = 'abcdefghijkmnopqrstuvwxyz'; $digitos = '23456789'
$todo = $mayus + $minus + $digitos
$rng = [System.Security.Cryptography.RandomNumberGenerator]
$elegir = { param($c) $c[$rng::GetInt32($c.Length)] }
function Nueva-Contrasena {
    $chars = @((& $elegir $mayus), (& $elegir $minus), (& $elegir $digitos)) + (1..13 | ForEach-Object { & $elegir $todo })
    -join ($chars | Sort-Object { $rng::GetInt32(1000000) })
}
$contrasenaAdmin = Nueva-Contrasena
$contrasenaAsesor = Nueva-Contrasena
$contrasenaCliente = Nueva-Contrasena

# 1. BD vacía con utf8mb4_unicode_ci (solo la de exportación)
$argsConexion = @("-h$Servidor", "-P$Puerto", "-u$Usuario")
if ($ClaveBd) { $argsConexion += "-p$ClaveBd" }
& $mysql @argsConexion -e "DROP DATABASE IF EXISTS ``$BaseExport``; CREATE DATABASE ``$BaseExport`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
Verificar-Salida 'crear la BD de exportación'

# 2. Migrar y sembrar en modo producción, con las variables solo en el proceso
$variables = @{
    APP_ENV = 'production'; APP_DEBUG = 'false'
    DB_CONNECTION = 'mysql'; DB_HOST = $Servidor; DB_PORT = "$Puerto"
    DB_DATABASE = $BaseExport; DB_USERNAME = $Usuario; DB_PASSWORD = $ClaveBd
    HASH_DRIVER = 'bcrypt'; BCRYPT_ROUNDS = '10'
    SESSION_DRIVER = 'array'; CACHE_STORE = 'array'; QUEUE_CONNECTION = 'sync'
    MAIL_MAILER = 'log'; TAREAS_POR_PETICION = 'false'
    ADMIN_PASSWORD_INICIAL = $contrasenaAdmin
    ASESOR_PASSWORD_INICIAL = $contrasenaAsesor
    CLIENTE_PASSWORD_INICIAL = $contrasenaCliente
}
$anteriores = @{}
foreach ($k in $variables.Keys) {
    $anteriores[$k] = [Environment]::GetEnvironmentVariable($k)
    [Environment]::SetEnvironmentVariable($k, $variables[$k])
}
try {
    Push-Location $raiz
    php artisan migrate:fresh --seed --force --database=mysql --no-ansi --quiet
    Verificar-Salida 'migrate:fresh --seed'
} finally {
    Pop-Location
    foreach ($k in $variables.Keys) { [Environment]::SetEnvironmentVariable($k, $anteriores[$k]) }
}

# 3. Volcado: estructura de todo + datos salvo tablas transitorias
$transitorias = @('sessions', 'cache', 'cache_locks', 'jobs', 'failed_jobs')
$comunes = @(
    '--no-tablespaces', '--skip-triggers', '--skip-routines', '--skip-events',
    '--single-transaction', '--default-character-set=utf8mb4', '--set-gtid-purged=OFF'
)
$parteDatos = Join-Path $build 'bd.datos.tmp'
$parteVacias = Join-Path $build 'bd.vacias.tmp'
$ignorar = $transitorias | ForEach-Object { "--ignore-table=$BaseExport.$_" }
& $mysqldump @argsConexion @comunes @ignorar "--result-file=$parteDatos" $BaseExport
Verificar-Salida 'mysqldump (datos)'
& $mysqldump @argsConexion @comunes --no-data "--result-file=$parteVacias" $BaseExport @transitorias
Verificar-Salida 'mysqldump (tablas transitorias)'

# 4. Compatibilidad con MariaDB / MySQL de InfinityFree
$utf8 = New-Object System.Text.UTF8Encoding($false)
$sql = [IO.File]::ReadAllText($parteDatos, $utf8) + "`n" + [IO.File]::ReadAllText($parteVacias, $utf8)
Remove-Item $parteDatos, $parteVacias -Force
$sql = $sql -replace 'utf8mb4_0900_ai_ci', 'utf8mb4_unicode_ci'
$sql = $sql -replace '/\*!\d+\s+DEFINER=[^*]*\*/', ''
# Sin ENGINE: se aplica el motor por defecto del servidor (InnoDB o MyISAM)
$sql = $sql -replace ' ENGINE=\w+', ''
$sql = $sql -replace 'DEFINER=`[^`]*`@`[^`]*`\s*', ''
$sql = $sql -replace '(?m)^(CREATE DATABASE|USE )[^\r\n]*\r?\n', ''
[IO.File]::WriteAllText($salida, $sql, $utf8)

# 5. Verificaciones que abortan
$tam = (Get-Item $salida).Length
if ($tam -gt 8MB) { throw "bd.sql pesa $([math]::Round($tam / 1MB, 1)) MB (máximo 8 MB)" }
$prohibidos = '0900_ai_ci', 'DEFINER', 'TRIGGER', 'CREATE VIEW', 'PROCEDURE', 'ENGINE='
foreach ($p in $prohibidos) {
    if (Select-String -Path $salida -Pattern $p -SimpleMatch -Quiet) { throw "bd.sql contiene '$p'" }
}
if (-not (Select-String -Path $salida -Pattern 'CREATE TABLE `usuario`' -SimpleMatch -Quiet)) { throw 'bd.sql no contiene la tabla usuario' }

# 6. Credenciales iniciales (archivo ignorado por git; no se imprimen las contraseñas)
Set-Content -Path $credenciales -Encoding utf8 -Value @(
    'Credenciales iniciales (no versionar, borrar tras el primer ingreso)',
    'Administrador: admin@inmobiliaria.test',
    "Contraseña: $contrasenaAdmin",
    'Asesor demo: asesor@inmobiliaria.test',
    "Contraseña: $contrasenaAsesor",
    'Cliente demo: cliente@inmobiliaria.test',
    "Contraseña: $contrasenaCliente"
)

Write-Host "bd.sql generado: $salida ($([math]::Round($tam / 1KB)) KB)"
Write-Host "Credenciales del administrador en: $credenciales"
