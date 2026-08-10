# Trazabilidad del sistema

> Mapa general de "qué archivo toca a cuál". Objetivo: si algo se rompe (un
> model, un controller, una ruta, una conexión), poder ubicar en segundos
> todo lo relacionado sin releer el código completo. El estado de salud y
> los hallazgos de la auditoría viven en el otro documento:
> [`Documentación/QA/QA-Sistema.md`](../QA/QA-Sistema.md).

Alcance: `inmobiliarialaravel/` (Laravel 13). `inmobiliaria2/` es el
prototipo PHP nativo original — solo referencia histórica de reglas de
negocio (`inmobiliaria2/HU.md`), no está en producción.

**Este documento es el mapa general** (arquitectura, cómo se protege cada
ruta, flujo de una petición, tabla resumen por módulo). Para el
paso-a-paso detallado de un módulo específico —qué modelo, qué controller,
qué método, qué vista, y por qué funciona así— hay un documento aparte por
módulo, pensado para poder arreglar algo sin tener que releer todo el
sistema:

| Módulo | Documento |
|---|---|
| Catálogo público y CRUD de inmuebles (panel admin), imágenes | [Gestion-de-Inmuebles.md](Gestion-de-Inmuebles.md) |
| Login, registro, logout, recuperar/cambiar contraseña | [Autenticacion-y-Sesion.md](Autenticacion-y-Sesion.md) |
| Editar perfil, foto de perfil, favoritos, mis arriendos/compras | [Perfil-y-Favoritos.md](Perfil-y-Favoritos.md) |
| Alta de usuarios, cambiar rol/estado, matriz de permisos | [Gestion-de-Usuarios-y-Roles.md](Gestion-de-Usuarios-y-Roles.md) |
| Reservar, pagar (manual y con Stripe), tarjetas guardadas, webhook | [Reservas-y-Pagos.md](Reservas-y-Pagos.md) |
| Emitir/rescindir contratos de arriendo, subir el PDF firmado | [Contratos.md](Contratos.md) |
| Registrar/cerrar/cancelar ventas, subir la escritura | [Ventas.md](Ventas.md) |
| Agendar visitas, asignar asesor, franjas horarias, observaciones | [Citas.md](Citas.md) |
| Chat cliente-asesor: envío sin recargar, sondeo, adjuntos | [Mensajeria.md](Mensajeria.md) |
| El mecanismo compartido de avisos in-app y por correo | [Notificaciones.md](Notificaciones.md) |
| Reportes de reservas/pagos/contratos/ventas, exportar Excel/PDF | [Reportes.md](Reportes.md) |

---

## 1. Arquitectura real (ojo con los nombres)

```
app/
  Models/         19 modelos Eloquent (tablas en español, singular)
  Http/
    Controllers/  27 controllers. Públicos en raíz, panel bajo Admin/, asesor bajo Asesor/, auth bajo Auth/
    Middleware/   EnsureUserHasRole (alias 'rol'), EnsureUserIsActive (alias 'activo', global)
    Requests/     Form Requests — toda la validación vive aquí, no en los controllers
  Enumerados/     16 enums PHP 8.1+ backed   ← NO se llama "Enums" como en Laravel/README
  Politicas/      7 policies                 ← NO se llama "Policies"
  Servicios/      21 servicios de dominio     ← NO se llama "Services"
  Soporte/        Iconos, RangosPrecio — se llaman por FQCN, sin "use"
  Reglas/         PasswordSegura (validación custom)
  Mail/           Aviso, ComprobantePago
  Console/Commands/  ExpirarReservas, VencerContratos (cron, cada hora)
  Providers/AppServiceProvider.php   ← registra las 7 policies a mano (namespace no estándar)
routes/
  web.php         todas las rutas (público + auth + asesor/ + admin/)
  auth.php        rutas de autenticación (Breeze)
  console.php     scheduler: reservas:expirar y contratos:vencer, cada hora
bootstrap/app.php ← aquí se registran los middleware (no hay Kernel.php, es Laravel 11+)
resources/
  views/          73 archivos Blade. layouts/app.blade.php es el layout raíz (@vite vive solo ahí)
  js/             app.js importa los otros 9 módulos JS — no hay <script> sueltos
```

**Los namespaces reales están en español.** Si buscás `app/Enums`,
`app/Policies`, `app/Services` o `app/Support` (como dice el README) no vas a
encontrar nada — son `Enumerados`, `Politicas`, `Servicios`, `Soporte`.

## 2. Cómo se protege cada ruta (después del refactor de middleware)

