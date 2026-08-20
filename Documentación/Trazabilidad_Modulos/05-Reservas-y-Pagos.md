# Módulo 05: Reservas y Pagos

> **Propósito**: Gestión integral del ciclo transaccional de reservas inmobiliarias (solicitud protegida contra concurrencia, bloqueo lógico, caducidad por cron) y procesamiento multicanal de pagos (Stripe con tarjetas guardadas SetupIntent/PaymentIntent, transferencias manuales con comprobante auditadas por administradores y webhooks asíncronos idempotentes).

---

## 1. Mapa de Componentes y Trazabilidad

| Capa | Archivo / Recurso | Función y Responsabilidad |
|---|---|---|
| **Rutas Cliente** | `routes/web.php` | ├── `GET /mis-reservas` (`reservas.index`)<br>├── `POST /reservas` (`reservas.store`)<br>├── `GET /reservas/{id}` (`reservas.show`)<br>├── `POST /reservas/{id}/pago` (`reservas.pago` - Pago manual con comprobante)<br>├── `POST /reservas/{id}/pagar-con-tarjeta/{tarjeta}` (`reservas.pagar-con-tarjeta`)<br>├── `POST /reservas/{id}/cancelar` (`reservas.cancelar`)<br>└── `perfil.tarjetas.*` (Gestión de métodos guardados Stripe). |
| **Rutas Admin** | `routes/web.php` (`/admin`) | ├── `admin.reservas.index` (Listado con filtros por estado).<br>├── `admin.reservas.show` (Detalle de reserva y auditoría de pagos).<br>├── `admin.reservas.pagos.revisar` (Aprobación/Rechazo de pago manual).<br>└── `admin.reservas.cancelar` (Cancelación forzada). |
| **Ruta Webhook** | `routes/web.php` | `POST /stripe/webhook` (`stripe.webhook` - Exento de CSRF y sesión, validación HMAC). |
| **Controladores** | `app/Http/Controllers/` | ├── `ReservaController.php`, `Admin/ReservaController.php`.<br>├── `MetodoPagoController.php` (SetupIntents de tarjetas).<br>└── `StripeWebhookController.php` (Eventos asíncronos de pasarela). |
| **Form Requests** | `app/Http/Requests/` | ├── `SolicitarReservaRequest.php` (Valida disponibilidad e id de inmueble).<br>├── `RegistrarPagoRequest.php` (Valida comprobante físico o método de pago).<br>├── `GuardarTarjetaRequest.php` (Valida PaymentMethod ID generado por Stripe.js).<br>└── `Admin/RevisarPagoRequest.php` (Valida decisión: 'aprobar' o 'rechazar' con motivo). |
| **Servicios** | `app/Servicios/` | ├── `ReservaService.php` (Bloqueo pesimista, creación atómica, transiciones de estado y recálculo del inmueble).<br>├── `PagoService.php` (Registro contable, validación de montos y aprobación).<br>└── `StripeCardService.php` (Comunicación con Stripe API, Clientes, Tarjetas y Cobros). |
| **Políticas** | `app/Politicas/ReservaPolicy.php` | Métodos: `view`, `pay`, `cancel`, `adminManage`. |
| **Modelos & Tablas** | `app/Models/` | ├── `Reserva.php` (`reserva`), `HistorialReserva.php` (`historial_reserva`).<br>├── `Pago.php` (`pago`), `MetodoPagoGuardado.php` (`metodo_pago_guardado`).<br>└── `WebhookEvento.php` (`webhook_evento`). |
| **Enums** | `app/Enumerados/` | `EstadoReserva.php` (`pendiente_pago`, `confirmada`, `expirada`, `cancelada`, `completada`), `EstadoPago.php` (`pendiente`, `aprobado`, `rechazado`), `MetodoPago.php`, `PasarelaPago.php`. |
| **Cron Command** | `app/Console/Commands/` | `ExpirarReservas.php` (Comando `reservas:expirar` ejecutado cada hora en scheduler). |

---

## 2. Esquema de Base de Datos y Mapeo

```sql
-- Tabla reserva
CREATE TABLE `reserva` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `codigo_reserva` VARCHAR(30) NOT NULL UNIQUE, -- Ej: 'RES-2026-00341'
  `inmueble_id` BIGINT UNSIGNED NOT NULL,
  `usuario_id` BIGINT UNSIGNED NOT NULL,
  `monto_reserva` DECIMAL(12,2) NOT NULL,
  `estado` VARCHAR(30) NOT NULL DEFAULT 'pendiente_pago',
  `expira_en` TIMESTAMP NOT NULL, -- +24 horas desde la solicitud
  `notas_cliente` TEXT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  FOREIGN KEY (`inmueble_id`) REFERENCES `inmueble`(`id`),
  FOREIGN KEY (`usuario_id`) REFERENCES `usuario`(`id`),
  INDEX `idx_reserva_estado_expira` (`estado`, `expira_en`)
);

-- Tabla pago
CREATE TABLE `pago` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `reserva_id` BIGINT UNSIGNED NOT NULL,
  `monto` DECIMAL(12,2) NOT NULL,
  `metodo_pago` VARCHAR(50) NOT NULL, -- 'stripe', 'transferencia', 'efectivo'
  `estado` VARCHAR(30) NOT NULL DEFAULT 'pendiente', -- 'pendiente', 'aprobado', 'rechazado'
  `referencia_pasarela` VARCHAR(150) NULL, -- 'pi_3MtwLw2eZvKYlo2C0VvZxxxx'
  `comprobante_ruta` VARCHAR(255) NULL, -- Almacenado en disco privado
  `observaciones_revision` TEXT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  FOREIGN KEY (`reserva_id`) REFERENCES `reserva`(`id`) ON DELETE CASCADE
);

-- Tabla webhook_evento (Idempotencia)
CREATE TABLE `webhook_evento` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `evento_id` VARCHAR(150) NOT NULL UNIQUE, -- ID del evento en Stripe 'evt_xxxx'
  `tipo` VARCHAR(100) NOT NULL,
  `payload` JSON NOT NULL,
  `procesado_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP NULL
);
```

---

## 3. Flujos de Trazabilidad Detallados

### 3.1 Solicitud de Reserva con Bloqueo Pesimista (HU-07)

```
[Cliente] POST /reservas (inmueble_id, modalidad, notas)
   │
   ▼
