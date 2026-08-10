# Inmobiliaria García — Laravel 13

Migración del MVP en PHP nativo (`../inmobiliaria2`) a Laravel 13, conservando su
identidad visual y sus reglas de negocio. El alcance son dos módulos:

- **Gestión de usuarios** — registro, autenticación, perfil, recuperación y
  cambio de contraseña, administración de cuentas y roles, matriz de permisos.
- **Gestión de inmuebles** — catálogo público con filtros, ficha de detalle,
  favoritos y CRUD completo con galería de imágenes en el panel.

## Stack

PHP 8.3 · Laravel 13 · MySQL · Blade · Tailwind CSS v4 (plugin nativo de Vite) ·
Laravel Breeze para el andamiaje de autenticación.

## Puesta en marcha

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate:fresh --seed
php artisan storage:link
npm run build
php artisan serve
```

### Usuarios de demostración

Todos con la contraseña `Password1*`.

| Rol | Correo |
|---|---|
| Administrador | `admin@inmobiliaria.test` |
| Asesor | `asesor@inmobiliaria.test` |
| Cliente | `cliente@inmobiliaria.test` |

## Organización

```
app/Enums/            RolUsuario, EstadoUsuario, EstadoInmueble, ModalidadInmueble,
                      TipoInmueble, EstadoReserva, EstadoContrato
app/Models/           User, Role, Permiso, Inmueble, ImagenInmueble, Reserva,
                      Contrato, Auditoria
app/Http/Controllers/ públicos en la raíz, panel bajo Admin/, autenticación bajo Auth/
app/Http/Requests/    una clase por formulario; toda la validación vive aquí
app/Http/Middleware/  EnsureUserHasRole (alias `rol`), EnsureUserIsActive
app/Policies/         InmueblePolicy, UserPolicy
app/Services/         ImagenInmuebleService, AvatarService
app/Support/          Iconos (SVG Lucide inline), RangosPrecio

resources/views/
  layouts/            app (público), panel (con barra lateral), auth
  components/         icon, inmueble-card, estado-badge, flash, password-field…
  inmuebles/          inicio, index, show
  perfil/             edit, favoritos, cambiar-password
  admin/              inmuebles/, usuarios/, permisos/
```

Las vistas se agrupan por rol: todo lo del panel de administración vive bajo
`admin/`. El asesor no tiene pantallas exclusivas dentro de este alcance —los
módulos de mensajes, citas y ventas quedaron fuera—, así que trabaja sobre el
catálogo público y su perfil.

## Reglas de negocio conservadas del prototipo

- **Estado del inmueble derivado** (HU-09): `Inmueble::estadoCalculado()` lo
  deduce de `reserva` y `contrato`, con prioridad Ocupado > Reservado >
  Disponible. Una reserva rechazada sigue bloqueando el inmueble.
  `InmuebleRequest` impide fijar «Reservado» u «Ocupado» a mano sin respaldo.
- La descripción de un inmueble exige un mínimo de 50 caracteres (HU-08.4).
- El código `INM-XXXXXX` lo genera el sistema y no se edita.
- El correo y el documento son inmutables desde el perfil (HU-25).
- Un administrador no puede cambiarse el rol, desactivarse ni eliminarse.
- No se elimina un inmueble con reservas activas ni un usuario con historial.
- Al borrar una imagen se retira también del disco y se promueve otra portada.
- La sesión caduca a los 15 minutos de inactividad (`SESSION_LIFETIME=15`) y
  desactivar una cuenta corta su sesión en la siguiente petición.

## Diferencias deliberadas respecto al prototipo

- Se eliminaron las tablas espejo `cliente` y `asesor`: no aportaban columnas
  propias y duplicaban datos de `usuario`. Las claves foráneas apuntan a `usuario`.
- `modalidad` es un enum real de tres valores (`venta`, `arriendo`, `ambos`). El
  prototipo guardaba «ambos» como «venta» porque su restricción de base de datos
  no lo contemplaba; ese ajuste ya no hace falta.
- El control de intentos de acceso usa el `RateLimiter` de Laravel en lugar de la
  tabla `login_attempts`.

## Pruebas

```bash
php artisan test
```

Cubren autenticación y bloqueo de cuentas, registro, perfil y contraseñas,
autorización del panel, CRUD de inmuebles con imágenes, filtros del catálogo y
el cálculo del estado del inmueble.
