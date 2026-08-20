# Módulo 08: Citas y Agenda de Visitas

> **Propósito**: Coordinación de visitas presenciales a inmuebles: solicitud por clientes basada en franjas horarias parametrizables, asignación de asesores comerciales por la administración, registro de bitácora/observaciones post-visita y auditoría inmutable de estados.

---

## 1. Mapa de Componentes y Trazabilidad

| Capa | Archivo / Recurso | Función y Responsabilidad |
|---|---|---|
| **Rutas Cliente** | `routes/web.php` | ├── `GET /mis-citas` (`citas.index`)<br>├── `POST /citas` (`citas.store`)<br>├── `POST /citas/{id}/cancelar` (`citas.cancelar`)<br>└── `GET /citas/franjas-disponibles` (`citas.franjas-disponibles` - AJAX). |
| **Rutas Asesor** | `routes/web.php` (`/asesor/citas`) | ├── `GET /asesor/citas` (`asesor.citas.index` - Agenda propia)<br>├── `GET /asesor/citas/{id}` (`asesor.citas.show`)<br>├── `POST /asesor/citas/{id}/observacion` (`asesor.citas.observacion`)<br>└── `PATCH /asesor/citas/{id}/observacion` (`asesor.citas.observacion.editar`) |
| **Rutas Admin** | `routes/web.php` (`/admin`) | ├── `admin.citas.index` (Supervisión global de citas)<br>├── `admin.citas.show` (Detalle y reasignación)<br>├── `POST /admin/citas/{id}/asignar` (`admin.citas.asignar`)<br>├── `GET /admin/franjas` (`admin.franjas.index`)<br>└── `POST /admin/franjas` (`admin.franjas.update`) |
| **Controladores** | `app/Http/Controllers/` | ├── `CitaController.php` (Flujo cliente y disponibilidad).<br>├── `Asesor/CitaController.php` (Agenda del asesor y bitácora).<br>├── `Admin/CitaController.php` (Asignación y supervisión).<br>└── `Admin/FranjaController.php` (Parametrización horaria semanal). |
| **Form Requests** | `app/Http/Requests/` | ├── `SolicitarCitaRequest.php` (Valida fecha futura y franja permitida).<br>├── `Admin/AsignarCitaRequest.php` (Valida asesor activo).<br>├── `Admin/ActualizarFranjaRequest.php` (Valida rangos hora_inicio/hora_fin).<br>└── `Asesor/RegistrarObservacionRequest.php` (Valida texto y resultado comercial). |
| **Servicios** | `app/Servicios/` | `CitaService.php` (Cálculo de slots disponibles, bloqueo de solapamientos concurrentes, asignación con auditoría en `CitaHistorial` y cambios de estado). |
| **Políticas** | `app/Politicas/CitaPolicy.php` | Métodos: `view`, `cancel`, `assign`, `observe`. |
| **Modelos & Tablas** | `app/Models/` | ├── `Cita.php` (`cita`), `CitaHistorial.php` (`cita_historial`).<br>├── `ObservacionCita.php` (`observacioncita`).<br>└── `ConfigFranjaCita.php` (`config_franja_cita`). |
| **Enums** | `app/Enumerados/` | `EstadoCita.php` (`pendiente`, `asignada`, `confirmada`, `realizada`, `cancelada`, `reprogramada`). |
| **Vistas** | `resources/views/` | `citas/index.blade.php`, `admin/citas/index.blade.php`, `admin/citas/franjas.blade.php`, `asesor/citas/index.blade.php`, `asesor/citas/show.blade.php`. |

---

## 2. Esquema de Base de Datos y Relaciones

```sql
-- Tabla config_franja_cita
CREATE TABLE `config_franja_cita` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `dia_semana` TINYINT UNSIGNED NOT NULL, -- 1 = Lunes ... 7 = Domingo
  `hora_inicio` TIME NOT NULL, -- Ej: '08:00:00'
  `hora_fin` TIME NOT NULL, -- Ej: '18:00:00'
  `duracion_minutos` SMALLINT UNSIGNED NOT NULL DEFAULT 60,
  `activo` BOOLEAN NOT NULL DEFAULT 1
);

-- Tabla cita
CREATE TABLE `cita` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `cliente_id` BIGINT UNSIGNED NOT NULL,
  `inmueble_id` BIGINT UNSIGNED NOT NULL,
  `asesor_id` BIGINT UNSIGNED NULL,
  `fecha` DATETIME NOT NULL,
  `estado` VARCHAR(30) NOT NULL DEFAULT 'pendiente',
  `notas_cliente` TEXT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  FOREIGN KEY (`cliente_id`) REFERENCES `usuario`(`id`),
  FOREIGN KEY (`inmueble_id`) REFERENCES `inmueble`(`id`),
  FOREIGN KEY (`asesor_id`) REFERENCES `usuario`(`id`),
  INDEX `idx_cita_fecha_estado` (`fecha`, `estado`),
  INDEX `idx_cita_inmueble_fecha` (`inmueble_id`, `fecha`)
);

-- Tabla cita_historial
CREATE TABLE `cita_historial` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `cita_id` BIGINT UNSIGNED NOT NULL,
  `usuario_id` BIGINT UNSIGNED NOT NULL,
  `accion` VARCHAR(50) NOT NULL, -- 'CREADA', 'ASIGNADA', 'REASIGNADA', 'CANCELADA', 'REALIZADA'
  `detalles` TEXT NOT NULL,
  `created_at` TIMESTAMP NULL,
  FOREIGN KEY (`cita_id`) REFERENCES `cita`(`id`) ON DELETE CASCADE
);
```

