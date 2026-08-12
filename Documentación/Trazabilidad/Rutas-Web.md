# Rutas Web (`web.php`)

> Este archivo define el mapa completo de rutas web orientadas al usuario final.
> Todas las peticiones que ingresan a través del navegador (excepto webhooks)
> son procesadas aquí, pasando por validación de sesión, protección CSRF y filtros de rol.

## 1. Qué es este archivo y cómo se estructura

En Laravel, `routes/web.php` es el punto de entrada para las interfaces web. El archivo está estructurado de lo más público a lo más restrictivo, agrupando rutas de forma lógica por niveles de acceso (middlewares) y aplicando prefijos para mantener orden en las URLs.

## 2. Mapa de Grupos Principales

| Grupo / Middleware | Prefijo de URL | Qué maneja |
|---|---|---|
| **Excepciones (Sin CSRF)** | `/stripe` | Webhook de Stripe. Se valida por firma criptográfica, no por sesión. |
| **Públicas** (sin auth) | `/` | El catálogo público de inmuebles y la página de inicio (HU-01 / HU-02). |
| **Autenticados transversales** (`auth`) | (sin prefijo) | Perfil de usuario, favoritos, historial, reservas, citas propias, notificaciones, mensajería y descargas protegidas. |
| **Asesores** (`rol:asesor,administrador`) | `/asesor` | Módulo de registro de ventas, subida de escrituras y gestión de las citas asignadas al asesor. |
| **Administradores** (`rol:administrador`) | `/admin` | Gestión global del sistema (inventario de inmuebles, control de usuarios, revisión de reservas, contratos y reportes generales). |

---

## 3. Referencia rápida de todas las rutas

### 3.1 Webhook de Stripe (línea 42)

| Verbo | URL | Nombre | Controller::método | HU |
|---|---|---|---|---|
| `POST` | `stripe/webhook` | `stripe.webhook` | `StripeWebhookController::recibir` | HU-20.4 / HU-23.1 |

La ruta `stripe/webhook` es especial. Stripe hace peticiones POST allí de forma asíncrona para notificar pagos. Al ser consumida por un servidor externo, no tiene sesión de usuario. Para que funcione en Laravel, se añade una excepción al middleware de protección CSRF (configurado en `bootstrap/app.php`). La firma `Stripe-Signature` del encabezado HTTP es la que garantiza la autenticidad de la petición.

---

### 3.2 Catálogo público (líneas 47-49)

Accesible sin autenticación. Cualquier visitante puede ver el listado e ingresar al detalle de un inmueble.

| Verbo | URL | Nombre | Controller::método | HU |
|---|---|---|---|---|
| `GET` | `/` | `inicio` | `InmuebleController::inicio` | HU-01 |
| `GET` | `inmuebles` | `inmuebles.index` | `InmuebleController::index` | HU-02 |
| `GET` | `inmuebles/{inmueble}` | `inmuebles.show` | `InmuebleController::show` | HU-02 |

---

### 3.3 Área del usuario autenticado (líneas 54-104)

Middleware: `auth`. Cualquier usuario logueado (cliente, asesor o administrador) puede acceder. Dentro de este bloque hay varios subgrupos funcionales:

#### Perfil de usuario (HU-18 / HU-25)

| Verbo | URL | Nombre | Controller::método |
|---|---|---|---|
| `GET` | `perfil` | `perfil.edit` | `PerfilController::edit` |
| `PATCH` | `perfil` | `perfil.update` | `PerfilController::update` |
| `POST` | `perfil/foto` | `perfil.foto.store` | `PerfilController::actualizarFoto` |
| `DELETE` | `perfil/foto` | `perfil.foto.destroy` | `PerfilController::eliminarFoto` |

#### Favoritos (HU-25)

| Verbo | URL | Nombre | Controller::método |
|---|---|---|---|
| `GET` | `favoritos` | `favoritos.index` | `FavoritoController::index` |
| `POST` | `favoritos/{inmueble}` | `favoritos.toggle` | `FavoritoController::toggle` |

