# Contratos de Arriendo

> El administrador emite un contrato formal a partir de una reserva de
> arriendo ya confirmada y pagada, sube el PDF firmado, y el sistema lo
> vence o lo rescinde liberando el inmueble cuando corresponde.

## 1. Qué es este módulo

Un contrato **siempre nace de una reserva confirmada** — no se puede crear
un contrato "suelto" sin pasar antes por todo el flujo de reserva y pago
(ver `Documentación/Trazabilidad/Reservas-y-Pagos.md`). Mientras un
contrato está `Vigente`, el inmueble asociado se considera `Ocupado`
(regla de estado derivado, ver
`Documentación/Trazabilidad/Gestion-de-Inmuebles.md` §6).

Hay un plazo de negocio importante: **7 días naturales** desde que la
reserva se confirmó para emitir el contrato (constante
`Contrato::DIAS_PARA_EMITIR`, RN-18). Pasado ese plazo, esa reserva ya no
puede generar un contrato — hay que rehacer la operación desde cero.

## 2. Mapa de archivos

| Pieza | Archivo |
|---|---|
| Modelo | `app/Models/Contrato.php` |
| Controller | `app/Http/Controllers/Admin/ContratoController.php` |
| Lógica de negocio | `app/Servicios/ContratoService.php` |
| Validación de alta | `app/Http/Requests/Admin/StoreContratoRequest.php` |
| Validación de subir documento | `app/Http/Requests/Admin/DocumentoContratoRequest.php` |
| Quién puede ver/crear/rescindir | `app/Politicas/ContratoPolicy.php` |
| Descarga del PDF | `app/Http/Controllers/DescargaController.php` + `app/Servicios/ArchivoPrivadoService.php` |
| Comando de vencimiento automático | `app/Console/Commands/VencerContratos.php` |
| Vistas | `resources/views/admin/contratos/{index,create,show}.blade.php` |
| Vista del cliente | `resources/views/perfil/mis-arriendos.blade.php` (ver `Documentación/Trazabilidad/Perfil-y-Favoritos.md` §6) |
| Rutas | `routes/web.php` líneas 152-157 (panel), línea 76 (descarga, dentro del grupo `auth` general) |
| Tests | `tests/Feature/ContratoTest.php` |

## 3. Emitir un contrato desde una reserva confirmada

### 3.1 Qué reservas se ofrecen para elegir

`GET /admin/contratos/nuevo` → `ContratoController::create()` — a
diferencia de otros módulos, esta pantalla **sí es una página aparte** (no
un modal), porque el formulario necesita primero listar reservas elegibles
para escoger una. Ese listado lo arma `reservasElegibles()` (método privado
del controller, línea 84): reservas `Confirmada`, sin contrato ya emitido
(`whereDoesntHave('contrato')`), y **dentro del plazo de 7 días** — el
filtro se hace en dos pasos, primero por SQL (estado y ausencia de
contrato) y después en memoria con `->filter(...)` comparando
`confirmadaEn()` contra la fecha límite, porque esa comparación de fechas
no es directa de expresar como condición SQL simple sobre el historial.
**El motivo de este filtrado cuidadoso**: si el formulario mostrara una
reserva fuera de plazo, el envío fallaría igual del lado del servicio — se
prefiere que la persona nunca la vea como opción, en vez de dejarla elegir
algo que se va a rechazar después.

### 3.2 El envío del formulario

`POST /admin/contratos` → `StoreContratoRequest` (número de contrato lo
genera el sistema, no se pide; sí se piden fecha de inicio, fecha de fin
opcional y valor mensual) → `ContratoController::store()` →
`ContratoService::crearDesdeReserva()`:

1. **`asegurarQueAdmiteContrato()`** — repite, del lado del servicio, las
   mismas tres condiciones que ya filtró la pantalla (reserva confirmada,
   sin contrato previo, dentro del plazo de 7 días). Esto es deliberado:
   **nunca hay que confiar en que el formulario ya filtró bien** — alguien
   podría mandar la petición directamente sin pasar por la pantalla.
2. Dentro de una transacción: crea el `Contrato` con
   `Contrato::generarNumero($reserva)` (formato `CON-2026-00042`, año +
   ID de la reserva con ceros a la izquierda) y **cambia el inmueble a
   `Ocupado`** — este es el único lugar del sistema donde una reserva de
   arriendo hace que el inmueble pase a Ocupado (una venta cerrada también
   lo hace, mirar `Documentación/Trazabilidad/Ventas.md`, pero por un
   camino totalmente distinto).
