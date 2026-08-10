# Autenticación y Sesión

> Registro, inicio de sesión, cierre de sesión, recuperar contraseña
> olvidada, y cambiar la contraseña estando ya logueado. Es la puerta de
> entrada de todo el sistema — casi todos los demás módulos asumen que ya
> pasaste por acá.

## 1. Qué es este módulo

Controla quién puede entrar al sistema y con qué identidad. Está construido
sobre el sistema de autenticación propio de Laravel (no es código hecho a
mano desde cero), con el andamiaje que generó **Laravel Breeze** al principio
del proyecto y luego se adaptó a los nombres de columnas y reglas de este
sistema en particular (por ejemplo, la contraseña se guarda en la columna
`contrasena`, no `password`).

Una particularidad importante: **una sola tabla (`usuario`) cubre los tres
roles** (cliente, asesor, administrador) — no hay tablas separadas por tipo
de usuario. El rol es solo una columna (`usuario.rol`) que apunta a la tabla
`rol`.

## 2. Mapa de archivos

| Pieza | Archivo |
|---|---|
| Modelo | `app/Models/User.php` |
| Modelo de rol | `app/Models/Role.php` |
| Login | `app/Http/Controllers/Auth/AuthenticatedSessionController.php` |
| Registro | `app/Http/Controllers/Auth/RegisteredUserController.php` |
| Recuperar contraseña (solicitar enlace) | `app/Http/Controllers/Auth/PasswordResetLinkController.php` |
| Recuperar contraseña (aplicar la nueva) | `app/Http/Controllers/Auth/NewPasswordController.php` |
| Cambiar contraseña (ya logueado) | `app/Http/Controllers/Auth/PasswordController.php` |
| Validación de login | `app/Http/Requests/Auth/LoginRequest.php` |
| Validación de registro | `app/Http/Requests/RegistroRequest.php` |
| Validación de cambio de contraseña | `app/Http/Requests/CambiarPasswordRequest.php` |
| Regla de contraseña segura (compartida) | `app/Reglas/PasswordSegura.php` |
| Rutas | `routes/auth.php` (archivo aparte, incluido desde `routes/web.php` línea 169) |
| Middleware de sesión desactivada | `app/Http/Middleware/EnsureUserIsActive.php` (global, ver `Documentación/Trazabilidad/Trazabilidad-Sistema.md` §2) |
| Vistas | `resources/views/auth/{login,register,forgot-password,reset-password}.blade.php` |
| Tests | `tests/Feature/Auth/AutenticacionTest.php`, `tests/Feature/Auth/RegistroTest.php` |

## 3. Iniciar sesión — trazabilidad completa

### 3.1 El formulario

`resources/views/auth/login.blade.php` tiene un `<form method="POST" action="{{ route('login') }}">`
con dos campos (`email`, `password`) y un botón para mostrar/ocultar la
contraseña (resuelto genéricamente por `iniciarToggleContrasena()` en
`resources/js/ui.js`, igual que en cualquier otro campo de contraseña del
sitio — no hay JS específico de login).

### 3.2 La ruta y el middleware `guest`

`routes/auth.php` línea 19 registra `POST /login` dentro de un grupo
`Route::middleware('guest')`. Ese middleware (de Laravel, no propio) hace lo
opuesto a `auth`: si la petición viene de alguien que **ya** tiene sesión
iniciada, lo redirige antes de llegar al controller — así una persona
logueada no puede volver a ver el formulario de login por accidente.

### 3.3 El controller y la validación

`AuthenticatedSessionController::store()`
(`app/Http/Controllers/Auth/AuthenticatedSessionController.php`, línea 21)
recibe un `LoginRequest $request` — toda la lógica de verificar la
contraseña vive en esa clase, no en el controller:

```php
public function store(LoginRequest $request): RedirectResponse
{
    $request->authenticate();
    $request->session()->regenerate();

    return redirect()->intended(route($request->user()->rol->rutaInicio()));
}
```

`LoginRequest::authenticate()` (`app/Http/Requests/Auth/LoginRequest.php`,
línea 39) hace, en orden:

1. **`asegurarQueNoEstaBloqueado()`** — antes de intentar nada, revisa si esa
   combinación de correo + IP ya falló 5 veces seguidas
   (`RateLimiter::tooManyAttempts`, usando Laravel `RateLimiter`, no una
   tabla propia — el prototipo PHP original sí usaba una tabla
   `login_attempts`, acá se reemplazó). Si está bloqueado, lanza un error
   con el tiempo de espera restante y ni siquiera llega a comparar la
   contraseña.
