# Despliegue en InfinityFree

## Cómo ejecutar la sonda

1. Editar las constantes al inicio de `deploy/infinityfree/sonda.php`: `CLAVE` (una cadena larga y aleatoria) y las credenciales MySQL del panel (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`). No commitear estos valores.
2. Subir el archivo a `htdocs/sonda.php`.
3. Abrir `https://<dominio>/sonda.php?clave=<CLAVE>` en el navegador.
4. Pegar el resultado en la tabla de abajo.
5. Borrar `htdocs/sonda.php` del servidor y comprobar que la URL da 404.

## Resultado de la sonda

| clave | valor | ¿cumple? |
|---|---|---|
| php_version | pendiente | pendiente |
| extensiones_faltantes | pendiente | pendiente |
| memory_limit | pendiente | pendiente |
| upload_max_filesize | pendiente | pendiente |
| post_max_size | pendiente | pendiente |
| max_execution_time | pendiente | pendiente |
| symlink | pendiente | pendiente |
| putenv | pendiente | pendiente |
| fastcgi_finish_request | pendiente | pendiente |
| argon2id | pendiente | pendiente |
| mysql_version | pendiente | pendiente |
| motor_por_defecto | pendiente | pendiente |
| fk_soportadas | pendiente | pendiente |
| create_view | pendiente | pendiente |
| smtp_587_brevo | pendiente | pendiente |
| smtp_587_gmail | pendiente | pendiente |
| stripe_https | pendiente | pendiente |

## Criterios de aceptación (requisitos mínimos)

- Bloqueante: PHP < 8.3, o falta alguna de `pdo_mysql, mbstring, openssl, fileinfo, dom, xml, ctype, tokenizer`.
- Si falta `gd`: la redimensión de `ImagenInmuebleService::redimensionarSiExcede` debe omitirse cuando `! extension_loaded('gd')` (Tarea 14).
- Si falta `zip` o `xmlwriter`: la exportación Excel se valida en la Tarea 19.
- `motor_por_defecto` y `fk_soportadas` determinan la rama condicional de la Tarea 4; `smtp_587_*` y `stripe_https` la de la Tarea 6 (`stripe_https` con cualquier código HTTP distinto de 0, p. ej. 401 o 404, indica conectividad correcta; 0 = sin salida HTTPS).

## Hash de contraseñas (bcrypt)

La documentación (HU-03.4, HU-24.4, HU-25.2) exige bcrypt. `.env.example` y `deploy/infinityfree/env.produccion.example` ya fijan `HASH_DRIVER=bcrypt`.

- El `.env` local también debe pasar a `HASH_DRIVER=bcrypt` (no se modifica automáticamente porque contiene credenciales reales).
- Las contraseñas de los seeders se regeneran con bcrypt al ejecutar `migrate:fresh --seed` (Task 9); los hashes argon2id existentes dejan de ser válidos al cambiar el driver.

## Correo (Tarea 6)

Producción usa `QUEUE_CONNECTION=sync` y `MAIL_MAILER=failover` (`config/mail.php`: `['smtp', 'log']`). Los `Mailable` con `ShouldQueue` se envían en la misma petición; si SMTP falla o está bloqueado, el correo se escribe en `storage/logs/laravel.log` y la petición sigue (prueba: `NotificacionTest::test_smtp_caido_no_interrumpe_el_pago`).

Proveedor SMTP según la sonda:

- `smtp_587_brevo` abierto: Brevo, `MAIL_HOST=smtp-relay.brevo.com`, `MAIL_PORT=587`, cifrado TLS.
- Si no, `smtp_587_gmail` abierto: Gmail con contraseña de aplicación, `MAIL_HOST=smtp.gmail.com`, `MAIL_PORT=587`.
- Si ambos están cerrados: el sistema funciona igual y los correos quedan solo en el log (riesgo aceptado: el cliente no recibe comprobantes ni enlaces de recuperación de contraseña por correo).

## Stripe sin webhook (Tarea 10)

Ningún flujo de pago exige el webhook entrante:

| Flujo | Cómo se completa sin webhook |
|---|---|
| Guardar tarjeta | Stripe.js confirma el SetupIntent desde el navegador y `MetodoPagoController::store` consulta a Stripe. |
| Pagar con tarjeta guardada | `StripeCardService::pagarConTarjeta` crea el PaymentIntent con `confirm => true` en el servidor; si queda `succeeded`, aprueba el pago en la misma petición (`PagoTest::test_hu_23_1_pago_exitoso_sin_webhook`: reserva `Confirmada`, inmueble `Reservado`, comprobante enviado, cero eventos de webhook). |
| Verificación 3D Secure | Tras `handleNextAction` en el navegador, `ReservaController::sincronizarPago` consulta el intento a Stripe y aplica el resultado. |
| 3DS abandonado (pestaña cerrada) | `pagos:conciliar`, ejecutado por el planificador por petición (`EjecutarTareasPendientes`, cada 15 min como mínimo), consulta y concilia el pago (Tarea 5). |

- **Salida HTTPS a Stripe**: la fila `stripe_https` de la sonda debe mostrar cualquier código HTTP > 0 (401 o 404 son válidos: hubo respuesta de `api.stripe.com`). Con 0 no hay salida HTTPS: solo queda el pago manual (transferencia/efectivo) con revisión del administrador, y HU-20 y HU-23.1 se marcan «No soportado en InfinityFree gratuito» en la matriz de conformidad.
- **Webhook**: en el panel de Stripe no se registra ningún endpoint para el dominio de InfinityFree; sería bloqueado por el desafío JavaScript `aes.js`. `STRIPE_WEBHOOK_SECRET` puede quedar vacío; la ruta `stripe/webhook` sigue existiendo pero no se usa.
- **Claves**: solo modo de pruebas (`pk_test_…` en `STRIPE_KEY`, `sk_test_…` en `STRIPE_SECRET`). Stripe.js (`js.stripe.com`) se carga sin Content-Security-Policy, así que no hay cabeceras que ajustar.
