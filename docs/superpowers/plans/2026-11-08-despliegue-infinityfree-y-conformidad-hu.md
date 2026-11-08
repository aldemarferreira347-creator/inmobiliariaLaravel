# Despliegue en InfinityFree (plan gratuito) y conformidad con las HU — Plan de implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Dejar el sistema publicado en InfinityFree gratuito sin errores y con las 27 historias de usuario (≈110 escenarios) funcionando y mostrando exactamente lo que dice la documentación.

**Architecture:** Dos frentes sobre la arquitectura actual (Middleware → Form Request → Controller → `app/Servicios` → Model, Policies en `app/Politicas`). (A) Compatibilidad: todo lo que InfinityFree gratuito no ofrece (cron, colas, symlinks, webhooks entrantes, InnoDB/FK, PHP > 1 MB, artisan en servidor) se sustituye por mecanismos que corren dentro de una petición web normal. (B) Conformidad: una matriz HU → escenario → código → test, y por cada escenario un test de feature que fija el texto exacto de la documentación.

**Tech Stack:** Laravel 13, PHP 8.3 (servidor: 8.3 o 8.4), PHPUnit 12, SQLite en tests / MySQL-MariaDB en producción, Tailwind 4 + Vite, Stripe PHP 17 + Stripe.js, DomPDF 3, PhpSpreadsheet 5.

**Spec:**
- `Documentacion-inmobiliaria/Carlo Aldemar García Ferreira/Analisis/Historias_de_usuario.xlsx` (hoja «Historias de Usuario», HU-01…HU-27) — **fuente de verdad de comportamiento y textos**.
- Mismo directorio: `Documento de requerimientos de software.pdf`, `Especificaciones de Casos de Uso.pdf`, `Diseño/SDD.pdf` — consulta para reglas de negocio (RN-xx) citadas en las HU.
- Petición del usuario en el chat (8-nov-2026): revisar todo el sistema contra la documentación y garantizar despliegue sin errores en InfinityFree gratuito.
- Guía oficial del foro de InfinityFree «How to install a Laravel site on InfinityFree» (mayo-junio 2026) y respuestas de administradores sobre MySQL (2019).

## Global Constraints

- Código, comentarios, mensajes y nombres en español, con el estilo existente (enums en `app/Enumerados`, servicios en `app/Servicios`, policies en `app/Politicas`).
- Los textos visibles al usuario que la HU cita entre comillas «…» se copian **literalmente** (tildes, mayúsculas y puntuación incluidas).
- Sin dependencias nuevas de Composer ni npm.
- Toda corrección de lógica lleva un test en `tests/Feature` que falla antes y pasa después. Nombre: `test_hu_NN_E_descripcion` (p. ej. `test_hu_07_4_reserva_duplicada`).
- `vendor/bin/pint --dirty` limpio y `php artisan test --compact` en verde (hoy: 157 tests) al cerrar cada tarea.
- No se modifica `.env` (credenciales reales). Los valores de producción van a `deploy/infinityfree/env.produccion.example`.
- Migraciones nuevas, nunca editar las existentes.
- Nada puede depender de: cron, `queue:work`, `storage:link`/symlinks, webhooks entrantes, claves foráneas o transacciones de BD, triggers, vistas SQL, tablas temporales, artisan en el servidor, archivos PHP de más de 1 000 000 bytes.
- Hash de contraseñas: **bcrypt** (HU-03.4, HU-24.4, HU-25.2 lo exigen).
- Tiempo de inactividad de sesión: 15 minutos (HU-05.4).
- Límites de archivo de la documentación: imágenes de inmueble JPG/PNG ≤ 5 MB (HU-08.2/8.3), foto de perfil JPG/PNG ≤ 2 MB (HU-25.4), PDFs ≤ 5 MB.

## Equivalencias aceptadas (no se cambian)

La documentación nombra tecnología que el sistema implementa de otra forma; el comportamiento observable es el mismo y no requiere trabajo:

| Documentación | Implementación | Motivo |
|---|---|---|
| «sesiones PHP nativas», `session_destroy` | Sesión de Laravel (driver `file`) con vida de 15 min | Mismo efecto observable |
| Vistas `v_historial_ventas`, `v_historial_arriendos`, `v_notif_no_leidas` | Consultas Eloquent equivalentes | InfinityFree no concede `CREATE VIEW` de forma fiable |
| «almacenamiento de objetos» | Disco `public` (fotos) y `local` privado (PDFs) dentro de `storage/app` | Sin S3 en plan gratuito |
| Pasarela «Stripe/PayU» | Solo Stripe (modo test) | PayU no integrado; HU acepta cualquiera de las dos |
| CRON horario (HU-09.2, HU-17.3) | Planificador por petición (Task 5) | InfinityFree no tiene cron y bloquea cron externos |
| `ON DELETE CASCADE` (HU-04.3, HU-18.4) | Borrado en cascada desde la aplicación (Task 4) | MyISAM ignora las claves foráneas |

## Decisiones tomadas por defecto (el usuario puede revertirlas antes de ejecutar)

1. **Enumeración de cuentas.** HU-05.3 («No encontramos una cuenta con ese correo. ¿Deseas registrarte?») y HU-24.2 («No existe una cuenta asociada a este correo electrónico») revelan qué correos existen; la auditoría anterior lo había ocultado a propósito. **Por defecto se sigue la documentación** (petición explícita del usuario) y se anota el riesgo en `docs/despliegue/infinityfree.md`. Mitigación que se mantiene: throttling de login y de recuperación.
2. **WebP.** Hoy se aceptan JPG, PNG y WebP. HU-08.2 dice «Use JPG o PNG»: se restringe a `jpg,jpeg,png` en imágenes de inmueble y foto de perfil. El adjunto del chat no está en ninguna HU y conserva WebP.
3. **Datos iniciales en producción.** Se importan roles, permisos, franjas, un administrador y los datos de demostración de `InmuebleSeeder`/`OperacionSeeder` (es un sistema de demostración académica).
4. **Disposición de archivos.** Se sigue la guía oficial: todo el proyecto dentro de `htdocs/` con un `.htaccess` raíz que reescribe a `public/`. `public/index_infinityfree.php` (que asume `../laravel` fuera de `htdocs`) se elimina.

## Review Focus

1. **SMTP caído o bloqueado** durante registro, pago o notificación → la operación se completa y el correo queda en el log; nunca un 500 (test en Task 6).
2. **Primera visita tras horas sin tráfico** → reservas vencidas y contratos vencidos se aplican en esa petición; si una tarea lanza excepción la página del usuario responde igual (test en Task 5).
3. **Cliente que autentica 3DS y cierra la pestaña** → el pago queda conciliado sin webhook en la siguiente ejecución del planificador (test en Task 5).
4. **Eliminar un inmueble con favoritos, imágenes y citas en MyISAM** → no quedan filas huérfanas ni archivos en disco (test en Task 4).
5. **Acceso directo a `/.env`, `/storage/logs/laravel.log`, `/storage/app/private/...`, `/vendor/...`** → 404/403 en producción (verificación manual en Task 8 y Task 21).

## Hallazgos que motivan el plan

