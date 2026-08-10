# Notificaciones

> El "aviso" que todos los demás módulos usan para comunicarle algo a un
> usuario, tanto dentro del sistema (campanita con contador) como por
> correo. No tiene una pantalla de "creación" propia salvo para el
> administrador — el resto del sistema la dispara automáticamente.

## 1. Qué es este módulo

Es un módulo de **infraestructura compartida**: casi ningún otro módulo
crea una fila en `notificacion` directamente — todos pasan por
`NotificacionService`, que centraliza cómo se insertan las notificaciones y
cuándo además se manda un correo. Si buscás "¿de dónde sale esta
notificación que le llegó a un usuario?", la respuesta casi siempre está en
otro módulo (Reservas, Contratos, Citas, Mensajería...), no en este — este
documento explica el mecanismo compartido, no cada mensaje puntual.

## 2. Mapa de archivos

| Pieza | Archivo |
|---|---|
| Modelo | `app/Models/Notificacion.php` |
| Servicio central (lo usan todos los módulos) | `app/Servicios/NotificacionService.php` |
| Controller del usuario (leer/marcar leídas) | `app/Http/Controllers/NotificacionController.php` |
| Controller del admin (enviar manualmente) | `app/Http/Controllers/Admin/NotificacionController.php` |
| Validación del envío manual | `app/Http/Requests/Admin/EnviarNotificacionRequest.php` |
| Correo genérico que acompaña a una notificación | `app/Mail/Aviso.php`, plantilla `resources/views/emails/aviso.blade.php` |
| Vistas | `resources/views/notificaciones/index.blade.php`, `resources/views/admin/notificaciones/create.blade.php` |
| Rutas | `routes/web.php` líneas 79-82 (usuario), 159-160 (admin) |
| Tests | `tests/Feature/NotificacionTest.php` |

## 3. `NotificacionService` — los cuatro métodos que usa todo el sistema

Cualquier otro servicio (`ReservaService`, `ContratoService`, `CitaService`,
`MensajeService`, etc.) que necesite avisarle algo a alguien llama a uno de
estos cuatro métodos — nunca crean el modelo `Notificacion` directamente:

| Método | A quién avisa |
|---|---|
| `paraUsuario($usuario, ...)` | Una persona puntual (ej. "tu reserva fue confirmada") |
| `paraRol($rol, ...)` | Todos los usuarios activos de un rol (ej. todos los asesores) |
| `paraStaff(...)` | Administradores **y** asesores activos, juntos (usado para avisos operativos: "hay una cita nueva por asignar") |
| `paraTodos(...)` | Todos los usuarios activos del sistema, sin importar el rol |

Cada uno tiene un parámetro opcional `conCorreo: true/false` — si es
`true`, además de la fila en `notificacion`, se manda un correo real usando
el `Mailable` genérico `App\Mail\Aviso`. **La decisión de "¿esto merece un
correo o solo un aviso dentro del sistema?" la toma cada módulo que llama
al servicio**, no el servicio en sí — por ejemplo,
`ReservaService::confirmar()` sí manda correo (es un hito importante para
el cliente), pero `CitaService::asignar()` no (es un aviso operativo para
el asesor, no necesita salir de la bandeja del sistema).

**Detalle de rendimiento**: `insertar()` (línea 97) arma todas las filas en
un array de PHP y hace **un solo** `Notificacion::insert($filas)` — el
comentario del código aclara que el prototipo original hacía un `INSERT`
individual por cada usuario dentro de un bucle; acá se cambió a una sola
consulta masiva, importante si `paraTodos()` se usa con muchos usuarios
activos.

**Los correos nunca rompen el flujo que los origina.** Tanto en
`enviarCorreo()` (línea 127) como en cualquier lugar del sistema donde se
manda un correo relacionado con una notificación, el envío va envuelto en
`try/catch` con `report($e)` — si el servidor de correo está caído, la
reserva/contrato/lo que sea ya se guardó igual; el usuario simplemente no
recibe el correo (pero si abre el sistema, va a ver la notificación
in-app).

