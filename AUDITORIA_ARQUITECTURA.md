# Auditoría arquitectónica — Inmobiliaria Laravel

**Fecha:** 2026-08-14
**Alcance:** `app/`, `routes/`, `resources/views/`, `resources/js/`, `database/`, `bootstrap/app.php`, `tests/`.

## Resumen ejecutivo

Se realizó una auditoría completa de responsabilidades por capa (Model / Controller / Form Request / Service / Policy / Middleware / View / rutas), buscando específicamente lógica de Controller disfrazada dentro de los Models, tal como pedía el encargo.

**Hallazgo principal:** el proyecto ya tenía, antes de esta auditoría, una arquitectura deliberada y consistente:

- Controllers delgados que delegan en `app/Servicios/*` (Services) y usan `App\Http\Requests\*` para validar y autorizar.
- Autorización centralizada en `app/Politicas/*` (Policies, namespace en español, registradas explícitamente en `AppServiceProvider`), nunca dentro de Models.
- Middleware dedicado para rol (`EnsureUserHasRole`) y estado de cuenta (`EnsureUserIsActive`), aplicado desde rutas/`bootstrap/app.php`, no desde los controllers.
- Rutas (`routes/web.php`) sin closures con lógica: solo declaración y agrupamiento por middleware.

Sobre esa base, se encontraron **3 violaciones concretas** de "Model haciendo de Controller" (acceso a `Illuminate\Support\Facades\Request`, `auth()` y `route()` directamente desde Models), que fueron corregidas. No se encontraron Controllers gigantes, validaciones duplicadas, problemas de autorización, N+1 no controlados, ni código muerto.

**Estado inicial:** arquitectura ya sólida, con 3 fugas puntuales de responsabilidad HTTP hacia el Model.
**Estado final:** las 3 fugas corregidas; separación de capas confirmada por segunda pasada (grep de patrones HTTP en `app/Models` → 0 coincidencias) y por la suite de tests completa en verde.

---

## Problemas encontrados y solución aplicada

### 1. `App\Models\Auditoria::registrar()` — acceso a `auth()` y `Request` desde el Model

- **Archivo:** [app/Models/Auditoria.php](app/Models/Auditoria.php)
- **Línea aproximada:** 31–41 (antes de corregir)
- **Problema:** el método estático `registrar()` llamaba `auth()->id()` y `Illuminate\Support\Facades\Request::ip()` directamente dentro del Model para completar `usuario_id` e `ip_origen`.
- **Por qué es mala práctica:** el Model queda acoplado al ciclo de vida HTTP (sesión autenticada, request actual). Es imposible instanciar/testear el registro de auditoría sin simular un request/usuario autenticado global, y el Model deja de representar solo datos y reglas de la entidad.
- **Impacto:** bajo en funcionalidad (el dato capturado era correcto), pero es exactamente el antipatrón que el encargo pedía eliminar: "Auth dentro de Model", "Request dentro de Model".
- **Solución aplicada:** `registrar()` ahora recibe `?int $usuarioId` y `?string $ip` como parámetros explícitos. El único call site (`UsuarioController::cambiarEstado()`) los resuelve desde el `Request` de la petición (`$request->user()->id`, `$request->ip()`) y se los pasa. El Model quedó puro.

### 2. `App\Models\HistorialReserva::registrar()` — acceso a `Request` desde el Model

- **Archivo:** [app/Models/HistorialReserva.php](app/Models/HistorialReserva.php)
- **Línea aproximada:** 47–63 (antes de corregir)
- **Problema:** igual que el caso anterior, el método estático llamaba `Illuminate\Support\Facades\Request::ip()` directamente.
- **Por qué es mala práctica:** el mismo acoplamiento a HTTP dentro del Model. Además, este método se invoca desde `app/Servicios/ReservaService.php`, `PagoService.php` y `StripeCardService.php` — Services que a su vez son llamados tanto desde Controllers (con request real) como desde Console Commands (`ExpirarReservas`) sin ningún request de usuario. El Model no tenía forma de saber cuál era el origen legítimo del dato.
- **Impacto:** bajo (en consola, la IP capturada ya era irrelevante), pero mismo antipatrón arquitectónico.
- **Solución aplicada:** `registrar()` ahora acepta `?string $ip = null`. Los 5 call sites en los tres Services pasan `request()?->ip()` explícitamente — es decir, la resolución de IP se movió de la capa de dominio (Model) a la capa de orquestación (Service), que es donde corresponde tocar infraestructura/contexto de petición. Se verificó con `php artisan tinker` que `request()->ip()` sigue resolviendo el mismo valor sintético en contexto de consola que antes resolvía el facade `Request::ip()`, por lo que el comportamiento observable no cambió (sin regresión).