| # | Severidad | Hallazgo | Task |
|---|---|---|---|
| 1 | Crítico | `vendor/composer/autoload_classmap.php` (1,05 MB) y `autoload_static.php` (1,15 MB) superan el límite de 1 MB de InfinityFree: el sitio no arranca | 7 |
| 2 | Crítico | Índices `string()` de 255 en `utf8mb4` (sessions, cache, jobs, password_reset_tokens) = 1 020 bytes > 1 000 de MyISAM: la importación falla con «Specified key was too long» | 3 |
| 3 | Crítico | `HASH_DRIVER=argon2id` contradice la documentación (bcrypt) y depende de que PHP del servidor tenga Argon2 | 2 |
| 4 | Alto | Correos con `ShouldQueue` + `QUEUE_CONNECTION=database`: sin worker nunca se envían; con `sync` un fallo SMTP rompe la petición | 6 |
| 5 | Alto | `reservas:expirar` y `contratos:vencer` dependen de cron (HU-09.2, HU-17.3) | 5 |
| 6 | Alto | Pagos 3DS «Procesando» dependen del webhook de Stripe, que el sistema anti-bots de InfinityFree (aes.js / cookie `__test`) bloquea | 5 |
| 7 | Alto | Cascadas `cascadeOnDelete()` confiadas a la BD; MyISAM las ignora (HU-04.3, HU-18.4) | 4 |
| 8 | Alto | 31 usos de `DB::transaction`/`lockForUpdate`: en MyISAM no hay atomicidad; transiciones críticas de pago/reserva pueden duplicarse | 4 |
| 9 | Alto | `public/storage` es un symlink: no existe en InfinityFree; fotos de inmuebles y perfiles darían 404 | 8 |
| 10 | Alto | Volcado desde MySQL 8 local trae `utf8mb4_0900_ai_ci` y `DEFINER`: MariaDB lo rechaza | 9 |
| 11 | Alto | Textos de la documentación ausentes o distintos en ≈25 escenarios (barrido de cadenas exactas, ver Task 11) | 11-20 |
| 12 | Alto | HU-03.4 (correo de confirmación al registrarse) no implementada: `RegisteredUserController` no envía correo | 13 |
| 13 | Alto | HU-09.3 (aviso «Este inmueble acaba de cambiar de estado» por sondeo de 30 s en la ficha) no implementada | 12 |
| 14 | Medio | HU-05.4: al expirar la sesión no se muestra «Su sesión ha expirado» | 13 |
| 15 | Medio | HU-02.5: un inmueble inexistente da el 404 genérico, no «Inmueble no encontrado» con enlace al listado | 12 |
| 16 | Medio | Sondeos de avisos y chat suman *hits* diarios; InfinityFree limita hits/día por cuenta | 12 |

---

## Fase A — Compatibilidad con InfinityFree

### Task 1: Sonda del servidor real

Objetivo: confirmar con datos del servidor del usuario lo que la documentación del foro deja en duda (versión de PHP, extensiones, motor de BD, privilegios, salida HTTPS y SMTP). Las tareas 4 y 6 tienen ramas condicionales según este resultado.

**Files:**
- Create: `deploy/infinityfree/sonda.php`
- Create: `docs/despliegue/infinityfree.md` (sección «Resultado de la sonda»)

**Interfaces:**
- Produces: `docs/despliegue/infinityfree.md` con una tabla `clave | valor | ¿cumple?` que las tareas 4 y 6 consultan (`motor_por_defecto`, `fk_soportadas`, `smtp_587`, `stripe_https`).

- [ ] **Step 1: Escribir `deploy/infinityfree/sonda.php`** — PHP plano, sin Laravel, protegido con `?clave=` comparado con `hash_equals` contra una constante que el usuario edita antes de subirlo. Imprime en texto plano:
  - `PHP_VERSION`, `PHP_INT_SIZE`, `php_sapi_name()`.
  - `extension_loaded()` de: `pdo_mysql, mbstring, intl, gd, zip, bcmath, fileinfo, openssl, curl, dom, xml, xmlwriter, iconv, ctype, tokenizer, sodium`.
  - `ini_get()` de: `memory_limit, max_execution_time, upload_max_filesize, post_max_size, max_file_uploads, disable_functions, open_basedir`.
  - `function_exists()` de: `symlink, putenv, proc_open, exec, fastcgi_finish_request, password_hash` y `defined('PASSWORD_ARGON2ID')`.
  - Con PDO a las credenciales MySQL del panel: `SELECT VERSION()`, `SHOW VARIABLES LIKE 'default_storage_engine'`, `SHOW ENGINES`, `SHOW GRANTS`; luego crea `sonda_fk_padre`/`sonda_fk_hijo` con `ENGINE=InnoDB` y FK `ON DELETE CASCADE`, inserta, borra el padre, reporta si el hijo desapareció y el motor real (`information_schema.TABLES.ENGINE`), e intenta `CREATE VIEW sonda_v AS SELECT 1` (reporta éxito/error); borra todo al final.
  - `fsockopen('smtp-relay.brevo.com', 587, …, 5)` y `fsockopen('smtp.gmail.com', 587, …, 5)`: abierto/cerrado.
  - `curl` GET `https://api.stripe.com/v1` con timeout 10 s: código HTTP (401 = conectividad correcta).
- [ ] **Step 2: El usuario la sube a `htdocs/sonda.php`, la abre en el navegador y pega el resultado.** Se copia a `docs/despliegue/infinityfree.md`.
- [ ] **Step 3: Borrar `htdocs/sonda.php` del servidor.** Verificar que la URL da 404.
- [ ] **Step 4: Validar requisitos mínimos.** Bloqueante si: PHP < 8.3, falta alguna de `pdo_mysql, mbstring, openssl, fileinfo, dom, xml, ctype, tokenizer`. Si falta `gd`, la redimensión de `ImagenInmuebleService::redimensionarSiExcede` debe omitirse cuando `! extension_loaded('gd')` (añadir a Task 14). Si falta `zip`/`xmlwriter`, la exportación Excel se valida en Task 19.
- [ ] **Step 5: Commit**
```bash
git add deploy/infinityfree/sonda.php docs/despliegue/infinityfree.md
git commit -m "chore: sonda del servidor InfinityFree y registro de resultados"
```

### Task 2: Hash bcrypt

**Files:**
- Modify: `.env.example` (`HASH_DRIVER=bcrypt`, `BCRYPT_ROUNDS=12`)
- Create: `deploy/infinityfree/env.produccion.example` (se completa en Task 7; aquí solo `HASH_DRIVER=bcrypt`)
- Test: `tests/Feature/Auth/RegistroTest.php`

- [ ] **Step 1: Test que falla** — `test_hu_03_4_contrasena_se_guarda_con_bcrypt`: registra un cliente por `POST route('register')` con datos válidos; asserta `Hash::info($usuario->password)['algoName'] === 'bcrypt'`.
- [ ] **Step 2: Correr y ver que falla** (hoy hereda `argon2id` del `.env`). Run: `php artisan test --compact --filter=test_hu_03_4`. Luego fijar `<env name="HASH_DRIVER" value="bcrypt"/>` en `phpunit.xml` y verlo pasar.
- [ ] **Step 3: Cambiar `.env.example`** y documentar en `docs/despliegue/infinityfree.md` que el `.env` local también debe pasar a `bcrypt` y que las contraseñas de los seeders se regeneran al hacer `migrate:fresh --seed` (Task 9).
- [ ] **Step 4: Commit** `fix: hash bcrypt como exige la documentación`

