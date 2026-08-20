# Módulo 07: Ventas de Inmuebles

> **Propósito**: Gestión integral del ciclo de compraventa de inmuebles (iniciación por asesor comercial o administrador, bloqueo preventivo de la propiedad, carga notarial de escrituras públicas, formalización de cierre y descarga privada por el comprador).

---

## 1. Mapa de Componentes y Trazabilidad

| Capa | Archivo / Recurso | Función y Responsabilidad |
|---|---|---|
| **Rutas Asesor / Admin**| `routes/web.php` (`/asesor/ventas`) | ├── `GET /asesor/ventas` (`asesor.ventas.index`)<br>├── `POST /asesor/ventas` (`asesor.ventas.store`)<br>├── `GET /asesor/ventas/{id}` (`asesor.ventas.show`)<br>├── `POST /asesor/ventas/{id}/cerrar` (`asesor.ventas.cerrar`)<br>├── `POST /asesor/ventas/{id}/cancelar` (`asesor.ventas.cancelar`)<br>└── `POST /asesor/ventas/{id}/escritura` (`asesor.ventas.escritura`) |
| **Ruta Descarga** | `routes/web.php` | `GET /ventas/{id}/escritura` (`ventas.escritura` - Descarga autorizada). |
| **Controladores** | `app/Http/Controllers/` | ├── `Asesor/VentaController.php` (Operaciones de registro, cambio de estado y adjuntos).<br>└── `DescargaController.php` (Validación de policy y streaming seguro de escrituras). |
| **Form Requests** | `app/Http/Requests/Asesor/` | `StoreVentaRequest.php` (Valida existencia de cliente, inmueble en venta y precio acordado `precio_final`). |
| **Servicios** | `app/Servicios/` | ├── `VentaService.php` (Lógica transaccional de venta, bloqueo pesimista, cierre definitivo, cálculo de liberación y avisos).<br>└── `ArchivoPrivadoService.php` (Custodia de documentos notariales en `storage/app/escrituras`). |
| **Políticas** | `app/Politicas/VentaPolicy.php` | Métodos: `view`, `update`, `manage`, `downloadEscritura`. Autoriza al asesor titular, administradores y al cliente comprador. |
| **Modelos & Tablas** | `app/Models/` | ├── `Venta.php` (`venta`, campos: `id_inmueble`, `id_cliente`, `id_asesor`, `precio_final`, `estado`, `ruta_escritura`, `fecha_venta`).<br>├── `Inmueble.php` (`inmueble`).<br>└── `User.php` (`usuario` para comprador y asesor). |
| **Enums** | `app/Enumerados/` | `EstadoVenta.php` (`en_proceso`, `cerrada`, `cancelada`), `EstadoInmueble.php` (`disponible`, `reservado`, `ocupado`). |
| **Vistas** | `resources/views/asesor/ventas/` | `index.blade.php` (Bandeja con buscador y modal de apertura), `show.blade.php` (Línea de tiempo, datos de la notaría y carga de archivo). |

---

## 2. Esquema de Base de Datos y Relaciones

```sql
-- Tabla venta
CREATE TABLE `venta` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `inmueble_id` BIGINT UNSIGNED NOT NULL,
  `cliente_id` BIGINT UNSIGNED NOT NULL,
  `asesor_id` BIGINT UNSIGNED NOT NULL,
  `precio_final` DECIMAL(14,2) NOT NULL,
  `estado` VARCHAR(30) NOT NULL DEFAULT 'en_proceso', -- 'en_proceso', 'cerrada', 'cancelada'
  `escritura_ruta` VARCHAR(255) NULL, -- storage/app/escrituras/{hash}.pdf
  `fecha_venta` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `observaciones` TEXT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  FOREIGN KEY (`inmueble_id`) REFERENCES `inmueble`(`id`),
  FOREIGN KEY (`cliente_id`) REFERENCES `usuario`(`id`),
  FOREIGN KEY (`asesor_id`) REFERENCES `usuario`(`id`),
  INDEX `idx_venta_asesor_estado` (`asesor_id`, `estado`),
  INDEX `idx_venta_cliente` (`cliente_id`)
);
```

