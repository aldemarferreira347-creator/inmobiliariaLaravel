# Reportes

> El administrador consulta cifras de reservas, pagos, contratos y ventas
> por periodo, con la posibilidad de exportar cada uno a Excel o PDF. Es el
> módulo más "de patrón de diseño" del sistema — vale la pena entender la
> estructura antes de agregar un reporte nuevo.

## 1. Qué es este módulo

Hay 5 tipos de reporte (`TipoReporte`: Reservaciones, Pagos, Contratos,
Ventas, Integral), y **los 5 comparten exactamente la misma estructura**:
cada uno sabe describirse a sí mismo (sus columnas, sus filas, sus cifras
resumen), y a partir de esa única descripción se generan **tres cosas
distintas** sin duplicar código: la pantalla en el navegador, el archivo
Excel, y el PDF. Si un reporte "se ve distinto" entre la pantalla y el
Excel, el problema no puede estar en cómo se pinta cada uno — están
generados de los mismos datos; hay que revisar la clase del reporte en sí.

## 2. Mapa de archivos

| Pieza | Archivo |
|---|---|
| Controller | `app/Http/Controllers/Admin/ReporteController.php` |
| Clase base de todo reporte | `app/Servicios/Reportes/Reporte.php` (abstracta) |
| Fábrica que elige la clase según el tipo | `app/Servicios/Reportes/FabricaReportes.php` |
| Los 5 reportes concretos | `app/Servicios/Reportes/Reporte{Reservaciones,Pagos,Contratos,Ventas,Integral}.php` |
| Criterios de filtro (fechas, estado) | `app/Servicios/Reportes/FiltroReporte.php` |
| Exportador a Excel | `app/Servicios/Reportes/ExportadorExcel.php` (usa PhpSpreadsheet) |
| Exportador a PDF | `app/Servicios/Reportes/ExportadorPdf.php` (usa Dompdf) |
| Enum de tipos | `app/Enumerados/TipoReporte.php` |
| Enum de periodo | `app/Enumerados/PeriodoReporte.php` |
| Vistas | `resources/views/admin/reportes/{index,show,pdf,partials/filtros}.blade.php` |
| Rutas | `routes/web.php` líneas 163-166 |
| Tests | `tests/Feature/ReporteTest.php` |

## 3. La clase abstracta `Reporte` — el contrato que todos cumplen

`app/Servicios/Reportes/Reporte.php` define 4 métodos que cada reporte
concreto **debe** implementar:

```php
abstract public function tipo(): TipoReporte;
abstract public function columnas(): array;   // encabezados de la tabla
abstract public function filas(): Collection; // los datos, ya formateados
abstract public function resumen(): array;    // las cifras destacadas arriba
```

Por ejemplo, `ReporteReservaciones` (`app/Servicios/Reportes/ReporteReservaciones.php`)
implementa `filas()` consultando `Reserva::query()` con los filtros de
fecha/estado aplicados, y devuelve cada fila como un array plano ya
formateado para mostrar (`$reserva->codigo_reserva`, la fecha ya
formateada como texto, `$reserva->inmueble->titulo`, etc. — **no** devuelve
objetos Eloquent crudos, porque tanto la vista Blade como el Excel y el PDF
consumen ese mismo array indistintamente).

`columnasNumericas()` (línea 31 de la clase base, con implementación por
defecto vacía) devuelve los índices de columna que son importes — así
`ExportadorExcel` sabe cuáles formatear como moneda y sumar, sin que cada
reporte tenga que repetir esa lógica de formato.

## 4. Agregar un reporte nuevo (guía práctica)

Si en el futuro hace falta un sexto tipo de reporte, estos son los pasos,
en orden:

1. Agregar el caso nuevo a `App\Enumerados\TipoReporte`.
2. Crear la clase `ReporteLoQueSea extends Reporte` en
   `app/Servicios/Reportes/`, implementando los 4 métodos abstractos.
3. Agregar el `case` correspondiente al `match()` de
   `FabricaReportes::crear()` (y a `estadosDe()` si el reporte tiene un
   filtro de estado).
4. **No hace falta tocar nada de `ExportadorExcel`, `ExportadorPdf`, ni las
   vistas genéricas** — todos consumen la interfaz de `Reporte`, no cada
   tipo concreto. Esa es la razón de ser de este diseño.