### Task 3: Esquema compatible con MyISAM/MariaDB

**Files:**
- Modify: `app/Providers/AppServiceProvider.php` (`boot()`)
- Test: `tests/Unit/EsquemaCompatibleTest.php` (extiende `Tests\TestCase`, usa `RefreshDatabase`)

**Interfaces:**
- Produces: `Schema::defaultStringLength(191)` activo en todas las migraciones.

- [ ] **Step 1: Test que falla** — `test_ningun_indice_supera_1000_bytes_en_utf8mb4`: tras migrar (SQLite), recorre `Schema::getTables()`; por cada tabla, `Schema::getIndexes($t)` y `Schema::getColumns($t)`; para cada índice suma, por columna, `n * 4` si el tipo es `varchar(n)`/`char(n)`, 8 si entero/fecha, y falla si la suma > 1000 indicando tabla e índice. En SQLite el tipo llega como `varchar` con longitud en `type` (`varchar(255)`); parsear con `preg_match('/\((\d+)\)/')`.
- [ ] **Step 2: Correr y ver que falla** en `sessions.id`, `cache.key`, `cache_locks.key`, `jobs.queue`, `failed_jobs.uuid`, `password_reset_tokens.email`.
- [ ] **Step 3: Añadir `Schema::defaultStringLength(191);`** en `AppServiceProvider::boot()`. Si algún índice con longitud explícita sigue > 1000 bytes (p. ej. índice compuesto de `auditoria.entidad`), crear migración nueva que reduzca esa columna a la longitud mínima que cubra los datos.
- [ ] **Step 4: Correr el test y la suite completa.** Expected: PASS.
- [ ] **Step 5: Commit** `fix: índices dentro del límite de 1000 bytes de MyISAM`

### Task 4: Integridad sin claves foráneas ni transacciones

**Files:**
- Modify: `app/Models/Inmueble.php` (evento `deleting`)
- Modify: `app/Http/Controllers/Admin/InmuebleController.php:83-100` (`destroy`)
- Modify (solo si la sonda reporta MyISAM): `app/Servicios/PagoService.php` (`aprobar`, `rechazar`), `app/Servicios/ReservaService.php` (crear, confirmar, cancelar), `app/Servicios/CitaService.php` (crear)
- Test: `tests/Feature/Admin/GestionInmueblesTest.php`, `tests/Feature/PagoTest.php`

**Interfaces:**
- Produces: `Inmueble::deleting` borra en este orden: `observacioncita` y `cita_historial` de sus citas → `cita` → `favorito` → `imageninmueble` (filas **y** archivos vía `ImagenInmuebleService::eliminarTodas`). Lo que hoy es `nullOnDelete` hacia `inmueble` (p. ej. `conversacion.inmueble_id`, si aplica) se pone a `null` explícitamente.
- Produces (rama MyISAM): transiciones de estado críticas como *compare-and-swap*: `Modelo::whereKey($id)->where('estado', $esperado)->update([...])`; si devuelve `0`, la operación es un no-op idempotente.

- [ ] **Step 1: Test que falla** — `test_hu_18_4_eliminar_inmueble_borra_favoritos_imagenes_y_citas_sin_fk`: crea inmueble con 2 favoritos, 2 imágenes (`Storage::fake('public')` con archivos reales), 1 cita con historial y observación; ejecuta `Schema::disableForeignKeyConstraints()`; admin hace `DELETE route('admin.inmuebles.destroy', $inmueble)`; asserta `assertDatabaseMissing` en `favorito`, `imageninmueble`, `cita`, `cita_historial`, `observacioncita` para ese inmueble y `Storage::disk('public')->assertMissing` de cada ruta.
- [ ] **Step 2: Correr y ver que falla** (filas huérfanas).
- [ ] **Step 3: Implementar `Inmueble::booted()` con `static::deleting(...)`** y dejar `destroy` sin borrados duplicados.
- [ ] **Step 4: Correr y ver que pasa.**
- [ ] **Step 5 (solo si la sonda dice MyISAM): Test** — `test_aprobar_pago_ya_aprobado_no_confirma_dos_veces`: pago en `PAGADO`; llamar `PagoService::aprobar($pago)` sobre una instancia obsoleta cargada antes con estado `PENDIENTE` (`$obsoleto = Pago::find($id)` antes de aprobar una vez); asserta una sola fila en `historial_reserva` con estado `CONFIRMADA` y una sola notificación de pago. Implementar el *compare-and-swap* en `aprobar`, `rechazar` y `ReservaService::confirmar`/`cancelar`; en creación de reserva y cita, comprobar duplicado justo antes de insertar y otra vez después (si hay dos activas del mismo cliente e inmueble, cancelar la más reciente).
- [ ] **Step 6: Suite completa en verde. Commit** `fix: integridad en la aplicación para motores sin FK ni transacciones`

### Task 5: Planificador por petición y conciliación de pagos sin webhook

**Files:**
- Create: `app/Http/Middleware/EjecutarTareasPendientes.php`
- Create: `app/Console/Commands/ConciliarPagos.php` (firma `pagos:conciliar`)
- Modify: `bootstrap/app.php` (añadir al grupo `web`)
- Modify: `routes/console.php` (añadir `pagos:conciliar` cada 15 min para hosts con cron)
- Test: `tests/Feature/TareasPendientesTest.php`

**Interfaces:**
- Consumes: comandos existentes `reservas:expirar`, `contratos:vencer`; `StripeCardService::sincronizarPago(Pago $pago): string` (devuelve el estado de Stripe y aplica el resultado).
- Produces: `EjecutarTareasPendientes::terminate(Request, Response): void` — si `Cache::add('tareas:ultima-ejecucion', now()->timestamp, 900)` devuelve `true`, ejecuta en orden `reservas:expirar`, `contratos:vencer`, `pagos:conciliar` con `Artisan::call`, cada uno en su propio `try/catch` que hace `report($e)`. Intervalo: **15 minutos** (más fino que el «horario» de la HU, sin coste apreciable). Se salta si `config('app.tareas_por_peticion')` es `false` (`env('TAREAS_POR_PETICION', true)`; en `phpunit.xml` `false` salvo en este test).
- Produces: `pagos:conciliar` — para cada `Pago` con `pasarela = STRIPE`, estado `PROCESANDO` y `creado_en < now()->subMinutes(10)`, llama `sincronizarPago`; errores de red se reportan y el pago queda igual.