El método `toggle` agrega el inmueble a favoritos si no está, o lo quita si ya estaba. Un solo endpoint maneja los dos sentidos de la acción.

#### Reservas del cliente (HU-07 / HU-23)

| Verbo | URL | Nombre | Controller::método |
|---|---|---|---|
| `GET` | `mis-reservas` | `reservas.index` | `ReservaController::index` |
| `POST` | `reservas` | `reservas.store` | `ReservaController::store` |
| `GET` | `reservas/{reserva}` | `reservas.show` | `ReservaController::show` |
| `POST` | `reservas/{reserva}/pago` | `reservas.pago` | `ReservaController::registrarPago` |
| `POST` | `reservas/{reserva}/pagar-con-tarjeta/{tarjeta}` | `reservas.pagar-con-tarjeta` | `ReservaController::pagarConTarjeta` |
| `POST` | `reservas/{reserva}/cancelar` | `reservas.cancelar` | `ReservaController::cancelar` |

Hay **dos rutas de pago distintas** para la misma reserva: `pago` es para registrar un comprobante manual (transferencia bancaria), mientras que `pagar-con-tarjeta/{tarjeta}` ejecuta el cobro automático a través de Stripe usando una tarjeta ya guardada en el perfil.

#### Tarjetas guardadas del cliente (HU-20)

| Verbo | URL | Nombre | Controller::método |
|---|---|---|---|
| `GET` | `perfil/tarjetas` | `perfil.tarjetas.index` | `MetodoPagoController::index` |
| `POST` | `perfil/tarjetas/setup-intent` | `perfil.tarjetas.setup-intent` | `MetodoPagoController::setupIntent` |
| `POST` | `perfil/tarjetas` | `perfil.tarjetas.store` | `MetodoPagoController::store` |
| `DELETE` | `perfil/tarjetas/{tarjeta}` | `perfil.tarjetas.destroy` | `MetodoPagoController::destroy` |

`setup-intent` devuelve un `SetupIntent` de Stripe que el frontend usa para tokenizar la tarjeta de forma segura (los datos de la tarjeta nunca pasan por el servidor propio). Solo después de que Stripe confirma el tokenizado se llama a `store` para guardar el `PaymentMethod` de referencia.

#### Historial del cliente (HU-19)

| Verbo | URL | Nombre | Controller::método |
|---|---|---|---|
| `GET` | `mis-arriendos` | `perfil.arriendos` | `PerfilController::misArriendos` |
| `GET` | `mis-compras` | `perfil.compras` | `PerfilController::misCompras` |

#### Citas de visita del cliente (HU-27)

| Verbo | URL | Nombre | Controller::método |
|---|---|---|---|
| `GET` | `mis-citas` | `citas.index` | `CitaController::index` |
| `POST` | `citas` | `citas.store` | `CitaController::store` |
| `POST` | `citas/{cita}/cancelar` | `citas.cancelar` | `CitaController::cancelar` |
| `GET` | `citas/franjas-disponibles` | `citas.franjas-disponibles` | `CitaController::franjasDisponibles` |

`citas/franjas-disponibles` es la única ruta de tipo "autocompletar" del bloque de cliente. Devuelve JSON con las horas libres para una combinación `inmueble_id + fecha` dada. Ver la documentación completa del flujo en `Citas.md`.

#### Descargas privadas de documentos

| Verbo | URL | Nombre | Controller::método |
|---|---|---|---|
| `GET` | `contratos/{contrato}/descargar` | `contratos.descargar` | `DescargaController::contrato` |
| `GET` | `ventas/{venta}/escritura` | `ventas.escritura` | `DescargaController::escritura` |

Estas rutas no muestran vistas — generan una descarga directa del archivo. Están en el grupo `auth` para asegurar que nadie sin sesión pueda acceder a documentos privados.

#### Notificaciones (HU-15)

