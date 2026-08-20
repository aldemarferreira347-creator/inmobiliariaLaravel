# Módulo 06: Contratos de Arriendo

> **Propósito**: Formalización legal de contratos de arrendamiento originados a partir de reservas confirmadas, control de vigencia temporal (plazo de emisión de 7 días, vencimientos automáticos por cron), almacenamiento seguro de documentos PDF firmados en disco privado y streaming autorizado.

---

## 1. Mapa de Componentes y Trazabilidad

| Capa | Archivo / Recurso | Función y Responsabilidad |
|---|---|---|
| **Rutas Admin** | `routes/web.php` (`/admin/contratos`) | ├── `GET /admin/contratos` (`admin.contratos.index`)<br>├── `GET /admin/contratos/nuevo` (`admin.contratos.create`)<br>├── `POST /admin/contratos` (`admin.contratos.store`)<br>├── `GET /admin/contratos/{id}` (`admin.contratos.show`)<br>├── `POST /admin/contratos/{id}/documento` (`admin.contratos.documento`)<br>└── `POST /admin/contratos/{id}/rescindir` (`admin.contratos.rescindir`) |
| **Ruta Descarga** | `routes/web.php` | `GET /contratos/{id}/descargar` (`contratos.descargar` - Protegida por policy). |
| **Controladores** | `app/Http/Controllers/` | ├── `Admin/ContratoController.php` (Gestión completa, emisión, rescisión y carga).<br>└── `DescargaController.php` (Streaming seguro de PDFs privados). |
| **Form Requests** | `app/Http/Requests/Admin/` | ├── `StoreContratoRequest.php` (Valida coherencia de fechas `fecha_inicio` y `fecha_fin > fecha_inicio`, reserva confirmada).<br>└── `DocumentoContratoRequest.php` (Valida archivo PDF firmado, máx 10MB). |
| **Servicios** | `app/Servicios/` | ├── `ContratoService.php` (Emisión, cambio de estado del inmueble a 'ocupado', rescisión, liberación y cron de vencimiento).<br>└── `ArchivoPrivadoService.php` (Almacenamiento no expuesto en `storage/app/contratos` y entrega mediante `Storage::download()`). |
| **Políticas** | `app/Politicas/ContratoPolicy.php` | Métodos: `view`, `download`, `manage`. Autoriza solo a administradores o al cliente arrendatario titular. |
| **Modelos & Tablas** | `app/Models/` | ├── `Contrato.php` (`contrato`, relación con `reserva`, `inmueble` y `arrendatario`).<br>├── `Reserva.php` (Reserva origen).<br>└── `Inmueble.php` (Inmueble ocupado/liberado). |
| **Enums** | `app/Enumerados/` | `EstadoContrato.php` (`vigente`, `vencido`, `rescindido`), `EstadoInmueble.php` (`disponible`, `ocupado`, `reservado`). |
| **Cron Command** | `app/Console/Commands/` | `VencerContratos.php` (Comando `contratos:vencer` ejecutado cada hora en scheduler). |
| **Vistas** | `resources/views/admin/contratos/` | `index.blade.php`, `create.blade.php`, `show.blade.php`. |

---

## 2. Esquema de Base de Datos y Relaciones

```sql
-- Tabla contrato
CREATE TABLE `contrato` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `reserva_id` BIGINT UNSIGNED NOT NULL UNIQUE, -- Una reserva confirmada genera un único contrato
  `numero_contrato` VARCHAR(50) NOT NULL UNIQUE, -- Ej: 'CTR-2026-00089'
  `fecha_inicio` DATE NOT NULL,
  `fecha_fin` DATE NULL,
  `valor_mensual` DECIMAL(12,2) NOT NULL, -- Tomado directamente del monto de la reserva
  `estado` VARCHAR(30) NOT NULL DEFAULT 'vigente', -- 'vigente', 'vencido', 'rescindido'
  `archivo_ruta` VARCHAR(255) NULL, -- storage/app/contratos/{hash}.pdf
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  FOREIGN KEY (`reserva_id`) REFERENCES `reserva`(`id`),
  INDEX `idx_contrato_estado_fechas` (`estado`, `fecha_fin`)
);
```

---

## 3. Flujos de Trazabilidad Detallados

