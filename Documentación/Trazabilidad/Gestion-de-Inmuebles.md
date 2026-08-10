# Gestión de Inmuebles

> Cómo está armado, paso a paso, todo lo relacionado con las propiedades: el
> catálogo público que ve cualquier visitante, y el panel donde el
> administrador las crea, edita, elimina y les sube fotos.
>
> Formato de este documento: cada acción del usuario (un clic, un envío de
> formulario) se explica como una cadena de eslabones — "esto pasa primero,
> que llama a esto, que llama a esto otro" — con el archivo y la línea donde
> vive cada eslabón, para que si algo se rompe sepas exactamente dónde mirar.

## 1. Qué es este módulo

Es el corazón del sistema: la tabla `inmueble` guarda cada propiedad
(apartamento, casa, oficina, etc.) con su precio, ubicación y características.
Dos públicos muy distintos la usan:

- **Cualquier visitante** (sin iniciar sesión) puede ver el catálogo,
  filtrarlo y abrir la ficha de un inmueble.
- **Solo el administrador**, desde el panel (`/admin/inmuebles`), puede
  crear, editar, eliminar inmuebles y gestionar sus fotos.

El **estado** de un inmueble (Disponible / Reservado / Ocupado) casi nunca lo
escribe una persona a mano — lo calcula el sistema mirando si hay una reserva
o un contrato activo sobre esa propiedad. Esto es la regla de negocio más
importante de todo el módulo y se explica en la [sección 6](#6-el-estado-del-inmueble-no-es-un-campo-normal).

## 2. Mapa de archivos

| Pieza | Archivo |
|---|---|
| Modelo | `app/Models/Inmueble.php` |
| Modelo de imagen | `app/Models/ImagenInmueble.php` |
| Controller público (catálogo) | `app/Http/Controllers/InmuebleController.php` |
| Controller del panel (CRUD) | `app/Http/Controllers/Admin/InmuebleController.php` |
| Controller de imágenes del panel | `app/Http/Controllers/Admin/ImagenInmuebleController.php` |
| Validación del formulario | `app/Http/Requests/Admin/InmuebleRequest.php` |
| Validación de filtros del catálogo | `app/Http/Requests/FiltroInmuebleRequest.php` |
| Quién puede crear/editar/eliminar | `app/Politicas/InmueblePolicy.php` |
| Lógica de subir/borrar fotos | `app/Servicios/ImagenInmuebleService.php` |
| Enums relacionados | `app/Enumerados/{TipoInmueble,ModalidadInmueble,EstadoInmueble}.php` |
| Vistas públicas | `resources/views/inmuebles/{inicio,index,show}.blade.php` |
| Vistas del panel | `resources/views/admin/inmuebles/{index,show,edit}.blade.php` y su carpeta `partials/` |
| JS relevante | `resources/js/ui.js` (modales, campos de precio), `resources/js/tabla.js` (la tabla del listado), `resources/js/galeria.js` (carrusel de fotos en la ficha pública) |
| Rutas | `routes/web.php` líneas 35-37 (público) y 125-130 (panel) |
| Tests | `tests/Feature/CatalogoTest.php`, `tests/Feature/Admin/GestionInmueblesTest.php` |

## 3. El modal "Registrar inmueble" — trazabilidad completa

Este es el flujo de alta de un inmueble desde el panel. Lo explico entero,
eslabón por eslabón, porque es el patrón que se repite (con variaciones) en
casi todos los formularios del sistema.

### 3.1 El botón que abre el modal

En `resources/views/admin/inmuebles/index.blade.php` hay un botón
`"Registrar inmueble"` con el atributo `data-modal-abrir="modal-inmueble"`.
No hay ningún JavaScript escrito a mano para este botón — el sistema usa un
patrón de "delegación de eventos": `resources/js/ui.js` (función
`iniciarModales()`, línea 40) escucha **todos** los clics de la página, y
cuando detecta uno sobre un elemento con `data-modal-abrir`, le agrega la
clase CSS `is-open` al modal cuyo `id` coincide con el valor de ese atributo.
El modal en sí es el `<div id="modal-inmueble">` definido en
`resources/views/admin/inmuebles/partials/modal-crear.blade.php`. Esto
significa: **si un botón "Registrar X" no abre su modal, lo primero a
revisar es que el `id` del modal y el `data-modal-abrir` del botón sean
idénticos** — es el error más fácil de cometer al copiar este patrón para un
módulo nuevo.

### 3.2 Los campos del formulario

El modal incluye (`@include`) el archivo
`resources/views/admin/inmuebles/partials/campos.blade.php`, que **es el
mismo parcial que usa la pantalla de edición** (`edit.blade.php`) — así se
evita duplicar el HTML de 20 campos en dos sitios. La variable `$inmueble`
llega en `null` cuando es alta y con el modelo real cuando es edición; cada
campo usa `old('campo', $inmueble?->campo)` para: (a) recordar lo que la
persona escribió si hubo un error de validación, o si no, (b) mostrar el
valor guardado si se está editando.

Dos comportamientos dinámicos de este formulario, ambos resueltos en
`resources/js/ui.js`:

- **Mostrar/ocultar precio de venta vs. arriendo según la modalidad**
  (función `sincronizarPrecios`, línea 122): el `<select name="modalidad">`
  tiene el atributo `data-modalidad`; cada `<div class="form-group">` de
  precio tiene `data-precio="venta"` o `data-precio="arriendo"`. Al cambiar
  la modalidad, JS oculta el campo que no aplica y le quita el `required`
  (para que el navegador no bloquee el envío pidiendo un campo que ni
  siquiera se ve).
- El campo **Código** siempre aparece de solo lectura con el texto "Se
  generará automáticamente" — el código real lo asigna el servidor, nunca lo
  escribe una persona (ver [3.4](#34-qué-pasa-en-el-servidor-el-controller)).

### 3.3 El envío del formulario

El `<form>` apunta a `method="POST"` y
`action="{{ route('admin.inmuebles.store') }}"`, con
`enctype="multipart/form-data"` (obligatorio porque el formulario incluye un
`<input type="file" name="imagenes[]" multiple>` para las fotos). Al enviarse,
esto es una petición HTTP normal — no hay `fetch` ni AJAX aquí, el navegador
recarga la página con la respuesta del servidor.

`route('admin.inmuebles.store')` se resuelve en `routes/web.php` línea 125:

```php
Route::resource('inmuebles', AdminInmuebleController::class)->except('create');
```

Esa única línea registra 6 rutas de golpe (el patrón "resource" de Laravel:
`index`, `store`, `show`, `update`, `destroy`, `edit` — se excluye `create`
porque, como vimos, no existe una pantalla aparte para el formulario de alta,
vive dentro del modal del listado). La petición `POST /admin/inmuebles` cae
en el método `store()` del controller.

Antes de llegar al controller, la petición pasa por el middleware del grupo
en el que está esa ruta: `Route::middleware(['auth', 'rol:administrador'])`
(ver `routes/web.php`, alrededor de la línea 120) — si quien envía el
formulario no tiene sesión iniciada o no es administrador, nunca llega al
controller, recibe un 403 antes.

### 3.4 Qué pasa en el servidor: el controller

`app/Http/Controllers/Admin/InmuebleController.php`, método `store()` (línea
30):

```php
public function store(InmuebleRequest $request): RedirectResponse
{
    $inmueble = DB::transaction(function () use ($request) {
        $inmueble = Inmueble::create([
            ...$request->safe()->except('imagenes'),
            'codigo' => Inmueble::generarCodigo(),
        ]);

        $this->imagenes->agregar($inmueble, $request->file('imagenes', []));

        return $inmueble;
    });

    return $this->volverAlListado("Inmueble «{$inmueble->titulo}» registrado con el código {$inmueble->codigo}.");
}
```

Paso a paso:

1. **`InmuebleRequest $request`** — antes de que el método `store()` se
   ejecute siquiera, Laravel instancia esta clase (un "Form Request", ver
   `app/Http/Requests/Admin/InmuebleRequest.php`) y corre su validación
   automáticamente. Si algo falla, la petición nunca llega a `store()`: el
   usuario vuelve al listado con los errores, y el modal se reabre solo
   (gracias al atributo `data-modal-abierto` que la vista agrega
   condicionalmente cuando `$errors->any()` es verdadero — ver
   `modal-crear.blade.php` línea 2, y `iniciarModales()` en `ui.js` línea 65
   que detecta ese atributo al cargar la página).
   - `InmuebleRequest::authorize()` (línea 28) también corre acá:
     `$this->user()?->can('create', Inmueble::class)`, que dispara
     `InmueblePolicy::create()` — solo pasa si el usuario es administrador.
     Esto es una **segunda capa** de protección además del middleware
     `rol:administrador` de la ruta; redundante a propósito.
   - `InmuebleRequest::rules()` (línea 33) define qué es válido: título
     obligatorio, descripción de mínimo 50 caracteres
     (`Inmueble::DESCRIPCION_MINIMA`), el tipo/modalidad/estado deben ser
     valores reales del enum correspondiente, y — importante — el precio de
     venta y el de arriendo son obligatorios **condicionalmente**, según qué
     exija la modalidad elegida (`Rule::requiredIf($this->modalidadElegida()?->exigePrecioVenta())`).
   - El método `after()` (línea 68) agrega una validación extra que no cabe
     en `rules()`: si alguien intentara forzar el estado a "Reservado" u
     "Ocupado" sin que haya una reserva o contrato real que lo respalde,
     `$inmueble->admiteEstado($estado)` (definido en el modelo, ver
     [sección 6](#6-el-estado-del-inmueble-no-es-un-campo-normal)) lo
     rechaza.
   - `prepareForValidation()` (línea 84) limpia los datos *antes* de
     validar: convierte el checkbox de parqueadero a booleano real, y
     convierte un precio vacío o en cero a `null` (porque el campo oculto
     por JS igual viaja en el `POST`, aunque esté vacío).

2. **`Inmueble::generarCodigo()`** (definido en el modelo, línea 280): genera
   un código con el formato `INM-XXXXXX` y, antes de darlo por bueno,
   consulta la base de datos para asegurarse de que no exista ya otro
   inmueble con ese mismo código (`while (static::where('codigo', $codigo)->exists())`).
   Este código nunca lo escribe el formulario — por eso el campo "Código" en
   la vista es de solo lectura.

3. **`Inmueble::create([...])`** — guarda la fila en la tabla `inmueble`.
   `$request->safe()->except('imagenes')` toma **solo** los campos que
   pasaron la validación (nunca datos crudos sin validar) y descarta
   `imagenes` porque esas no son una columna de la tabla `inmueble`, son
   archivos que se procesan aparte en el paso siguiente.

4. **`$this->imagenes->agregar($inmueble, $request->file('imagenes', []))`**
   — delega a `ImagenInmuebleService::agregar()`
   (`app/Servicios/ImagenInmuebleService.php`, línea 33). Este servicio, no
   el controller, es responsable de:
   - Guardar cada archivo físico en `storage/app/public/inmuebles/` (disco
     `public` de Laravel, que se sirve al navegador a través del symlink
     `public/storage` — si ese symlink está roto, esto es exactamente lo que
     falla en silencio, ver `Documentación/QA/QA-Sistema.md` §4.1).
   - Redimensionar la imagen si supera 1600px de ancho (para no servir
     archivos pesados).
   - Crear una fila en `imageninmueble` por cada archivo, marcando la
     **primera** como portada (`es_principal = true`) si el inmueble
     todavía no tenía ninguna imagen.
   - Actualizar la columna `inmueble.imagen` para que apunte siempre a la
     portada actual — así el listado y las tarjetas del catálogo no
     necesitan hacer un `JOIN` con `imageninmueble` para mostrar una foto.

5. **`DB::transaction(...)`** envuelve los pasos 2-4: si guardar las imágenes
   fallara a mitad de camino, el inmueble recién creado también se revierte
   — no queda un registro "a medias" en la base de datos.

6. **`volverAlListado(...)`** (método privado, línea 93) redirige de vuelta a
   `admin.inmuebles.index` con un mensaje flash (`session()->with(['mensaje' => ..., 'tipo' => 'success'])`).
   Ese mensaje lo pinta el componente `<x-flash>` (`resources/views/components/flash.blade.php`),
   presente en el layout del panel.

### 3.5 Resumen visual del camino completo

```
Click en "Registrar inmueble"
  → ui.js: iniciarModales() abre <div id="modal-inmueble">
  → usuario llena resources/views/admin/inmuebles/partials/campos.blade.php
  → ui.js: sincronizarPrecios() muestra/oculta precio venta u arriendo
  → submit POST /admin/inmuebles
     → middleware: auth, rol:administrador           (routes/web.php)
     → InmuebleRequest: authorize() + rules() + after()   (valida y autoriza)
     → Admin\InmuebleController::store()
         → Inmueble::generarCodigo()                  (modelo)
         → Inmueble::create()                          (INSERT en tabla inmueble)
         → ImagenInmuebleService::agregar()            (guarda archivos + INSERT en imageninmueble)
     → redirect a admin.inmuebles.index con mensaje flash
  → <x-flash> muestra "Inmueble «...» registrado con el código INM-..."
```

## 4. Editar un inmueble

Mismo formulario (`campos.blade.php`), pero cargado desde
`edit.blade.php` con `$inmueble` ya lleno. La ruta es
`PUT/PATCH /admin/inmuebles/{inmueble}` → `Admin\InmuebleController::update()`
(línea 61). Diferencias clave con el alta:

- `InmuebleRequest::authorize()` esta vez llama a `can('update', $inmueble)`
  (con el inmueble real, no la clase) — dispara `InmueblePolicy::update()`.
- No se regenera el código: `$inmueble->update($request->safe()->except('imagenes'))`
  solo toca los campos editables.
- Si se suben imágenes nuevas, se **agregan** a las existentes (no las
  reemplaza) — `ImagenInmuebleService::agregar()` es el mismo método que en
  el alta, y sigue las reglas de portada explicadas arriba.
- El campo "Estado" en `campos.blade.php` (línea 110) se vuelve de **solo
  lectura** (`<input type="hidden">` con un badge informativo) en cuanto el
  inmueble tiene un estado que no admite cambio manual — ver
  `$inmueble?->admiteEstado($estado)` en la vista y la
  [sección 6](#6-el-estado-del-inmueble-no-es-un-campo-normal) para
  entender por qué.

## 5. Eliminar un inmueble

Botón "Eliminar" en la tabla del listado → formulario oculto con
`method="DELETE"` (Laravel simula `DELETE` con un campo `_method` porque los
navegadores solo mandan `GET`/`POST` de verdad) → ruta
`DELETE /admin/inmuebles/{inmueble}` → `Admin\InmuebleController::destroy()`
(línea 72).

Antes de borrar nada, dos chequeos:

1. `$this->authorize('delete', $inmueble)` → `InmueblePolicy::delete()`.
2. `$inmueble->tieneReservasActivas()` (modelo, línea 270) — si el inmueble
   tiene alguna reserva en un estado "activo" (ver
   `EstadoReserva::activas()`), **no se borra**: se devuelve al listado con
   un mensaje de error explicando por qué. Esta regla existe para no perder
   el historial de una reserva/pago real solo porque alguien borró el
   inmueble asociado (HU-04.5).

Si pasa ambos chequeos, `ImagenInmuebleService::eliminarTodas($inmueble)`
borra los archivos físicos de `storage/app/public/inmuebles/` (el borrado en
cascada de la base de datos —`ON DELETE CASCADE` en la tabla
`imageninmueble`— limpia las filas, pero **no toca el disco**, por eso el
servicio lo hace explícitamente antes de `$inmueble->delete()`).

## 6. El estado del inmueble no es un campo normal

Esta es la regla de negocio más importante de todo el módulo (HU-09) y la
causa más probable de confusión si alguien "arregla" este módulo sin
conocerla:

- La columna `inmueble.estado` **sí existe** en la base de datos y sí se
  puede leer/escribir directamente.
- Pero el valor "correcto" en cualquier momento se calcula con
  `Inmueble::estadoCalculado()` (modelo, línea 193), que **no lee la
  columna** — mira si hay una reserva o un contrato relacionados:
  - **Ocupado**: hay un contrato de arriendo vigente, o una venta con estado
    "Cerrada".
  - **Reservado**: hay una reserva viva, o una reserva confirmada cuyo
    contrato todavía no se emitió, o una venta "En proceso".
  - **Disponible**: ninguna de las anteriores.
- `admiteEstado(EstadoInmueble $estado)` (línea 250) es lo que usa el
  formulario para decidir si te deja escribir el estado a mano: "Disponible"
  siempre se permite (para poder liberar un inmueble manualmente), pero
  "Reservado" y "Ocupado" solo se aceptan si los datos relacionales
  realmente lo justifican — si no, el formulario ni siquiera muestra un
  `<select>`, muestra el badge de solo lectura.

**Si en el futuro el estado de un inmueble "parece mal"** (por ejemplo,
sigue en "Disponible" cuando debería estar "Reservado", o al revés), el
primer lugar a mirar **no es la columna `estado`** sino las reservas
(`Reserva::where('inmueble_id', ...)`) y contratos asociados — el problema
casi siempre está en el flujo que crea/cambia esas reservas o contratos
(ver `Documentación/Trazabilidad/Reservas-y-Pagos.md` y
`Documentación/Trazabilidad/Contratos.md`), no en este módulo.

## 7. El catálogo público (sin sesión)

`InmuebleController` (raíz de `Http/Controllers`, no `Admin/`) sirve tres
rutas sin autenticación:

- `GET /` → `inicio()` → vista `inmuebles/inicio.blade.php` (portada con los
  inmuebles más recientes).
- `GET /inmuebles` → `index()` → vista `inmuebles/index.blade.php`. Recibe
  los filtros por query string (`?ciudad=...&modalidad=...`), validados por
  `FiltroInmuebleRequest`, y se los pasa a `Inmueble::filtrar()` (scope del
  modelo, línea 120) — ver esa función para la lista completa de filtros
  admitidos y cómo se combinan (todos con `AND` entre sí).
- `GET /inmuebles/{inmueble}` → `show()` → vista `inmuebles/show.blade.php`,
  la ficha completa con galería (JS: `resources/js/galeria.js`), precio,
  botón de reservar/favorito/agendar cita (cada uno visible u oculto según
  el estado calculado y si hay sesión iniciada).

Ninguna de estas tres rutas pasa por el middleware `rol` — son públicas a
propósito. Si alguna vez alguien "protege por error" este controller con
`rol:administrador`, el catálogo entero deja de ser visible para clientes
sin sesión, que es el público principal del sitio.

## 8. Errores comunes al tocar este módulo

| Síntoma | Causa probable | Dónde mirar |
|---|---|---|
| El modal no se abre | El `id` del `<div class="modal-overlay">` no coincide con el `data-modal-abrir` del botón | `resources/views/admin/inmuebles/partials/modal-crear.blade.php` vs. el botón en `index.blade.php` |
| El formulario de alta/edición no valida un campo nuevo que agregaste | Falta agregarlo a `InmuebleRequest::rules()` — el HTML solo no protege nada, todo pasa por ahí | `app/Http/Requests/Admin/InmuebleRequest.php` |
| Subís una imagen y no aparece en la ficha pública | Revisar primero que `public/storage` sea un symlink válido (`ls -la public/storage`); si el enlace apunta a una ruta que no existe, toda imagen subida da 404 | `Documentación/QA/QA-Sistema.md` §4.1 |
| El precio no se guarda / se guarda como 0 | La modalidad decide qué precio es obligatorio; revisar `InmuebleRequest::limpiarPrecio()` y el JS `sincronizarPrecios()` en `ui.js` — si el campo estaba oculto por JS pero el `required` no se quitó, el navegador bloquea el envío | `app/Http/Requests/Admin/InmuebleRequest.php` + `resources/js/ui.js` |
| El estado no deja seleccionarse en el formulario | Es esperado si el inmueble tiene una reserva o contrato activo — no es un bug, es `admiteEstado()` protegiendo la coherencia del dato | Ver [sección 6](#6-el-estado-del-inmueble-no-es-un-campo-normal) |
| Un inmueble no se deja eliminar | Tiene reservas activas — mensaje explícito, no es un error silencioso | `Admin\InmuebleController::destroy()` + `Inmueble::tieneReservasActivas()` |