- [ ] **Step 1: Tests que fallan**
  - `test_hu_09_2_reserva_vencida_se_libera_en_la_siguiente_visita`: reserva confirmada con `expira_en` pasado e inmueble `Reservado`; `GET route('inicio')` con tareas activas; asserta inmueble `Disponible` y reserva en el estado de expiración que hoy usa `reservas:expirar`.
  - `test_hu_17_3_contrato_vencido_en_la_siguiente_visita`: contrato `Vigente` con `fecha_fin` ayer; `GET /`; asserta `Vencido` y notificación al administrador.
  - `test_tareas_se_ejecutan_una_vez_cada_15_minutos`: dos `GET /` seguidos → `reservas:expirar` corre una vez (contar filas de `historial_reserva`); `travel(16)->minutes()`; tercer `GET` → vuelve a correr.
  - `test_fallo_de_tarea_no_rompe_la_pagina`: `Artisan::command('contratos:vencer', fn () => throw new RuntimeException)` sobrescrito; `GET /` → 200.
  - `test_pago_3ds_abandonado_se_concilia_sin_webhook`: pago `PROCESANDO` de hace 20 min; mock de `StripeCardService` (`$this->mock(...)`) cuyo `sincronizarPago` llama `PagoService::aprobar` y devuelve `'succeeded'`; `GET /`; asserta reserva `Confirmada` e inmueble `Reservado`.
- [ ] **Step 2: Correr y ver que fallan.** Run: `php artisan test --compact --filter=TareasPendientesTest`
- [ ] **Step 3: Implementar middleware y comando**; registrar con `$middleware->appendToGroup('web', EjecutarTareasPendientes::class)`.
- [ ] **Step 4: Correr y ver que pasan; suite completa en verde.**
- [ ] **Step 5: Commit** `feat: tareas programadas y conciliación de pagos sin cron ni webhook`

### Task 6: Correo síncrono con respaldo

**Files:**
- Modify: `config/mail.php` (mailer `failover` con `['smtp', 'log']`); producción usa `QUEUE_CONNECTION=sync`, así los `Mailable` con `ShouldQueue` se envían en la misma petición sin tocar su código
- Test: `tests/Feature/NotificacionTest.php`

**Interfaces:**
- Produces: `MAIL_MAILER=failover` en producción; si SMTP falla, el correo se escribe en `storage/logs/laravel.log` y la petición sigue.

- [ ] **Step 1: Test que falla** — `test_smtp_caido_no_interrumpe_el_pago`: `config(['queue.default' => 'sync', 'mail.default' => 'failover', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1, 'mail.mailers.smtp.timeout' => 1])`; aprobar un pago desde el panel admin (`route` existente de aprobación); asserta redirección con éxito, reserva `Confirmada` y que no hubo excepción.
- [ ] **Step 2: Correr y ver que falla** (hoy `failover` no tiene `log` o el `smtp` lanza).
- [ ] **Step 3: Definir en `config/mail.php`** `'failover' => ['transport' => 'failover', 'mailers' => ['smtp', 'log'], 'retry_after' => 60]`.
- [ ] **Step 4: Correr; suite completa en verde.**
- [ ] **Step 5: Documentar en `docs/despliegue/infinityfree.md`** el proveedor SMTP según la sonda: Brevo (`smtp-relay.brevo.com:587`, TLS) si el puerto está abierto; si no, Gmail con contraseña de aplicación (`smtp.gmail.com:587`); si ambos están cerrados, el sistema funciona y los correos quedan en log (riesgo aceptado, anotado).
- [ ] **Step 6: Commit** `fix: correos síncronos con respaldo a log`

### Task 7: Empaquetado para subir

**Files:**
- Create: `deploy/infinityfree/empaquetar.ps1`
- Modify: `deploy/infinityfree/env.produccion.example`
- Delete: `public/index_infinityfree.php`
- Modify: `.gitignore` (añadir `/build-infinityfree/`)

**Interfaces:**
- Produces: carpeta `build-infinityfree/htdocs/` lista para subir por FTP, y `build-infinityfree/htdocs.zip`.

- [ ] **Step 1: `env.produccion.example`** con exactamente: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://<dominio>`, `APP_KEY=` (vacío: el usuario pega el de `php artisan key:generate --show`), `APP_LOCALE=es`, `LOG_CHANNEL=single`, `LOG_LEVEL=error`, `DB_CONNECTION=mysql`, `DB_HOST=sql106.infinityfree.com`, `DB_PORT=3306`, `DB_DATABASE=`, `DB_USERNAME=`, `DB_PASSWORD=`, `SESSION_DRIVER=file`, `SESSION_LIFETIME=15`, `SESSION_SECURE_COOKIE=true`, `CACHE_STORE=file`, `QUEUE_CONNECTION=sync`, `FILESYSTEM_DISK=public`, `HASH_DRIVER=bcrypt`, `BCRYPT_ROUNDS=10`, `MAIL_MAILER=failover`, `MAIL_HOST=`, `MAIL_PORT=587`, `MAIL_SCHEME=null`, `MAIL_USERNAME=`, `MAIL_PASSWORD=`, `MAIL_FROM_ADDRESS=`, `TAREAS_POR_PETICION=true`, `STRIPE_KEY=`, `STRIPE_SECRET=`, `STRIPE_WEBHOOK_SECRET=`, `BROADCAST_CONNECTION=log`.
- [ ] **Step 2: Escribir `empaquetar.ps1`** que, con `$ErrorActionPreference='Stop'`:
  1. Borra y recrea `build-infinityfree/htdocs`.
  2. `npm ci; npm run build` en el repo.
  3. Copia con `robocopy` el repo excluyendo: `.git .claude .superpowers node_modules tests Documentacion-inmobiliaria Documentación docs deploy build-infinityfree storage/logs/* storage/framework/{cache,sessions,views}/* bootstrap/cache/*.php public/hot public/storage .env .phpunit.result.cache *.md phpunit.xml vite.config.js package*.json router.php`.
  4. En la copia: `composer install --no-dev --no-interaction --prefer-dist`, luego `composer dump-autoload --no-dev` **sin** `--optimize` (evita el classmap > 1 MB).
  5. Copia `deploy/infinityfree/htdocs.htaccess` (Task 8) como `htdocs/.htaccess`.
  6. Crea vacíos (con `.gitignore`) `storage/framework/{cache/data,sessions,views}`, `storage/logs`, `storage/app/public`, `storage/app/private`, `bootstrap/cache`.
  7. **Verificaciones que abortan**: cualquier `*.php` > 1 000 000 bytes (lista los archivos); conteo de archivos + carpetas > 25 000 (margen bajo el límite de inodos); existencia de `public/build/manifest.json`; ausencia de `.env`.
  8. Imprime el total de archivos y genera `htdocs.zip`.
- [ ] **Step 3: Ejecutar** `pwsh deploy/infinityfree/empaquetar.ps1`. Expected: termina sin error, informa < 25 000 entradas y 0 PHP > 1 MB.
- [ ] **Step 4: Prueba de humo local del paquete**: copiar `env.produccion.example` como `build-infinityfree/htdocs/.env` apuntando a una BD local de prueba (Task 9), servir con `php -S 127.0.0.1:8099 -t build-infinityfree/htdocs/public` y abrir `/`, `/login`, una ficha de inmueble. Expected: 200 y estilos cargados.
- [ ] **Step 5: Commit** `chore: script de empaquetado para InfinityFree`

### Task 8: Reglas Apache, archivos públicos sin symlink y protección de rutas

**Files:**
- Create: `deploy/infinityfree/htdocs.htaccess`
- Verify (sin cambios): `public/.htaccess` estándar de Laravel

