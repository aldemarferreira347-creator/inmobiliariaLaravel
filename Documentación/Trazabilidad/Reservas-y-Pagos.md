# Reservas y Pagos (incluye Stripe)

> El flujo más largo y con más piezas del sistema: un cliente reserva un
> inmueble, paga (por transferencia manual que revisa un administrador, o
> con tarjeta guardada vía Stripe), y el inmueble cambia de estado solo
> cuando el pago queda confirmado — nunca antes.

## 1. Qué es este módulo

Cubre tres cosas que están entrelazadas y conviene entender juntas:

1. **Reservas** (`reserva`): un cliente "aparta" un inmueble por 24 horas
   mientras completa el pago.
2. **Pagos** (`pago`): el registro de cada intento de pago sobre una
   reserva — puede ser declarado a mano por el cliente (transferencia,
   consignación, efectivo) y revisado por un administrador, o procesado
   automáticamente por Stripe con una tarjeta guardada.
3. **Tarjetas guardadas** (`metodo_pago_guardado`) y el **webhook de
   Stripe**: la integración con la pasarela de pago real.

**Regla de oro de todo el módulo, repetida en varios comentarios del
código:** el inmueble **no** cambia de estado cuando se crea la reserva —
sigue "Disponible" con un bloqueo lógico (nadie más puede reservarlo
mientras esta solicitud esté viva) y solo pasa a "Reservado" cuando el pago
queda **confirmado**. Si en algún momento ves un inmueble marcado
"Reservado" sin ningún pago aprobado detrás, algo rompió esta regla.

## 2. Mapa de archivos

| Pieza | Archivo |
|---|---|
| Modelo reserva | `app/Models/Reserva.php` |
| Modelo pago | `app/Models/Pago.php` |
| Modelo tarjeta guardada | `app/Models/MetodoPagoGuardado.php` |
| Modelo historial | `app/Models/HistorialReserva.php` |
| Modelo evento de webhook | `app/Models/WebhookEvento.php` |
| Controller del cliente | `app/Http/Controllers/ReservaController.php` |
| Controller del panel | `app/Http/Controllers/Admin/ReservaController.php` |
| Controller de tarjetas | `app/Http/Controllers/MetodoPagoController.php` |
| Controller del webhook | `app/Http/Controllers/StripeWebhookController.php` |
| Lógica de reservas | `app/Servicios/ReservaService.php` |
| Lógica de pagos | `app/Servicios/PagoService.php` |
| Lógica de Stripe (tarjetas + cobro) | `app/Servicios/StripeCardService.php` |
| Validación de solicitud de reserva | `app/Http/Requests/SolicitarReservaRequest.php` |
| Validación de registrar pago manual | `app/Http/Requests/RegistrarPagoRequest.php` |
| Validación de revisión de pago (admin) | `app/Http/Requests/Admin/RevisarPagoRequest.php` |
| Validación de guardar tarjeta | `app/Http/Requests/GuardarTarjetaRequest.php` |
| Quién puede ver/cancelar/pagar | `app/Politicas/ReservaPolicy.php` |
| Comando de expiración automática | `app/Console/Commands/ExpirarReservas.php` |
| Vistas cliente | `resources/views/reservas/{index,show,partials/*}.blade.php`, `resources/views/perfil/tarjetas/index.blade.php` |
| Vistas admin | `resources/views/admin/reservas/{index,show}.blade.php` |
| JS | `resources/js/cuenta-atras.js` (temporizador de 24h), `resources/js/tarjetas.js` (Stripe.js/Elements en el navegador) |
| Correo de comprobante | `app/Mail/ComprobantePago.php`, plantilla `resources/views/emails/comprobante-pago.blade.php` |
| Rutas | `routes/web.php` líneas 51-63 (cliente), 59-63 (tarjetas), 147-150 (admin), línea 30 (webhook) |
| Tests | `tests/Feature/ReservaTest.php`, `tests/Feature/PagoTest.php` |

## 3. Solicitar una reserva — trazabilidad completa

### 3.1 El botón "Reservar" en la ficha del inmueble

En `resources/views/inmuebles/show.blade.php`, el botón solo aparece si el
inmueble está Disponible **y** hay sesión iniciada (si no hay sesión, el
enlace lleva al login en su lugar — HU-07.2). Abre un modal
(`reservas/partials/modal-solicitar.blade.php`) con un `<select>` de
modalidad (solo si el inmueble es "Ambos"; si ya es Venta o Arriendo puro,
no hace falta elegir) y una casilla de aceptación de condiciones.

### 3.2 El controller: `ReservaController::store()`

