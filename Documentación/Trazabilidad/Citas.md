# Citas de Visita

> Un cliente agenda visitar un inmueble en una fecha/hora concreta, el
> administrador asigna un asesor, el asesor va, marca la visita como
> realizada y deja sus observaciones. Es el módulo con más participantes
> distintos (cliente, administrador, asesor) tocando el mismo registro en
> etapas diferentes.

## 1. Qué es este módulo

Tiene **tres controllers** para el mismo modelo (`Cita`), uno por cada rol
que interactúa con ella:

- `app/Http/Controllers/CitaController.php` — el **cliente**: solicita,
  cancela, consulta sus citas.
- `app/Http/Controllers/Admin/CitaController.php` — el **administrador**:
  ve todas las citas sin asignar, asigna un asesor.
- `app/Http/Controllers/Asesor/CitaController.php` — el **asesor**: ve las
  que le asignaron, marca como realizada y registra observaciones.

Además hay un cuarto controller, `Admin/FranjaController.php`, que no toca
citas directamente — configura **en qué horarios** se pueden agendar.

## 2. Mapa de archivos

| Pieza | Archivo |
|---|---|
| Modelo | `app/Models/Cita.php` |
| Historial de cambios | `app/Models/CitaHistorial.php` |
| Observaciones del asesor | `app/Models/ObservacionCita.php` |
| Configuración de franjas horarias | `app/Models/ConfigFranjaCita.php` |
| Controller cliente | `app/Http/Controllers/CitaController.php` |
| Controller admin | `app/Http/Controllers/Admin/CitaController.php` |
| Controller franjas | `app/Http/Controllers/Admin/FranjaController.php` |
| Controller asesor | `app/Http/Controllers/Asesor/CitaController.php` |
| Lógica de negocio | `app/Servicios/CitaService.php` |
| Validaciones | `app/Http/Requests/SolicitarCitaRequest.php`, `app/Http/Requests/Admin/AsignarCitaRequest.php`, `app/Http/Requests/Admin/ActualizarFranjaRequest.php`, `app/Http/Requests/Asesor/RegistrarObservacionRequest.php` |
| Quién puede ver/cancelar | `app/Politicas/CitaPolicy.php` |
| Vistas | `resources/views/citas/{index,partials/modal-solicitar}.blade.php`, `resources/views/admin/citas/{index,show}.blade.php`, `resources/views/admin/franjas/index.blade.php`, `resources/views/asesor/citas/{index,show}.blade.php` |
| JS | `resources/js/citas.js` (carga las horas disponibles por AJAX) |
| Rutas | `routes/web.php` líneas 69-73 (cliente), 141-145 (admin, incluye franjas), 109-113 (asesor) |
| Tests | `tests/Feature/CitaTest.php` |

## 3. Solicitar una visita — trazabilidad completa

### 3.1 El formulario y el selector de hora dinámico

El botón "Agendar visita" en la ficha del inmueble abre
`resources/views/citas/partials/modal-solicitar.blade.php`. A diferencia de
otros formularios del sistema, **el selector de hora no viene precargado**
— empieza deshabilitado ("Elige una hora") porque las horas disponibles
dependen de la fecha que se elija y de qué otras citas ya existan ese día
para ese mismo inmueble.

`resources/js/citas.js` escucha el cambio del campo de fecha
(`#fecha_cita`) y dispara un `fetch()` a
`GET /citas/franjas-disponibles?inmueble_id=...&fecha=...` — esta es la
**única llamada AJAX real de tipo "autocompletar"** en gran parte del
sistema (la mayoría de los formularios son envíos de página completa). El
controller (`CitaController::franjasDisponibles()`) responde JSON con la
lista de horas libres, calculada por
`ConfigFranjaCita::disponiblesPara()` (`app/Models/ConfigFranjaCita.php`,
línea 82):

1. Busca la franja configurada para el día de la semana de esa fecha
   (`delDia()`) — si no hay ninguna franja activa ese día (por ejemplo,
   domingo, si nunca se activó), devuelve un arreglo vacío y el JS muestra
   "Sin horarios disponibles".
2. Genera todas las horas posibles entre `hora_inicio` y `hora_fin` con
   saltos de `intervalo_minutos` (por defecto cada 30 minutos).
3. Le resta las horas que ya tienen una cita `Pendiente` o `Asignada` **para
   ese mismo inmueble** en esa fecha.

### 3.2 El envío

`POST /citas` → `SolicitarCitaRequest` → `CitaController::store()` →
`CitaService::solicitar()`:

1. **`asegurarQueEsVisitable()`** — el inmueble no puede estar `Ocupado`
   (si está Reservado sí se puede visitar; si ya tiene dueño/inquilino, no
   tiene sentido agendar una visita), y la hora elegida tiene que caer
   **exactamente** sobre un slot válido de la franja configurada
   (`ConfigFranjaCita::esHoraValida()`) — esto protege contra alguien que
   arme la petición a mano saltándose el selector del formulario y mandando
   una hora arbitraria como las 3:07 AM.
2. Verifica que el cliente no tenga ya **otra** cita activa para ese mismo
   inmueble (`Cita::activaDeClientePorInmueble()`, scope del modelo) —
   HU-27.3, evita citas duplicadas.
