# Módulo 01: Autenticación y Seguridad

> **Propósito**: Gestión integral de la identidad, control de sesiones, ciclo de vida de credenciales (registro, inicio de sesión, recuperación y cambio de contraseñas) y validación de seguridad perimetral (roles, estados activos y mitigación de ataques de fuerza bruta).

---

## 1. Mapa de Componentes y Trazabilidad

| Capa | Archivo / Recurso | Función y Responsabilidad |
|---|---|---|
| **Rutas** | `routes/auth.php` (incluido en `routes/web.php`) | Define endpoints públicos bajo `guest` y protegidos bajo `auth`. |
| **Controladores** | `app/Http/Controllers/Auth/` | ├── `RegisteredUserController.php` (Alta pública de clientes).<br>├── `AuthenticatedSessionController.php` (Login, Logout y ruteo según rol).<br>├── `PasswordResetLinkController.php` (Envío de token de recuperación vía correo).<br>├── `NewPasswordController.php` (Reinicio de contraseña con token).<br>└── `PasswordController.php` (Cambio de contraseña para usuario autenticado). |
| **Form Requests** | `app/Http/Requests/Auth/` y `app/Http/Requests/` | ├── `LoginRequest.php` (Rate Limiting por IP+Email, validación y autenticación).<br>├── `RegistroRequest.php` (Unicidad de email, formato y fortaleza de contraseña).<br>└── `CambiarPasswordRequest.php` (Verificación de `current_password`). |
| **Reglas Custom** | `app/Reglas/PasswordSegura.php` | Regla reutilizable que exige: mínimo 8 caracteres, al menos una mayúscula, una minúscula, un número y un símbolo. |
| **Middleware** | `app/Http/Middleware/` | ├── `EnsureUserIsActive.php` (Alias `activo`, aplicado globalmente a `web`).<br>└── `EnsureUserHasRole.php` (Alias `rol:admin,asesor` para control de rutas). |
| **Modelos** | `app/Models/User.php`, `app/Models/Role.php` | Mapeo de tablas `usuario` y `rol` con casts a enums y métodos de soporte. |
| **Enums** | `app/Enumerados/RolUsuario.php`<br>`app/Enumerados/EstadoUsuario.php` | `RolUsuario`: `administrador`, `asesor`, `cliente`.<br>`EstadoUsuario`: `activo`, `inactivo`. |
| **Vistas** | `resources/views/auth/` | `login.blade.php`, `register.blade.php`, `forgot-password.blade.php`, `reset-password.blade.php`, `change-password.blade.php`. |
| **Tests** | `tests/Feature/Auth/` | `AutenticacionTest.php`, `RegistroTest.php`, `RecuperarPasswordTest.php`, `RutasProtegidasTest.php`. |

---

## 2. Esquema de Base de Datos y Mapeo Eloquent

### Tabla `usuario`
```sql
CREATE TABLE `usuario` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `nombre` VARCHAR(150) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `telefono` VARCHAR(30) NULL,
  `contrasena` VARCHAR(255) NOT NULL,
  `rol` VARCHAR(50) NOT NULL, -- FK lógica contra rol.codigo ('cliente', 'asesor', 'administrador')
  `estado` VARCHAR(20) NOT NULL DEFAULT 'activo', -- 'activo' | 'inactivo'
  `foto_perfil` VARCHAR(255) NULL,
  `remember_token` VARCHAR(100) NULL,
  `email_verified_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  INDEX `idx_usuario_email` (`email`),
  INDEX `idx_usuario_rol` (`rol`),
  INDEX `idx_usuario_estado` (`estado`)
);
```

### Particularidades del Modelo `User.php`
* **Columna de Contraseña Personalizada**: En Laravel por defecto se usa `password`. En este sistema se sobreescribe el método para leer la columna en español:
  ```php
  public function getAuthPassword(): string
  {
      return $this->contrasena;
  }
  ```
* **Casts Nativos de Enums**:
  ```php
  protected function casts(): array
  {
      return [
          'rol' => RolUsuario::class,
          'estado' => EstadoUsuario::class,
          'email_verified_at' => 'datetime',
          'contrasena' => 'hashed',
      ];
  }
  ```

---

## 3. Flujos de Trazabilidad Detallados

### 3.1 Inicio de Sesión y Redirección Inteligente por Rol

```
[Navegador] POST /login (email, password, remember)
   │
   ▼
