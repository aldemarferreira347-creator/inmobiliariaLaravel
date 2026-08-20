# Módulo 11: Reportes y Exportación

> **Propósito**: Inteligencia de negocios, agregación de métricas financieras y operativas (reservas, pagos confirmados, contratos de arrendamiento, escrituración de ventas e informe integral consolidado) con motor de filtros temporales y exportación nativa a Excel y PDF.

---

## 1. Mapa de Componentes y Trazabilidad

| Capa | Archivo / Recurso | Función y Responsabilidad |
|---|---|---|
| **Rutas Admin** | `routes/web.php` (`/admin/reportes`) | ├── `GET /admin/reportes` (`admin.reportes.index` - Dashboard general de métricas).<br>├── `GET /admin/reportes/{tipo}` (`admin.reportes.show` - Tabla detallada del reporte).<br>├── `GET /admin/reportes/{tipo}/excel` (`admin.reportes.excel` - Descarga Excel/CSV).<br>└── `GET /admin/reportes/{tipo}/pdf` (`admin.reportes.pdf` - Descarga PDF imprimible). |
| **Controlador** | `app/Http/Controllers/Admin/ReporteController.php` | Orquesta la fábrica de reportes, inyecta los filtros parseados y despacha vistas o respuestas `StreamedResponse`. |
| **Fábrica de Reportes** | `app/Servicios/Reportes/FabricaReportes.php` | Instancia de forma polimórfica la clase adecuada según el Enum `TipoReporte` (Factory Pattern). |
| **Clases de Dominio** | `app/Servicios/Reportes/` | ├── `Reporte.php` (Contrato/Interfaz base con métodos: `resumen()`, `filas()`, `columnas()`, `titulo()`).<br>├── `FiltroReporte.php` (Objeto de valor que encapsula fechas desde/hasta y períodos predefinidos).<br>├── `ReporteReservaciones.php`<br>├── `ReportePagos.php`<br>├── `ReporteContratos.php`<br>├── `ReporteVentas.php`<br>└── `ReporteIntegral.php` (Consolidado macro financiero). |
| **Exportadores** | `app/Servicios/Reportes/` | ├── `ExportadorExcel.php` (Genera salida CSV/XLSX optimizada con BOM UTF-8 y cabeceras de streaming).<br>└── `ExportadorPdf.php` (Renderiza la plantilla Blade con estilos tipográficos a PDF de alta resolución). |
| **Enums** | `app/Enumerados/` | `TipoReporte.php` (`reservas`, `pagos`, `contratos`, `ventas`, `integral`), `PeriodoReporte.php` (`hoy`, `esta_semana`, `este_mes`, `este_ano`, `personalizado`). |
| **Vistas** | `resources/views/admin/reportes/` | ├── `index.blade.php` (Tarjetas ejecutivas con totales, gráficos y enlaces).<br>├── `show.blade.php` (Filtros interactivos, barra de herramientas y tabla paginada).<br>└── `pdf.blade.php` (Plantilla de maquetación limpia para salida en papel/PDF). |

---

## 2. Diagrama de Clases y Patrón Fábrica (Factory Pattern)

```
                       ┌──────────────────────┐
                       │ <<interface>>        │
                       │     Reporte          │
                       ├──────────────────────┤
                       │ + resumen(): array   │
                       │ + filas(): Collection│
                       │ + columnas(): array  │
                       │ + titulo(): string   │
                       └──────────▲───────────┘
                                  │
          ┌───────────────────────┼───────────────────────┬──────────────────────┐
          │                       │                       │                      │
┌─────────┴─────────┐   ┌─────────┴─────────┐   ┌─────────┴─────────┐  ┌─────────┴─────────┐
│ReporteReservas    │   │ReportePagos       │   │ReporteContratos   │  │ReporteVentas      │
├───────────────────┤   ├───────────────────┤   ├───────────────────┤  ├───────────────────┤
│- filtro: FiltroRep│   │- filtro: FiltroRep│   │- filtro: FiltroRep│  │- filtro: FiltroRep│
└───────────────────┘   └───────────────────┘   └───────────────────┘  └───────────────────┘
                                  ▲
                                  │ Instancia según Enum TipoReporte
                       ┌──────────┴───────────┐
                       │   FabricaReportes    │
                       ├──────────────────────┤
                       │ + crear(tipo, filtro)│
                       │ + estadosDe(tipo)    │
                       └──────────────────────┘
```

---

## 3. Flujos de Trazabilidad Detallados

### 3.1 Consulta y Filtrado en Pantalla (HU-06)

```
[Administrador] GET /admin/reportes/ventas?periodo=este_mes&estado=cerrada
   │
   ▼
[Middleware: auth + rol:administrador]
   │
   ▼
[ReporteController::show()]
   ├── FiltroReporte::desdePeticion($request):
   │     ├── Resuelve fechas: fecha_inicio = 2026-08-01 00:00:00, fecha_fin = 2026-08-31 23:59:59
   │     └── Parsea estados solicitados
   ├── $reporte = FabricaReportes::crear(TipoReporte::Ventas, $filtro)
   ├── Ejecuta consultas de agregación optimizadas con scopes de Eloquent
   └── Renderiza view('admin.reportes.show', compact('reporte', 'filtro', 'estados'))
```

### 3.2 Exportación Eficiente a Excel / CSV (HU-21.1)

```
[Administrador] GET /admin/reportes/pagos/excel?desde=2026-01-01&hasta=2026-08-31
   │
   ▼
[ReporteController::excel()]
   │
   ▼
[ExportadorExcel::exportar($reporte)]
   ├── Construye StreamedResponse con cabeceras:
   │     - 'Content-Type: text/csv; charset=UTF-8'
   │     - 'Content-Disposition: attachment; filename="reporte-pagos-2026-08-20.csv"'
   ├── Imprime Byte Order Mark (BOM) UTF-8 (\xEF\xBB\xBF) para compatibilidad nativa con MS Excel
   ├── Escribe fila de cabeceras: $reporte->columnas()
   ├── Itera colecciones en chunks y escribe mediante fputcsv($handle, $fila)
   └── Retorna descarga directa sin saturar la memoria RAM del servidor
```

### 3.3 Exportación a PDF con Vista Imprimible (HU-21.2)

```
[Administrador] GET /admin/reportes/integral/pdf?periodo=este_ano
   │
   ▼
[ReporteController::pdf()]
   │
   ▼
[ExportadorPdf::exportar($reporte)]
   ├── Carga plantilla 'admin.reportes.pdf' con métricas consolidadas, tablas y pie legal
   └── Retorna StreamedResponse de PDF formateado para impresión en tamaño carta/A4
```

---

## 4. Reglas de Negocio y Métricas Clave

1. **Desacoplamiento Extensible (Open/Closed Principle)**:
   - Toda la lógica matemática y de consulta reside dentro de su clase de reporte correspondiente (`app/Servicios/Reportes/`). Si se requiere un nuevo reporte (ej. *Reporte de Asesores*), solo se crea la clase respectiva y se registra en `TipoReporte` sin tocar el controlador ni los exportadores.
2. **Streaming de Datos para Evitar Memory Limit**:
   - `ExportadorExcel` utiliza `StreamedResponse` y punteros de archivo en memoria (`php://output`). Esto permite exportar cientos de miles de registros históricos sin exceder el `memory_limit` de PHP.
3. **Consolidado Integral**:
   - `ReporteIntegral` calcula métricas transversales del negocio: ingresos brutos de arriendos, volumen de ventas cerradas, índice de conversión de reservas a contratos y tasa de retención de clientes.