2. **`Auth::attempt(...)`** — esto es Laravel puro: busca el usuario por
   `email` y compara el `password` recibido contra el hash guardado. Como el
   modelo `User` sobreescribe `getAuthPassword()` para devolver
   `$this->contrasena` (línea 69 de `User.php`), Laravel sabe comparar
   contra esa columna aunque no se llame `password`. Si falla, se registra
   un intento fallido (`RateLimiter::hit`) y se lanza un error **genérico**
   a propósito (no dice si falló el correo o la contraseña — es una decisión
   de seguridad para no revelarle a un atacante si un correo existe en el
   sistema).
3. **`Auth::user()->estaActivo()`** — aunque la contraseña sea correcta, si
   el administrador desactivó esa cuenta (`User::estaActivo()`, que compara
   `estado === EstadoUsuario::Activo`), se cierra la sesión que se acababa
   de abrir y se informa el motivo explícitamente (a diferencia del error de
   contraseña, acá sí se le dice a la persona qué pasó, porque ya demostró
   ser el dueño de la cuenta).
4. Si todo salió bien, se limpia el contador de intentos fallidos
   (`RateLimiter::clear`).

De vuelta en el controller: `$request->session()->regenerate()` cambia el ID
de sesión (para prevenir *session fixation*), y
`redirect()->intended(route($request->user()->rol->rutaInicio()))` manda a
la persona a la pantalla inicial de su rol —
`RolUsuario::rutaInicio()` (`app/Enumerados/RolUsuario.php`, línea 58)
decide esa ruta: administrador va a `admin.inmuebles.index`, asesor a
`asesor.ventas.index`, cliente al catálogo (`inicio`). `intended()` es una
función de Laravel: si la persona había intentado entrar a una página
protegida sin sesión y por eso terminó en el login, la manda de vuelta ahí
en lugar de a la pantalla por defecto de su rol.

### 3.4 Resumen visual

```
Formulario de login (auth/login.blade.php)
  → POST /login
     → middleware: guest    (routes/auth.php)
     → LoginRequest::authenticate()
         → RateLimiter: ¿bloqueado por intentos fallidos?
         → Auth::attempt()  (compara contra User::getAuthPassword())
         → User::estaActivo()  (¿la cuenta sigue activa?)
     → AuthenticatedSessionController::store()
         → session()->regenerate()
         → redirect()->intended(RolUsuario::rutaInicio())
```

## 4. Registro público (solo crea clientes)

`RegisteredUserController::store()` (línea 26) es deliberadamente limitado:
**siempre** crea el usuario con `'rol' => RolUsuario::Cliente`, sin importar
qué mande el formulario — no hay forma de autorregistrarse como asesor o
administrador; esas cuentas solo las crea un administrador desde el panel
(ver `Documentación/Trazabilidad/Gestion-de-Usuarios-y-Roles.md`).

`RegistroRequest` (`app/Http/Requests/RegistroRequest.php`) valida, entre
otras cosas, que el correo y el número de documento sean únicos en la tabla
(`unique:usuario,email` / `unique:usuario,documento_numero`) y que la
contraseña cumpla `PasswordSegura::reglas()` — la misma regla que comparten
el cambio de contraseña autenticado y la recuperación por enlace, definida
en un único sitio (`app/Reglas/PasswordSegura.php`) para que las tres
pantallas nunca queden con requisitos distintos entre sí.

Tras crear el usuario, el controller:

1. Le envía una notificación de bienvenida **con copia por correo**
   (`NotificacionService::paraUsuario(..., conCorreo: true)` — ver
   `Documentación/Trazabilidad/Notificaciones.md`). El comentario en el
   código aclara que si el envío de correo fallara, **no debe** impedir que
   el registro se complete — es una notificación de cortesía, no un paso
   crítico.
2. `Auth::login($usuario)` — inicia sesión automáticamente, sin pedirle a la
   persona que vuelva a loguearse después de registrarse.

## 5. Cerrar sesión

`AuthenticatedSessionController::destroy()` (línea 29), enlazado a
`POST /logout` (protegido por el middleware `auth`, no `guest` — solo alguien
ya logueado puede cerrar sesión). Hace tres cosas en orden, todas
necesarias para que sea un cierre de sesión real y no solo "olvidar" la
cookie: `Auth::guard('web')->logout()`, `session()->invalidate()` (destruye
los datos de sesión en el servidor) y `session()->regenerateToken()`
(cambia el token CSRF, para que un formulario viejo ya abierto en el
navegador no pueda reusarse tras el logout).