| Verbo | URL | Nombre | Controller::método |
|---|---|---|---|
| `GET` | `notificaciones` | `notificaciones.index` | `NotificacionController::index` |
| `PATCH` | `notificaciones/leidas` | `notificaciones.leidas` | `NotificacionController::marcarTodas` |
| `PATCH` | `notificaciones/{notificacion}` | `notificaciones.leida` | `NotificacionController::marcarLeida` |

Hay dos rutas `PATCH` que se diferencian solo por si llevan `{notificacion}` o no: `marcarTodas` actúa sobre todas las notificaciones pendientes del usuario; `marcarLeida` actúa sobre una sola.

#### Mensajería (HU-13)

| Verbo | URL | Nombre | Controller::método |
|---|---|---|---|
| `GET` | `mensajes` | `mensajes.index` | `MensajeController::index` |
| `GET` | `mensajes/sin-leer` | `mensajes.sin-leer` | `MensajeController::sinLeer` |
| `GET` | `mensajes/{conversacion}` | `mensajes.show` | `MensajeController::show` |
| `POST` | `mensajes/{conversacion}` | `mensajes.store` | `MensajeController::store` |
| `GET` | `mensajes/{conversacion}/anteriores` | `mensajes.anteriores` | `MensajeController::anteriores` |
| `GET` | `mensajes/{conversacion}/nuevos` | `mensajes.nuevos` | `MensajeController::nuevos` |
| `POST` | `inmuebles/{inmueble}/contactar` | `mensajes.iniciar` | `MensajeController::iniciar` |

`anteriores` y `nuevos` son rutas de paginación AJAX que cargan mensajes en bloques sin recargar la página. `iniciar` crea (o reutiliza) una conversación entre el cliente y el staff a partir de un inmueble concreto.

---

### 3.4 Panel del asesor (líneas 111-131)

Middleware base: `auth` + prefijo `asesor` + nombre `asesor.`. Internamente tiene **dos subgrupos con roles distintos**:

#### Ventas (HU-14) — `rol:asesor,administrador`

El administrador tiene acceso para supervisar y auditar, aunque registrar la venta y subir la escritura son operaciones que hace el asesor.

| Verbo | URL | Nombre | Controller::método |
|---|---|---|---|
| `GET` | `asesor/ventas` | `asesor.ventas.index` | `VentaController::index` |
| `POST` | `asesor/ventas` | `asesor.ventas.store` | `VentaController::store` |
| `GET` | `asesor/ventas/{venta}` | `asesor.ventas.show` | `VentaController::show` |
| `POST` | `asesor/ventas/{venta}/cerrar` | `asesor.ventas.cerrar` | `VentaController::cerrar` |
| `POST` | `asesor/ventas/{venta}/cancelar` | `asesor.ventas.cancelar` | `VentaController::cancelar` |
| `POST` | `asesor/ventas/{venta}/escritura` | `asesor.ventas.escritura` | `VentaController::subirEscritura` |

#### Citas asignadas al asesor (HU-11 / HU-12) — `rol:asesor`

Los administradores **no** acceden aquí — tienen su propio listado global en `/admin/citas`.

| Verbo | URL | Nombre | Controller::método |
|---|---|---|---|
| `GET` | `asesor/citas` | `asesor.citas.index` | `AsesorCitaController::index` |
| `GET` | `asesor/citas/{cita}` | `asesor.citas.show` | `AsesorCitaController::show` |
| `POST` | `asesor/citas/{cita}/observacion` | `asesor.citas.observacion` | `AsesorCitaController::registrarObservacion` |
| `PATCH` | `asesor/citas/{cita}/observacion` | `asesor.citas.observacion.editar` | `AsesorCitaController::editarObservacion` |

`POST observacion` marca la cita como realizada y guarda el texto. `PATCH observacion` permite corregir el texto **después** sin cambiar el estado de la cita.

---

### 3.5 Panel de administración (líneas 137-185)

Middleware: `auth` + `rol:administrador` + prefijo `admin` + nombre `admin.`.

#### Inmuebles (HU-04)

