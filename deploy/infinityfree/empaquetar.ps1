# Empaqueta la aplicación para subirla por FTP a InfinityFree.
# Resultado: build-infinityfree/htdocs/ y build-infinityfree/htdocs.zip
$ErrorActionPreference = 'Stop'

$raiz = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$build = Join-Path $raiz 'build-infinityfree'
$htdocs = Join-Path $build 'htdocs'

function Verificar-Salida([string]$paso) {
    if ($LASTEXITCODE -ne 0) { throw "Falló: $paso (código $LASTEXITCODE)" }
}

Write-Host "PHP en uso:"; php -v | Select-Object -First 1

# 1. Carpeta limpia (se conservan otros archivos del build, p. ej. bd.sql)
if (Test-Path $htdocs) { Remove-Item $htdocs -Recurse -Force }
$zip = Join-Path $build 'htdocs.zip'
if (Test-Path $zip) { Remove-Item $zip -Force }
New-Item -ItemType Directory -Path $htdocs -Force | Out-Null

# 2. Recursos de Vite
Push-Location $raiz
try {
    npm ci; Verificar-Salida 'npm ci'
    npm run build; Verificar-Salida 'npm run build'
} finally { Pop-Location }

# 3. Copia del proyecto (vendor se instala limpio más abajo)
$dirsExcluidos = @(
    '.git', '.claude', '.superpowers', 'node_modules', 'tests', 'Documentacion-inmobiliaria',
    'Documentación', 'docs', 'deploy', 'build-infinityfree', 'vendor', 'graphify-out'
) | ForEach-Object { Join-Path $raiz $_ }
$dirsExcluidos += @(
    (Join-Path $raiz 'storage\logs'),
    (Join-Path $raiz 'storage\framework'),
    (Join-Path $raiz 'public\storage')
)
$archivosExcluidos = @(
    '.env', '.phpunit.result.cache', '*.md', 'phpunit.xml', 'vite.config.js',
    'package.json', 'package-lock.json', 'router.php', 'hot'
)
robocopy $raiz $htdocs /E /XD @dirsExcluidos /XF @archivosExcluidos /NFL /NDL /NJH /NJS /NP | Out-Null
if ($LASTEXITCODE -ge 8) { throw "robocopy falló (código $LASTEXITCODE)" }
$global:LASTEXITCODE = 0
Get-ChildItem (Join-Path $htdocs 'bootstrap\cache') -Filter '*.php' -ErrorAction SilentlyContinue | Remove-Item -Force

# 4. Dependencias de producción en la copia (no toca el vendor del repositorio)
Push-Location $htdocs
try {
    composer install --no-dev --no-interaction --prefer-dist --no-ansi --no-progress; Verificar-Salida 'composer install'
    # Sin --optimize explícito (composer.json ya activa optimize-autoloader); la verificación 7 aborta si el classmap supera 1 MB
    composer dump-autoload --no-dev --no-ansi; Verificar-Salida 'composer dump-autoload'
} finally { Pop-Location }

# 5. Reglas Apache de la raíz
Copy-Item (Join-Path $PSScriptRoot 'htdocs.htaccess') (Join-Path $htdocs '.htaccess') -Force

# 6. Carpetas de escritura vacías (con .gitignore para que existan al subir)
$vacias = @(
    'storage\framework\cache\data', 'storage\framework\sessions', 'storage\framework\views',
    'storage\logs', 'storage\app\public', 'storage\app\private', 'bootstrap\cache'
)
foreach ($d in $vacias) {
    $ruta = Join-Path $htdocs $d
    New-Item -ItemType Directory -Path $ruta -Force | Out-Null
    Get-ChildItem $ruta -Filter '*.php' -ErrorAction SilentlyContinue | Remove-Item -Force
    if (-not (Test-Path (Join-Path $ruta '.gitignore'))) {
        Set-Content -Path (Join-Path $ruta '.gitignore') -Value "*`n!.gitignore" -Encoding ascii
    }
}
Get-ChildItem (Join-Path $htdocs 'bootstrap\cache') -Filter '*.php' | Remove-Item -Force -ErrorAction SilentlyContinue

# 7. Verificaciones que abortan
$errores = @()
$grandes = Get-ChildItem $htdocs -Recurse -File -Filter '*.php' -Force | Where-Object { $_.Length -gt 1000000 }
if ($grandes) {
    $errores += "PHP mayores de 1 000 000 bytes:`n" + (($grandes | ForEach-Object { "  $($_.FullName) ($($_.Length))" }) -join "`n")
}
$entradas = @(Get-ChildItem $htdocs -Recurse -Force).Count
if ($entradas -gt 25000) { $errores += "Demasiadas entradas: $entradas (máximo 25000)" }
if (-not (Test-Path (Join-Path $htdocs 'public\build\manifest.json'))) { $errores += 'Falta public/build/manifest.json' }
if (Test-Path (Join-Path $htdocs '.env')) { $errores += 'El paquete contiene un .env' }
if ($errores) { throw ("Verificación fallida:`n" + ($errores -join "`n")) }

# 8. Resumen y zip
$archivos = @(Get-ChildItem $htdocs -Recurse -File -Force).Count
Write-Host "Archivos: $archivos | Entradas (archivos + carpetas): $entradas | PHP > 1 MB: 0"
Compress-Archive -Path (Join-Path $htdocs '*') -DestinationPath $zip -Force
Write-Host "Paquete listo: $htdocs"
Write-Host "Zip: $zip ($([math]::Round((Get-Item $zip).Length / 1MB, 1)) MB)"