```php
// bootstrap/app.php
$middleware->appendToGroup('web', EnsureUserIsActive::class);   // corta sesión de cuentas desactivadas, en TODA ruta web
$middleware->alias(['rol' => EnsureUserHasRole::class]);        // ->middleware('rol:administrador')
```

```php
// routes/web.php
Route::middleware(['auth', 'rol:administrador'])->prefix('admin')-> ...      // todo el panel admin
Route::middleware('rol:asesor,administrador')->group(...)                    // ventas (asesor + admin las supervisa)
Route::middleware('rol:asesor')->group(...)                                  // agenda de citas del asesor
```

La protección por rol vive en la ruta, no en el controller — ver
`tests/Feature/RutasProtegidasTest.php` para el test que lo garantiza.

## 3. Flujo de una petición típica

```
Route (routes/web.php, nombre de ruta)
   │
   ▼
Middleware: auth → activo (global) → rol:xxx (si aplica)
   │
   ▼
Controller (delgado a propósito)
   │
   ├─ Form Request (app/Http/Requests/**)     ← valida entrada
   ├─ $this->authorize(...)                   ← Policy, si el modelo tiene una
   ├─ Servicio de dominio (app/Servicios/**)  ← lógica de negocio real, transacciones
   │      └─ Modelo Eloquent (app/Models/**)  ← relaciones, scopes, casts a Enum
   └─ return view('...', [...]) / redirect()->route('...')
```

Si algo calcula mal un estado o un monto, el bug casi siempre está en el
**Servicio** o en un método del **Modelo**, no en el controller — ahí es
donde vive la lógica real.

## 4. Trazabilidad por módulo

| Módulo (HU) | Modelo(s) | Controller(s) | Rutas (prefijo) | Vistas | Service | Policy | Tests |
|---|---|---|---|---|---|---|---|
| Catálogo público (HU-01/02) | `Inmueble`, `ImagenInmueble` | `InmuebleController` | `/`, `/inmuebles*` | `inmuebles/inicio,index,show` | `ImagenInmuebleService` | `InmueblePolicy` | `CatalogoTest` |
| Auth (HU-03/05/24) | `User`, `Role` | `Auth/*` (5 controllers) | `routes/auth.php` | `auth/*` | — | `UserPolicy` | `Auth/AutenticacionTest`, `Auth/RegistroTest` |
| Perfil (HU-25) | `User` | `PerfilController` | `/perfil*` | `perfil/edit,cambiar-password` | `AvatarService` | `UserPolicy` | `PerfilTest` |
| Favoritos (HU-18) | `Inmueble` (pivot `favorito`) | `FavoritoController` | `/favoritos*` | `perfil/favoritos` | — | `InmueblePolicy` | — |
| Reservas + Pago (HU-07/20/23) | `Reserva`, `Pago`, `MetodoPagoGuardado` | `ReservaController`, `Admin\ReservaController`, `MetodoPagoController`, `StripeWebhookController` | `/mis-reservas*`, `/admin/reservas*`, `/perfil/tarjetas*`, `POST /stripe/webhook` | `reservas/*` | `ReservaService`, `PagoService`, `StripeCardService` | `ReservaPolicy` | `ReservaTest`, `PagoTest` |
| Contratos (HU-17/19) | `Contrato` | `Admin\ContratoController`, `DescargaController` | `/admin/contratos*`, `/contratos/{c}/descargar` | `admin/contratos/*` | `ContratoService`, `ArchivoPrivadoService` | `ContratoPolicy` | `ContratoTest` |
| Ventas (HU-14) | `Venta` | `Asesor\VentaController`, `DescargaController` | `/asesor/ventas*` (rol asesor+admin) | `asesor/ventas/*` | `VentaService` | `VentaPolicy` | `VentaTest` |
| Citas (HU-10/11/12/27) | `Cita`, `CitaHistorial`, `ObservacionCita`, `ConfigFranjaCita` | `CitaController`, `Admin\CitaController`, `Admin\FranjaController`, `Asesor\CitaController` (rol asesor) | `/mis-citas*`, `/admin/citas*`, `/asesor/citas*` | `citas/*`, `admin/citas/*`, `asesor/citas/*` | `CitaService` | `CitaPolicy` | `CitaTest` |
| Mensajería (HU-13) | `Conversacion`, `Mensaje` | `MensajeController` | `/mensajes*` | `mensajes/index,panel` (resueltas dinámicamente) | `MensajeService` | `ConversacionPolicy` | `MensajeTest` |
| Notificaciones (HU-15/22) | `Notificacion` | `NotificacionController`, `Admin\NotificacionController` | `/notificaciones*`, `/admin/notificaciones*` | `notificaciones/index`, `admin/notificaciones/create` | `NotificacionService` | — (check manual) | `NotificacionTest` |
| Usuarios/roles (HU-16/26) | `User`, `Role`, `Permiso` | `Admin\UsuarioController`, `Admin\PermisoController` | `/admin/usuarios*`, `/admin/permisos*` | `admin/usuarios/*`, `admin/permisos/index` | — | `UserPolicy` | `Admin/GestionUsuariosTest` |
| Auditoría | `Auditoria` | (interno, `Auditoria::registrar()`) | — | — | — | — | — |
| Reportes (HU-06/21) | agregaciones | `Admin\ReporteController` | `/admin/reportes*` | `admin/reportes/*` | `Servicios/Reportes/*` | — | `ReporteTest` |
| Webhooks Stripe | `WebhookEvento`, `Pago` | `StripeWebhookController` | `POST /stripe/webhook` (sin CSRF, sin sesión) | — | `PagoService` | — | (en `PagoTest`) |