## 5. El flujo de una petición: pantalla, Excel y PDF nacen del mismo lugar

```
GET /admin/reportes/{tipo}
  → FiltroReporte::desdePeticion($request)      (parsea query string: periodo/desde/hasta/estado)
  → FabricaReportes::crear($tipo, $filtro)        (resuelve a la clase concreta correcta)
  → ReporteController::show()
      → vista admin/reportes/show.blade.php       (pinta columnas()/filas()/resumen())

GET /admin/reportes/{tipo}/excel
  → mismo FiltroReporte + FabricaReportes
  → ExportadorExcel::exportar($reporte)           (arma el .xlsx con branding, formato moneda, autofiltro)

GET /admin/reportes/{tipo}/pdf
  → mismo FiltroReporte + FabricaReportes
  → ExportadorPdf::exportar($reporte)
      → renderiza admin/reportes/pdf.blade.php   (una vista Blade normal, pero pensada para imprimir)
      → Dompdf convierte ese HTML a PDF
```

**El PDF no es un diseño aparte** — reutiliza una vista Blade
(`admin/reportes/pdf.blade.php`) que recibe el mismo objeto `$reporte`
que la pantalla normal. Si el PDF "se ve mal", el problema suele estar en
CSS que Dompdf no soporta (no es un navegador completo, tiene soporte
limitado de CSS moderno) más que en los datos.

## 6. `FiltroReporte` — cómo se decide el rango de fechas

Dos formas de acotar el periodo, y una manda sobre la otra:

- **Pestañas de periodo** (`PeriodoReporte`: Semana, Mes, Año) — rápido,
  predefinido.
- **Rango explícito** (`desde`/`hasta` en el formulario) — si el
  administrador escribe fechas concretas, **esas mandan**, sin importar qué
  pestaña esté seleccionada (`usaRangoPropio()`, y el método `inicio()`/
  `fin()` prioriza `$this->desde`/`$this->hasta` sobre el periodo con el
  operador `??`).

`comoParametros()` (línea 67) reconstruye el filtro actual como query
string — se usa para que los enlaces "Exportar Excel"/"Exportar PDF" en
pantalla lleven exactamente los mismos filtros que el usuario ya aplicó, en
vez de exportar sin filtrar.

## 7. Seguridad de los exportadores

`ExportadorPdf` configura Dompdf explícitamente con
`isPhpEnabled: false` e `isRemoteEnabled: false` (`Options`, línea 19-22)
— esto **desactiva a propósito** la capacidad de Dompdf de ejecutar código
PHP embebido en el HTML o de ir a buscar recursos remotos (imágenes por
URL externa, etc.) al renderizar el PDF. Es una decisión de seguridad
explícita, documentada en el comentario del archivo: sin esto, cualquier
dato que terminara en el HTML del reporte (por ejemplo, un título de
inmueble con contenido malicioso) podría, en teoría, ejecutarse como código
al generar el PDF.

## 8. Errores comunes al tocar este módulo

| Síntoma | Causa probable | Dónde mirar |
|---|---|---|
| Un reporte nuevo "no aparece" en el panel | Falta agregar el `case` a `TipoReporte` y al `match()` de `FabricaReportes::crear()` — sin el segundo, `FabricaReportes` lanza un error de `match` no exhaustivo | `app/Enumerados/TipoReporte.php` + `app/Servicios/Reportes/FabricaReportes.php` |
| El Excel y la pantalla muestran números distintos | No debería pasar — ambos leen `filas()`/`resumen()` del mismo objeto `Reporte`; si difieren, revisar si el filtro (`FiltroReporte`) se está reconstruyendo igual en ambas rutas | `app/Http/Controllers/Admin/ReporteController.php` (confirmar que `excel()`/`show()` usan el mismo `FiltroReporte::desdePeticion()`) |
| El PDF sale con el diseño roto (desalineado, sin estilos) | Dompdf no soporta CSS moderno completo (flexbox/grid limitado) — revisar `admin/reportes/pdf.blade.php`, puede necesitar CSS más simple que el de la pantalla normal | `resources/views/admin/reportes/pdf.blade.php` |
| El filtro de fechas "no respeta" la pestaña de periodo elegida | Es esperado si además hay fechas explícitas cargadas — un rango propio siempre gana sobre la pestaña, ver `FiltroReporte::inicio()`/`fin()` | `app/Servicios/Reportes/FiltroReporte.php` |