- [ ] **Step 1: Escribir `htdocs.htaccess`** con este contenido exacto (orden obligatorio, según la guía corregida del 7-jun-2026 más bloqueos explícitos):
```apache
Options -Indexes
RewriteEngine On

# Nunca servir archivos ocultos ni directorios internos
RewriteRule (^|/)\. - [F,L]
RewriteRule ^(app|bootstrap|config|database|lang|resources|routes|storage/(app/private|framework|logs)|vendor)(/|$) - [F,L]

# Archivos públicos subidos (sustituye a storage:link)
RewriteRule ^storage/(.*)$ /storage/app/public/$1 [END]

# Todo lo demás al front controller de Laravel
RewriteRule (.*) /public/$1 [L]
```
- [ ] **Step 2: Verificación local con Apache de Laragon**: crear un vhost temporal con `DocumentRoot` en `build-infinityfree/htdocs` y comprobar con `curl -s -o NUL -w "%{http_code}"`: `/` → 200; `/.env` → 403; `/storage/logs/laravel.log` → 403; `/vendor/autoload.php` → 403; `/storage/app/private/x` → 403; `/storage/inmuebles/<foto existente>.jpg` → 200 con `Content-Type: image/jpeg`; `/build/manifest.json` → 200.
- [ ] **Step 3: Confirmar que `Storage::disk('public')->url(...)` genera `https://<dominio>/storage/...`** (ya lo hace `config/filesystems.php`). Sin cambios de código.
- [ ] **Step 4: Commit** `chore: reglas Apache para InfinityFree`

### Task 9: Base de datos de producción

**Files:**
- Create: `deploy/infinityfree/exportar-bd.ps1`
- Modify: `database/seeders/UsuarioSeeder.php` (contraseña del admin desde `env('ADMIN_PASSWORD_INICIAL')`, obligatoria si `app()->isProduction()`)

**Interfaces:**
- Produces: `build-infinityfree/bd.sql` importable por phpMyAdmin.

- [ ] **Step 1: Escribir `exportar-bd.ps1`** que: crea la BD local `inmobiliaria_export` con `utf8mb4_unicode_ci`; ejecuta `php artisan migrate:fresh --seed --force --database=mysql` con `DB_DATABASE=inmobiliaria_export` y `HASH_DRIVER=bcrypt` en el entorno del proceso; vuelca con `mysqldump --no-tablespaces --skip-triggers --skip-routines --skip-events --single-transaction --default-character-set=utf8mb4 --set-gtid-purged=OFF`; sobre el SQL resultante: reemplaza `utf8mb4_0900_ai_ci` → `utf8mb4_unicode_ci`, elimina cláusulas `DEFINER=...`, elimina `CREATE DATABASE`/`USE`; excluye datos de `sessions`, `cache`, `cache_locks`, `jobs`, `failed_jobs`. Aborta si el archivo supera 8 MB.
- [ ] **Step 2: Ejecutar** y comprobar con `Select-String` que no quedan `0900_ai_ci`, `DEFINER`, `TRIGGER`, `CREATE VIEW`, `PROCEDURE`.
- [ ] **Step 3: Prueba de importación** en un MariaDB local (o en la BD de InfinityFree vacía): importar `bd.sql` y verificar 0 errores. Si el servidor usa MyISAM, confirmar que la importación no falla (las FK se ignoran en silencio).
- [ ] **Step 4: Documentar en `docs/despliegue/infinityfree.md`** el procedimiento para cambios de esquema futuros: nueva migración → re-exportar solo el `ALTER` con `php artisan migrate --pretend` → pegar en phpMyAdmin.
- [ ] **Step 5: Commit** `chore: exportación de BD compatible con InfinityFree`

### Task 10: Stripe en InfinityFree

**Files:**
- Modify: `docs/despliegue/infinityfree.md`
- Verify: `resources/js/` (Stripe.js), `app/Http/Middleware/AgregarCabecerasSeguridad.php`

- [ ] **Step 1: Confirmar que ningún flujo exige el webhook**: guardar tarjeta (SetupIntent desde el navegador), pagar (PaymentIntent con `confirm => true` en servidor), 3DS (`ReservaController` verificar → `sincronizarPago`), y pagos abandonados (Task 5). Test existente o nuevo `test_hu_23_1_pago_exitoso_sin_webhook` con mock de `StripeCardService::pagarConTarjeta` devolviendo `succeeded`: reserva `Confirmada`, inmueble `Reservado`, correo `ComprobantePago` enviado (`Mail::fake()` + `assertSent`; con `sync` cuenta como enviado).
- [ ] **Step 2: Verificar que la sonda devolvió 401 de `api.stripe.com`** (salida HTTPS permitida). Si no hay salida, documentar que solo queda el pago manual con revisión del administrador y marcar HU-20 y HU-23.1 como «No soportado en InfinityFree gratuito» en la matriz.
- [ ] **Step 3: Documentar** que en el panel de Stripe no se registra webhook para el dominio de InfinityFree (sería bloqueado por aes.js) y que se usan claves `pk_test_`/`sk_test_`.
- [ ] **Step 4: Commit** `docs: flujo de Stripe sin webhook en InfinityFree`

---

## Fase B — Conformidad con las historias de usuario

Cada tarea de esta fase sigue el mismo ciclo: por cada escenario listado, (1) escribir el test con el nombre `test_hu_NN_E_…` y las aserciones de la tabla, (2) verlo fallar o pasar, (3) si falla, corregir el código en la capa dueña (Form Request para mensajes de validación, Controller para flash, Blade para etiquetas, Service para reglas), (4) suite en verde, (5) commit `fix(hu-NN): …`. Los escenarios que ya cumplen se documentan con su test existente en la matriz y no se tocan.

### Task 11: Matriz de conformidad

**Files:**
- Create: `docs/hu/matriz-conformidad.md`

**Interfaces:**
- Produces: tabla `HU | Escenario | Título | Ruta/acción | Archivo(s) | Test | Estado (Cumple / Difiere: texto / Difiere: comportamiento / Falta / Equivalencia)` con las 110 filas de la hoja «Historias de Usuario». Las tareas 12-20 la actualizan al cerrar cada escenario.

- [ ] **Step 1: Volcar las 110 filas** (HU, escenario, título, resultado esperado) desde el xlsx.
- [ ] **Step 2: Rellenar ruta/archivo/test** con `php artisan route:list --except-vendor`, los documentos de `Documentación/Trazabilidad/*.md` y `grep -rn "HU-NN" app tests`.
- [ ] **Step 3: Marcar el estado inicial.** Punto de partida del barrido de cadenas exactas (8-nov-2026), todas con 0 coincidencias en `app/`, `resources/`, `lang/`: 2.5, 3.1, 3.2, 3.3, 4.5, 5.2, 5.3, 5.4, 5.5, 6.3, 7.2, 7.4, 8.2, 8.3, 8.4, 9.3, 23.2, 23.3, 23.4, 24.2, 24.3, 24.4, 25.1, 27.3, 27.6; y comportamiento ausente en 3.4 y 9.3.
- [ ] **Step 4: Commit** `docs: matriz de conformidad de historias de usuario`

### Task 12: Catálogo, filtros y ficha (HU-01, HU-02, HU-09.3)

