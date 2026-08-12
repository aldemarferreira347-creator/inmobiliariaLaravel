# Perfil de Usuario y Favoritos

> Todo lo que un usuario logueado (cliente, asesor o administrador) puede
> hacer sobre su propia cuenta: ver y editar sus datos, cambiar su foto,
> guardar inmuebles favoritos, y consultar su historial de arriendos/compras
> si es cliente.

## 1. Qué es este módulo

A diferencia de "Gestión de Inmuebles" o "Autenticación", este módulo no
tiene una regla de negocio compleja — es sobre todo formularios simples que
actualizan la fila del propio usuario en la tabla `usuario`, más una tabla
puente (`favorito`) para la relación muchos-a-muchos con `inmueble`.

## 2. Mapa de archivos

| Pieza | Archivo |
|---|---|
| Modelo | `app/Models/User.php` |
| Controller de perfil | `app/Http/Controllers/PerfilController.php` |
| Controller de favoritos | `app/Http/Controllers/FavoritoController.php` |
| Validación de datos del perfil | `app/Http/Requests/PerfilUpdateRequest.php` |
| Validación de la foto | `app/Http/Requests/FotoPerfilRequest.php` |
| Guardado/borrado de la foto | `app/Servicios/AvatarService.php` |
| Vistas | `resources/views/perfil/edit.blade.php` (página única) + `resources/views/perfil/partials/{modal-password,modal-arriendos,modal-compras,modal-tarjetas}.blade.php` |
| Vistas heredadas (ya no enlazadas desde la UI, ver §3.1) | `resources/views/perfil/{favoritos,mis-arriendos,mis-compras,cambiar-password,tarjetas/index}.blade.php` |
| JS | `resources/js/perfil.js`, `resources/js/tarjetas.js` (montaje diferido de Stripe, ver §3.1) |
| Rutas | `routes/web.php`, dentro del grupo `Route::middleware('auth')`, líneas 55-79 |
| Tests | `tests/Feature/PerfilTest.php` |

## 3. Editar los datos del perfil

`GET /perfil` → `PerfilController::edit()` (línea 36) pasa a la vista el
usuario autenticado (`$request->user()`, que Laravel resuelve solo a partir
de la sesión — no hay que buscarlo a mano), el total de favoritos, **y
además** las reservas de arriendo, las ventas y las tarjetas guardadas — ver
§3.1, porque esta única vista ahora también alimenta los modales de las
otras secciones de la cuenta.

La sección "Mi perfil" en sí **no usa un modal** — usa un patrón distinto:
dos bloques (`#vista-lectura` y `#form-editar`), uno visible y el otro
oculto con la clase `hidden`. El botón `[data-perfil-editar]` alterna cuál
se ve, resuelto en `resources/js/perfil.js` (`iniciarEdicion()`, línea 6).
Si una edición falla la validación, la vista marca el formulario con
`data-abierto="si"` (ahora acotado a los campos propios de este formulario
con `$errors->hasAny([...])`, para no reabrirse por error si falló un modal
distinto en la misma página) y el JS lo detecta al cargar la página para
reabrirlo automáticamente.

`PATCH /perfil` → `PerfilController::update()` (línea 59) →
`PerfilUpdateRequest`. **Importante:** el correo y el número de documento
**no están** en las reglas de validación de este formulario — es
intencional (HU-25, ver el comentario en el propio archivo): esos dos datos
identifican la cuenta y son inmutables desde el perfil. Si en el futuro
alguien pide "que el cliente pueda cambiar su correo desde el perfil", este
es el archivo a tocar, y hay que decidir qué pasa con el email de
verificación/login si se habilita.

### 3.1 Cambiar contraseña / Mis arriendos / Mis compras / Mis tarjetas — ahora son modales

**Cambio de arquitectura importante:** hasta antes de esta pasada, cada una
de estas cuatro secciones era una vista Blade separada a la que el sidebar
del perfil te llevaba con un `<a href>` normal — es decir, cambiar de
sección recargaba la página entera. Eso generaba un salto brusco de
scroll/layout al navegar, y el requisito de producto (HU-25) es que el
perfil nunca redirija a otra vista.

**Ahora las cuatro abren como modal sobre `/perfil`, sin cambiar de URL:**

- `resources/views/perfil/partials/sidebar.blade.php` — los cuatro enlaces
  del menú son ahora `<button data-modal-abrir="modal-...">` en vez de
  `<a href="...">`. El sistema de modales genérico
  (`resources/js/ui.js::iniciarModales()`) los abre/cierra.