[SolicitarReservaRequest] ──► Valida existencia de inmueble
   │
   ▼
[ReservaController::store()]
   │
   ▼
[ReservaService::solicitar()]
   ├── DB::transaction():
   │     ├── Inmueble::whereKey($id)->lockForUpdate()->firstOrFail() ──► Evita condiciones de carrera
   │     ├── Invariante 1: Verifica que no exista otra reserva activa en conflicto sobre el inmueble
   │     ├── Invariante 2: El monto NUNCA se recibe del cliente; se calcula en el backend desde el inmueble
   │     ├── Reserva::create([
   │     │      'codigo_reserva' => Reserva::generarCodigo(),
   │     │      'monto_reserva'  => $montoCalculado,
   │     │      'estado'         => EstadoReserva::PendientePago,
   │     │      'expira_en'      => now()->addHours(24),
   │     │   ])
   │     └── HistorialReserva::registrar($reserva, null, 'pendiente_pago', 'Reserva creada')
   ├── NotificacionService::paraUsuario($cliente, 'Reserva registrada', ...) [conCorreo: true]
   ├── NotificacionService::paraStaff('Nueva reserva', ...)
   └── Redirige a /reservas/{id} para proceder con el pago
```

### 3.2 Pago Directo con Stripe / Tarjeta Guardada (HU-20 / HU-23)

```
[Cliente] POST /reservas/{id}/pagar-con-tarjeta/{tarjeta}
   │
   ▼
[ReservaPolicy::pay()] ──► Verifica que la reserva pertenezca al cliente y siga 'pendiente_pago'
   │
   ▼
[StripeCardService::cobrar()]
   ├── Ejecuta Stripe\PaymentIntent::create([
   │     'amount' => $reserva->monto_reserva * 100, // En centavos
   │     'currency' => 'cop',
   │     'customer' => $user->stripe_customer_id,
   │     'payment_method' => $tarjeta->stripe_payment_method_id,
   │     'off_session' => true,
   │     'confirm' => true,
   │   ])
   ├── Si Stripe confirma 'status: succeeded':
   │     ├── PagoService::registrarPagoAprobado($reserva, $paymentIntent->id)
   │     ├── ReservaService::confirmar($reserva):
   │     │     ├── $reserva->update(['estado' => EstadoReserva::Confirmada])
   │     │     ├── $inmueble->update(['estado' => EstadoInmueble::Reservado])
   │     │     └── HistorialReserva::registrar(...)
   │     └── NotificacionService envía recibo por correo y alerta in-app
   └── Si Stripe rechaza: Lanza ValidationException con mensaje del emisor bancario
```

### 3.3 Procesamiento Asíncrono de Webhooks de Stripe (HU-23.1)

```
[Stripe Server] POST /stripe/webhook (Headers: Stripe-Signature)
   │
   ▼
[StripeWebhookController::recibir()]
   ├── Stripe\Webhook::constructEvent($payload, $sigHeader, config('services.stripe.webhook_secret'))
   │     └── Si la firma es inválida: Aborta con HTTP 400
   ├── Idempotencia: WebhookEvento::where('evento_id', $event->id)->exists()
   │     └── Si ya existe: Retorna HTTP 200 inmediatamente sin reprocesar
   ├── Registra evento en 'webhook_evento'
   ├── Switch ($event->type):
   │     case 'payment_intent.succeeded':
   │        └── Confirma pago y transiciona Reserva a Confirmada
   │     case 'payment_intent.payment_failed':
   │        └── Marca Pago como rechazado y notifica al cliente para reintentar
   └── Retorna HTTP 200 JSON ['status' => 'success']
```

---

## 4. Invariantes de Seguridad y Reglas de Negocio

1. **Invariante de Montos Inalterables**:
   - El precio a pagar jamás se confía al cliente. En la transacción de solicitud, el `ReservaService` consulta directamente la columna `inmueble.precio` o `inmueble.precio_venta` según la modalidad solicitada.
2. **Expiración Automática por Cron (`reservas:expirar`)**:
   - Cada hora, el cron busca reservas en `pendiente_pago` con `expira_en < now()`. Las transiciona a `EstadoReserva::Expirada` y restaura el inmueble a `EstadoInmueble::Disponible` sin requerir acciones del staff.
3. **Idempotencia Estricta en Pagos**:
   - La tabla `webhook_evento` previene dobles cobros o estados inconsistentes cuando Stripe reenvía eventos tras fallos temporales de red.
