# Mensajería (Chat cliente-asesor)

> Un cliente le escribe a su asesor sobre un inmueble puntual, desde la
> ficha del inmueble o desde su bandeja. Es el único módulo del sistema que
> se actualiza en tiempo (casi) real sin recargar la página — vale la pena
> entender cómo, porque el patrón es distinto al resto del sitio.

## 1. Qué es este módulo

Cada conversación (`conversacion`) es un hilo fijo entre **un cliente**, **un
asesor**, y **un inmueble** — no es un chat genérico persona-a-persona, está
siempre anclado a una propiedad concreta. Un mismo cliente puede tener
varios hilos si escribe sobre varios inmuebles distintos.

La misma pantalla y el mismo controller (`MensajeController`) sirven a los
tres roles — cliente, asesor y administrador — mostrando distinto contenido
según quién esté logueado, en vez de tener controllers separados como sí
pasa en Citas o Ventas.

## 2. Mapa de archivos

| Pieza | Archivo |
|---|---|
| Modelo de conversación | `app/Models/Conversacion.php` |
| Modelo de mensaje | `app/Models/Mensaje.php` |
| Controller (único, para los 3 roles) | `app/Http/Controllers/MensajeController.php` |
| Lógica de negocio | `app/Servicios/MensajeService.php` |
| Validación de envío | `app/Http/Requests/EnviarMensajeRequest.php` |
| Quién puede ver/responder un hilo | `app/Politicas/ConversacionPolicy.php` |
| Vistas | `resources/views/mensajes/{index,panel,partials/bandeja}.blade.php`, componente `resources/views/components/mensajes/burbuja.blade.php` |
| JS | `resources/js/chat.js` |
| Rutas | `routes/web.php` líneas 84-91 |
| Tests | `tests/Feature/MensajeTest.php` |

## 3. Cómo se elige el asesor de un cliente nuevo

Un cliente nunca elige manualmente con qué asesor hablar — al escribir por
primera vez sobre un inmueble
(`POST /inmuebles/{inmueble}/contactar` → `MensajeController::iniciar()` →
`MensajeService::abrirConversacion()`), el sistema decide:

**La ficha del inmueble incluye un formulario de contacto opcional**
(tarjeta "Escríbele al asesor" en `inmuebles/show.blade.php`, visible solo
para cliente/invitado, no para staff) con un `<textarea name="mensaje">`.
Si el campo llega no vacío, `iniciar()` abre/reutiliza la conversación
**y además** llama `MensajeService::enviar()` con ese texto antes de
redirigir — el cliente cae directo en el hilo con su pregunta ya publicada,
en vez de tener que escribirla de nuevo en la bandeja. El campo es
`nullable`; si viene vacío (por ejemplo, alguien usa el botón corto
"Contactar asesor" sin escribir nada), el comportamiento es idéntico al de
siempre: solo abre/reutiliza el hilo, sin publicar ningún mensaje. Cubierto
por `MensajeTest::test_el_formulario_de_contacto_de_la_ficha_envia_el_mensaje_de_una_vez`.

1. Si el mismo cliente ya tuvo una conversación antes con **cualquier**
   asesor (sobre otro inmueble) y ese asesor sigue activo, se reutiliza —
   así no se parte la relación ya construida (`asesorPara()`, línea 104).
2. Si no, se reparte entre los asesores activos **por carga de trabajo**:
   `User::withCount('conversacionesComoAsesor')->orderBy(...)` — el que
   menos conversaciones tenga asignadas se lleva la nueva. El comentario en
   el código aclara que el prototipo original repartía al azar; acá se
   cambió a por carga.