3. Dentro de una transacción, **vuelve a comprobar con bloqueo pesimista**
   (`Cita::ocupaFranja(...)->lockForUpdate()->exists()`) que ese slot siga
   libre — el mismo patrón de "doble chequeo, uno antes y uno con lock
   dentro de la transacción" que usan Reservas y Ventas, para blindarse
   contra dos clientes agendando la misma hora al mismo tiempo.
4. Crea la `Cita` en estado `Pendiente`, **sin asesor asignado todavía**
   (`asesor_id` queda `null`).
5. Notifica al staff — alguien tiene que asignarle un asesor.

## 4. El administrador asigna un asesor

`GET /admin/citas` → `Admin\CitaController::index()` separa las citas en
dos grupos para la vista: las `sinAsignar()` (scope del modelo `Cita`) y
las que ya tienen asesor, agrupadas por asesor
(`->groupBy('asesor_id')`) — así el panel muestra de un vistazo tanto lo
pendiente de repartir como la carga de trabajo de cada asesor.

`POST /admin/citas/{cita}/asignar` → `AsignarCitaRequest` →
`CitaService::asignar()`:

- `$cita->estado->admiteAsignacion()` — solo se puede asignar (o
  **reasignar**, si ya tenía un asesor y se cambia por otro) mientras la
  cita no haya avanzado más allá de ese punto.
- Verifica que el usuario elegido sea realmente un asesor activo
  (`$asesor->esAsesor() && $asesor->estaActivo()`) — protege contra un
  formulario manipulado que intente "asignar" a un cliente o a un asesor
  desactivado.
- Registra el cambio en `CitaHistorial` (una tabla de bitácora aparte,
  distinta del historial de reservas — cada módulo con transiciones de
  estado complejas tiene la suya) con la etiqueta `ASIGNADA` o `REASIGNADA`
  según corresponda.
- Notifica al asesor.

## 5. El asesor completa la visita

`GET /asesor/citas` → lista solo `$request->user()->citasAsignadas()`, con
un filtro opcional por estado. `POST /asesor/citas/{cita}/observacion` →
`RegistrarObservacionRequest` (exige texto no vacío, HU-12.2) →
`CitaService::marcarRealizada()`:

- Solo funciona si la cita está exactamente `Asignada` — no se puede
  "completar" una que sigue `Pendiente` (sin asesor) ni una ya `Realizada`.
- `ObservacionCita::guardarPara()` — guarda (o actualiza, si ya existía) el
  texto de la visita en su propia tabla (`observacioncita`), separada de
  `cita` para no mezclar el registro operativo con el contenido narrativo
  de la visita.
- Notifica al staff y al cliente.

`PATCH /asesor/citas/{cita}/observacion` (ruta distinta, mismo Request) →
`CitaService::editarObservacion()` — permite corregir el texto **después**
de haber marcado la cita como realizada, sin cambiar el estado de nuevo.

## 6. Cancelar una cita

Solo el cliente dueño puede cancelar la suya (`CitaPolicy::cancelar`),
mientras siga en un estado no final. `CitaService::cancelar()` cambia el
estado a `Cancelada` y, si ya tenía un asesor asignado, lo notifica.

## 7. Configurar las franjas horarias

`GET /admin/franjas` → `FranjaController::index()` — una fila por día de la
semana (`ConfigFranjaCita::DIAS_SEMANA`, empezando en lunes para mostrar en
el panel, aunque la columna de la base de datos guarda los días en el
orden que usa PHP/Carbon — domingo primero, ver `ConfigFranjaCita::DIAS`
línea 24 vs. 27, **son dos listas distintas a propósito**: una para
almacenamiento/cálculo, otra solo para el orden de la interfaz).

`POST /admin/franjas` → `ActualizarFranjaRequest` →
`ConfigFranjaCita::guardarPara()` — usa `updateOrCreate` sobre
`dia_semana`, así que **no hay un botón "crear" separado de "editar"**: si
el día no tenía fila, se crea; si ya tenía, se actualiza. Si un día no
tiene ninguna fila o su fila tiene `activo = false`, ese día completo queda
sin horarios disponibles para agendar (efecto visible en
`ConfigFranjaCita::delDia()`, que solo busca franjas `activo = true`).

## 8. Errores comunes al tocar este módulo

| Síntoma | Causa probable | Dónde mirar |
|---|---|---|
| El selector de hora nunca se llena | Revisar la consola del navegador por errores de `fetch` a `/citas/franjas-disponibles`, y que `data-franjas-url` esté bien puesto en el formulario | `resources/js/citas.js`, `resources/views/citas/partials/modal-solicitar.blade.php` |
| "No hay horarios disponibles" para un día que debería tenerlos | Revisar `/admin/franjas` — ese día puede no tener fila, o estar desactivado (`activo = false`) | `app/Models/ConfigFranjaCita.php::delDia()` |
| Dos clientes agendaron la misma hora para el mismo inmueble | El doble chequeo con `lockForUpdate()` en `CitaService::solicitar()` debería impedirlo | `app/Servicios/CitaService.php` línea 42 |
| Un asesor no puede marcar una visita como realizada | Revisar que la cita esté exactamente en estado `Asignada` — no funciona sobre `Pendiente` ni sobre una ya `Realizada` | `CitaService::marcarRealizada()` |
| Se puede asignar una cita a alguien que no es asesor, o a un asesor inactivo | No debería poder pasar — revisar que no se haya quitado la verificación `esAsesor() && estaActivo()` | `app/Servicios/CitaService.php::asignar()` |