| Verbo | URL | Nombre | Controller::método |
|---|---|---|---|
| `GET` | `admin/inmuebles` | `admin.inmuebles.index` | `AdminInmuebleController::index` |
| `POST` | `admin/inmuebles` | `admin.inmuebles.store` | `AdminInmuebleController::store` |
| `GET` | `admin/inmuebles/{inmueble}` | `admin.inmuebles.show` | `AdminInmuebleController::show` |
| `PUT/PATCH` | `admin/inmuebles/{inmueble}` | `admin.inmuebles.update` | `AdminInmuebleController::update` |
| `DELETE` | `admin/inmuebles/{inmueble}` | `admin.inmuebles.destroy` | `AdminInmuebleController::destroy` |

Se usa `Route::resource` excluyendo `create` y `edit` (línea 143) porque el alta y la edición se realizan mediante **modales** dentro del propio listado `index`, sin pantallas de formulario separadas.

#### Imágenes de inmuebles

| Verbo | URL | Nombre | Controller::método |
|---|---|---|---|
| `PATCH` | `admin/imagenes/{imagen}/principal` | `admin.imagenes.principal` | `ImagenInmuebleController::principal` |
| `DELETE` | `admin/imagenes/{imagen}` | `admin.imagenes.destroy` | `ImagenInmuebleController::destroy` |

`principal` marca una imagen como la foto de portada del inmueble; solo puede haber una principal por inmueble.

#### Usuarios

| Verbo | URL | Nombre | Controller::método |
|---|---|---|---|
| `GET` | `admin/usuarios` | `admin.usuarios.index` | `UsuarioController::index` |
| `POST` | `admin/usuarios` | `admin.usuarios.store` | `UsuarioController::store` |
| `PATCH` | `admin/usuarios/{usuario}/rol` | `admin.usuarios.rol` | `UsuarioController::cambiarRol` |
| `PATCH` | `admin/usuarios/{usuario}/estado` | `admin.usuarios.estado` | `UsuarioController::cambiarEstado` |
| `DELETE` | `admin/usuarios/{usuario}` | `admin.usuarios.destroy` | `UsuarioController::destroy` |

`cambiarRol` y `cambiarEstado` son dos `PATCH` distintos en lugar de un solo `update` para que cada acción tenga su propia validación y sus propios eventos de auditoría.

#### Permisos

| Verbo | URL | Nombre | Controller::método |
|---|---|---|---|
| `GET` | `admin/permisos` | `admin.permisos.index` | `PermisoController::index` |

Solo lectura — muestra qué rol puede hacer qué en el sistema.

#### Citas de visita — panel admin (HU-10)

| Verbo | URL | Nombre | Controller::método |
|---|---|---|---|
| `GET` | `admin/citas` | `admin.citas.index` | `AdminCitaController::index` |
| `GET` | `admin/citas/{cita}` | `admin.citas.show` | `AdminCitaController::show` |
| `POST` | `admin/citas/{cita}/asignar` | `admin.citas.asignar` | `AdminCitaController::asignar` |

#### Franjas horarias (RF-26.2)

| Verbo | URL | Nombre | Controller::método |
|---|---|---|---|
| `GET` | `admin/franjas` | `admin.franjas.index` | `FranjaController::index` |
| `POST` | `admin/franjas` | `admin.franjas.update` | `FranjaController::update` |

Solo hay `GET` y `POST` — no hay rutas `create`/`edit` separadas porque `update` usa `updateOrCreate`: si el día no tenía configuración, se crea; si ya tenía, se actualiza.

#### Reservas — panel admin (HU-08)

| Verbo | URL | Nombre | Controller::método |
|---|---|---|---|
| `GET` | `admin/reservas` | `admin.reservas.index` | `AdminReservaController::index` |
| `GET` | `admin/reservas/{reserva}` | `admin.reservas.show` | `AdminReservaController::show` |
| `POST` | `admin/reservas/{reserva}/pagos/{pago}` | `admin.reservas.pagos.revisar` | `AdminReservaController::revisarPago` |
| `POST` | `admin/reservas/{reserva}/cancelar` | `admin.reservas.cancelar` | `AdminReservaController::cancelar` |