`POST /reservas` → `SolicitarReservaRequest` (valida que se acepten las
condiciones y, si aplica, que se indique una modalidad) →

```php
public function store(SolicitarReservaRequest $request): RedirectResponse
{
    $inmueble = Inmueble::findOrFail($request->integer('inmueble_id'));

    $reserva = $this->reservas->solicitar(
        $inmueble, $request->user(), $request->modalidad(), $request->input('notas_cliente'),
    );
    // ...
}
```

Nótese que **el monto a pagar no viene del formulario en ningún momento** —
el controller ni siquiera lo lee del `Request`. Esto es intencional (HU-07.1,
"el monto se toma del inmueble y no del formulario" — hay un test que lo
verifica explícitamente, `ReservaTest::el_monto_se_toma_del_inmueble_y_no_del_formulario`).

### 3.3 `ReservaService::solicitar()` — el corazón de la reserva

Tres cosas importantes pasan dentro de una única transacción de base de
datos (`DB::transaction`):

1. **`Inmueble::whereKey(...)->lockForUpdate()->firstOrFail()`** — un
   "bloqueo pesimista": mientras esta transacción esté abierta, ninguna
   otra petición puede leer y modificar esa misma fila de inmueble al mismo
   tiempo. Esto existe específicamente para que **dos clientes no puedan
   reservar el mismo inmueble al mismo instante** (condición de carrera) —
   sin este bloqueo, dos peticiones casi simultáneas podrían pasar ambas la
   validación "está disponible" antes de que ninguna alcance a guardar su
   reserva.
2. **`asegurarQueEsReservable()`** — verifica que el inmueble siga
   `Disponible` y que no tenga ya otra solicitud pendiente
   (`tieneSolicitudPendiente()`, del modelo `Inmueble`). Si falla, lanza una
   `ValidationException` — la transacción se revierte sola, no queda nada a
   medias.
3. **`montoDeReserva()`** — lee `precio_venta` o `precio_arrendamiento`
   directamente del inmueble bloqueado, según la modalidad. Es el único
   lugar de todo el flujo donde se decide cuánto va a pagar el cliente.

Se crea la `Reserva` con `estado = PendientePago` y
`expira_en = now()->addHours(Reserva::HORAS_PARA_PAGAR)` (24 horas,
constante del modelo). Se registra también la primera fila de
`HistorialReserva` — cada cambio de estado de una reserva, en todo el
sistema, queda anotado ahí con quién lo hizo y cuándo (útil para reconstruir
qué pasó si algo se ve raro).

Fuera de la transacción (a propósito: si el envío de notificaciones fallara,
no debe deshacer la reserva ya guardada), se avisa al cliente **y** al
staff (administradores/asesores) vía `NotificacionService` — ver
`Documentación/Trazabilidad/Notificaciones.md`.

### 3.4 El temporizador de 24 horas