### 3.1 Emisión de Contrato desde Reserva Confirmada (HU-17.1)

```
[Administrador] POST /admin/contratos (reserva_id, fecha_inicio, fecha_fin)
   │
   ▼
[StoreContratoRequest] ──► Valida que la reserva exista y esté en EstadoReserva::Confirmada
   │
   ▼
[Admin\ContratoController::store()]
   │
   ▼
[ContratoService::crearDesdeReserva()]
   ├── RN-18: Comprueba que la reserva se haya confirmado dentro de los últimos 7 días naturales
   │     └── Si supera el plazo: Lanza ValidationException exigiendo reactivar la operación
   ├── DB::transaction():
   │     ├── Crea Contrato con 'numero_contrato' = Contrato::generarNumero($reserva)
   │     ├── Invariante: 'valor_mensual' se copia de $reserva->monto_reserva (Inviolabilidad contable)
   │     ├── Setea estado inicial en EstadoContrato::Vigente
   │     └── Actualiza Inmueble a EstadoInmueble::Ocupado
   ├── NotificacionService::paraUsuario($cliente, 'Contrato emitido', ...) [conCorreo: true]
   └── Redirige al detalle del contrato con alerta de éxito
```

### 3.2 Carga de Documento Firmado (PDF) (HU-17.2)

```
[Administrador] POST /admin/contratos/{id}/documento (archivo .pdf)
   │
   ▼
[DocumentoContratoRequest] ──► Valida extensión .pdf, mime application/pdf, máx 10MB
   │
   ▼
[ContratoService::adjuntarDocumento()]
   ├── ArchivoPrivadoService::eliminar($contrato->archivo_ruta) (Si ya existía un PDF anterior)
   ├── $nuevaRuta = ArchivoPrivadoService::guardar($file, 'contratos', "contrato_{$id}_".time())
   └── $contrato->update(['archivo_ruta' => $nuevaRuta])
```

### 3.3 Descarga Privada Autorizada (HU-19.1)

```
[Cliente o Administrador] GET /contratos/{id}/descargar
   │
   ▼
[ContratoPolicy::download()]
   ├── ¿Es Administrador? ──► Permite
   ├── ¿El id_arrendatario coincide con Auth::id()? ──► Permite
   └── De lo contrario: Aborta inmediatamente con HTTP 403 Forbidden
   │
   ▼
[DescargaController::contrato()]
   └── ArchivoPrivadoService::descargar($contrato->archivo_ruta, "Contrato-{$contrato->numero_contrato}.pdf")
         └── Retorna Storage::download() con cabeceras 'Content-Type: application/pdf' y 'private, no-cache'
```

### 3.4 Rescisión y Vencimiento Automático (HU-17.3 / HU-17.4)

```
[Administrador] POST /admin/contratos/{id}/rescindir (motivo)
   │  ──► ContratoService::rescindir()
   │        ├── Contrato pasa a EstadoContrato::Rescindido
   │        ├── Inmueble pasa a EstadoInmueble::Disponible
   │        └── Notifica al cliente vía email
   │
[Cron horario] php artisan contratos:vencer
   └── ContratoService::vencerContratosCumplidos()
         ├── Busca contratos Vigentes con 'fecha_fin < today()'
         ├── Actualiza a EstadoContrato::Vencido
         └── Libera Inmueble a EstadoInmueble::Disponible
```

---

## 4. Reglas de Negocio y Seguridad Críticas

1. **Plazo de Emisión Estricto (Regla RN-18)**:
   - La formalización del contrato debe ocurrir obligatoriamente dentro de los **7 días posteriores** a la aprobación del pago de la reserva. Si este tiempo expira sin emitirse el contrato, el sistema bloquea la emisión directa y requiere regularización.
2. **Inmuebles Ocupados vs Disponibles**:
   - Mientras el contrato permanece `vigente`, el inmueble se encuentra en estado `ocupado` (no visible en catálogo para nuevos alquileres). Tanto la rescisión manual como el vencimiento por cron devuelven el inmueble automáticamente a `disponible`.
3. **Privacidad de Documentos**:
   - Los contratos firmados contienen datos sensibles (documentos de identidad, cláusulas económicas). Jamás se guardan en el directorio público; su lectura pasa exclusivamente por `DescargaController` bajo `ContratoPolicy`.