**Files:**
- Modify: `app/Http/Controllers/InmuebleController.php`, vistas del catálogo y de la ficha en `resources/views/inmuebles/`, `resources/js/avisos.js` (o nuevo `resources/js/estado-inmueble.js` importado desde `app.js`)
- Modify: `routes/web.php` (ruta `GET /inmuebles/{inmueble}/estado` → `inmuebles.estado`, `throttle:60,1`)
- Test: `tests/Feature/CatalogoTest.php`

| Escenario | Aserciones |
|---|---|
| 1.1 | `GET route('inmuebles.index')` muestra por cada inmueble: imagen principal, precio formateado, ciudad/dirección, habitaciones y baños |
| 1.2 | Ficha de inmueble `Disponible`: etiqueta con texto `Disponible` y clase de color verde existente; botón `Reservar` sin `disabled` |
| 1.3 | Ficha `Reservado`: etiqueta `Reservado` amarilla; botón `Reservar` presente con atributo `disabled` |
| 1.4 | Ficha `Ocupado`: etiqueta `Ocupado` gris; `assertDontSee('Reservar')` como botón |
| 1.5 | Sin inmuebles: `No hay inmuebles disponibles en este momento` |
| 2.1 / 2.2 | Filtro `tipo=APARTAMENTO` y combinado `tipo=CASA` + rango de precio + `habitaciones=3` devuelven solo los que cumplen todos |
| 2.3 | Botón con texto `Limpiar filtros` que enlaza a `route('inmuebles.index')` sin query |
| 2.4 | `No se encontraron inmuebles con los criterios seleccionados` y enlace para modificar filtros |
| 2.5 | `GET /inmuebles/999999` → 404 con `Inmueble no encontrado` y enlace a `route('inmuebles.index')` (vista `errors/404` condicionada a `ModelNotFoundException` de `Inmueble`, o `->missing()` en la ruta) |
| 9.3 | `GET route('inmuebles.estado', $i)` → JSON `{"estado":"DISPONIBLE"}`; la ficha incluye `data-url-estado` y `data-estado`; el JS consulta cada **30 000 ms** solo si `!document.hidden` y, si cambia, muestra (SweetAlert2 toast existente) `Este inmueble acaba de cambiar de estado` y recarga la ficha |

- [ ] Ciclo de la Fase B para cada fila. Además, en `avisos.js` y `chat.js`, confirmar que el intervalo de sondeo es ≥ 30 000 ms (límite de hits diarios de InfinityFree); si es menor, subirlo a 30 000.

### Task 13: Registro, sesión, contraseña y perfil (HU-03, HU-05, HU-24, HU-25)

**Files:**
- Modify: `app/Http/Requests/RegistroRequest.php`, `app/Reglas/PasswordSegura.php`, `app/Http/Controllers/Auth/RegisteredUserController.php`, `app/Http/Requests/Auth/LoginRequest.php`, `app/Http/Middleware/EnsureUserIsActive.php`, `app/Http/Controllers/Auth/PasswordResetLinkController.php`, `app/Http/Controllers/Auth/NewPasswordController.php`, `app/Http/Controllers/PerfilController.php`, `app/Http/Requests/FotoPerfilRequest.php`, `lang/es/passwords.php`, `bootstrap/app.php`
- Create: `app/Mail/BienvenidaRegistro.php` (sin `ShouldQueue`; vista `resources/views/correos/bienvenida.blade.php`)
- Test: `tests/Feature/Auth/RegistroTest.php`, `tests/Feature/Auth/AutenticacionTest.php`, `tests/Feature/PerfilTest.php`

| Escenario | Aserciones |
|---|---|
| 3.1 | Registro válido (nombre, correo, contraseña, teléfono) → flash `Registro exitoso. Bienvenido` |
| 3.2 | Correo existente → error `El correo ingresado ya está registrado. Por favor inicie sesión.` |
| 3.3 | `abc` y `abcdefgh` → error `La contraseña debe tener mínimo 8 caracteres e incluir números y letras` |
| 3.4 | `Mail::fake()`; tras registrar, `Mail::assertSent(BienvenidaRegistro::class, fn ($m) => $m->hasTo($email))` |
| 5.1 | Login correcto de cada rol redirige a `route($usuario->rol->rutaInicio())` |
| 5.2 | Contraseña incorrecta de correo existente → `Correo o contraseña incorrectos` |
| 5.3 | Correo inexistente → `No encontramos una cuenta con ese correo. ¿Deseas registrarte?` con enlace a `route('register')` (Decisión 1) |
| 5.4 | Login; `travel(16)->minutes()`; `GET` a ruta protegida → redirige a login con `Su sesión ha expirado`. Implementación: al iniciar sesión se pone la cookie `sesion_iniciada=1` (vida 30 días, `httpOnly`); en `redirectGuestsTo` de `bootstrap/app.php`, si la petición trae esa cookie, se hace `session()->flash('estado', 'Su sesión ha expirado')` y se olvida la cookie; `logout` la olvida también |
| 5.5 | Asesor inactivo con credenciales correctas → `Su cuenta está inactiva. Contacte al administrador` |
| 24.1 | Correo existente → se crea fila en `password_reset_tokens` y mensaje de confirmación; `config('auth.passwords.users.expire') === 60` |
| 24.2 | Correo inexistente → `No existe una cuenta asociada a este correo electrónico` (Decisión 1) |
| 24.3 | Token con `created_at` de hace 61 min → `El enlace ha expirado. Solicite uno nuevo` y enlace a `route('password.request')` |
| 24.4 | Token válido → contraseña bcrypt nueva y `Contraseña restablecida exitosamente` |
| 25.1 | Editar nombre/teléfono → `Perfil actualizado exitosamente` |
| 25.2 | Contraseña actual incorrecta → error en `current_password`; correcta → `Hash::check` con la nueva |
| 25.3 | Campos inválidos → un error por campo (`assertSessionHasErrors(['nombre', 'telefono'])`) |
| 25.4 | JPG de 2 MB → guardado, `foto_url` actualizado y visible; PNG de 2,1 MB → error; WebP → error (Decisión 2) |

- [ ] Ciclo de la Fase B para cada fila. Mantener el throttling existente de login y recuperación.

### Task 14: Gestión de inmuebles e imágenes (HU-04, HU-08)

**Files:**
- Modify: `app/Http/Requests/Admin/InmuebleRequest.php`, `app/Http/Controllers/Admin/InmuebleController.php`, `app/Servicios/ImagenInmuebleService.php` (omitir redimensión sin GD, según Task 1)
- Test: `tests/Feature/Admin/GestionInmueblesTest.php`

| Escenario | Aserciones |
|---|---|
| 4.1 | Alta válida → `codigo` único no vacío y visible en `GET route('inmuebles.index')` |
| 4.2 | Edición → cambio visible en el listado público |
| 4.3 | Eliminar sin reservas activas → fila borrada (cubierto con Task 4) |
| 4.4 | Envío vacío → error por cada campo obligatorio |
| 4.5 | Con reserva activa → `No es posible eliminar un inmueble con reservas activas` |
| 8.1 | Dos JPG → dos filas en `imageninmueble` y visibles en la ficha pública |
| 8.2 | `.pdf` → `Formato no permitido. Use JPG o PNG` |
| 8.3 | JPG de 5 121 KB → `El archivo supera el tamaño máximo permitido (5 MB)` |
| 8.4 | Descripción de 49 caracteres → `La descripción debe tener al menos 50 caracteres` |