3. Notifica al cliente por sistema y por correo.

## 4. Subir el PDF firmado

`POST /admin/contratos/{contrato}/documento` → `DocumentoContratoRequest`
→ `ContratoController::subirDocumento()` → `ContratoService::adjuntarDocumento()`:
borra el archivo anterior si existía (`ArchivoPrivadoService::eliminar()`)
y guarda el nuevo con `ArchivoPrivadoService::guardar()`.

**Importante — este archivo NO se guarda en el disco público.**
`ArchivoPrivadoService` usa el disco `local` de Laravel
(`storage/app/private/` o `storage/app/` según versión, **no**
`storage/app/public/`), que **no** tiene un symlink accesible desde el
navegador. Esto es a propósito: un contrato firmado es un documento
sensible, no algo que cualquiera con la URL pueda descargar. La única forma
de bajarlo es a través de `DescargaController::contrato()`
(`GET /contratos/{contrato}/descargar`), que primero llama
`$this->authorize('descargar', $contrato)` (`ContratoPolicy`) — solo el
cliente dueño de la reserva o un administrador pueden descargarlo. **Si en
el futuro un contrato "no se puede descargar"**, antes de sospechar del
archivo en sí, revisar la policy — es más probable que sea un problema de
permisos que de almacenamiento.

Este mismo patrón (`ArchivoPrivadoService` + `DescargaController`) lo
reutiliza el módulo de Ventas para las escrituras — ver
`Documentación/Trazabilidad/Ventas.md`.

## 5. Rescindir un contrato

`POST /admin/contratos/{contrato}/rescindir` → `ContratoPolicy::rescindir()`
(solo administrador, solo si está vigente) → `ContratoService::rescindir()`:
cambia el estado a `Rescindido` y libera el inmueble con
`$inmueble->update(['estado' => $inmueble->estadoCalculado()])` — el mismo
patrón de "recalcular en vez de forzar a Disponible" que usa
`ReservaService::liberarInmueble()` (ver
`Documentación/Trazabilidad/Reservas-y-Pagos.md` §5), para no liberar de
más un inmueble que por otro motivo debería seguir ocupado o reservado.

## 6. Vencimiento automático

`app/Console/Commands/VencerContratos.php`, programado cada hora junto con
`reservas:expirar` (`routes/console.php`). Recorre `Contrato::porVencer()`
(vigente, con `fecha_fin` en el pasado) y llama a
`ContratoService::vencer()` por cada uno — mismo efecto que rescindir
(estado cambia, inmueble se libera) pero con un mensaje distinto y
dirigido al staff, no al cliente (un vencimiento es un evento esperado del
calendario, no una decisión administrativa como sí lo es una rescisión).

**A diferencia del prototipo original** (según el comentario del código),
acá el vencimiento también libera el inmueble — el prototipo lo dejaba
"Ocupado" indefinidamente después de vencer, lo cual era un defecto que se
corrigió en esta versión.

## 7. Errores comunes al tocar este módulo

| Síntoma | Causa probable | Dónde mirar |
|---|---|---|
| "No se puede emitir un contrato" en una reserva que parece estar bien | Revisar los 3 requisitos: confirmada, sin contrato previo, y dentro de los 7 días desde la confirmación — `reserva.confirmadaEn()` mira el `HistorialReserva`, no un campo directo | `ContratoService::asegurarQueAdmiteContrato()` |
| El PDF del contrato no se puede descargar | Primero revisar la policy (`ContratoPolicy::descargar`), no el archivo — es más común un problema de permisos que de almacenamiento | `app/Politicas/ContratoPolicy.php` |
| El inmueble sigue "Ocupado" después de que el contrato venció/se rescindió | Confirmar que el cron real corre `reservas:expirar`/`contratos:vencer` (para vencimiento automático) o que se llamó `rescindir()` (no un `update()` directo a la tabla) | `Documentación/Trazabilidad/Trazabilidad-Sistema.md` §2 |
| Un contrato nuevo no aparece como opción para una reserva que sí está confirmada | Puede estar fuera del plazo de 7 días — revisar `reservasElegibles()` en el controller, que filtra antes de mostrar el formulario | `app/Http/Controllers/Admin/ContratoController.php` línea 84 |
