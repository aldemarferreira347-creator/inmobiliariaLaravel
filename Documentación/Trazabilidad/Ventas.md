# Ventas

> El asesor registra el proceso de venta de un inmueble (distinto de una
> reserva de arriendo con pago en línea — esto es más manual, orientado a
> ventas de compraventa acompañadas por un asesor), lo cierra o lo cancela,
> y sube la escritura pública al finalizar.

## 1. Qué es este módulo

A diferencia de "Reservas y Pagos" (que es autoservicio: el cliente reserva
y paga solo, en línea), las ventas las **inicia y lleva un asesor** desde su
panel — hay una persona humana gestionando cada operación de principio a
fin, no un flujo de pago automatizado. Vive bajo el prefijo `/asesor/ventas`,
pero el administrador también puede supervisar todas (no solo las suyas).

## 2. Mapa de archivos

| Pieza | Archivo |
|---|---|
| Modelo | `app/Models/Venta.php` |
| Controller | `app/Http/Controllers/Asesor/VentaController.php` |
| Lógica de negocio | `app/Servicios/VentaService.php` |
| Validación de alta | `app/Http/Requests/Asesor/StoreVentaRequest.php` |
| Quién puede ver/crear/actualizar | `app/Politicas/VentaPolicy.php` |
| Descarga de la escritura | `app/Http/Controllers/DescargaController.php` + `app/Servicios/ArchivoPrivadoService.php` (mismo mecanismo que los contratos, ver `Documentación/Trazabilidad/Contratos.md` §4) |
| Vistas | `resources/views/asesor/ventas/{index,show,partials/modal-registrar}.blade.php` |
| Vista del cliente | `resources/views/perfil/mis-compras.blade.php` (ver `Documentación/Trazabilidad/Perfil-y-Favoritos.md` §6) |
| Rutas | `routes/web.php` líneas 102-107, dentro de `Route::middleware('rol:asesor,administrador')` |
| Tests | `tests/Feature/VentaTest.php` |

## 3. Quién ve qué (la policy hace el trabajo pesado)

`VentaPolicy` (`app/Politicas/VentaPolicy.php`) es corta pero concentra
toda la lógica de visibilidad — vale la pena leerla entera antes de tocar
este módulo:

- **`viewAny`**: cualquiera que no sea cliente (asesor o administrador)
  puede entrar al listado.
- **`view`** (una venta puntual): el administrador ve todas; un asesor solo
  ve las que él registró (`$venta->asesor_id === $usuario->id`); un cliente
  solo ve las suyas como comprador (`$venta->usuario_id === $usuario->id`).
- **`create`**: asesor o administrador.
- **`update`** (cerrar, cancelar, subir escritura — las tres acciones
  comparten esta misma ability): **solo si la venta sigue `EnProceso`**, y
  solo el administrador o el asesor que la registró. Una vez cerrada o
  cancelada, ya no se puede tocar — es un estado final.

En el controller, el filtro de "el asesor solo ve las suyas" también se
aplica **a nivel de consulta**, no solo de policy — en `index()`:

```php
$ventas = Venta::query()
    ->when(! $request->user()->esAdministrador(), fn ($q) => $q->where('asesor_id', $request->user()->id))
    ->latest('fecha_venta')
    ->get();
```

Esto es una doble protección: aunque la policy ya evitaría que un asesor
*abra* la ficha de una venta ajena por URL directa, el listado tampoco se
la muestra para empezar.

## 4. Registrar una venta

Modal en `asesor/ventas/index.blade.php` (mismo patrón `data-modal-abrir` de
siempre) → `POST /asesor/ventas` → `StoreVentaRequest` → `VentaController::store()`
→ `VentaService::registrar()`:

```php
$inmueble = Inmueble::whereKey($datos['inmueble_id'])->lockForUpdate()->firstOrFail();
$this->asegurarQueEsVendible($inmueble);
$venta = Venta::create([...$datos, 'asesor_id' => $asesor->id, 'estado' => EstadoVenta::EnProceso]);
$inmueble->update(['estado' => EstadoInmueble::Reservado]);
```

Mismo patrón de **bloqueo pesimista** (`lockForUpdate()`) que
`ReservaService::solicitar()` — evita que dos asesores inicien una venta
sobre el mismo inmueble al mismo tiempo. A diferencia de una reserva de
arriendo (que no toca el estado del inmueble hasta que el pago se
confirma), **una venta en proceso reserva el inmueble de inmediato**
(HU-14.1) — no hay una etapa de pago intermedia en este flujo, la venta en
sí ya es el compromiso.

## 5. Cerrar una venta

`POST /asesor/ventas/{venta}/cerrar` → `VentaService::cerrar()`: cambia el
estado a `Cerrada` y — este es el detalle importante — el inmueble pasa a
`Ocupado`, no a "Disponible" ni sigue en "Reservado". Una venta cerrada
significa que el inmueble ya tiene dueño nuevo, así que sale del catálogo
de forma permanente (mientras nadie lo vuelva a poner en venta desde el
panel de inmuebles).

## 6. Cancelar una venta

`POST /asesor/ventas/{venta}/cancelar` (exige un motivo) →
`VentaService::cancelar()`: cambia el estado a `Cancelada` y libera el
inmueble con `$inmueble->estadoCalculado()` — mismo patrón de "recalcular
en vez de forzar" que se repite en Reservas y Contratos (ver esos
documentos si no te resulta familiar el porqué).

## 7. Subir la escritura pública

`POST /asesor/ventas/{venta}/escritura` (PDF, máximo 5 MB) →
`VentaService::adjuntarEscritura()` — usa `ArchivoPrivadoService`, el mismo
servicio que los contratos: el archivo va al disco `local` (no público), y
solo se descarga a través de `DescargaController::escritura()`, protegido
por `VentaPolicy::descargarEscritura()` (que además exige que la venta
tenga escritura cargada — `tieneEscritura()`). Ver
`Documentación/Trazabilidad/Contratos.md` §4 para el detalle completo del
mecanismo de almacenamiento privado, es idéntico acá.

## 8. Errores comunes al tocar este módulo

| Síntoma | Causa probable | Dónde mirar |
|---|---|---|
| Un asesor ve ventas de otro asesor en su listado | Revisar el `when()` en `VentaController::index()` — si se quitó por error, el filtro de "solo las mías" desaparece | `app/Http/Controllers/Asesor/VentaController.php` línea 29 |
| No se puede cerrar/cancelar/subir escritura en una venta que "se ve activa" | La venta ya no está `EnProceso` (fue cerrada o cancelada antes) — es un estado final, no se puede reabrir | `VentaPolicy::update()` |
| Dos asesores registraron una venta sobre el mismo inmueble | El `lockForUpdate()` de `VentaService::registrar()` debería impedirlo | `app/Servicios/VentaService.php` línea 32 |
| El inmueble no vuelve a estar disponible tras cancelar una venta | Revisar que se llamó `VentaService::cancelar()` (que sí libera el inmueble) y no un `update()` directo a la tabla `venta` | `app/Servicios/VentaService.php::cancelar()` |