## 5. "¿Qué se rompe si...?"

| Si vas a... | Revisa/actualiza también... |
|---|---|
| Renombrar un **Modelo** | Su import en cada Controller/Service/Request; `Gate::policy(...)` en `AppServiceProvider.php` si tiene policy; factories en `database/factories/`; el route model binding implícito en `routes/web.php` (`{inmueble}` asume resolver al modelo `Inmueble` por nombre) |
| Renombrar una **tabla/columna** ya migrada | `$fillable`/`casts()` del modelo; el prototipo PHP usaba vistas SQL (`v_historial_ventas`, etc.) que la versión Laravel NO recreó — se resuelve con scopes Eloquent en su lugar |
| Cambiar la **conexión de BD** (`.env`) | `config/database.php` — el default es `sqlite` si `DB_CONNECTION` no está seteado; confirmar con `php artisan tinker` → `DB::connection()->getPdo()` |
| Mover/renombrar la **carpeta del proyecto** | `public/storage` es un symlink hacia `storage/app/public` con la ruta absoluta grabada al crearlo — si la carpeta del proyecto se mueve o renombra, el symlink queda apuntando a la ruta vieja y toda imagen subida (inmuebles, avatares) empieza a dar 404 en el navegador sin ningún error en logs. Recrear con `php artisan storage:link` (borrando antes el enlace roto) |
| Mover/renombrar una **vista Blade** | El literal `view('carpeta.archivo')` en el Controller, o en Mailable/Servicio si es correo o PDF (`ExportadorPdf`, `Mail/Aviso`) |
| Renombrar el **nombre de una ruta** | Todo `route('nombre.viejo')` en Blade y Controllers; `App\Models\Notificacion` construye nombres de ruta dinámicamente según `referencia_tipo` — es el único sitio no obvio |
| Cambiar un **Enum** (agregar/quitar `case`) | `match()`/`switch` exhaustivo en Servicios y vistas (`estado-badge.blade.php`); columnas `ENUM` a nivel MySQL si las hay; `Rule::enum(...)` en Form Requests |
| Agregar una ruta nueva bajo `/admin` o `/asesor` | Agregar `->middleware('rol:...')` — si se olvida, `tests/Feature/RutasProtegidasTest.php` falla y avisa |
| Agregar un **ícono** que no existe | Agregarlo a `app/Soporte/Iconos.php` (constante `TRAZOS`, formato Lucide `viewBox 0 0 24 24`) — no se resuelve solo, y en local/testing revienta si el nombre no está |
| Cambiar credenciales de **Stripe** | `config/services.php` lee de `.env`; si cambia `STRIPE_WEBHOOK_SECRET` sin actualizar el dashboard de Stripe, el webhook empieza a rechazar todo con 400 |
| Editar el **scheduler** (`routes/console.php`) | Confirmar que el cron real del servidor llama `php artisan schedule:run` cada minuto — si no, los estados de reservas/contratos quedan desactualizados sin ningún error visible |

## 6. Dos estrategias de autorización de datos (no de rol)

Distinto del punto 2 (que es *quién puede entrar a la ruta*): esto es *quién
puede tocar este registro puntual*.

- **Con Policy** (registradas a mano en `AppServiceProvider::boot()`):
  `Cita`, `Contrato`, `Conversacion`, `Inmueble`, `Reserva`, `User`, `Venta`.
- **Con `abort_unless(...)` inline** en el controller: `Notificacion`,
  `MetodoPagoGuardado`, `Pago`, y el resto de modelos administrativos.

Si agregás un endpoint nuevo sobre un modelo del segundo grupo, no hay una
policy que te obligue a pensar en el ownership — hay que poner el
`abort_unless` a mano, como en `NotificacionController.php:22`.
