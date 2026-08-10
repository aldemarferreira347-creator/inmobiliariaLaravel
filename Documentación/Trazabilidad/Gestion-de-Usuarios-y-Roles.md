# Gestión de Usuarios y Roles

> El panel donde el administrador crea cuentas de asesores/administradores,
> cambia el rol o el estado (activo/inactivo) de cualquier usuario, y
> consulta la matriz de permisos por rol. Todo bajo `/admin/usuarios` y
> `/admin/permisos`.

## 1. Qué es este módulo

Complementa al registro público (que **solo** crea clientes, ver
`Documentación/Trazabilidad/Autenticacion-y-Sesion.md` §4): este es el
único lugar del sistema donde se crean cuentas de **asesor** o
**administrador**. También es donde se desactiva una cuenta sin borrarla
(para no perder su historial), y donde se le cambia el rol a alguien.

Tiene una regla de seguridad que aparece una y otra vez en el código: **un
administrador no puede actuar sobre su propia cuenta** en ninguna de estas
acciones — ni cambiarse el rol, ni desactivarse, ni eliminarse. Si se
permitiera, un administrador podría quedar accidentalmente sin acceso al
panel y sin nadie que pueda revertirlo.

## 2. Mapa de archivos

| Pieza | Archivo |
|---|---|
| Modelo de usuario | `app/Models/User.php` |
| Modelo de rol | `app/Models/Role.php` |
| Modelo de permiso | `app/Models/Permiso.php` |
| Modelo de auditoría | `app/Models/Auditoria.php` |
| Controller de usuarios | `app/Http/Controllers/Admin/UsuarioController.php` |
| Controller de permisos (solo lectura) | `app/Http/Controllers/Admin/PermisoController.php` |
| Validación de alta | `app/Http/Requests/Admin/StoreUsuarioRequest.php` |
| Reglas de quién puede hacer qué | `app/Politicas/UserPolicy.php` |
| Vistas | `resources/views/admin/usuarios/{index,partials/modal-crear}.blade.php`, `resources/views/admin/permisos/index.blade.php` |
| Rutas | `routes/web.php`, dentro de `Route::middleware(['auth', 'rol:administrador'])->prefix('admin')`, líneas 132-138 |
| Tests | `tests/Feature/Admin/GestionUsuariosTest.php` |

## 3. Crear un usuario (asesor o administrador)

Mismo patrón de modal que "Registrar inmueble" (ver
`Documentación/Trazabilidad/Gestion-de-Inmuebles.md` §3.1 si no conocés el
mecanismo de `data-modal-abrir`/`data-modal-cerrar` de `ui.js`): un botón en
`admin/usuarios/index.blade.php` abre el modal definido en
`admin/usuarios/partials/modal-crear.blade.php`, que incluye un `<select>`
de rol (`RolUsuario::cases()` — los 3 valores: cliente, asesor,
administrador).

`POST /admin/usuarios` → `UsuarioController::store()` (línea 31) →
`StoreUsuarioRequest`:

- `authorize()` llama a `$this->user()?->can('gestionar', User::class)` →
  `UserPolicy::gestionar()` → solo pasa si `$usuario->esAdministrador()`.
  Esta es la **única** ability de la policy sin un "objetivo" (no recibe un
  segundo `User $objetivo`) — tiene sentido: para *crear* un usuario nuevo
  no hay todavía un objetivo sobre el cual comparar "¿sos vos mismo?".
- `rules()` exige contraseña seguera (`PasswordSegura::reglas()`, la misma
  regla compartida de todo el sistema), correo y documento únicos, y un
  `rol` que sea un valor válido del enum.
- El controller simplemente hace `User::create($request->validated())` — no
  hay pasos intermedios como en el alta de inmuebles (no hay imágenes que
  procesar acá).

## 4. Cambiar el rol de un usuario existente

`PATCH /admin/usuarios/{usuario}/rol` → `UsuarioController::cambiarRol()`
(línea 41):

```php
public function cambiarRol(Request $request, User $usuario): RedirectResponse
{
    $this->authorize('cambiarRol', $usuario);

    $datos = $request->validate(['rol' => ['required', Rule::enum(RolUsuario::class)]]);
    $usuario->update($datos);
    // ...
}
```

`$this->authorize('cambiarRol', $usuario)` dispara
`UserPolicy::cambiarRol($usuarioAutenticado, $usuario)` (línea 21):

```php
public function cambiarRol(User $usuario, User $objetivo): bool
{
    return $usuario->esAdministrador() && ! $objetivo->esElMismoQue($usuario);
}
```

`esElMismoQue()` (`User.php` línea 184) compara las claves primarias de los
dos usuarios. **Si un administrador intenta cambiarse el rol a sí mismo,
esta línea lo bloquea con un 403** — no es un error de la interfaz, es una
regla de negocio deliberada. Este mismo patrón (`$objetivo->esElMismoQue($usuario)`)
se repite en `cambiarEstado` y `delete` — si alguna vez alguien necesita
que un "super-administrador" pueda saltarse esta regla, es acá donde hay que
agregar esa excepción, con mucho cuidado de no dejar el sistema sin ningún
administrador activo.