---

## 3. Flujos de Trazabilidad Detallados

### 3.1 Apertura de Negociación de Venta (HU-14.1)

```
[Asesor o Admin] POST /asesor/ventas (inmueble_id, cliente_id, precio_final, observaciones)
   │
   ▼
[Middleware: auth + rol:asesor,administrador]
   │
   ▼
[StoreVentaRequest] ──► Valida cliente activo e inmueble con modalidad venta/ambas
   │
   ▼
[Asesor\VentaController::store()]
   │
   ▼
[VentaService::registrar()]
   ├── DB::transaction():
   │     ├── Inmueble::whereKey($id)->lockForUpdate()->firstOrFail() ──► Bloqueo pesimista
   │     ├── Comprueba que el inmueble esté disponible y no tenga otra venta en proceso
   │     ├── Crea registro 'venta' con 'estado = EstadoVenta::EnProceso'
   │     └── Bloquea Inmueble: actualiza 'estado = EstadoInmueble::Reservado'
   ├── NotificacionService::paraUsuario($cliente, 'Proceso de venta iniciado', ...)
   └── Redirige a la ficha de la venta en curso
```

### 3.2 Carga de Escritura Pública Notarial (HU-14.3)

```
[Asesor o Admin] POST /asesor/ventas/{id}/escritura (archivo PDF)
   │
   ▼
[VentaPolicy::update()] ──► Verifica permisos sobre el expediente
   │
   ▼
[VentaService::adjuntarEscritura()]
   ├── ArchivoPrivadoService::eliminar($venta->escritura_ruta) (Si ya existía versión previa)
   ├── $ruta = ArchivoPrivadoService::guardar($file, 'escrituras', "escritura_{$id}_".time())
   └── $venta->update(['escritura_ruta' => $ruta])
```

### 3.3 Cierre Definitivo de la Venta (HU-14.2)

```
[Asesor o Admin] POST /asesor/ventas/{id}/cerrar
   │
   ▼
[VentaService::cerrar()]
   ├── Verifica que la venta esté en 'en_proceso'
   ├── DB::transaction():
   │     ├── $venta->update(['estado' => EstadoVenta::Cerrada])
   │     └── $inmueble->update(['estado' => EstadoInmueble::Ocupado]) ──► Retira definitivamente de venta
   └── NotificacionService::paraUsuario($cliente, 'Venta cerrada', '¡Felicitaciones!...') [conCorreo: true]
```

### 3.4 Cancelación de Venta y Restauración de Estado (HU-14.4)

```
[Asesor o Admin] POST /asesor/ventas/{id}/cancelar (motivo)
   │
   ▼
[VentaService::cancelar()]
   ├── DB::transaction():
   │     ├── $venta->update(['estado' => EstadoVenta::Cancelada])
   │     ├── Recalcula estado del inmueble: $inmueble->estadoCalculado() (Devuelve a 'disponible')
   │     └── Registra motivo en bitácora de auditoría
   └── Notifica al cliente de la cancelación
```

---

## 4. Reglas de Negocio y Seguridad Críticas

1. **Permiso Compartido Asesor-Administrador**:
   - Toda la operativa de ventas está accesible tanto para asesores como para administradores (`rol:asesor,administrador`), permitiendo al supervisor auditar o cerrar ventas cuando el asesor esté ausente.
2. **Descarga de Escrituras Protegida**:
   - La escritura notarial solo puede ser descargada por el cliente comprador titular, el asesor a cargo o un administrador vía [`DescargaController::escritura()`](file:///c:/laragon/www/inmobiliarialaravel/app/Http/Controllers/DescargaController.php), garantizando el secreto fiduciario.
