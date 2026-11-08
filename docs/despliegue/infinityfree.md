# Despliegue en InfinityFree

## Cómo ejecutar la sonda

La sonda se ejecuta **antes de subir la aplicación**. Con la aplicación ya subida, el `.htaccess` de la raíz reenvía toda URL a `/public/`: un `htdocs/sonda.php` no llegaría a ejecutarse y el 404 tras borrarlo no probaría nada. Si la aplicación ya está subida, usar `htdocs/public/sonda.php` en su lugar (misma URL).

1. Activar antes el SSL gratuito y crear la BD (pasos 1 y 2 de «Publicación paso a paso») para abrir la sonda por `https://`; si aún no está emitido, abrirla por `http://` y repetir solo la sección «HTTPS entrante» cuando lo esté.
2. Editar las constantes al inicio de `deploy/infinityfree/sonda.php`: `CLAVE` (una cadena larga y aleatoria) y las credenciales MySQL del panel (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`). No commitear estos valores.
3. Subir el archivo a `htdocs/sonda.php` (o a `htdocs/public/sonda.php` si la aplicación ya está subida).
4. Abrir `https://<dominio>/sonda.php?clave=<CLAVE>` en el navegador.
5. Pegar el resultado en la tabla de abajo.
6. Borrar el archivo del servidor y comprobar que la URL da 404 (con la aplicación subida, la página 404 de Laravel).

## Resultado de la sonda

| clave | valor | ¿cumple? |
|---|---|---|
| php_version | pendiente | pendiente |
| server_https | pendiente | pendiente |
| http_x_forwarded_proto | pendiente | pendiente |
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
- Si falta `gd`: la redimensión de `ImagenInmuebleService::redimensionarSiExcede` debe omitirse cuando `! extension_loaded('gd')` (Tarea 14); la guarda está implementada incondicionalmente (`ImagenInmuebleService::gdDisponible`).
- Límites de subida: cada imagen de inmueble admite hasta 5 MB (`max:5120`). El formulario permite hasta 10 imágenes por envío (`InmuebleRequest::MAXIMO_IMAGENES`). Si `post_max_size` de la sonda es menor que 5 MB × imágenes por envío (hasta 50 MB), el administrador debe subir menos imágenes por guardado (p. ej. `floor(post_max_size / 5 MB)`); no se cambia el límite del formulario hasta tener el dato de la sonda. `upload_max_filesize` debe ser ≥ 5 MB.
- Si falta `zip` o `xmlwriter`: la exportación Excel se valida en la Tarea 19.
- `motor_por_defecto` y `fk_soportadas` determinan la rama condicional de la Tarea 4; `smtp_587_*` y `stripe_https` la de la Tarea 6 (`stripe_https` con cualquier código HTTP distinto de 0, p. ej. 401 o 404, indica conectividad correcta; 0 = sin salida HTTPS).
- `fastcgi_finish_request`: con «si», las tareas cada 15 min (`EjecutarTareasPendientes`) corren después de entregar la página. Con «no», el visitante que las dispara espera a que terminen (normalmente menos de un segundo; aceptable, no bloquea el despliegue).
- HTTPS: abierta por `https://`, `server_https` debe ser `on` o `http_x_forwarded_proto` debe ser `https`. Si ninguno lo es, la redirección a HTTPS del `.htaccess` entraría en bucle: quitar ese bloque de `htdocs/.htaccess` y avisar.
- SMTP: si `smtp_587_brevo` y `smtp_587_gmail` están cerrados, poner `MAIL_MAILER=log` en el `.env` (ver «Correo»).

## Publicación paso a paso

Requisitos locales: suite en verde (`php artisan test --compact`) y `vendor/bin/pint --test` sin cambios.

1. **SSL primero.** En el panel de InfinityFree, activar el certificado SSL gratuito del dominio y **esperar a que figure como emitido** antes de seguir. El `.htaccess` redirige todo a `https://`; sin certificado el sitio no abriría. La validación usa `/.well-known/`, que el `.htaccess` deja pasar.
2. **Base de datos.** En el panel, «MySQL Databases»: crear la BD y copiar del panel el **host** (`sqlNNN.infinityfree.com`, varía por cuenta), el **nombre** (`if0_…_nombre`), el **usuario** (`if0_…`) y la **contraseña** de la cuenta.
3. **Sonda.** Ejecutarla ahora, con esas credenciales y con `htdocs` todavía sin la aplicación (ver «Cómo ejecutar la sonda»), y revisar los criterios de aceptación.
4. **Generar los artefactos en local** (si no están al día): `pwsh deploy/infinityfree/empaquetar.ps1` y `pwsh deploy/infinityfree/exportar-bd.ps1`. El segundo aborta si existe `bootstrap/cache/config.php` (ejecutar antes `php artisan config:clear`), para que la configuración en caché nunca dirija la migración a la BD de desarrollo.
5. **Importar** `build-infinityfree/bd.sql` desde phpMyAdmin (botón en el panel), pestaña «Importar», sobre la BD creada. Debe terminar sin errores.
6. **Crear el `.env`.** Copiar `deploy/infinityfree/env.produccion.example` como `build-infinityfree/htdocs/.env` y completar: `APP_URL=https://<dominio>`, `APP_KEY` con la salida de `php artisan key:generate --show` (una clave nueva, nunca la de desarrollo), `DB_HOST`/`DB_DATABASE`/`DB_USERNAME`/`DB_PASSWORD` con los datos del paso 2, y el correo según la sonda (`MAIL_MAILER=log` si ambos SMTP están cerrados). Las claves de Stripe, solo de pruebas.
7. **Subir por FTP** con FileZilla (datos FTP del panel): en «Transferencia → Tipo de transferencia» elegir **Binario**, y en «Servidor → Forzar mostrar archivos ocultos» activarlo para que suban `.env` y `.htaccess`. Subir **todo el contenido** de `build-infinityfree/htdocs/` dentro de `htdocs/` del servidor (no la carpeta `htdocs` en sí).
8. **Borrar `index2.html`** de `htdocs/` si InfinityFree lo creó (página por defecto).
9. **Primer ingreso.** Abrir `https://<dominio>/login` con las credenciales de `build-infinityfree/CREDENCIALES.txt` (administrador, asesor y cliente de demostración). Cambiar las tres contraseñas desde el perfil y **borrar la copia local de `CREDENCIALES.txt`** al terminar. Borrar el `.env` de `build-infinityfree/htdocs/` (o volver a ejecutar `empaquetar.ps1`) para que no quede la configuración de producción en local.
10. **Seguridad y humo**: plan, Task 21, pasos 5 a 7 (desde el navegador; el anti-bots bloquea curl).

## Hash de contraseñas (bcrypt)

La documentación (HU-03.4, HU-24.4, HU-25.2) exige bcrypt. `.env.example` y `deploy/infinityfree/env.produccion.example` ya fijan `HASH_DRIVER=bcrypt`.

- El `.env` local también debe pasar a `HASH_DRIVER=bcrypt` (no se modifica automáticamente porque contiene credenciales reales).
- Las contraseñas de los seeders se regeneran con bcrypt al ejecutar `migrate:fresh --seed` (Task 9); los hashes argon2id existentes dejan de ser válidos al cambiar el driver.

## Riesgo aceptado: enumeración de cuentas

Por exigencia de la documentación, HU-05.3 («No encontramos una cuenta con ese correo. ¿Deseas registrarte?») y HU-24.2 («No existe una cuenta asociada a este correo electrónico») revelan si un correo tiene cuenta, y el registro también avisa de correos repetidos (HU-03.2). La auditoría anterior lo había ocultado a propósito (OWASP A07). Se acepta el riesgo; la mitigación que se mantiene es el límite de intentos de inicio de sesión (5 por correo e IP, y además 20 peticiones de inicio de sesión por minuto e IP con `throttle:20,1`, que frena probar muchos correos desde una misma IP; `AutenticacionTest::test_el_inicio_de_sesion_esta_limitado_por_ip`) y de solicitudes de recuperación y registro (6 por minuto).

## Correo (Tarea 6)

Producción usa `QUEUE_CONNECTION=sync` y `MAIL_MAILER=failover` (`config/mail.php`: `['smtp', 'log']`). Los `Mailable` con `ShouldQueue` se envían en la misma petición; si SMTP falla o está bloqueado, el correo se escribe en `storage/logs/laravel.log` y la petición sigue (prueba: `NotificacionTest::test_smtp_caido_no_interrumpe_el_pago`, que usa el timeout por defecto y exige menos de 10 s). El mailer `smtp` espera como mucho `MAIL_TIMEOUT` segundos (5 por defecto, `config/mail.php`) antes de pasar al log; sin ese límite un puerto filtrado bloquearía cada petición con correo alrededor de 60 s.

Proveedor SMTP según la sonda:

- `smtp_587_brevo` abierto: Brevo, `MAIL_HOST=smtp-relay.brevo.com`, `MAIL_PORT=587`, cifrado TLS.
- Si no, `smtp_587_gmail` abierto: Gmail con contraseña de aplicación, `MAIL_HOST=smtp.gmail.com`, `MAIL_PORT=587`.
- Si la sonda muestra ambos SMTP cerrados, poner `MAIL_MAILER=log` (así ninguna petición espera al timeout SMTP): el sistema funciona igual y los correos quedan solo en el log (riesgo aceptado: el cliente no recibe comprobantes ni enlaces de recuperación de contraseña por correo).

Envío masivo (HU-22.1): toda difusión del administrador (a un rol o «a todos») es solo dentro del sistema, sin correo (el formulario muestra la casilla únicamente para un usuario y el servidor ignora `enviar_correo` en difusiones). Con `QUEUE_CONNECTION=sync` y SMTP real, cientos de envíos en una sola petición superarían el límite de ejecución de InfinityFree. Solo el envío individual conserva el correo opcional (`NotificacionTest::test_hu_22_1_envio_a_todos_es_solo_in_app_aunque_se_marque_el_correo`, `::test_hu_22_1_envio_a_un_rol_es_solo_in_app_aunque_se_marque_el_correo`).

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
- **Cobro sin confirmar**: si la conexión con Stripe cae al pagar con tarjeta guardada (23.3) o hay un error interno (23.5), el pago queda rechazado en la aplicación pero el cliente no recibe «Pago rechazado» ni correo: su aviso es neutro («No pudimos confirmar tu pago»), porque Stripe pudo haber cobrado. Ante la caída de conexión se avisa además a los administradores («Pago con tarjeta sin confirmar», con código de reserva, id de pago y clave de idempotencia `pago-{id}`) para que verifiquen el cobro en el panel de Stripe, y el botón «Reintentar pago» reutiliza esa clave en las 23 h siguientes para no cobrar dos veces. El vínculo del reintento vive en la caché de Laravel: si se vacía, se pierde (mejor esfuerzo) y solo queda el aviso al administrador.
- **Claves**: solo modo de pruebas (`pk_test_…` en `STRIPE_KEY`, `sk_test_…` en `STRIPE_SECRET`). Stripe.js (`js.stripe.com`) se carga sin Content-Security-Policy, así que no hay cabeceras que ajustar.

## Base de datos de producción (Tarea 9)

`pwsh deploy/infinityfree/exportar-bd.ps1` crea la BD local `inmobiliaria_export` (la de desarrollo no se toca), ejecuta `migrate:fresh --seed` en modo producción con bcrypt y genera `build-infinityfree/bd.sql` (sin `DEFINER`, sin `utf8mb4_0900_ai_ci`, sin datos de `sessions`, `cache`, `cache_locks`, `jobs` y `failed_jobs`; máximo 8 MB). La contraseña inicial del administrador (`admin@inmobiliaria.test`) se genera al azar, se pasa al seeder mediante `ADMIN_PASSWORD_INICIAL` y solo se escribe en `build-infinityfree/CREDENCIALES.txt` (ignorado por git). Importar `bd.sql` desde phpMyAdmin.

### Cambios de esquema posteriores

No hay artisan en el servidor. Para un cambio de esquema:

1. Crear la migración nueva (nunca editar las existentes) y probarla en local.
2. Obtener solo el SQL del cambio: `php artisan migrate --pretend`.
3. Pegar ese `ALTER`/`CREATE` en phpMyAdmin (pestaña SQL) sobre la BD de InfinityFree.
4. Insertar la fila correspondiente en la tabla `migrations` para mantener el registro coherente con el repositorio.

No volver a importar `bd.sql` completo: borraría los datos reales.

### Prueba de humo local del paquete

Si se sirve `build-infinityfree/htdocs` en local (p. ej. con `php -S`), la aplicación crea sesiones, vistas compiladas y logs dentro de ese directorio y necesita un `.env` temporal. Después hay que volver a ejecutar `deploy/infinityfree/empaquetar.ps1` (o hacer la prueba sobre una copia) antes de subir por FTP, para que `htdocs` quede sin residuos ni `.env`.

## Exportación de reportes (Tarea 19)

Los topes se midieron justo en el límite, cada uno en un proceso PHP aparte con `php -d memory_limit=128M` (el valor supuesto de InfinityFree): una petición web completa (arranque de Laravel, autenticación, consulta y generación del archivo) sobre una BD SQLite aparte con pagos de factory, reporte de pagos (el más ancho: 8 columnas) y `APP_ENV=production`. Pico = `memory_get_peak_usage(true)`, que es lo que compara `memory_limit`.

| Exportación | Filas | Pico real | Margen sobre 128 MB | Tiempo |
|---|---|---|---|---|
| PDF pagos | 200 | 80 MB | 37 % | 1,3 s |
| **PDF pagos (tope)** | **250** | **94 MB** | **27 %** | **1,5 s** |
| PDF pagos | 300 | 108 MB | 16 % (insuficiente) | 1,9 s |
| Excel pagos | 2 000 | 80 MB | 37 % | 2,1 s |
| **Excel pagos (tope)** | **3 000** | **94 MB** | **27 %** | **3,0 s** |
| Excel pagos | 4 000 | 110 MB | 14 % (insuficiente) | 3,9 s |
| Excel pagos | 5 000 | 128 MB | 0 % (al límite) | 5,0 s |

La base tras arrancar Laravel es de 24 MB en local (sin OPcache); en el servidor el arranque suele costar menos, así que el margen real es mayor. El tiempo se midió en un equipo de desarrollo; aun 5 veces más lento queda por debajo de 20 s. La exportación tiene tres topes, todos con aviso en español al volver a la pantalla del reporte:

- **PDF: 250 filas** (`ReporteController::MAX_FILAS_PDF`): «El reporte tiene N filas y el PDF admite hasta 250. Acorte el rango o exporte a Excel.»
- **Excel: 3 000 filas** (`ReporteController::MAX_FILAS_EXCEL`): «El reporte tiene N filas y el Excel admite hasta 3.000. Acorte el rango.»
- **Rango de fechas** como guarda adicional: 31 días para el reporte diario (reservaciones) y 12 meses para el resto.

Las filas se cuentan con un `COUNT` antes de construir el archivo. El PDF y el `.xlsx` se generan por completo (el Excel en `php://temp`) antes de empezar la descarga, dentro del `try` del controlador: si falla la generación o el guardado se muestra la pantalla de error con «Intentar nuevamente» (HU-06.3). La consulta en pantalla no tiene esos límites. Si la sonda muestra un `memory_limit` mayor, se pueden subir las constantes repitiendo la medición.

## HTTPS

- `htdocs/.htaccess` redirige con 301 a `https://` cuando ni `HTTPS` es `on` ni llega `X-Forwarded-Proto: https`; `/.well-known/` queda exento (validación del certificado) y es la única ruta oculta que se sirve.
- `AppServiceProvider` fuerza el esquema `https` en los enlaces generados cuando `APP_ENV=production` y `APP_URL` empieza por `https://` (detrás del proxy PHP puede ver HTTP).
- Bajo `/storage/` nunca se sirve ni ejecuta `.php`, `.php5`, `.phtml` ni `.phar` (403), aunque alguien lograra subir uno.
- Verificado en local con Apache 2.4 de Laragon y un `htdocs` mínimo: `/` sin cabecera → 301 a `https://`; con `X-Forwarded-Proto: https`: `/` 200, `/storage/foto.txt` 200, `/storage/evil.php`, `/storage/evil.phtml`, `/storage/evil.PHP`, `/storage/app/public/evil.php`, `/.env`, `/storage/logs/laravel.log`, `/storage/app/private/x.txt` y `/vendor/autoload.php` → 403, `/.well-known/acme-challenge/token.txt` 200 (también sin cabecera, sin redirección).

## Caché en archivos

Con `CACHE_STORE=file` Laravel solo borra una entrada vencida cuando alguien la vuelve a leer, y sin cron nada limpia las de un solo uso (límites de intentos, vínculos de reintento). `EjecutarTareasPendientes`, tras las tareas de cada 15 min, revisa como mucho 500 archivos de `storage/framework/cache/data` (empezando por subcarpetas al azar) y borra los vencidos (`TareasPendientesTest::test_se_podan_las_entradas_vencidas_de_la_cache_en_archivos`). No hace falta limpiar esa carpeta por FTP.