Esta validación **no está en el formulario ni en el HTML** — es puramente
del lado del servidor. Si la interfaz no oculta el botón de "cambiar rol"
para la fila del propio usuario logueado, el clic igual llegaría al
servidor y ahí sería rechazado; conviene revisar la vista
(`admin/usuarios/index.blade.php`) si se quiere además ocultar el botón
visualmente para evitar el clic inútil.

## 5. Activar / desactivar una cuenta

`PATCH /admin/usuarios/{usuario}/estado` → `UsuarioController::cambiarEstado()`
(línea 55). Mismo candado de policy que el rol
(`UserPolicy::cambiarEstado()`, misma regla "no podés actuar sobre vos
mismo"). Lo interesante de este método es que **no recibe el nuevo estado
del formulario** — lo calcula él mismo con `$usuario->estado->opuesto()`
(método del enum `EstadoUsuario`, invierte Activo↔Inactivo). Es un botón de
"alternar", no un `<select>`.

Dentro de una transacción:

1. Actualiza `estado`, y si el nuevo estado es Inactivo, también graba
   `desactivado_en` (timestamp) y `desactivado_por` (quién lo hizo). Si se
   está **reactivando**, ambos vuelven a `null`.
2. `Auditoria::registrar('usuario', $usuario->id, 'cambiar_estado', ...)`
   (`app/Models/Auditoria.php` línea 31) — deja una fila permanente en la
   tabla `auditoria` con quién hizo el cambio (`auth()->id()`, tomado de la
   sesión actual, no de un parámetro), sobre qué entidad, y desde qué IP
   (`Request::ip()`). Esta tabla **no tiene pantalla propia** en el panel
   actualmente — solo se escribe, no se consulta desde ninguna vista; si se
   necesita en el futuro un "historial de auditoría" visible, los datos ya
   están, falta el controller/vista que los liste.

**Efecto inmediato de desactivar a alguien:** no hace falta que esa persona
cierre sesión — el middleware global `EnsureUserIsActive` (ver
`Documentación/Trazabilidad/Autenticacion-y-Sesion.md` §8) revisa
`estaActivo()` en cada petición y la saca automáticamente en la siguiente
página que visite.

## 6. Eliminar un usuario

`DELETE /admin/usuarios/{usuario}` → `destroy()` (línea 80). Mismo patrón
que eliminar un inmueble (`Documentación/Trazabilidad/Gestion-de-Inmuebles.md`
§5): primero `$this->authorize('delete', $usuario)`, después
`$usuario->tieneHistorial()` (`User.php` línea 190, verdadero si tiene
alguna reserva o venta asociada) — si tiene historial, **no se borra**, el
mensaje de error sugiere desactivarlo en su lugar. Esto evita que borrar un
usuario deje reservas/ventas "huérfanas" apuntando a un `usuario_id` que ya
no existe.

## 7. Matriz de permisos (solo lectura)

`GET /admin/permisos` → `PermisoController::index()` — un único método,
carga `Role::with('permisos')` y la vista pinta una tabla con qué permisos
tiene cada rol. **No hay forma de editar permisos desde acá** — la tabla
`permiso` es, según su propio comentario en el modelo, un catálogo
informativo; la autorización real del sistema la deciden el middleware
`rol` (ver `Documentación/Trazabilidad/Trazabilidad-Sistema.md` §2) y las
policies de cada módulo, no lo que diga esta tabla. Si algún día se quiere
que los permisos sean realmente configurables (que cambiar una fila acá
cambie el comportamiento del sistema), hace falta reescribir el sistema de
autorización para que consulte esta tabla en vez de los chequeos de rol
fijos en el código — es un cambio de arquitectura grande, no un ajuste
menor.

## 8. Errores comunes al tocar este módulo

| Síntoma | Causa probable | Dónde mirar |
|---|---|---|
| Un administrador no puede cambiarse su propio rol/estado, ni eliminarse | Es intencional (regla de seguridad) — revisar `esElMismoQue()` en `UserPolicy` antes de asumir que es un bug | `app/Politicas/UserPolicy.php` |
| Un usuario "no se puede eliminar" | Tiene reservas o ventas asociadas — usar "desactivar" en su lugar | `UsuarioController::destroy()` + `User::tieneHistorial()` |
| Cambiaste un permiso en `/admin/permisos` y no pasó nada en el sistema | Es esperado: esa tabla es solo informativa, no controla el acceso real | Ver sección 7 arriba |
| No hay forma de ver quién desactivó a un usuario y cuándo | Sí se guarda (`desactivado_en`, `desactivado_por`, y una fila en `auditoria`), pero no hay pantalla que lo muestre todavía | `app/Models/Auditoria.php` — falta construir la vista |
