# Módulo 10: Notificaciones

> **Propósito**: Subsistema transversal de comunicaciones y alertas reactivas (notificaciones in-app en tiempo real con contador en navbar y despacho de correos electrónicos transaccionales con plantillas Mailable `App\Mail\Aviso`), emisión masiva/segmentada por roles y resolución dinámica de rutas.

---

## 1. Mapa de Componentes y Trazabilidad

| Capa | Archivo / Recurso | Función y Responsabilidad |
|---|---|---|
| **Rutas Usuario** | `routes/web.php` (grupo `auth`) | ├── `GET /notificaciones` (`notificaciones.index` - Bandeja de alertas).<br>├── `PATCH /notificaciones/leidas` (`notificaciones.leidas` - Marcar todo leído).<br>└── `PATCH /notificaciones/{id}` (`notificaciones.leida` - Marcar individual). |
| **Rutas Admin** | `routes/web.php` (`/admin`) | ├── `GET /admin/notificaciones` (`admin.notificaciones.create` - Formulario de emisión).<br>└── `POST /admin/notificaciones` (`admin.notificaciones.store` - Despacho masivo/segmentado). |
| **Controladores** | `app/Http/Controllers/` | ├── `NotificacionController.php` (Bandeja del usuario, lectura y conteo).<br>└── `Admin/NotificacionController.php` (Emisión por rol, a todos o a usuario puntual). |
| **Form Requests** | `app/Http/Requests/Admin/` | `EnviarNotificacionRequest.php` (Valida título, cuerpo, tipo, destinatarios y flag de correo). |
| **Servicios** | `app/Servicios/` | `NotificacionService.php` (Inserción atómica por lotes `insert()`, control de tolerancia a fallos de SMTP con `try-catch`, y métodos: `paraUsuario`, `paraRol`, `paraStaff`, `paraTodos`). |
| **Mailable** | `app/Mail/Aviso.php` | Clase Mailable de Laravel con vista HTML enriquecida, asunto parametrizable y botón de acción directa. |
| **Modelos & Tablas** | `app/Models/` | `Notificacion.php` (`notificacion`, campos: `id_usuario`, `titulo`, `mensaje`, `tipo`, `referencia_tipo`, `referencia_id`, `leido_at`; método: `obtenerUrl()`). |
| **Enums** | `app/Enumerados/` | `TipoNotificacion.php` (`info`, `exito`, `aviso`, `error`, `sistema`). |
| **Vistas** | `resources/views/` | ├── `notificaciones/index.blade.php` (Bandeja con filtros: todas / no leídas).<br>├── `admin/notificaciones/create.blade.php` (Panel de emisión masiva).<br>└── `emails/aviso.blade.php` (Plantilla de correo transaccional). |

---

## 2. Esquema de Base de Datos y Relaciones

```sql
-- Tabla notificacion
CREATE TABLE `notificacion` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `usuario_id` BIGINT UNSIGNED NOT NULL,
  `titulo` VARCHAR(150) NOT NULL,
  `mensaje` TEXT NOT NULL,
  `tipo` VARCHAR(30) NOT NULL DEFAULT 'info', -- 'info', 'exito', 'aviso', 'error', 'sistema'
  `referencia_tipo` VARCHAR(50) NULL, -- 'reserva', 'contrato', 'cita', 'inmueble', 'conversacion'
  `referencia_id` BIGINT UNSIGNED NULL,
  `leido_en` TIMESTAMP NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`usuario_id`) REFERENCES `usuario`(`id`) ON DELETE CASCADE,
  INDEX `idx_notificacion_usuario_leido` (`usuario_id`, `leido_en`),
  INDEX `idx_notificacion_fecha` (`created_at`)
);
```

---

## 3. Flujos de Trazabilidad Detallados

### 3.1 Emisión Transversal y Tolerancia a Fallos (`NotificacionService`)

```
Ocurre un evento en cualquier servicio (Ej: ReservaService::confirmar, CitaService::asignar)
   │
   ▼
[NotificacionService::paraUsuario($usuario, $titulo, $mensaje, $tipo, $refTipo, $refId, conCorreo: true)]
   ├── Inserción en Base de Datos:
   │     └── Notificacion::insert([...]) (Inserción optimizada por lote único)
   │
   └── Despacho de Correo Electrónico:
         try {
             Mail::to($usuario->email)->send(new Aviso($titulo, $mensaje, $urlAccion));
         } catch (Throwable $e) {
             report($e); // Registra en log de errores de Laravel (storage/logs/laravel.log)
             // PRINCIPIO DE RESILIENCIA: El fallo de SMTP NO rompe la transacción de la reserva
         }
```

### 3.2 Despacho Masivo / Segmentado por el Administrador (HU-15)

```
[Administrador] POST /admin/notificaciones (destinatario: 'rol:asesor', titulo: "...", mensaje: "...", con_correo: 1)
   │
   ▼
[EnviarNotificacionRequest] ──► Valida parámetros
   │
   ▼
[Admin\NotificacionController::store()]
   ├── Switch (destino):
   │     case 'todos'      ──► NotificacionService::paraTodos()
   │     case 'rol:asesor' ──► NotificacionService::paraRol(RolUsuario::Asesor)
   │     case 'usuario_id' ──► NotificacionService::paraUsuario()
   └── Inserción masiva en tabla 'notificacion' y encolamiento de correos
```

### 3.3 Consulta y Marcado de Lectura por el Cliente (HU-22)

```
[Cliente] GET /notificaciones
   │  ──► NotificacionController::index()
   │        ├── Notificacion::where('usuario_id', Auth::id())->latest()->paginate(20)
   │        └── Renderiza view('notificaciones.index')
   │
[Cliente] PATCH /notificaciones/{id}
   │  ──► NotificacionController::marcarLeida()
   │        ├── Valida pertenencia: abort_unless($notificacion->usuario_id === Auth::id(), 403)
   │        └── $notificacion->update(['leido_en' => now()])
   │
[Cliente] PATCH /notificaciones/leidas
   │  ──► NotificacionController::marcarTodas()
   │        └── UPDATE notificacion SET leido_en = NOW() WHERE usuario_id = :authId AND leido_en IS NULL
```

---

## 4. Reglas de Negocio y Resiliencia

1. **Principio de Aislamiento de Fallos en Notificaciones**:
   - Una caída temporal del servidor SMTP, un error de DNS o una clave de API de correo inválida jamás cancela ni revierte una transacción de negocio crítica (como el cobro de una reserva o el cierre de una venta). El error de correo se captura y se reporta silenciosamente al log.
2. **Inserción en Lote (Batch Insert)**:
   - Al notificar a miles de usuarios (ej. aviso de sistema o a todos los clientes), `NotificacionService` construye un array multidimensional e inserta todas las filas en una única sentencia SQL `INSERT INTO notificacion ...`, eliminando el cuello de botella de consultas N+1 en bucles.
3. **Resolución Inteligente de URLs (`obtenerUrl()`)**:
   - Cada registro de notificación sabe cómo generar su enlace de acción según `referencia_tipo` (`reserva` $\rightarrow$ `/reservas/{id}`, `contrato` $\rightarrow$ `/contratos/{id}/descargar`, `conversacion` $\rightarrow$ `/mensajes/{id}`).
