# Módulo 02: Gestión de Usuarios y Permisos

> **Propósito**: Administración centralizada de cuentas de usuario desde el panel de control, asignación de roles de negocio (`cliente`, `asesor`, `administrador`), activación/desactivación con auditoría de estados, prevención de auto-bloqueo y consulta de la matriz de permisos.

---

## 1. Mapa de Componentes y Trazabilidad

| Capa | Archivo / Recurso | Función y Responsabilidad |
|---|---|---|
| **Rutas Admin** | `routes/web.php` (prefijo `/admin`) | ├── `GET /admin/usuarios` (`admin.usuarios.index`)<br>├── `POST /admin/usuarios` (`admin.usuarios.store`)<br>├── `PATCH /admin/usuarios/{id}/rol` (`admin.usuarios.rol`)<br>├── `PATCH /admin/usuarios/{id}/estado` (`admin.usuarios.estado`)<br>├── `DELETE /admin/usuarios/{id}` (`admin.usuarios.destroy`)<br>└── `GET /admin/permisos` (`admin.permisos.index`) |
| **Controladores** | `app/Http/Controllers/Admin/` | ├── `UsuarioController.php` (CRUD administrativo, toggle de estado y borrado condicional).<br>└── `PermisoController.php` (Visualización de la matriz de permisos por rol). |
| **Form Requests** | `app/Http/Requests/Admin/` | `StoreUsuarioRequest.php` (Valida nombre, email único, teléfono opcional, rol válido en `RolUsuario` y contraseña con regla `PasswordSegura`). |
| **Políticas** | `app/Politicas/UserPolicy.php` | Métodos: `gestionar`, `cambiarRol`, `cambiarEstado`, `delete`. Protege contra auto-modificación. |
| **Modelos** | `app/Models/` | ├── `User.php` (Scopes: `deRol`, `activos`; Métodos: `tieneHistorial()`, `estaActivo()`).<br>├── `Role.php` (Catálogo de roles).<br>├── `Permiso.php` (Catálogo de capacidades del sistema).<br>└── `Auditoria.php` (Registro inmutable de cambios de estado). |
| **Enums** | `app/Enumerados/` | `RolUsuario.php` (`administrador`, `asesor`, `cliente`), `EstadoUsuario.php` (`activo`, `inactivo`). |
| **Vistas** | `resources/views/admin/` | ├── `usuarios/index.blade.php` (Listado con filtros, tarjetas de resumen por rol y modales).<br>└── `permisos/index.blade.php` (Matriz tabular de permisos vs roles). |

---

## 2. Esquema de Base de Datos y Relaciones

```sql
-- Tabla rol
CREATE TABLE `rol` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `codigo` VARCHAR(50) NOT NULL UNIQUE, -- 'cliente', 'asesor', 'administrador'
  `nombre` VARCHAR(100) NOT NULL,
  `descripcion` TEXT NULL
);

-- Tabla permiso
CREATE TABLE `permiso` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `codigo` VARCHAR(100) NOT NULL UNIQUE,
  `modulo` VARCHAR(50) NOT NULL,
  `descripcion` VARCHAR(255) NOT NULL
);

-- Tabla pivot rol_permiso
CREATE TABLE `rol_permiso` (
  `id_rol` INT UNSIGNED NOT NULL,
  `id_permiso` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id_rol`, `id_permiso`),
  FOREIGN KEY (`id_rol`) REFERENCES `rol`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`id_permiso`) REFERENCES `permiso`(`id`) ON DELETE CASCADE
);
```

---

## 3. Flujos de Trazabilidad Detallados

### 3.1 Creación de Usuarios desde el Panel de Administración (HU-16)

```
[Administrador] POST /admin/usuarios
   │
   ▼
[Middleware: auth + rol:administrador]
   │
   ▼
[StoreUsuarioRequest]
   ├── Valida: nombre, email (unique:usuario), rol (in:cliente,asesor,administrador),
   │           telefono, contrasena (PasswordSegura)
   │
   ▼
[UsuarioController::store()]
   ├── User::create($request->validated()) -> Se crea con estado EstadoUsuario::Activo
   ├── Se hashea la contraseña automáticamente vía mutador/cast 'hashed'
   └── Redirige a admin.usuarios.index con mensaje: "Usuario «X» creado con rol Y"
```

### 3.2 Cambio de Estado (Activar / Desactivar) con Auditoría (HU-26.3)

```
[Administrador] PATCH /admin/usuarios/{id}/estado
   │
   ▼
[UserPolicy::cambiarEstado($admin, $usuarioObjetivo)]
   ├── Valida: $admin->esAdministrador()
   └── Valida: !$usuarioObjetivo->esElMismoQue($admin) ──► (HTTP 403 si intenta auto-desactivarse)
   │
   ▼
[UsuarioController::cambiarEstado()]
   ├── $nuevoEstado = $usuario->estado->opuesto() (Activo <-> Inactivo)
   ├── DB::transaction():
   │     ├── $usuario->update([
   │     │      'estado' => $nuevoEstado,
   │     │      'desactivado_en' => $nuevoEstado === Inactivo ? now() : null,
   │     │      'desactivado_por' => $nuevoEstado === Inactivo ? $admin->id : null,
   │     │   ])
   │     └── Auditoria::registrar(
   │           modulo: 'usuario',
   │           idRegistro: $usuario->id,
   │           accion: 'cambiar_estado',
   │           detalles: "La cuenta de {$usuario->email} pasó a estado {$nuevoEstado->etiqueta()}"
   │         )
   └── Retorna flash alert de confirmación
```

### 3.3 Eliminación Protegida vs Desactivación Obligatoria

```
[Administrador] DELETE /admin/usuarios/{id}
   │
   ▼
[UserPolicy::delete()] ──► Verifica que no sea el mismo administrador
   │
   ▼
[UsuarioController::destroy()]
   ├── Comprobación de integridad: $usuario->tieneHistorial()
   │     ├── ¿Tiene registros en 'reserva', 'contrato', 'venta' o 'cita'?
   │     ├── SI: Aborta eliminación con flash error:
   │     │       "No se puede eliminar: el usuario tiene historial asociado. Desactívalo en su lugar."
   │     └── NO: $usuario->delete() físico y redirección exitosa
```

---

## 4. Reglas de Negocio y Seguridad Críticas

1. **Invariante de Auto-Preservación del Administrador**:
   - `UserPolicy` bloquea terminantemente cualquier intento de un administrador de degradar su propio rol, desactivar su estado o eliminar su usuario. Esto garantiza que el panel de administración jamás quede sin acceso.
2. **Preservación de Trazabilidad Transaccional (`tieneHistorial()`)**:
   - Si un usuario ya ha generado contratos, pagos, reservas o citas, el sistema prohíbe el borrado de clave primaria para no violar restricciones de clave foránea ni destruir registros contables/legales históricos. La solución exigida es la desactivación (`EstadoUsuario::Inactivo`).
3. **Métricas en Vivo**:
   - El método `index()` calcula agregaciones en tiempo real mediante `User::selectRaw('rol, COUNT(*) as total')->groupBy('rol')` para alimentar las tarjetas informativas superiores del panel.