### 3. `App\Models\Notificacion::getEnlaceAttribute()` — resolución de rutas (`route()`) dentro del Model

- **Archivo:** [app/Models/Notificacion.php](app/Models/Notificacion.php)
- **Línea aproximada:** 58–74 (antes de corregir)
- **Problema:** un accessor mapeaba `referencia_tipo` (string de dominio) a un nombre de ruta (`reservas.show`, `admin.contratos.show`, etc.) y llamaba `route()` para construir la URL a mostrar.
- **Por qué es mala práctica:** el Model terminaba conociendo la estructura de rutas de la aplicación — un cambio de nombre de ruta rompería el Model, no la View. Es lógica de navegación/presentación, no de dominio.
- **Impacto:** bajo, pero es uno de los patrones que el encargo pide buscar explícitamente ("route dentro de Model").
- **Solución aplicada:** se eliminó el accessor. El único consumidor, [resources/views/notificaciones/index.blade.php](resources/views/notificaciones/index.blade.php), ahora resuelve la ruta con un `@php` local (mismo `match` que antes, pero en la capa de presentación) usando los atributos crudos `referencia_tipo` / `referencia_id` que el Model ya exponía. No se creó ninguna clase nueva: es el único uso en todo el proyecto, así que una capa de "presenter" habría sido sobre-ingeniería.

---

## Models revisados (19/19) — sin otros hallazgos

`Auditoria`, `Cita`, `CitaHistorial`, `ConfigFranjaCita`, `Contrato`, `Conversacion`, `HistorialReserva`, `ImagenInmueble`, `Inmueble`, `Mensaje`, `MetodoPagoGuardado`, `Notificacion`, `ObservacionCita`, `Pago`, `Permiso`, `Reserva`, `Role`, `User`, `Venta`, `WebhookEvento`.

El resto de los Models ya seguía el criterio correcto: relaciones, casts, scopes de consulta reutilizables (`scopeFiltrar`, `scopeDisponibles`, `scopeVencidas`...), y métodos de dominio propios de la entidad (`Inmueble::estadoCalculado()`, `Reserva::admiteNuevoPago()`, `User::tieneHistorial()`, etc.). Estos métodos de dominio **no** se tocaron porque expresan reglas de negocio genuinamente propias de la entidad (p. ej. "un inmueble está ocupado si tiene un contrato vigente o una venta cerrada"), no lógica de Controller.

Un caso límite evaluado y descartado como violación: `Inmueble::motivoEstadoNoAdmitido()` devuelve un string explicando por qué un cambio de estado no es válido. Se decidió **no** moverlo: es la descripción de una regla de dominio (similar a los métodos `etiqueta()` de los enums, patrón ya establecido en todo el proyecto), no un mensaje flash ni una respuesta HTTP.

---

## Controllers revisados (28/28) — sin hallazgos que corregir

Se leyó el código completo de los 28 controllers (`app/Http/Controllers` y subcarpetas `Admin/`, `Asesor/`, `Auth/`). Todos:

- Reciben la petición, delegan en un Service o en el propio Eloquent, y devuelven una respuesta — sin lógica de negocio de varios pasos.
- Usan `$this->authorize()` contra las Policies en vez de condicionales manuales de rol.
- Usan Form Requests para validación no trivial.

