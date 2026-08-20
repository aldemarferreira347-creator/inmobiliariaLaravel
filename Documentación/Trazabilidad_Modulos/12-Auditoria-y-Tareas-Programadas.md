# Módulo 12: Auditoría y Tareas Programadas

> **Propósito**: Bitácora inmutable de auditoría para registro de acciones críticas en el sistema (cambios de estado de usuarios, transacciones de reservas, ventas y contratos) y automatización de procesos mediante comandos de consola y cron jobs de Laravel (expiración de reservas caducadas y vencimiento de contratos de arrendamiento).

---

## 1. Mapa de Componentes y Trazabilidad

| Capa | Archivo / Recurso | Función y Responsabilidad |
|---|---|---|
| **Scheduler** | `routes/console.php` | Orquesta la periodicidad de comandos horarios mediante `Schedule::command(...)->hourly()->withoutOverlapping()`. |
| **Comandos Artisan** | `app/Console/Commands/` | ├── `ExpirarReservas.php` (Comando `reservas:expirar` para cancelación y liberación de inmuebles).<br>└── `VencerContratos.php` (Comando `contratos:vencer` para vencimiento legal y retorno a catálogo). |
| **Modelo de Auditoría** | `app/Models/Auditoria.php` | Método estático `Auditoria::registrar($modulo, $idRegistro, $accion, $detalles, $usuarioId, $ip)` para inserción no bloqueante. |
| **Historiales Especializados**| `app/Models/` | ├── `HistorialReserva.php` (Línea de tiempo de cambios de estado de cada reserva).<br>├── `CitaHistorial.php` (Bitácora de asignaciones, reasignaciones y estados de visitas).<br>└── `WebhookEvento.php` (Registro de eventos de pasarela Stripe). |
| **Tabla de BD** | Base de datos (`auditoria`) | Tabla inmutable con índices optimizados para consultas forenses y seguimiento de seguridad. |

---

## 2. Esquema de Base de Datos y Relaciones

```sql
-- Tabla auditoria general
CREATE TABLE `auditoria` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `modulo` VARCHAR(50) NOT NULL, -- 'usuario', 'inmueble', 'reserva', 'contrato', 'venta'
  `id_registro` BIGINT UNSIGNED NOT NULL,
  `accion` VARCHAR(100) NOT NULL, -- 'cambiar_estado', 'crear', 'cancelar', 'cerrar_venta', 'reserva_expirada_cron'
  `detalles` TEXT NOT NULL,
  `usuario_id` BIGINT UNSIGNED NULL, -- NULL si fue ejecutado automáticamente por un cron del sistema
  `ip` VARCHAR(45) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_auditoria_modulo_registro` (`modulo`, `id_registro`),
  INDEX `idx_auditoria_usuario` (`usuario_id`),
  INDEX `idx_auditoria_fecha` (`created_at`)
);
```

---

## 3. Flujos de Trazabilidad Detallados

### 3.1 Registro Transversal de Auditoría (`Auditoria::registrar`)

```
Ocurre una operación administrativa o cambio de estado sensible
   │
   ▼
[Invocación desde Controlador o Servicio]
   Auditoria::registrar(
       modulo: 'usuario',
       idRegistro: $usuario->id,
       accion: 'cambiar_estado',
       detalles: "La cuenta de {$usuario->email} pasó a estado Inactivo.",
       usuarioId: Auth::id(),
       ip: request()?->ip()
   )
   │
   ▼
[Persistencia Inmutable]
   ├── Inserta registro en tabla 'auditoria'
   └── Los registros jamás se modifican ni eliminan (Garantía de no repudio)
```

### 3.2 Tarea Cron: Expiración Automática de Reservas (`reservas:expirar`)

```
[Servidor Linux/Windows Cron] Cada hora: php artisan schedule:run ──► php artisan reservas:expirar
   │
   ▼
[ExpirarReservas::handle()]
   ├── Consulta: Reserva::vencidas()->with('inmueble')->get()
   │     (Filtra: estado = 'pendiente_pago' Y expira_en < NOW())
   ├── Itera cada reserva de forma aislada (Resiliencia):
   │     try {
   │         ReservaService::expirar($reserva):
   │           ├── DB::transaction():
   │           │     ├── $reserva->update(['estado' => EstadoReserva::Expirada])
   │           │     ├── $inmueble->update(['estado' => EstadoInmueble::Disponible]) ──► Libera inmueble
   │           │     └── HistorialReserva::registrar($reserva, null, 'expirada', 'Expirada por cron.')
   │           └── NotificacionService::paraUsuario($cliente, 'Reserva expirada', ...)
   │     } catch (Throwable $e) {
   │         report($e); // Un fallo en una reserva no detiene la expiración de las demás
   │     }
   └── Muestra resumen en consola: "Reservas expiradas: X de Y."
```

### 3.3 Tarea Cron: Vencimiento de Contratos (`contratos:vencer`)

```
[Servidor Linux/Windows Cron] Cada hora: php artisan schedule:run ──► php artisan contratos:vencer
   │
   ▼
[VencerContratos::handle()]
   ├── Consulta: Contrato::porVencer()->with('reserva.inmueble')->get()
   │     (Filtra: estado = 'vigente' Y fecha_fin < TODAY())
   ├── Itera cada contrato:
   │     try {
   │         ContratoService::vencer($contrato):
   │           ├── DB::transaction():
   │           │     ├── $contrato->update(['estado' => EstadoContrato::Vencido])
   │           │     ├── $inmueble->update(['estado' => EstadoInmueble::Disponible]) ──► Vuelve al catálogo
   │           │     └── Auditoria::registrar('contrato', $contrato->id, 'contrato_vencido_cron', ...)
   │           └── NotificacionService::paraUsuario($arrendatario, 'Contrato vencido', ...)
   │     } catch (Throwable $e) {
   │         report($e);
   │     }
   └── Muestra resumen en consola: "Contratos vencidos: X de Y."
```

---

## 4. Reglas de Infraestructura y Negocio

1. **Configuración del Cron en Producción**:
   - Para que las tareas se ejecuten puntualmente, el servidor en producción requiere una única línea en el crontab del sistema operativo:
     ```bash
     * * * * * cd /ruta/al/proyecto && php artisan schedule:run >> /dev/null 2>&1
     ```
2. **Protección Anti-Solapamiento (`withoutOverlapping()`)**:
   - Ambas tareas en `routes/console.php` incluyen `withoutOverlapping()`, creando un lock en caché temporal para evitar que una ejecución tardía coincida con la siguiente hora y provoque bloqueos en base de datos.
3. **Aislamiento de Errores en Lotes**:
   - En ambos comandos de consola, cada entidad se procesa dentro de su propio bloque `try-catch`. Si una reserva o contrato arroja una excepción (por ejemplo, bloqueo de fila temporal), el error se reporta y el comando continúa procesando los restantes registros sin abortar.