`revisarPago` recibe dos parámetros de ruta — la reserva y el comprobante de pago concreto — porque una reserva puede tener varios pagos parciales y el admin aprueba o rechaza cada uno de forma individual.

#### Contratos

| Verbo | URL | Nombre | Controller::método |
|---|---|---|---|
| `GET` | `admin/contratos` | `admin.contratos.index` | `ContratoController::index` |
| `GET` | `admin/contratos/nuevo` | `admin.contratos.create` | `ContratoController::create` |
| `POST` | `admin/contratos` | `admin.contratos.store` | `ContratoController::store` |
| `GET` | `admin/contratos/{contrato}` | `admin.contratos.show` | `ContratoController::show` |
| `POST` | `admin/contratos/{contrato}/documento` | `admin.contratos.documento` | `ContratoController::subirDocumento` |
| `POST` | `admin/contratos/{contrato}/rescindir` | `admin.contratos.rescindir` | `ContratoController::rescindir` |

`subirDocumento` guarda el PDF del contrato firmado. `rescindir` cierra el contrato anticipadamente y desencadena las notificaciones correspondientes.

#### Notificaciones — panel admin

| Verbo | URL | Nombre | Controller::método |
|---|---|---|---|
| `GET` | `admin/notificaciones` | `admin.notificaciones.create` | `AdminNotificacionController::create` |
| `POST` | `admin/notificaciones` | `admin.notificaciones.store` | `AdminNotificacionController::store` |

El `GET` muestra el formulario de redacción de una notificación masiva y el `POST` la despacha. Nota el nombre `admin.notificaciones.create` para el `GET` (en vez de `.index`) porque el propósito de esa vista es crear, no listar.

#### Reportes (HU-06 / HU-21)

| Verbo | URL | Nombre | Controller::método |
|---|---|---|---|
| `GET` | `admin/reportes` | `admin.reportes.index` | `ReporteController::index` |
| `GET` | `admin/reportes/{tipo}` | `admin.reportes.show` | `ReporteController::show` |
| `GET` | `admin/reportes/{tipo}/excel` | `admin.reportes.excel` | `ReporteController::excel` |
| `GET` | `admin/reportes/{tipo}/pdf` | `admin.reportes.pdf` | `ReporteController::pdf` |

`{tipo}` se resuelve al enum `TipoReporte`. Cada tipo de reporte tiene tres acciones distintas: vista web, descarga Excel y descarga PDF.

---

### 3.6 Rutas de autenticación (`auth.php`, línea 187)

En la última línea de `web.php` se importa `require __DIR__.'/auth.php'`. Laravel extrae ahí todas las rutas nativas de registro, login, olvido de contraseñas, etc., para mantener `web.php` enfocado estrictamente en la lógica del negocio.

#### Rutas para visitantes no autenticados (`guest`)

| Verbo | URL | Nombre | Controller::método | HU |
|---|---|---|---|---|
| `GET` | `registro` | `register` | `RegisteredUserController::create` | HU-03 |
| `POST` | `registro` | — | `RegisteredUserController::store` | HU-03 |
| `GET` | `login` | `login` | `AuthenticatedSessionController::create` | HU-05 |
| `POST` | `login` | — | `AuthenticatedSessionController::store` | HU-05 |
| `GET` | `olvide-password` | `password.request` | `PasswordResetLinkController::create` | HU-24 |
| `POST` | `olvide-password` | `password.email` | `PasswordResetLinkController::store` | HU-24 |
| `GET` | `reset-password/{token}` | `password.reset` | `NewPasswordController::create` | HU-24 |
| `POST` | `reset-password` | `password.store` | `NewPasswordController::store` | HU-24 |

#### Rutas para usuarios autenticados

| Verbo | URL | Nombre | Controller::método | HU |
|---|---|---|---|---|
| `GET` | `cambiar-password` | `password.edit` | `PasswordController::edit` | HU-25.2 |
| `PUT` | `cambiar-password` | `password.update` | `PasswordController::update` | HU-25.2 |
| `POST` | `logout` | `logout` | `AuthenticatedSessionController::destroy` | — |