## 4. Ver y marcar como leídas (del lado del usuario)

`GET /notificaciones` → `NotificacionController::index()` — lista las
últimas 100 notificaciones del usuario logueado. `PATCH /notificaciones/{notificacion}`
→ `marcarLeida()`: antes de tocar nada,
`abort_unless($notificacion->usuario_id === $request->user()->id, 403)` —
esto es lo único que impide que alguien marque como leída una notificación
ajena adivinando el ID en la URL (no hay una Policy dedicada para este
modelo, es un chequeo manual inline — ver
`Documentación/Trazabilidad/Trazabilidad-Sistema.md` §6 sobre esta
estrategia mixta de autorización).

`PATCH /notificaciones/leidas` → `marcarTodas()` marca de una sola vez
todas las `sinLeer()` (scope del modelo) del usuario.

## 5. El enlace "ir al origen" de una notificación

`Notificacion::getEnlaceAttribute()` (línea 59) construye dinámicamente la
URL a la que debería llevar un clic sobre la notificación, según
`referencia_tipo` (guardado como texto libre: `'reserva'`, `'contrato'`,
`'inmueble'`, `'conversacion'`) y `referencia_id`. **Es el único lugar del
sistema donde un nombre de ruta se arma dinámicamente en vez de escribirse
literal** — si en el futuro se renombra alguna de esas 4 rutas
(`reservas.show`, `admin.contratos.show`, `inmuebles.show`,
`mensajes.show`), hay que actualizar este `match()` o los enlaces de
notificaciones antiguas empiezan a fallar silenciosamente (el método
devuelve `null` si el tipo no coincide con ninguno de los 4 casos, y la
vista simplemente no pinta un enlace).

## 6. Envío manual desde el panel (HU-22)

`GET /admin/notificaciones` → `Admin\NotificacionController::create()`
muestra un formulario con tres modos de destino
(`$datos['destino']`: `'usuario'`, `'rol'` o `'todos'`), resuelto con un
`match()` en `store()` que llama al método correspondiente del servicio.
Esta es la **única** pantalla del sistema donde un humano dispara una
notificación directamente, sin que la origine otro flujo de negocio.

## 7. Contador de "sin leer" en la barra de navegación

No vive en este módulo — lo resuelve `User::notificacionesSinLeer()`
(`app/Models/User.php`), consultado desde el layout compartido y también
devuelto junto con el contador de mensajes en
`MensajeController::sinLeer()` (ver
`Documentación/Trazabilidad/Mensajeria.md` §7) — los dos contadores viajan
juntos en la misma respuesta para no hacer dos peticiones separadas al
sondear la barra de navegación.

## 8. Errores comunes al tocar este módulo

| Síntoma | Causa probable | Dónde mirar |
|---|---|---|
| Una notificación no manda correo aunque debería | Revisar que el módulo que la originó haya pasado `conCorreo: true` al llamar a `NotificacionService` | Buscar la llamada específica en el servicio del módulo correspondiente (ej. `ReservaService`, `ContratoService`) |
| El clic en una notificación no lleva a ningún lado | `referencia_tipo` no coincide con ninguno de los 4 casos de `getEnlaceAttribute()`, o `referencia_id` apunta a un registro que ya no existe | `app/Models/Notificacion.php::getEnlaceAttribute()` |
| Un usuario recibe notificaciones que no le corresponden | Revisar qué método del servicio se usó (`paraRol`/`paraStaff`/`paraTodos` envían a grupos completos — confirmar que el grupo elegido es el correcto) | El módulo que originó la notificación, no `NotificacionService` en sí |
| El correo nunca llega aunque `conCorreo: true` esté puesto | El `try/catch` de `enviarCorreo()` silencia fallos de envío — revisar `storage/logs/laravel.log` (ahí va el `report($e)`) y la configuración `MAIL_*` en `.env` | `app/Servicios/NotificacionService.php::enviarCorreo()` |