[Middleware: guest] ──► Si ya está autenticado, redirige a su panel correspondiente
   │
   ▼
[LoginRequest::authenticate()]
   ├── Clave de bloqueo: Str::transliterate(Str::lower($email).'|'.$request->ip())
   ├── Verifica RateLimiter::tooManyAttempts (Máx: 5 intentos fallidos)
   │     └── Si se supera: dispara evento Lockout y lanza ValidationException (HTTP 422 con throttle)
   ├── Auth::attempt(['email' => $email, 'contrasena' => $password], $remember)
   │     └── Si falla: RateLimiter::hit() y retorna error genérico (OWASP A07)
   ├── Validación de cuenta activa: Auth::user()->estaActivo()
   │     └── Si está inactivo: Auth::logout() y lanza mensaje: "Tu cuenta está desactivada"
   └── Limpia RateLimiter::clear()
   │
   ▼
[AuthenticatedSessionController::store()]
   ├── $request->session()->regenerate() (Previene Session Fixation Attacks)
   └── redirect()->intended(route($request->user()->rol->rutaInicio()))
         ├── Administrador ──► 'admin.inmuebles.index' (/admin/inmuebles)
         ├── Asesor        ──► 'asesor.ventas.index' (/asesor/ventas)
         └── Cliente       ──► 'inicio' (/)
```

### 3.2 Registro Público de Clientes

```
[Navegador] POST /registro (nombre, email, telefono, password, password_confirmation)
   │
   ▼
[RegistroRequest]
   ├── Valida: nombre (req, string, max:150), email (req, email, unique:usuario),
   │           telefono (nullable, string, max:30), password (confirmed, PasswordSegura)
   │
   ▼
[RegisteredUserController::store()]
   ├── User::create([
   │     'nombre' => $request->nombre,
   │     'email' => $request->email,
   │     'telefono' => $request->telefono,
   │     'contrasena' => Hash::make($request->password),
   │     'rol' => RolUsuario::Cliente,
   │     'estado' => EstadoUsuario::Activo,
   │   ])
   ├── Auth::login($user)
   └── redirect(route('inicio')) con flash de bienvenida
```

### 3.3 Protección en Caliente por Inactividad (`EnsureUserIsActive`)

```
Toda petición HTTP en el grupo 'web'
   │
   ▼
[EnsureUserIsActive Middleware]
   ├── Comprueba: Auth::check() && !Auth::user()->estaActivo()
   │     ├── Invalida la sesión actual en el servidor: $request->session()->invalidate()
   │     ├── Regenera el token CSRF: $request->session()->regenerateToken()
   │     ├── Cierra la autenticación: Auth::guard('web')->logout()
   │     └── Redirige forzosamente a /login con flash error: "Cuenta desactivada"
   └── Si está activo o es invitado, continúa la petición ($next($request))
```

---

## 4. Casos Borde, Excepciones y Seguridad

1. **Ataques de Fuerza Bruta (Brute-Force Mitigation)**:
   - `LoginRequest` utiliza `RateLimiter` con un límite estricto de **5 intentos fallidos**.
   - La clave de rate limiting combina el email en minúsculas con la IP del cliente. Al bloquearse, se calcula el tiempo de espera restante (`RateLimiter::availableIn()`) en segundos y minutos.
2. **Mensajes de Error Neutros**:
   - Siguiendo OWASP A07 (Identification and Authentication Failures), si el correo no existe o la contraseña es incorrecta, el mensaje siempre es `"Estas credenciales no coinciden con nuestros registros."`, evitando la enumeración de usuarios.
3. **Cierre de Sesión Seguro (`destroy`)**:
   - Se ejecuta `Auth::guard('web')->logout()`, seguido obligatoriamente de `$request->session()->invalidate()` y `$request->session()->regenerateToken()` para evitar secuestros de sesión en equipos compartidos.