## 6. Recuperar contraseña olvidada (dos pasos, dos controllers)

Este flujo usa el sistema de *password broker* propio de Laravel
(`Illuminate\Support\Facades\Password`), que ya trae la tabla
`password_reset_tokens` y el manejo de expiración del enlace — no es lógica
escrita a mano.

**Paso 1 — pedir el enlace** (`PasswordResetLinkController`):
`GET /olvide-password` muestra el formulario
(`auth/forgot-password.blade.php`); al enviarlo, `Password::sendResetLink()`
genera un token, lo guarda hasheado en `password_reset_tokens`, y despacha
un correo con el enlace (la plantilla del correo y el disparo real del envío
los resuelve Laravel internamente a partir de la notificación
`ResetPassword` del propio framework, no hay un `Mailable` propio para esto
como sí lo hay para otros correos del sistema).

**Paso 2 — usar el enlace** (`NewPasswordController`): el enlace trae el
`token` en la URL. `GET /reset-password/{token}` muestra el formulario de
nueva contraseña; al enviarlo, `Password::reset()` valida el token contra la
tabla, y si es válido llama al *closure* que actualiza
`contrasena` (con `Hash::make`) y **regenera `remember_token`** — esto
invalida cualquier sesión "recuérdame" que hubiera quedado activa en otro
dispositivo con la contraseña vieja.

## 7. Cambiar contraseña estando logueado

Distinto de los dos anteriores: acá la persona ya demostró quién es (tiene
sesión), así que en vez de un token por correo, `CambiarPasswordRequest`
(`app/Http/Requests/CambiarPasswordRequest.php`) exige la contraseña
**actual** como prueba (`'contrasena_actual' => ['required', 'current_password']`
— la regla `current_password` es de Laravel, compara contra el usuario
autenticado automáticamente) y que la nueva sea `different:contrasena_actual`
además de cumplir `PasswordSegura::reglas()`.

## 8. La sesión expira sola por inactividad

`SESSION_LIFETIME=15` en `.env` — a los 15 minutos sin actividad, Laravel
invalida la sesión automáticamente (comportamiento nativo del framework, no
hay temporizador propio). Esto es distinto del cierre de sesión por cuenta
**desactivada**, que resuelve `EnsureUserIsActive` (middleware global, ver
`bootstrap/app.php`) en cada petición comparando `User::estaActivo()` — si
en el futuro alguien reporta "me saca sesión sin razón", primero hay que
distinguir cuál de los dos mecanismos actuó: ¿pasaron 15+ minutos sin usar
el sitio (normal), o el administrador desactivó la cuenta (mensaje
explícito "Tu cuenta está desactivada...")?

## 9. Errores comunes al tocar este módulo

| Síntoma | Causa probable | Dónde mirar |
|---|---|---|
| "Correo o contraseña incorrectos" con credenciales que sí son correctas | Puede ser el bloqueo por intentos fallidos (`RateLimiter`), que da el mismo tipo de error genérico | `LoginRequest::asegurarQueNoEstaBloqueado()` |
| Un usuario nuevo se crea con el rol equivocado | El registro público **siempre** fuerza `RolUsuario::Cliente`; si necesitás otro rol, se crea desde `/admin/usuarios`, no desde `/registro` | `RegisteredUserController::store()` línea 30 |
| El enlace de "olvidé mi contraseña" no llega | Revisar configuración de correo en `.env` (`MAIL_*`) — el envío lo dispara Laravel internamente vía `Password::sendResetLink()`, no hay cola visible que revisar salvo que `QUEUE_CONNECTION` esté en `sync` vs. algo asíncrono | `config/mail.php`, `.env` |
| Cambiar la contraseña no funciona aunque la actual es correcta | Revisar que la nueva sea *distinta* de la actual (`different:contrasena_actual`) y que cumpla los 4 requisitos de `PasswordSegura` | `app/Http/Requests/CambiarPasswordRequest.php` |
| La sesión se cierra sola muy rápido | Es esperado a los 15 minutos de inactividad (`SESSION_LIFETIME`); si pasa antes, revisar si hay JS que esté regenerando cookies o si el reloj del servidor está mal | `.env` `SESSION_LIFETIME` |
