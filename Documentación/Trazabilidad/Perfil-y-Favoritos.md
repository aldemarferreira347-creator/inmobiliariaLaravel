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
| Vistas | `resources/views/perfil/{edit,favoritos,mis-arriendos,mis-compras,cambiar-password}.blade.php` |
| JS | `resources/js/perfil.js` |
| Rutas | `routes/web.php`, dentro del grupo `Route::middleware('auth')`, líneas 43-49 y 66-67 |
| Tests | `tests/Feature/PerfilTest.php` |

## 3. Editar los datos del perfil

`GET /perfil` → `PerfilController::edit()` (línea 19) pasa a la vista el
usuario autenticado (`$request->user()`, que Laravel resuelve solo a partir
de la sesión — no hay que buscarlo a mano) y el total de favoritos.

La vista `perfil/edit.blade.php` **no usa un modal** como el módulo de
inmuebles — usa un patrón distinto: dos bloques (`#vista-lectura` y
`#form-editar`), uno visible y el otro oculto con la clase `hidden`. El
botón `[data-perfil-editar]` alterna cuál se ve, resuelto en
`resources/js/perfil.js` (`iniciarEdicion()`, línea 6). Si una edición falla
la validación, la vista marca el formulario con `data-abierto="si"` y el JS
lo detecta al cargar la página para reabrirlo automáticamente (mismo
principio que el modal de inmuebles, aplicado con un mecanismo distinto).

`PATCH /perfil` → `PerfilController::update()` (línea 27) →
`PerfilUpdateRequest`. **Importante:** el correo y el número de documento
**no están** en las reglas de validación de este formulario — es
intencional (HU-25, ver el comentario en el propio archivo): esos dos datos
identifican la cuenta y son inmutables desde el perfil. Si en el futuro
alguien pide "que el cliente pueda cambiar su correo desde el perfil", este
es el archivo a tocar, y hay que decidir qué pasa con el email de
verificación/login si se habilita.

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

Dos métodos de solo lectura en `PerfilController`:

- `misArriendos()` (línea 52): reservas del usuario en estado `Confirmada`
  cuyo inmueble es de modalidad Arriendo o Ambos. El comentario en el código
  aclara una regla del negocio (RN-18): una reserva confirmada que todavía
  no tiene contrato emitido se muestra como "Contrato pendiente" en la
  vista — no es un error, es un estado intermedio esperado mientras corre
  el plazo para que el administrador emita el contrato (ver
  `Documentación/Trazabilidad/Contratos.md`).
- `misCompras()` (línea 67): todas las ventas donde el usuario es el
  comprador (`$usuario->ventas()`), sin filtrar por estado — se muestran
  incluso las canceladas, con su badge de estado correspondiente.

Ninguna de las dos vistas permite editar nada — son de solo lectura, con
enlaces de descarga hacia `DescargaController` cuando hay un contrato o
escritura asociada (ver `Documentación/Trazabilidad/Contratos.md` y
`Documentación/Trazabilidad/Ventas.md`).

## 7. Errores comunes al tocar este módulo

| Síntoma | Causa probable | Dónde mirar |
|---|---|---|
| El correo o documento "no se pueden editar" desde el perfil | Es intencional, no un bug — HU-25 lo prohíbe a propósito | `PerfilUpdateRequest::rules()` (esos campos simplemente no están) |
| La foto no se actualiza tras elegir el archivo | El `<input>` debe tener `id="foto-input"` y el `<form>` `id="form-foto"` — el JS los busca por esos IDs exactos | `resources/js/perfil.js` + `perfil/edit.blade.php` |
| Un favorito sigue apareciendo después de eliminar el inmueble | Revisar que la migración de `favorito` tenga `ON DELETE CASCADE` en la FK contra `inmueble` | `database/migrations/2026_07_25_000006_create_favorito_table.php` |
| "Mis arriendos" no muestra una reserva confirmada | Revisar que el inmueble de esa reserva sea de modalidad Arriendo o Ambos — Venta no aparece ahí aunque la reserva esté confirmada | `PerfilController::misArriendos()` |