- [ ] Ciclo de la Fase B para cada fila. Confirmar que `upload_max_filesize`/`post_max_size` de la sonda admiten 5 MB por archivo; si `post_max_size` < (5 MB × imágenes por envío), limitar `imagenes` a `max:N` con N = `floor(post_max_size / 5 MB)` y mostrar ese límite en el formulario.

### Task 15: Reservas, pagos y tarjetas (HU-07, HU-09.1, HU-09.4, HU-20, HU-23)

**Files:**
- Modify: `app/Http/Controllers/ReservaController.php`, `app/Servicios/ReservaService.php`, `app/Servicios/StripeCardService.php`, `app/Http/Controllers/MetodoPagoController.php`, vistas de reserva/pago
- Test: `tests/Feature/ReservaTest.php`, `tests/Feature/PagoTest.php`

| Escenario | Aserciones |
|---|---|
| 7.1 | Reservar inmueble `Disponible` → reserva `Pendiente de pago`; inmueble sigue `Disponible` |
| 7.2 | Invitado pulsa Reservar → redirige a login con `Debe iniciar sesión para reservar un inmueble` (el `redirectGuestsTo` mira `routeIs('reservas.*')`) |
| 7.3 | Inmueble `Reservado` u `Ocupado` → `Este inmueble ya no está disponible` |
| 7.4 | Segunda reserva activa → `Ya tienes una reserva activa para este inmueble`; una sola fila |
| 7.5 | Cancelar pendiente → `Cancelada`; inmueble `Disponible` |
| 9.1 / 23.1 | Pago exitoso → reserva `Confirmada`, inmueble `Reservado`, `ComprobantePago` enviado |
| 9.4 | Transiciones Disponible→Reservado, Reservado→Ocupado, Ocupado→Disponible generan correo `Aviso` al admin; otras no |
| 20.1 | Guardar tarjeta → solo `token`, `ultimos4`, `marca` en `metodo_pago_guardado`; ninguna columna con el número completo |
| 20.2 / 20.3 / 20.4 | Listado con marca y últimos 4; eliminar; pagar con tarjeta guardada sin pedir datos |
| 23.2 | Stripe `card_declined` → `Pago rechazado. Intente con otro método de pago`; reserva `Pendiente de pago`, inmueble `Disponible` |
| 23.3 | `ApiConnectionException` → mensaje de error y botón `Reintentar pago`; reserva sin cambios |
| 23.4 | `incorrect_number`/`invalid_expiry_*`/`invalid_cvc` → `Datos inválidos. Verifique la información de su tarjeta`; ningún `Pago` creado |
| 23.5 | Excepción interna → mensaje de error, botón `Reintentar pago`, reserva `Pendiente de pago` |

- [ ] Ciclo de la Fase B para cada fila; las respuestas de Stripe se simulan con `$this->mock(StripeCardService::class)` o lanzando las excepciones del SDK (`Stripe\Exception\CardException::factory(...)`).

### Task 16: Citas (HU-10, HU-11, HU-12, HU-27)

**Files:**
- Modify: `app/Servicios/CitaService.php`, `app/Http/Controllers/CitaController.php`, `app/Http/Controllers/Admin/CitaController.php`, `app/Http/Controllers/Asesor/CitaController.php`, `app/Http/Requests/SolicitarCitaRequest.php`, vistas de citas
- Test: `tests/Feature/CitaTest.php`

| Escenario | Aserciones |
|---|---|
| 10.1 | Asignar asesor activo → `Asignada` + notificación al asesor |
| 10.2 | Vista agrupada por asesor con fecha, hora, inmueble y estado |
| 10.3 | Reasignar → nuevo asesor notificado y fila en `cita_historial` |
| 11.1 / 11.2 | Listado del asesor; vacío → `No tienes citas asignadas en este momento` |
| 11.3 | Detalle con nombre, teléfono, email del cliente, dirección del inmueble, notas y estado |
| 11.4 | Filtro por cada estado: Pendiente, Asignada, Confirmada, Realizada, Cancelada |
| 12.1 / 12.2 | Guardar observación → `Realizada`; vacía → `Debe ingresar observaciones de la visita antes de marcar como realizada` |
| 12.3 / 12.4 | Notificación al administrador y al cliente |
| 27.1 | Fecha dentro de 30 días, lunes-sábado 08:00-18:00, múltiplos de 30 min → `Pendiente` sin asesor + notificación al admin; domingo, 18:30 o día 31 → error |
| 27.2 | Día sin franjas → `No hay horarios disponibles para esta fecha. Por favor seleccione otra` |
| 27.3 | Cita activa previa → `Ya tienes una cita pendiente para este inmueble` |
| 27.4 | Cancelar → `Cancelada`, notifica a asesor (si hay) y admin |
| 27.5 | «Mis citas» con inmueble, fecha, hora, asesor y estado |
| 27.6 | Invitado → login con `Debe iniciar sesión para agendar una cita` |

- [ ] Ciclo de la Fase B para cada fila.

### Task 17: Mensajería y notificaciones (HU-13, HU-15, HU-22)

**Files:**
- Modify (si hace falta): `app/Servicios/MensajeService.php`, `app/Servicios/NotificacionService.php`, `app/Http/Controllers/MensajeController.php`, `app/Http/Controllers/Admin/NotificacionController.php`
- Test: `tests/Feature/MensajeTest.php`, `tests/Feature/NotificacionTest.php`

| Escenario | Aserciones |
|---|---|
| 13.1 | Bandeja del asesor: no leídos con nombre del cliente, inmueble y fecha |
| 13.2 | Responder → mensaje creado + notificación al cliente |
| 13.3 | Ver hilo → `leido_en` no nulo y deja de contar como pendiente |
| 13.4 | Hilo en orden cronológico ascendente |
| 15.1 / 15.2 | Notificación al asesor por mensaje nuevo y por cita asignada (con fecha, hora e inmueble) |
| 15.3 / 15.4 | Centro de notificaciones con leída/no leída; clic marca leída y baja el contador |
| 22.1 | Envío individual → tipo `sistema` visible en el centro del destinatario; envío a todos → una por usuario activo |
| 22.2 / 22.3 | Pago exitoso → notificación al cliente y alerta al administrador |
| 22.4 | Badge con el número de no leídas en el layout |

- [ ] Ciclo de la Fase B para cada fila. «Envío a todos» con muchos usuarios y `QUEUE_CONNECTION=sync`: si envía correo además de la notificación in-app, comprobar con 200 usuarios de factory que la petición termina en < 20 s; si no, limitar el correo masivo a la notificación in-app y documentarlo.

### Task 18: Ventas, contratos e historial (HU-14, HU-17, HU-19)

**Files:**
- Modify (si hace falta): `app/Servicios/VentaService.php`, `app/Servicios/ContratoService.php`, `app/Http/Controllers/Asesor/VentaController.php`, `app/Http/Controllers/Admin/ContratoController.php`, `app/Http/Controllers/PerfilController.php`, `app/Http/Controllers/DescargaController.php`
- Test: `tests/Feature/VentaTest.php`, `tests/Feature/ContratoTest.php`, `tests/Feature/PerfilTest.php`