- El contenido de cada modal vive en su propio parcial
  (`perfil/partials/modal-{password,arriendos,compras,tarjetas}.blade.php`),
  incluido directamente en `perfil/edit.blade.php`. Los datos que antes
  cargaba cada controller por separado (`misArriendos()`, `misCompras()`,
  `MetodoPagoController::index()`) ahora los carga **todos de una vez**
  `PerfilController::edit()` (línea 36), porque las cuatro secciones están
  en la misma página.
- Los formularios dentro de cada modal siguen apuntando a las mismas rutas
  de siempre (`password.update`, `perfil.tarjetas.store`,
  `perfil.tarjetas.destroy`) — al enviarse, Laravel redirige de vuelta a
  `perfil.edit` (o hace `back()`, que cae en la misma URL porque ahí es
  donde se envió el formulario), así que la página nunca cambia de sitio.
  Si falla la validación (ej. contraseña actual incorrecta), el modal
  correspondiente se reabre solo con `data-modal-abierto`, resuelto con
  `$errors->hasAny([...campos propios del modal...])` para no chocar con
  los otros formularios de la misma página.
- **Borrar una tarjeta** es la única acción de este grupo con
  confirmación + recarga completa (`data-confirmar` + envío normal): para
  que el modal de tarjetas se vea abierto otra vez después del reload, el
  controller (`MetodoPagoController::destroy()`) manda
  `session(['reabrirModal' => 'modal-tarjetas'])` junto con el flash, y el
  parcial del modal lo lee para agregarse `data-modal-abierto`.
- **El Stripe Card Element se monta perezoso.** Antes se montaba en
  `DOMContentLoaded` como cualquier otro widget; ahora, si se monta dentro
  de un modal que empieza oculto (`display: none`), Stripe.js lo renderiza
  roto/colapsado. `resources/js/tarjetas.js` ahora espera al primer clic en
  `[data-modal-abrir="modal-tarjetas"]` para montarlo, una sola vez.

**Las rutas y vistas viejas siguen existiendo** (`mis-arriendos`,
`mis-compras`, `perfil/tarjetas` GET, `cambiar-password` GET) — deliberado,
para no romper ningún enlace directo/marcador guardado, pero **ya no las
enlaza ninguna parte de la interfaz**. Si en el futuro se decide eliminarlas
del todo, hay que borrar también sus vistas Blade y los métodos
`PerfilController::misArriendos()/misCompras()` y
`MetodoPagoController::index()`.

**Transición de apertura/cierre:** se agregó una animación corta
(`opacity`/`scale` con `@starting-style`) a la clase genérica `.modal-box` /
`.modal-reserva-box` en `resources/css/app.css` — no es específica de
perfil, beneficia a **todos** los modales del sistema (reservas, citas,
alta de usuario, etc.), pero nació de resolver el "salto brusco" que se
pedía arreglar aquí.

## 4. Cambiar la foto de perfil

Dos rutas distintas, sin modal ni confirmación — se envían solas apenas se
elige un archivo:

- `POST /perfil/foto` → `PerfilController::actualizarFoto()` (línea 34) →
  `FotoPerfilRequest` valida que sea una imagen válida y de tamaño
  razonable → `AvatarService::reemplazar()`
  (`app/Servicios/AvatarService.php`, línea 20): guarda el archivo nuevo en
  `storage/app/public/avatares/`, borra el archivo anterior si existía, y
  actualiza `usuario.foto_url`.
- `DELETE /perfil/foto` → `PerfilController::eliminarFoto()` (línea 41) →
  `AvatarService::eliminar()`: borra el archivo y deja `foto_url` en `null`.

**El envío es automático al elegir el archivo**, no hay botón "Guardar
foto": `perfil.js` (`iniciarFoto()`, línea 22) escucha el evento `change`
del `<input type="file">`, muestra una vista previa instantánea con
`URL.createObjectURL(archivo)` (esto pasa en el navegador, antes de que el
archivo viaje al servidor) y llama `formulario.submit()` de inmediato.

**Sin foto propia**, `User::getFotoAttribute()` (`app/Models/User.php`,
línea 208) genera un avatar con las iniciales usando el servicio externo
`ui-avatars.com` — el mismo patrón de "placeholder generado por un servicio
externo" que usa `Inmueble::urlDeImagen()` para inmuebles sin foto (ver
`Documentación/Trazabilidad/Gestion-de-Inmuebles.md` y
`Documentación/QA/QA-Sistema.md` §4.2). Si algún día se quiere quitar esa
dependencia externa, es en este método donde hay que generar el avatar
localmente (por ejemplo, con GD, igual que se hizo para las imágenes de
inmuebles sembradas).

## 5. Favoritos

`FavoritoController` es deliberadamente pequeño:

- `GET /favoritos` → `index()` lista `$request->user()->favoritos()`
  (relación `belongsToMany` definida en `User.php` línea 83, contra la tabla
  puente `favorito`).
- `POST /favoritos/{inmueble}` → `toggle()` (línea 22) usa el método
  `toggle()` de Eloquent sobre la relación: si el inmueble **no** estaba en
  la lista, lo agrega; si **ya** estaba, lo quita. Una sola ruta para las dos
  acciones — el corazón (ícono) en la ficha del inmueble y en las tarjetas
  del catálogo dispara siempre el mismo `POST`, sin necesitar saber de
  antemano si va a agregar o quitar.

**Regla de borrado en cascada:** la tabla `favorito` tiene
`ON DELETE CASCADE` contra `inmueble` — si un inmueble se elimina, sus
favoritos desaparecen solos, sin que este controller tenga que hacer nada
(HU-18.4). Si alguna vez un favorito "fantasma" apunta a un inmueble que ya
no existe, eso indicaría que la migración de la tabla `favorito` perdió esa
restricción — revisar `database/migrations/2026_07_25_000006_create_favorito_table.php`.

## 6. Mis arriendos / Mis compras (historial del cliente)

La consulta vive **duplicada a propósito** en dos sitios desde el cambio de
§3.1 — no es descuido, es que hay dos caminos de acceso distintos:

- `PerfilController::edit()` (línea 36) arma `reservas` y `ventas` con la
  misma consulta, para alimentar los modales "Mis arriendos"/"Mis compras"
  de `/perfil`, que es el camino real que usa la interfaz hoy.
- `misArriendos()` (línea 86) y `misCompras()` (línea 101) siguen existiendo
  con la misma consulta, solo para las rutas heredadas `GET /mis-arriendos`
  y `GET /mis-compras` (§3.1) — nada de la UI las enlaza ya.

Si cambiás el filtro de una (por ejemplo, qué estados de reserva cuentan
como "arriendo"), **actualizá también la otra** o la vista modal y la vista
heredada van a mostrar cosas distintas.

- La consulta de arriendos filtra reservas en estado `Confirmada` cuyo
  inmueble es de modalidad Arriendo o Ambos. El comentario en el código
  aclara una regla del negocio (RN-18): una reserva confirmada que todavía
  no tiene contrato emitido se muestra como "Contrato pendiente" en la
  vista — no es un error, es un estado intermedio esperado mientras corre
  el plazo para que el administrador emita el contrato (ver
  `Documentación/Trazabilidad/Contratos.md`).
- La consulta de compras trae todas las ventas donde el usuario es el
  comprador (`$usuario->ventas()`), sin filtrar por estado — se muestran
  incluso las canceladas, con su badge de estado correspondiente.

Ninguna de las dos permite editar nada — son de solo lectura, con enlaces
de descarga hacia `DescargaController` cuando hay un contrato o escritura
asociada (ver `Documentación/Trazabilidad/Contratos.md` y
`Documentación/Trazabilidad/Ventas.md`).

## 7. Errores comunes al tocar este módulo

| Síntoma | Causa probable | Dónde mirar |
|---|---|---|
| El correo o documento "no se pueden editar" desde el perfil | Es intencional, no un bug — HU-25 lo prohíbe a propósito | `PerfilUpdateRequest::rules()` (esos campos simplemente no están) |
| La foto no se actualiza tras elegir el archivo | El `<input>` debe tener `id="foto-input"` y el `<form>` `id="form-foto"` — el JS los busca por esos IDs exactos | `resources/js/perfil.js` + `perfil/edit.blade.php` |
| Un favorito sigue apareciendo después de eliminar el inmueble | Revisar que la migración de `favorito` tenga `ON DELETE CASCADE` en la FK contra `inmueble` | `database/migrations/2026_07_25_000006_create_favorito_table.php` |
| "Mis arriendos" no muestra una reserva confirmada | Revisar que el inmueble de esa reserva sea de modalidad Arriendo o Ambos — Venta no aparece ahí aunque la reserva esté confirmada | `PerfilController::edit()` (modal) o `::misArriendos()` (vista heredada) |
| Un modal del perfil (contraseña/arriendos/compras/tarjetas) se abre solo al cargar la página sin que haya error | `session('reabrirModal')` quedó pegada de una petición anterior, o `$errors->hasAny([...])` de un modal está usando el nombre de campo de otro | `perfil/partials/modal-*.blade.php` |
| La tarjeta de Stripe no aparece (contenedor vacío) dentro del modal | Se está intentando montar antes de que el modal se abra — el montaje es perezoso, atado al clic en `[data-modal-abrir="modal-tarjetas"]` | `resources/js/tarjetas.js` |