Se detectaron **~11 usos de `$request->validate([...])` inline** (p. ej. `UsuarioController::cambiarRol()`, `MensajeController::iniciar()`, confirmaciones de "motivo" al cancelar/rescindir). Se evaluó extraerlos a Form Requests dedicados y se descartó: son validaciones de 1–2 campos, sin autorización propia más allá de la ya aplicada por Policy/middleware, y el propio encargo pide explícitamente no crear capas «solo por cumplir una regla» (punto 15). Convertir cada uno en una clase de Form Request habría sido ceremonia sin beneficio real.

`Admin\InmuebleController` envuelve `create`/`update`/`destroy` en `DB::transaction()` directamente en el controller (en vez de un `InmuebleService` dedicado). Se evaluó y se dejó así: la transacción envuelve únicamente `Inmueble::create/update` + una llamada a `ImagenInmuebleService` (que ya existe y concentra el manejo de archivos); no hay orquestación de múltiples entidades de negocio que justifique un Service nuevo.

---

## Form Requests, Policies, Middleware — sin hallazgos

- Los 21 Form Requests concentran reglas de validación, mensajes y, cuando corresponde, autorización (`authorize()` contra Policies) y normalización de datos (`prepareForValidation()`). Ninguno persiste datos ni redirige.
- Las 7 Policies (`app/Politicas`) solo deciden permisos; están registradas explícitamente en `AppServiceProvider::boot()` porque el namespace no sigue la convención de autodescubrimiento de Laravel — se verificó que el registro existe y es correcto.
- Los 2 Middleware (`EnsureUserHasRole`, `EnsureUserIsActive`) hacen exactamente lo que su nombre indica y nada más.
- `App\Reglas\PasswordSegura` centraliza la política de contraseñas en un único lugar reutilizado por registro, cambio autenticado y recuperación.

---

## Services (`app/Servicios`) — sin hallazgos que corregir

Se revisaron los 20 Services (incluyendo el paquete `Reportes/`). Cada uno tiene una responsabilidad concreta y coherente con su nombre (`ReservaService`, `PagoService`, `CitaService`, `ContratoService`, `VentaService`, `MensajeService`, `NotificacionService`, `ImagenInmuebleService`, `AvatarService`, `ArchivoPrivadoService`, `StripeCardService`, `FabricaReportes` + 7 clases de reporte). Las transacciones (`DB::transaction`) envuelven exactamente las operaciones que deben ser atómicas; el acceso a `Storage`, `Mail`, Stripe y generación de archivos vive aquí, no en Controllers ni Models.

`FiltroReporte::desdePeticion(Request $peticion)` acepta un `Request` de Laravel — se evaluó y se considera correcto: es una clase explícitamente diseñada como traductor HTTP → DTO de filtro, un patrón aceptado; no es un Model.

---

## Rutas, Views y frontend — sin hallazgos relevantes

- `routes/web.php` y `routes/auth.php`: solo declaración de rutas agrupadas por middleware (`auth`, `rol:...`); ningún closure contiene lógica de negocio ni consultas.
- `bootstrap/app.php`: configuración de middleware limpia y documentada.
- Se revisaron las Views Blade en busca de consultas Eloquent directas (`::where`, `::query`, `::create`, `DB::`) — no se encontró ninguna. La única lógica de presentación no trivial detectada (agrupar notificaciones por antigüedad, resolver el enlace de una notificación) es apropiada para la capa de vista.
- `resources/js/*`: revisado en busca de credenciales o llamadas que dupliquen lógica de negocio del backend — no se encontró nada (el único "secreto" visible es el `client_secret` de Stripe, que es público por diseño de Stripe.js).

---

## Base de datos / Eloquent

- Relaciones, `$fillable`, `casts()` (incluyendo enums PHP nativos) están completas y consistentes en los 19 Models.
- Los listados con N a N+1 potencial (`admin.inmuebles.index`, `admin.citas.index`, etc.) usan `->with(...)` explícito; se documentan inline los motivos ("sin el eager load, pintar esos N modales dispararía una consulta de imágenes por inmueble").
- No se encontraron consultas complejas dentro de Controllers o Models que ameriten un Repository: Eloquent + scopes ya resuelve el caso de uso, y el encargo pide explícitamente no introducir Repository Pattern sin necesidad real.