---

## 3. Flujos de Trazabilidad Detallados

### 3.1 Consulta de Disponibilidad y Solicitud de Cita (HU-27.1)

```
[Cliente] GET /citas/franjas-disponibles?inmueble_id=X&fecha=2026-09-15
   │  ──► CitaService::calcularFranjasDisponibles()
   │        ├── Obtiene día de la semana (Martes -> dia_semana 2) de 'config_franja_cita'
   │        ├── Genera slots de 60 minutos entre hora_inicio y hora_fin
   │        └── Filtra los slots que ya tienen citas en 'cita' (excluye canceladas)
   │        └── Retorna JSON de horas hábiles: ["09:00", "10:00", "14:00", "16:00"]
   │
[Cliente] POST /citas (inmueble_id, fecha, hora_cita, notas)
   │
   ▼
[SolicitarCitaRequest] ──► Valida que la fecha sea futura y pertenezca al catálogo
   │
   ▼
[CitaService::solicitar()]
   ├── Verifica que el cliente no tenga ya otra cita activa para ese inmueble
   ├── DB::transaction():
   │     ├── Cita::ocupaFranja($inmueble->id, $fechaHora)->lockForUpdate() (Bloqueo pesimista)
   │     │     └── Si ya fue ocupada por otro usuario: Lanza HTTP 422
   │     ├── Crea Cita en EstadoCita::Pendiente
   │     └── Registra en 'cita_historial' la acción 'CREADA'
   └── NotificacionService::paraStaff('Nueva cita por asignar', ...)
```

### 3.2 Asignación / Reasignación de Asesor por el Administrador (HU-10.1)

```
[Administrador] POST /admin/citas/{id}/asignar (asesor_id)
   │
   ▼
[Admin\AsignarCitaRequest] ──► Comprueba que el asesor exista, tenga rol 'asesor' y esté activo
   │
   ▼
[CitaService::asignar()]
   ├── Valida: $cita->estado->admiteAsignacion() (Solo si es Pendiente o Asignada)
   ├── DB::transaction():
   │     ├── $cita->update(['asesor_id' => $asesor->id, 'estado' => EstadoCita::Asignada])
   │     └── CitaHistorial::registrar($cita, $admin, 'ASIGNADA', "Asesor {$asesor->nombre} asignado...")
   ├── NotificacionService::paraUsuario($asesor, 'Cita asignada', ...)
   └── NotificacionService::paraUsuario($cita->cliente, 'Asesor asignado para tu visita', ...)
```

### 3.3 Registro de Observaciones y Conclusión de Visita (HU-12.1)

```
[Asesor] POST /asesor/citas/{id}/observacion (comentarios, estado_conclusion)
   │
   ▼
[CitaPolicy::observe()] ──► Verifica que el asesor autenticado sea el titular asignado a la cita
   │
   ▼
[Asesor\CitaController::registrarObservacion()]
   ├── ObservacionCita::create(['cita_id' => $id, 'asesor_id' => Auth::id(), 'observacion' => $texto])
   ├── Si se concluye: $cita->update(['estado' => EstadoCita::Realizada])
   └── CitaHistorial::registrar($cita, Auth::user(), 'REALIZADA', 'Visita completada con observaciones')
```

---

## 4. Reglas de Negocio y Concurrencia

1. **Prevención de Doble Reserva de Franja**:
   - `CitaService::solicitar()` implementa `lockForUpdate()` sobre la consulta de franja horaria para garantizar que dos clientes haciendo clic al mismo milisegundo no colisionen en el mismo horario para una propiedad.
2. **Historial Completo de Reasignaciones**:
   - Si un administrador reasigna una cita de un asesor a otro, `CitaHistorial` almacena el cambio con la etiqueta `REASIGNADA` junto con el nombre del administrador autor, garantizando trazabilidad laboral.