`resources/js/cuenta-atras.js` — puramente visual, lee un timestamp que la
vista le pasa (`expira_en`) y actualiza un contador "Vence en Xh Ym Zs" en
el navegador del cliente. **El que realmente hace cumplir el plazo no es
este JS** — es el comando de consola `reservas:expirar` (ver
[sección 7](#7-expiración-automática-de-reservas-vencidas)); si ese comando
no corre, una reserva vencida sigue "viva" en la base de datos aunque el
contador en pantalla ya muestre 0.

## 4. Pagar la reserva

Hay **dos caminos completamente distintos** para pagar, y es importante no
confundirlos:

### 4.1 Camino A — Pago manual (transferencia/consignación/efectivo), revisado por un humano

`POST /reservas/{reserva}/pago` → `RegistrarPagoRequest` → `ReservaController::registrarPago()`
→ `PagoService::registrar()`:

- Verifica `$reserva->admiteNuevoPago()` (del modelo `Reserva`: el estado lo
  permite, no está vencida, y no hay ya otro pago en revisión para esa
  misma reserva — esto es lo que impide que un cliente registre dos pagos a
  la vez sobre la misma reserva).
- Crea el `Pago` con `estado = Procesando` y cambia la reserva a
  `ProcesandoPago` — desde ese momento el contador de 24h deja de importar
  (la reserva ya no está "pendiente de pago", está "en revisión").
- Notifica al staff que hay un pago para revisar. **No pasa nada más
  automáticamente** — el pago se queda en "Procesando" hasta que un
  administrador lo revise a mano.

**La revisión, del lado del administrador:**
`POST /admin/reservas/{reserva}/pagos/{pago}` →
`Admin\ReservaController::revisarPago()` → `RevisarPagoRequest` decide si
es aprobación o rechazo (`$request->apruebaElPago()`) →

- **Aprobar** → `PagoService::aprobar()`: marca el pago `Pagado`, y —esto es
  clave— llama a `ReservaService::confirmar()`, que es el **único** lugar de
  todo el sistema donde `$reserva->inmueble->update(['estado' => EstadoInmueble::Reservado])`
  se ejecuta. Después intenta enviar el comprobante por correo
  (`Mail::to(...)->queue(new ComprobantePago(...))`) dentro de un
  `try/catch` — si el correo falla, se registra el error
  (`report($e)`) pero **la reserva sigue confirmada**, el fallo de envío no
  la revierte.
- **Rechazar** → `PagoService::rechazar()`: marca el pago `Rechazado` y
  devuelve la reserva a `PendientePago` — el cliente puede intentar pagar
  de nuevo mientras el plazo original de 24h no haya vencido.

### 4.2 Camino B — Pago con tarjeta guardada (Stripe), automático

Este camino tiene un paso previo (guardar la tarjeta) y luego el cobro en
sí, y **puede resolverse en el momento o quedar pendiente de una
verificación adicional del banco** — por eso existe el webhook.

**Guardar una tarjeta** (`/perfil/tarjetas`):

1. `POST /perfil/tarjetas/setup-intent` → `MetodoPagoController::setupIntent()`
   → `StripeCardService::crearSetupIntent()`: crea (o reutiliza) un
   `Customer` en Stripe para ese cliente, y abre un `SetupIntent`. El
   `client_secret` que devuelve viaja al navegador.
2. En el navegador, `resources/js/tarjetas.js` usa **Stripe.js/Elements**
   para tokenizar los datos de la tarjeta **directamente contra los
   servidores de Stripe** — el número de tarjeta, CVV y fecha de
   vencimiento **nunca llegan a este servidor**, ni siquiera de paso. Esto
   no es una opción de diseño menor: es lo que le permite al sistema no
   tener que cumplir PCI-DSS como si manejara tarjetas directamente.
3. `POST /perfil/tarjetas` → `MetodoPagoController::store()` →
   `StripeCardService::guardarTarjeta()`: recibe solo el `payment_method_id`
   que Stripe generó, le **vuelve a preguntar a Stripe** los datos de esa
   tarjeta (marca, últimos 4 dígitos) para no confiar en nada que el
   navegador diga sobre sí mismo, y guarda esos datos (nunca el número
   completo) en `metodo_pago_guardado`.

**Pagar con la tarjeta guardada:**
`POST /reservas/{reserva}/pagar-con-tarjeta/{tarjeta}` →
`ReservaController::pagarConTarjeta()` → primero confirma que la tarjeta le
pertenece al usuario logueado (`abort_unless($tarjeta->cliente_id === ...)`)
→ `StripeCardService::pagarConTarjeta()`:

1. Crea el `Pago` local en estado `Procesando` (igual que el camino manual).
2. Llama a `stripe->paymentIntents->create(['confirm' => true, ...])` — le
   pide a Stripe que cobre ya mismo.
3. Según lo que Stripe responda:
   - **`succeeded`** (aprobado al instante) → `PagoService::aprobar()` se
     llama directamente, sin esperar nada más.
   - **`requires_action`** (el banco pide 3D Secure u otra verificación) →
     el pago se queda en `Procesando` y el método devuelve un
     `client_secret` para que el navegador complete esa verificación con el
     cliente. **La confirmación final de este caso llega después, por el
     webhook** (sección siguiente).
   - Cualquier error de tarjeta (`CardException`) → `PagoService::rechazar()`
     inmediatamente, con el motivo que dio Stripe.

### 4.3 El webhook de Stripe — por qué existe

`POST /stripe/webhook` → `StripeWebhookController::recibir()`. Esta ruta es
distinta a todas las demás del sistema en dos aspectos, ambos visibles en
`routes/web.php` (línea 30) y `bootstrap/app.php`:

- **Está exenta de CSRF** (`validateCsrfTokens(except: ['stripe/webhook'])`)
  — porque quien llama no es un navegador con sesión, es el propio servidor
  de Stripe.
- **No pasa por el middleware `auth`** — en su lugar, se autentica de una
  forma completamente distinta: verificando la firma criptográfica del
  encabezado `Stripe-Signature` contra `STRIPE_WEBHOOK_SECRET`
  (`Webhook::constructEvent(...)`). Si la firma no coincide (petición
  falsa, o el secreto en `.env` no coincide con el configurado en el
  dashboard de Stripe), responde 400 sin procesar nada.

**Por qué hace falta este webhook y no basta con la respuesta directa de
Stripe:** cuando un pago requiere 3D Secure, la respuesta inmediata al
navegador es solo "hace falta verificar" — la confirmación real de si el
banco aprobó o no llega más tarde, de forma asíncrona, y Stripe la
comunica llamando a esta URL. Por eso `procesarPagoExitoso()` y
`procesarPagoFallido()` buscan el `Pago` local por `referencia_pasarela`
(el ID del PaymentIntent) y **solo actúan si sigue en estado
`Procesando`** — así, si el pago ya se resolvió por otro camino, el webhook
no lo vuelve a tocar.

`WebhookEvento::registrarSiEsNuevo('Stripe', $evento->id, $evento->type)`
(línea 43) es la protección contra **reintentos**: Stripe puede reenviar el
mismo evento más de una vez si no recibe una respuesta 200 a tiempo; esta
tabla evita procesar dos veces el mismo evento (lo que podría, por ejemplo,
intentar confirmar dos veces una reserva ya confirmada).

## 5. Cancelar una reserva

`ReservaPolicy::cancelar()` decide quién puede: un administrador puede
cancelar cualquier reserva que no esté en estado final; un cliente solo
puede cancelar **la suya propia**, y solo si el estado todavía
`admiteCancelacionDelCliente()` (típicamente, antes de haber pagado —
HU-07.5). `ReservaService::cancelar()` libera el inmueble llamando a
`$inmueble->estadoCalculado()` en vez de forzarlo siempre a "Disponible" —
si ese inmueble tuviera **otra** reserva viva o un contrato, conserva ese
estado en lugar de liberarse de más (el comentario del código aclara que
esto corrige un comportamiento distinto que tenía el prototipo original).

## 6. Revisar el "estado en revisión" de un pago

Un dato sutil: mientras un pago está `Procesando` (recién registrado, sin
resolver todavía), la reserva asociada está en `ProcesandoPago`, **no**
`PendientePago`. Si buscás por qué una reserva "desapareció" del contador
de pendientes de pago, revisá si tiene un pago en revisión — no está
perdida, cambió de categoría.

## 7. Expiración automática de reservas vencidas

`app/Console/Commands/ExpirarReservas.php`, programado cada hora
(`routes/console.php`). Recorre `Reserva::vencidas()` (estado
`PendientePago` + `expira_en` en el pasado) y llama
`ReservaService::expirar()` por cada una, dentro de un `try/catch`
individual — si una falla, no detiene el procesamiento de las demás. **Si
en producción las reservas vencidas nunca se liberan solas**, lo primero a
revisar no es este comando sino si el cron del sistema operativo está
corriendo `php artisan schedule:run` cada minuto (ver
`Documentación/Trazabilidad/Trazabilidad-Sistema.md` §2, tabla "qué se
rompe si...").

## 8. Errores comunes al tocar este módulo

| Síntoma | Causa probable | Dónde mirar |
|---|---|---|
| El inmueble cambió a "Reservado" sin que haya un pago aprobado | Alguien está llamando `$inmueble->update(['estado' => ...])` directamente en vez de pasar por `ReservaService::confirmar()` — buscar ese patrón fuera de `ReservaService.php` | `app/Servicios/ReservaService.php::confirmar()` es el único lugar legítimo |
| Un pago con tarjeta se queda "Procesando" para siempre | Revisar que `STRIPE_WEBHOOK_SECRET` en `.env` coincida con el configurado en el dashboard de Stripe, y que la URL `/stripe/webhook` sea alcanzable desde internet (Stripe no puede llamar a `localhost`) | `config/services.php`, dashboard de Stripe |
| Dos clientes lograron reservar el mismo inmueble | El `lockForUpdate()` de `ReservaService::solicitar()` debería impedirlo — revisar que no se haya quitado esa línea en algún refactor | `app/Servicios/ReservaService.php` línea 44 |
| El monto de una reserva no coincide con el precio actual del inmueble | Es esperado si el precio del inmueble cambió **después** de crear la reserva — el monto se congela en el momento de reservar (`monto_reserva`), no se recalcula | Comparar `reserva.monto_reserva` (fijo) vs. `inmueble.precio_venta`/`precio_arrendamiento` (puede haber cambiado) |
| Las reservas vencidas no se liberan solas | El comando `reservas:expirar` no está corriendo — revisar el cron del servidor, no el código PHP | Ver sección 7 arriba |
| Se registran dos pagos a la vez sobre la misma reserva | No debería poder pasar — `Reserva::admiteNuevoPago()` lo bloquea mientras haya un pago `Pendiente`/`Procesando`; si pasó, revisar si algo está llamando a `Pago::create()` sin pasar por `PagoService::registrar()` | `app/Models/Reserva.php::admiteNuevoPago()` |