---

## Código duplicado y código muerto

- No se encontró código muerto: se contrastó cada Policy, Service y clase de bajo uso aparente (`AvatarService`, `MensajeService`, las 7 clases de `Reportes/`) contra sus puntos de uso reales (incluyendo el registro explícito de Policies en `AppServiceProvider`, que no es descubrible por grep simple de "uso implícito").
- No se encontraron bloques de código comentado, `dd()`/`dump()`/`var_dump()` olvidados, ni TODOs pendientes en `app/`.
- No se detectó duplicación de lógica que justifique una nueva abstracción.

---

## Fuera de alcance (no tocado)

El repositorio tenía, antes de empezar esta auditoría, cambios sin commitear ajenos a la arquitectura: la migración `2026_07_25_000002_create_inmueble_table.php` con `precio_venta` ampliado a `decimal(15,2)` y una migración nueva `2026_08_13_011554_alter_precio_venta_in_inmueble_table.php` con el mismo cambio. Es trabajo en curso del usuario, no relacionado con la separación de responsabilidades pedida en este encargo, así que se dejó intacto.

---

## Riesgos pendientes / recomendaciones para revisión manual

Ninguno bloqueante. Dos observaciones menores, sin acción tomada porque no son responsabilidad-mal-ubicada sino decisiones de diseño válidas que conviene que el equipo confirme:

1. La migración duplicada mencionada arriba (editar la migración original + crear una migración `alter`) es redundante si la tabla `inmueble` ya se migró en producción con la definición vieja: conviene que el equipo decida cuál de las dos vía conservar.
2. Los ~11 usos de `$request->validate()` inline en Controllers son intencionalmente ligeros (ver sección Controllers); si el equipo prefiere consistencia estricta "toda validación en Form Request" por convención de estilo, son candidatos triviales a extraer, pero no son una mala práctica per se.

---

## Verificación ejecutada

- `php -l` sobre los 7 archivos modificados → sin errores de sintaxis.
- `php artisan route:list` → resuelve sin errores tras los cambios.
- `./vendor/bin/pint --test` sobre los archivos modificados → `passed`.
- `php artisan test` (suite completa, 16 archivos de test) → **119 tests, 287 assertions, 100% en verde**, incluyendo los tests que ejercitan directamente el código modificado (`GestionUsuariosTest` → `Auditoria::registrar`; `ReservaTest`, `PagoTest`, `ContratoTest` → `HistorialReserva::registrar` vía Services y Console Commands; `NotificacionTest` → `Notificacion` sin el accessor `enlace`).
- Verificación puntual con `php artisan tinker`: se confirmó que `request()->ip()` resuelve el mismo valor en contexto de consola (Artisan) que antes resolvía `Illuminate\Support\Facades\Request::ip()` desde dentro del Model — es decir, mover la resolución de IP a los Services no cambia el comportamiento observable en los Console Commands (`ExpirarReservas`, `VencerContratos`).
- Auditoría final (grep de `Request`, `Auth`, `Session`, `redirect`, `response`, `abort`, `back`, `route()`, `request()`, `auth()`, `session()` sobre `app/Models`) → **0 coincidencias**, confirmando que las 3 fugas de responsabilidad HTTP hacia el Model quedaron eliminadas.

---

## Resultado final

El proyecto queda arquitectónicamente consistente con el modelo objetivo (`Middleware → Form Request → Controller → Service → Model/Eloquent → Database`, con `Policy/Gate` para autorización). No se introdujeron capas, interfaces ni patrones nuevos: las 3 correcciones aplicadas consistieron en mover 2 líneas de resolución de contexto HTTP (`auth()->id()`, `Request::ip()`) desde 2 Models hacia sus llamadores (1 Controller, 3 Services), y en trasladar un `match` de 6 líneas de un Model a la única View que lo usaba. La funcionalidad observable no cambió en ningún caso, verificado por la suite de tests completa.
