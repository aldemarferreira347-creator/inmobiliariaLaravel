# QA — Estado general del sistema

> Guía sencilla del estado de salud del proyecto, para consulta rápida en una
> evaluación o cuando algo se rompe. La trazabilidad detallada (qué archivo
> toca a cuál) vive en un documento aparte: [`Documentación/Trazabilidad/Trazabilidad-Sistema.md`](../Trazabilidad/Trazabilidad-Sistema.md).

**Última auditoría:** 2026-08-07. **Última revisión funcional:** 2026-08-12.
**Estado:** sano. `php artisan test` → **119/119 tests pasan** (116 de la
auditoría original + 3 agregados el 2026-08-12, ver §6). Cero rutas rotas,
cero vistas rotas o huérfanas, cero código muerto real. Se corrigieron en la
auditoría original: la autorización por rol (§2.1), 2 íconos con nombre
equivocado (§3), un symlink de almacenamiento roto que causaba 404 en
imágenes reales (§4.1), y el catálogo de demostración sin imágenes propias
(§4.2). La revisión del 2026-08-12 (§6) corrigió, entre otras cosas, una
clase `.container` que no centraba el contenido en varias vistas del lado
cliente y un `input[type=checkbox]` que se deformaba en los formularios del
panel — ambos con causa raíz en CSS compartido, no en una vista puntual.

---

## 1. Qué se revisó

- Los 19 modelos, 27 controllers, 73 vistas Blade y todas las rutas — cada
  ruta apunta a un método que existe, cada `view(...)` apunta a un archivo
  que existe, cada modelo lo usa al menos un controller.