---

## 4. Detalles importantes por bloque

### 4.1. Webhook de Stripe (Línea 42)
La ruta `stripe/webhook` es especial. Stripe hace peticiones POST allí de forma asíncrona para notificar pagos. Al ser consumida por un servidor externo, no tiene sesión de usuario. Para que funcione en Laravel, se añade una excepción al middleware de protección CSRF (configurado en `bootstrap/app.php`).

### 4.2. Rutas transversales (Autenticados compartidos)
Hay operaciones idénticas sin importar si eres cliente, asesor o admin. Por ejemplo: ver la mensajería o marcar notificaciones como leídas. Estas rutas (líneas 54-104) están agrupadas solo bajo el middleware `auth`, lo que permite que cualquier usuario logueado acceda.

### 4.3. Accesos combinados en el grupo Asesor
El grupo `/asesor` tiene una particularidad de permisos:
- Las **ventas** tienen middleware `rol:asesor,administrador` (línea 115). Esto porque los asesores registran la venta, pero los administradores deben poder supervisar y auditar el listado.
- Las **citas asignadas** tienen middleware estricto `rol:asesor` (línea 125). Los administradores no acceden a esto aquí, porque ya tienen su propio listado global en `/admin/citas`.

### 4.4. Recursos y Modales (Panel de Administración)
En Laravel es común usar `Route::resource`. Sin embargo, fíjate en la línea 143:
```php
Route::resource('inmuebles', AdminInmuebleController::class)->except(['create', 'edit']);
```
Se excluyen las rutas `create` y `edit` porque el alta y edición de inmuebles se realiza a través de modales dentro de la misma vista de listado (`index`), reduciendo la cantidad de pantallas necesarias en la aplicación.

### 4.5. Rutas de Autenticación
En la última línea del archivo (187) se importa `require __DIR__.'/auth.php';`. Laravel extrae ahí todas las rutas nativas de registro, login, olvido de contraseñas, etc., para mantener el archivo `web.php` enfocado estrictamente en la lógica del negocio.

---

## 5. Errores comunes al tocar este archivo

| Síntoma | Causa probable | Dónde mirar |
|---|---|---|
| Error `403 Forbidden` al acceder a una ruta de admin | La ruta quedó escrita fuera del grupo `Route::middleware(['auth', 'rol:administrador'])` o el usuario de prueba no tiene el rol correspondiente. | Revisar el cierre del `});` del grupo administrador (línea 185). |
| Excepción `Route [nombre.ruta] not defined` | Te olvidaste de encadenar el `->name('...')` o no tomaste en cuenta los prefijos. Los grupos `admin` y `asesor` aplican su nombre a todas las rutas internas de forma automática (`->name('admin.')`). | Definición de la ruta en este archivo o en las llamadas `route()` en Blade. |
| Formulario POST falla con `419 Page Expired` | La ruta está bien, pero en la vista Blade te olvidaste de incluir la directiva `@csrf` dentro de la etiqueta `<form>`. | El archivo de vista (Blade) desde el cual se envía el formulario. |
| El webhook de Stripe devuelve `419` | La ruta no está en la lista de excepciones CSRF de `bootstrap/app.php`. | `bootstrap/app.php`, sección `$middleware->validateCsrfTokens(except: [...])`. |
| Un asesor puede ver las citas de otro asesor | No hay política (`Policy`) que filtre por `asesor_id` en `AsesorCitaController`. | `app/Http/Controllers/Asesor/CitaController.php` y `app/Politicas/CitaPolicy.php`. |
| La ruta `citas/franjas-disponibles` devuelve 404 | En Laravel, las rutas con segmentos literales (`franjas-disponibles`) deben declararse **antes** de las rutas con parámetros (`{cita}`) para que el router no confunda el segmento literal con un ID. Verificar el orden en el bloque de citas del cliente (líneas 82-85). | `routes/web.php` líneas 82-85. |