3. Si no hay ningún asesor activo, lanza un error explícito ("No hay
   asesores disponibles en este momento") en vez de crear una conversación
   sin nadie del otro lado.

**Contactar dos veces desde el mismo inmueble reutiliza el mismo hilo** —
`abrirConversacion()` busca primero por `cliente_id` + `inmueble_id` antes
de crear uno nuevo (hay un test específico para esto:
`MensajeTest::contactar_dos_veces_reutiliza_el_mismo_hilo`).

## 4. Cómo se ve la bandeja según el rol

`MensajeController::vistaPara()` (línea 128) decide entre dos plantillas
distintas según si el rol "usa panel"
(`$request->user()->rol->usaPanel()`, método del enum `RolUsuario` —
verdadero para asesor y administrador, falso para cliente):

- Cliente → `mensajes/index.blade.php` (dentro del layout público normal).
- Asesor/administrador → `mensajes/panel.blade.php` (dentro del layout con
  sidebar del panel).

Y `conversacionesDe()` (línea 133) decide **qué hilos** ve cada quien: el
administrador ve todos (para poder supervisar), cualquier otro rol solo ve
los suyos (scope `Conversacion::de($usuario)`, que filtra por
`cliente_id` o `asesor_id` según corresponda).

## 5. Enviar un mensaje sin recargar la página

Esta es la parte más distinta del resto del sistema. El formulario de envío
(`<form data-chat-form>`) **sí tiene una `action` normal** — funciona sin
JavaScript, como cualquier otro formulario del sitio — pero
`resources/js/chat.js` (`iniciarEnvio()`, línea 81) le agrega un
`addEventListener('submit', ...)` que:

1. Cancela el envío normal (`evento.preventDefault()`).
2. Pinta inmediatamente una burbuja "provisional" en pantalla (optimista:
   se muestra antes de que el servidor confirme nada), marcada con la clase
   `msg-pending`.
3. Manda el mismo formulario por `fetch()` en su lugar, con el encabezado
   `X-Requested-With: XMLHttpRequest` (esto no activa ningún comportamiento
   especial de Laravel por sí solo — es una convención que el propio
   frontend usa para reconocer sus propias peticiones AJAX si hiciera
   falta, pero el controller no lo revisa).
4. Si la respuesta es exitosa, quita la burbuja provisional y llama a
   `consultarNuevos()` — el mensaje "de verdad" (con su ID real de la base
   de datos) llega recién ahí.
5. Si falla, la burbuja se marca visualmente como error
   (`msg-error`) en vez de desaparecer.

`MensajeController::store()` en el servidor es completamente ajeno a todo
esto — recibe el `POST`, llama a `MensajeService::enviar()`, y devuelve un
`redirect()` normal. **El JS del navegador ignora esa redirección** (porque
interceptó el envío con `fetch`), pero si alguien tuviera JavaScript
deshabilitado, el envío normal seguiría funcionando y sí seguiría la
redirección — es un diseño deliberado de "mejora progresiva".

## 6. Cómo llegan los mensajes del otro lado: sondeo (polling)

No hay WebSockets ni Server-Sent Events en este sistema — los mensajes
nuevos se detectan con **sondeo periódico** (`setInterval`, cada 15
segundos, `INTERVALO_SONDEO_MS`): `chat.js` llama
`GET /mensajes/{conversacion}/nuevos?desde={ultimoIdEnPantalla}` cada 15s,
y el servidor (`MensajeController::nuevos()`) devuelve solo los mensajes
con `id` mayor al último que el navegador ya tiene. **El sondeo se pausa
cuando la pestaña no está a la vista** (`if (!document.hidden)`) para no
gastar peticiones de más en pestañas en segundo plano.

Este mismo endpoint (`nuevos()`) es también el que marca los mensajes como
leídos del lado de quien está mirando la conversación en ese momento
(`if ($conversacion->participa($request->user())) { $this->mensajes->marcarLeidos(...) }`)
— **importante:** el administrador, aunque puede ver cualquier hilo para
supervisar, **no** cuenta como "participante" (`participa()` solo es
verdadero para el cliente y el asesor del hilo), así que cuando un
administrador abre una conversación ajena para revisarla, **no** le quita
al asesor sus mensajes pendientes de leer — hay un test específico
(`MensajeTest::el_administrador_lee_el_hilo_sin_consumir_los_no_leidos_del_asesor`).

## 7. El contador de mensajes sin leer en la barra de navegación

`GET /mensajes/sin-leer` → `MensajeController::sinLeer()` — este endpoint
lo consulta el layout general (no `chat.js`, sino algo en el layout
compartido) para pintar el numerito rojo junto al ícono de mensajes,
independientemente de en qué página esté el usuario. Cuenta mensajes
`leido_en IS NULL` que **no** envió el propio usuario, dentro de las
conversaciones donde participa.

## 8. Adjuntos (imágenes en el chat)

`MensajeService::guardarAdjunto()` guarda el archivo en
`storage/app/public/chat/` (disco **público**, a diferencia de contratos y
escrituras que van al disco privado — una imagen de chat no es un
documento sensible del mismo nivel). Un mensaje puede llevar solo texto,
solo adjunto, o ambos — la única validación real es que **no puede ir
completamente vacío** (`EnviarMensajeRequest`/`MensajeService::enviar()`
rechazan un mensaje sin texto y sin adjunto a la vez, HU-13, ver
`MensajeTest::un_mensaje_vacio_y_sin_adjunto_se_rechaza`).

## 9. Errores comunes al tocar este módulo

| Síntoma | Causa probable | Dónde mirar |
|---|---|---|
| Los mensajes nuevos no aparecen solos, hay que recargar la página | Revisar que `setInterval` en `iniciarChat()` siga corriendo — se detiene si `document.hidden` es verdadero (pestaña en segundo plano, es esperado) o si hubo un error de JS que rompió la ejecución antes de llegar a esa línea | `resources/js/chat.js` línea 186 |
| Un mensaje se ve "enviado" (burbuja gris `msg-pending`) pero nunca se confirma | El `fetch()` del envío falló silenciosamente o tardó — revisar la pestaña de Red del navegador; el mensaje real solo aparece tras el siguiente `consultarNuevos()` | `resources/js/chat.js::iniciarEnvio()` |
| El administrador "roba" los mensajes no leídos del asesor al mirar un hilo | No debería pasar — `participa()` en el modelo `Conversacion` excluye al administrador a propósito | `app/Models/Conversacion.php::participa()` |
| Un cliente nuevo no puede iniciar conversación ("No hay asesores disponibles") | No hay ningún usuario con rol Asesor (o Administrador) en estado Activo — no es un bug del chat, es una condición real de datos | `app/Servicios/MensajeService.php::asesorPara()` |
| El asesor de un cliente "cambió solo" | Es esperado si el asesor anterior fue desactivado — `asesorPara()` reasigna automáticamente al siguiente disponible por carga de trabajo | `app/Servicios/MensajeService.php::asesorPara()` |