- Los 21 servicios de dominio — todos en uso, ninguno huérfano.
- Las 7 policies — registradas correctamente, ninguna ability inexistente.
- El sistema de íconos — ver [§3](#3-sistema-de-íconos).
- Los 2 comandos programados (`reservas:expirar`, `contratos:vencer`) — corren
  cada hora, correctamente configurados.
- El flujo de pagos con Stripe (webhook + tarjetas guardadas).

## 2. Cambios aplicados en esta pasada

### 2.1 Autorización por rol — ahora es middleware real (antes: ALTO riesgo)

**Antes:** cada controller de `Admin/`/`Asesor/` sobreescribía `callAction()`
y llamaba `requerirRol()` a mano. Si alguien creaba un controller nuevo ahí y
se olvidaba de ese código, quedaba sin protección de rol — cualquier usuario
logueado podía entrar.

**Ahora:** existe un middleware real, `App\Http\Middleware\EnsureUserHasRole`
(alias `rol`), declarado directamente en `routes/web.php`:

```php
Route::middleware(['auth', 'rol:administrador'])->prefix('admin')-> ...
Route::middleware('rol:asesor,administrador')->group(...)   // ventas del asesor
Route::middleware('rol:asesor')->group(...)                 // citas del asesor
```

La protección queda visible en un solo archivo y ya no depende de que cada
controller la implemente. `App\Http\Middleware\EnsureUserIsActive` (aplicado
globalmente en `bootstrap/app.php`) reemplaza el antiguo
`cerrarSesionSiInactivo()` y sigue cerrando la sesión de una cuenta
desactivada en su siguiente petición.

Los 12 controllers que tenían el patrón viejo se limpiaron (ya no tienen
`callAction()` propio) y la clase base `app/Http/Controllers/Controller.php`
quedó reducida a lo mínimo.

**Test de resguardo:** `tests/Feature/RutasProtegidasTest.php` recorre todas
las rutas registradas y falla si alguna bajo `/admin` o `/asesor` no declara
el middleware `rol`. Si alguien agrega una ruta nueva ahí sin protegerla, el
test lo va a atrapar en CI en vez de descubrirse en producción.

### 2.2 Íconos rotos corregidos

Ver detalle completo en [§3](#3-sistema-de-íconos). Resumen: dos íconos del
panel de reportes usaban nombres de otra librería (Heroicons) que no existen
en el catálogo del proyecto (Lucide) y cayeron silenciosamente al ícono
genérico de "?". Corregidos.

## 3. Sistema de íconos

**Cómo funciona:** no es una librería de terceros (ni paquete npm de íconos,
ni webfont). Es una clase propia, `app/Soporte/Iconos.php`, con 68 trazos SVG
inline copiados del set **Lucide** (licencia ISC). Se usan así desde Blade:

```blade
<x-icon name="calendar" class="h-5 w-5" />
```

`resources/views/components/icon.blade.php` delega en `Iconos::svg()`, que
arma el `<svg>` completo con `currentColor` (hereda el color del texto) y
tamaño por clases de Tailwind.

**¿Por qué así y no una librería real (paquete JS/PHP)?** Es una decisión
válida para este stack, no un defecto: los íconos se usan también en los PDF
generados con Dompdf (`Servicios/Reportes/ExportadorPdf`) y en correos
(`Mail/Aviso`), donde una librería JS de íconos (ej. Lucide vía npm +
`<script>`) no funciona — Dompdf no ejecuta JavaScript. Con SVG inline en PHP
el mismo ícono se ve igual en la web, en el PDF y en el correo. **No hace
falta "conectar" una librería — ya está conectada, solo que vive como datos
PHP en vez de como dependencia npm.**

**El problema real que encontré:** la función `Iconos::svg()` tiene un
*fallback silencioso* — si pides un ícono que no está en el catálogo, en vez
de fallar, devuelve el ícono genérico `circle-help` (un símbolo de "?") sin
avisar. Encontré 2 sitios donde esto pasaba:

| Archivo | Nombre pedido | Problema | Corregido a |
|---|---|---|---|
| `resources/views/admin/reportes/index.blade.php:13` | `document-text` | No existe en el catálogo (es nombre de Heroicons, no de Lucide) | `file-text` |
| `resources/views/admin/reportes/index.blade.php:16` | `arrow-down-tray` | Mismo problema | `download` |

Esos dos botones ("Exportar PDF", "Exportar Excel" en `/admin/reportes`)
mostraban un ícono de interrogación en vez del ícono correcto. Ya está
corregido.

**Blindaje agregado:** ahora `Iconos::svg()` revienta con una excepción clara
en entorno `local`/`testing` si le piden un ícono que no existe en el
catálogo — así el próximo nombre mal escrito se detecta apenas se visita la
página en desarrollo o corre un test, en vez de quedar como un "?" invisible
en producción. En producción sigue cayendo al ícono por defecto (no tumba la
página por un typo).

**Catálogo completo disponible:** 68 nombres, ver la lista en
`app/Soporte/Iconos.php` (constante `TRAZOS`). Si necesitas un ícono que no
está ahí, hay que agregar su trazo SVG (formato Lucide, `viewBox="0 0 24 24"`)
a esa lista — no se resuelve solo.

## 4. Revisión visual con navegador (ya hecha)

Se navegó la app real (servidor local + Browser) en los tres roles —
público/cliente, asesor, administrador— cubriendo catálogo, ficha de
inmueble, login/registro, perfil, favoritos, reservas (listado + detalle),
citas, tarjetas guardadas, mensajería (listado + hilo), notificaciones, y en
el panel: inmuebles, usuarios, permisos, citas, franjas, reservas,
contratos, reportes, notificaciones, y en asesor: ventas, citas. Sin
screenshots (el entorno no compositaba pantalla), pero sí con inspección de
DOM, red y consola — suficiente para pescar contenido roto, imágenes caídas
y errores JS.

### 4.1 Symlink de almacenamiento roto (CRÍTICO — corregido)

`public/storage` apuntaba a una ruta que ya no existe
(`.../migrando/inmobiliarialaravel/...`, de cuando la carpeta del proyecto
tenía otro nombre). **Cualquier imagen subida por el panel real —fotos de
inmuebles, avatares de perfil— devolvía 404 en el navegador.** Esto es
consistente con "vistas rotas sin diseño": no es que falte CSS, es que la
imagen detrás del `<img>` literalmente no resolvía.

**Corregido:** se recreó el symlink (`php artisan storage:link`, tras borrar
el enlace roto) apuntando a la ruta real del proyecto. Verificado sirviendo
imágenes con 200 OK.

### 4.2 Catálogo de demostración sin imágenes reales (MEDIO — corregido)

El seeder (`InmuebleSeeder`) creaba las 12 propiedades de demostración pero
nunca les asociaba una `ImagenInmueble`. Como consecuencia, **cada tarjeta
del catálogo dependía del fallback externo `placehold.co`** (ver
`Inmueble::urlDeImagen()`) en vez de ejercitar la galería real (HU-08). Dos
problemas con eso: (1) si no hay red hacia esa CDN externa —común en un
entorno de evaluación aislado— todas las tarjetas del sitio se ven sin
imagen; (2) nadie que revise la demo ve nunca la galería funcionando de
verdad.

**Corregido:** `InmuebleSeeder` ahora genera 2 imágenes locales por
propiedad con GD (mismo tono de marca `#1e3c72→#2a5298` que ya usaba el
placeholder, silueta simple de casa, título superpuesto) y crea los
registros reales de `ImagenInmueble` — el catálogo de demostración ahora
ejercita la galería real end-to-end, sin depender de ningún servicio
externo. Confirmado con `php artisan migrate:fresh --seed` + inspección en
el navegador: las 12 tarjetas del catálogo y la tabla del panel admin cargan
sus imágenes desde `storage/inmuebles/seed-*.jpg`.

### 4.3 Hallazgos menores, no corregidos

- **Enlaces de pie de página sin destino real.** "Términos de uso",
  "Política de privacidad", "Tratamiento de datos", "Facebook" e
  "Instagram" apuntan a `href="#"` en el footer, presente en absolutamente
  todas las páginas (`resources/views/layouts/partials/footer.blade.php`).
  Es un placeholder típico de proyecto en desarrollo, no un bug — pero es
  visible en cada pantalla, así que si esto va a evaluación vale la pena
  decidir: ¿se apagan esos enlaces (quitarlos), o se conectan a páginas
  reales?
- **Direcciones generadas no parecen colombianas.** El factory de `Inmueble`
  usa `fake()->streetAddress()` con el locale por defecto de Faker, que
  produce direcciones de estilo español/catalán (ej. "Passeig Marta, 489, 5º
  A") aunque la ciudad fijada sea "Neiva, Colombia". Cosmético, solo afecta
  al realismo de la demo — se resuelve fijando el locale de Faker a `es_CO`
  (o similar) en `config/app.php` / `.env` (`FAKER_LOCALE`) si les importa.

Sin más hallazgos: no se encontraron vistas con contenido faltante, errores
de JavaScript en consola (fuera del aviso esperado de Stripe.js sobre HTTP
en local), ni imágenes rotas después de las dos correcciones de arriba.

## 5. Hallazgos restantes (no bloqueantes)

**MEDIO**
- `.env` en disco tiene claves de Stripe con formato de credencial real (modo
  test). Confirmar que nunca se subió a un repo remoto y rotarlas si sí. No
  requiere cambio de código — es una verificación tuya.
- Mezcla de Policies + `abort_unless` inline como estrategia de autorización
  de datos (distinto del punto 2.1, que era autorización por *rol*). Sigue
  siendo válido pero sin una regla única — detalle en la Trazabilidad.

**BAJO**
- El README del proyecto está desactualizado (dice que el alcance es menor
  al real, y usa nombres de carpeta en inglés que no existen). Recomendado
  regenerarlo desde este documento y el de trazabilidad.
- `database/migrations/0001_01_01_000000_create_users_table.php` crea la
  tabla `usuario` (no `users`) — el nombre del archivo es el de Laravel por
  defecto y puede confundir a quien busque ahí la tabla `usuario`.

## 6. Revisión funcional del 2026-08-12

Pasada centrada en vistas mal alineadas/desorganizadas reportadas en uso
real, más un pedido explícito de que el perfil nunca navegue a otra vista.
No fue una auditoría de código automatizada como la de §1-§5 — fue
navegación real de la app (login como cliente y como administrador) más
lectura de código, con `php artisan test` como red de seguridad antes y
después de cada cambio de backend.

### 6.1 `.container` no centraba el contenido en reservas y notificaciones (ALTO — corregido)

**Síntoma reportado:** varias vistas se veían "corridas hacia un lado", con
todo el contenido pegado al borde izquierdo y un hueco enorme a la derecha
en pantallas anchas.

**Causa real:** en Tailwind CSS v4, `@apply container` dentro de una clase
propia **no reutiliza** una clase `.container` definida a mano con el mismo
nombre — resuelve al utility "container" nativo de Tailwind, que no trae
`margin: auto` y usa sus propios saltos de ancho máximo por breakpoint. El
proyecto define su propio `.container { mx-auto max-w-6xl px-5 }`
(`resources/css/app.css` línea 53), pero tres clases lo reutilizaban vía
`@apply container ...` en vez de repetir las utilidades — exactamente el
patrón que Tailwind v4 no resuelve como uno esperaría:
`.reservas-backbar`, `.reservas-container` (detalle de una reserva) y
`.notif-page` (centro de notificaciones).

**Corregido:** las tres clases pasaron de `@apply container ...` a repetir
las utilidades explícitas (`@apply mx-auto w-full max-w-6xl px-5 ...`),
igual que ya hacía `.detalle-page`. Verificado midiendo en el navegador que
el margen izquierdo y derecho del contenido quedan iguales a 1920px de
ancho. **Si en el futuro alguien necesita otra clase de ancho constante,
no usar `@apply container` — copiar el patrón de `.detalle-page` o de
`.container` mismo.**

### 6.2 Checkbox deformado en formularios del panel (MEDIO — corregido)

**Síntoma reportado:** en "Enviar notificación", el checkbox "Enviar
también por correo electrónico" se veía como una línea delgada centrada en
vez de un cuadrado normal, con la etiqueta cayendo a la línea siguiente.

**Causa real:** la regla genérica `.form-group input { width:100%;
border; padding; ... }` (pensada para `<input type="text">`) no excluía
`type="checkbox"`/`type="radio"`, así que cualquier checkbox dentro de un
`.form-group` heredaba `width: 100%` — el navegador lo dibuja como una
barra casi invisible en vez de un cuadrado. Encontrado en
`resources/views/admin/notificaciones/create.blade.php`, pero la causa es
compartida: cualquier otro checkbox futuro dentro de un `.form-group` iba
a tener el mismo problema.

**Corregido:** `.form-group input:not([type="checkbox"]):not([type="radio"])`
— fix a nivel de CSS compartido, no del formulario puntual. De paso se
corrigió que `.form-group label { display:block }` pisaba el `display:flex`
de `.modal-terminos` (la clase que alinea checkbox + texto en una fila) por
mayor especificidad; ahora `.form-group label:not(.modal-terminos)`.

### 6.3 Perfil: contraseña / arriendos / compras / tarjetas ahora son modales

Ver el detalle completo en
`Documentación/Trazabilidad/Perfil-y-Favoritos.md` §3.1. Resumen: antes
cada sección era una vista aparte con su propia URL; ahora las cuatro son
modales sobre `/perfil`, con `PerfilController::edit()` cargando todos los
datos de una sola vez. Las rutas y vistas viejas se dejaron intactas por
compatibilidad, pero ya no las enlaza la interfaz.

De paso se agregó una transición corta de apertura/cierre
(`opacity`/`scale` con `@starting-style`) a la clase genérica de modales
(`.modal-box`, `.modal-reserva-box`) — beneficia a todos los modales del
sistema, no solo a los de perfil.

### 6.4 Notificaciones: marcar como leída ahora elimina, y la vista se rediseñó

`NotificacionController::marcarLeida()`/`marcarTodas()` pasaron de
`->update(['leida_en' => now()])` a `->delete()` — decisión de producto
(HU-15): el centro de notificaciones es una bandeja de pendientes, no un
historial. Ver `Documentación/Trazabilidad/Notificaciones.md` §4 para el
detalle y la advertencia sobre el scope `sinLeer()`.

La vista además se agrupó por antigüedad (Hoy/Ayer/Esta semana/Anteriores)
y se le agregaron pestañas "Todas"/"No leídas" resueltas en el navegador
(`resources/js/notificaciones.js`), sin tocar el controller.

### 6.5 Contratos: el valor ya no lo escribe el administrador

`ContratoService::crearDesdeReserva()` ahora guarda
`valor_mensual = $reserva->monto_reserva` en vez de un valor libre del
formulario — mismo principio que ya aplicaba Reservas ("el monto nunca lo
escribe quien paga"). Ver `Documentación/Trazabilidad/Contratos.md` §3.2.

### 6.6 Mensajería: la ficha del inmueble ahora tiene un formulario de contacto real

`MensajeController::iniciar()` acepta un campo `mensaje` opcional y, si
viene, publica ese texto en la conversación antes de redirigir — antes solo
abría un hilo vacío. Ver `Documentación/Trazabilidad/Mensajeria.md` §3.

### 6.7 Encabezado de ancho completo

`header .container` se independizó del `.container` centrado del resto del
sitio (`max-w-none px-6`) para que el logo y las acciones (campana, perfil,
cerrar sesión) queden pegados a los bordes de la ventana — decisión visual
explícita, no aplica al resto de las vistas.

### 6.8 Tests agregados

Tres tests nuevos, uno por cada cambio de comportamiento del backend
(§6.3-§6.6 no tocan backend salvo lo ya cubierto):

- `ContratoTest::test_el_valor_del_contrato_se_toma_de_la_reserva_y_no_del_formulario`
- `NotificacionTest::test_marcar_una_notificacion_como_leida_la_elimina`
  (más una aserción nueva en `test_se_marcan_todas_como_leidas`)
- `MensajeTest::test_el_formulario_de_contacto_de_la_ficha_envia_el_mensaje_de_una_vez`

## 7. Comandos útiles para depurar

```bash
php artisan route:list                    # todas las rutas, con nombre y controller
php artisan route:list --name=admin       # filtrar por nombre
php artisan test                          # suite completa
php artisan test --filter=NombreDelTest   # aislar un módulo
php artisan tinker                        # inspeccionar modelos/DB en vivo
php artisan config:clear && php artisan cache:clear && php artisan view:clear
php artisan storage:link                  # si imágenes/avatares no cargan
composer dump-autoload                    # si una clase nueva "no existe"
```