| Escenario | Aserciones |
|---|---|
| 14.1 | Registrar venta → `En proceso`; inmueble `Reservado` |
| 14.2 | Subir PDF de escritura → descargable por admin y cliente, 403 para otros |
| 14.3 | Historial del asesor solo con sus ventas |
| 14.4 | Cancelar → inmueble `Disponible` + notificación al cliente |
| 17.1 | Contrato desde reserva `Confirmada` → `Vigente`; inmueble `Ocupado` |
| 17.2 | PDF firmado visible en el historial del cliente |
| 17.3 | Cubierto en Task 5 |
| 17.4 | Rescindir → inmueble `Disponible` + notificación al cliente |
| 19.1 / 19.2 | «Mis arriendos» (inmueble, contrato, valor mensual, último pago, estado) y «Mis compras» (inmueble, asesor, precio, fecha, estado) |
| 19.3 | `Descargar contrato` devuelve el PDF (`Content-Type: application/pdf`) |
| 19.4 | Reserva confirmada sin contrato → `Contrato pendiente` |

- [ ] Ciclo de la Fase B para cada fila. Las descargas usan `ArchivoPrivadoService` (disco privado, sin symlink): verificar en la prueba de humo (Task 21).

### Task 19: Reportes (HU-06, HU-21)

**Files:**
- Modify: `app/Http/Controllers/Admin/ReporteController.php`, `app/Servicios/Reportes/*`, vista de reportes
- Test: `tests/Feature/ReporteTest.php`

| Escenario | Aserciones |
|---|---|
| 6.1 | Reporte diario con fecha, inmueble, cliente y estado |
| 6.2 | Fecha sin datos → `No hay registros disponibles` |
| 6.3 | Excepción al construir el reporte (mock de `FabricaReportes` que lanza) → mensaje de error y botón `Intentar nuevamente` que repite la misma petición |
| 6.4 / 21.4 | Exportar PDF → `application/pdf`; Excel → `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet` |
| 21.1 | Pagos: monto, método, pasarela, estado |
| 21.2 | Contratos vigentes: número, inmueble, cliente, valor mensual, fecha fin |
| 21.3 | Ventas por rango: precio total y asesor |

- [ ] Ciclo de la Fase B para cada fila. Además: con 2 000 filas de factory, medir `memory_get_peak_usage()` de la exportación PDF y Excel; si supera el 60 % del `memory_limit` de la sonda o tarda > 20 s, limitar la exportación a un rango máximo (p. ej. 31 días para el diario, 12 meses para el resto) con mensaje claro.

### Task 20: Usuarios, asesores, roles y permisos (HU-16, HU-26)

**Files:**
- Modify (si hace falta): `app/Http/Controllers/Admin/UsuarioController.php`, `app/Http/Controllers/Admin/PermisoController.php`, `app/Http/Requests/Admin/StoreUsuarioRequest.php`
- Test: `tests/Feature/Admin/GestionUsuariosTest.php`

| Escenario | Aserciones |
|---|---|
| 16.1 / 26.2 | Crear usuario con rol asesor/cliente/administrador → puede iniciar sesión y llega a su panel |
| 16.2 / 26.3 | Desactivar → no puede iniciar sesión (mensaje de 5.5); sus reservas/citas siguen en BD |
| 26.1 | Cambiar rol → la siguiente petición ya usa los permisos nuevos (ruta de asesor pasa de 403 a 200) |
| 26.4 | Vista de roles y permisos lista lo que hay en la tabla `permiso` por rol |

- [ ] Ciclo de la Fase B para cada fila.

---

## Fase C — Despliegue y verificación final

### Task 21: Publicación en InfinityFree y prueba de humo

Pasos manuales del usuario (credenciales del panel) guiados por el ejecutor.

- [ ] **Step 1:** Suite completa local en verde y `vendor/bin/pint --test`.
- [ ] **Step 2:** En el panel de InfinityFree: activar SSL gratuito para el dominio y esperar a que esté emitido; crear la BD MySQL y anotar host, nombre, usuario y contraseña.
- [ ] **Step 3:** Importar `build-infinityfree/bd.sql` en phpMyAdmin. Expected: sin errores.
- [ ] **Step 4:** Crear `build-infinityfree/htdocs/.env` desde `env.produccion.example` con datos reales y `APP_KEY` nuevo; subir **todo el contenido** de `build-infinityfree/htdocs/` a `htdocs/` por FTP (FileZilla, modo binario, mostrar archivos ocultos para que suban `.env` y `.htaccess`). Borrar el `index2.html` por defecto de InfinityFree si existe.
- [ ] **Step 5: Seguridad** (desde el navegador, no curl: el sistema anti-bots bloquea clientes sin JS): `/.env`, `/storage/logs/laravel.log`, `/vendor/autoload.php`, `/composer.json`, `/storage/app/private/` → 403/404; una ruta inexistente muestra la página 404 propia sin traza (APP_DEBUG=false).
- [ ] **Step 6: Humo funcional por rol** — marcar en `docs/hu/matriz-conformidad.md` (columna «Producción»):
  - Invitado: catálogo (1.1), filtros (2.2), ficha inexistente (2.5), registro (3.1, y correo 3.4 recibido o en log), recuperar contraseña (24.1).
  - Cliente: reservar (7.1), pagar con tarjeta de prueba `4242 4242 4242 4242` (23.1) y con `4000 0027 6000 3184` (3DS, 23.1 por la vía 3DS), rechazo con `4000 0000 0000 0002` (23.2), favorito (18.1), agendar cita (27.1), mensaje al asesor (13.2), foto de perfil (25.4), esperar 16 min y comprobar `Su sesión ha expirado` (5.4).
  - Asesor: ver citas (11.1), registrar observación (12.1), registrar venta y subir escritura (14.1-14.2).
  - Administrador: crear inmueble con 2 fotos y verlas en la ficha pública (8.1, prueba del rewrite de `storage`), asignar cita (10.1), crear contrato y subir PDF (17.1-17.2), descargarlo como cliente (19.3), reportes y exportaciones PDF/Excel (6.4, 21.4), enviar notificación (22.1).
  - Ficha abierta en una pestaña mientras otra cambia el estado → aviso en ≤ 30 s (9.3).
  - Dejar el sitio sin tráfico > 15 min con una reserva vencida y visitar `/` → inmueble liberado (9.2).
- [ ] **Step 7:** Revisar `storage/logs/laravel.log` por FTP: sin errores nuevos.
- [ ] **Step 8:** Completar `docs/despliegue/infinityfree.md` con: URL, procedimiento de actualización (re-ejecutar `empaquetar.ps1`, subir solo lo cambiado, nunca sobrescribir `.env` ni `storage/app`), procedimiento de cambios de esquema (Task 9), limitaciones conocidas (sin webhook, tareas cada ≥ 15 min solo con tráfico, correos a log si SMTP cerrado, enumeración de cuentas por Decisión 1) y cómo revertir.
- [ ] **Step 9: Commit** `docs: despliegue en InfinityFree verificado`
