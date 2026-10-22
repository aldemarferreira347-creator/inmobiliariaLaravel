# Auditoría integral — Plan de implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Corregir los defectos de seguridad, funcionales, de integridad, rendimiento, mantenibilidad y UX encontrados en la auditoría completa del proyecto, sin regresiones.

**Architecture:** Se respeta la arquitectura existente (Middleware → Form Request → Controller → `app/Servicios` → Model, con Policies en `app/Politicas`). Las correcciones se hacen en la capa que ya es dueña de cada responsabilidad; no se introducen capas nuevas salvo un middleware de cabeceras y un helper de enlaces de notificación.

**Tech Stack:** Laravel 13, PHP 8.3, PHPUnit 12, SQLite (tests) / MySQL (producción), Tailwind 4 + Vite, Stripe PHP 17 / Stripe.js.

**Spec:** petición del usuario en el chat (auditoría integral, prioridad Seguridad → Funcional → Integridad → Arquitectura → Rendimiento → Mantenibilidad → UX/UI).

## Global Constraints

- Código, comentarios, mensajes y nombres en español, con el estilo existente (enums en `app/Enumerados`, servicios en `app/Servicios`, policies en `app/Politicas`).
- Sin dependencias nuevas de Composer ni npm.
- Toda corrección de lógica lleva un test de feature en `tests/Feature` que falla antes y pasa después.
- `vendor/bin/pint --dirty` limpio y `php artisan test` en verde al cerrar cada tarea.
- No se modifica `.env` (contiene credenciales reales del usuario).
- Migraciones nuevas, nunca editar las existentes (pueden estar aplicadas en producción).

## Review Focus

1. Doble clic en «Pagar»/«Registrar pago» → un solo pago y un solo cobro.
2. Webhook de Stripe y aprobación manual llegando a la vez → la reserva se confirma una sola vez.
3. Reserva cancelada con pago en revisión → el pago no puede aprobarse después y resucitarla.
4. Cliente que reservó un inmueble «ambos» en modalidad venta → no puede emitírsele un contrato de arriendo y el asesor sí puede registrar la venta.
5. Fecha/hora mal formada en solicitud de cita → error de validación, no 500.

---

## Hallazgos (resumen)

| # | Severidad | Hallazgo | Tarea |
|---|---|---|---|
| 1 | Crítico | `/admin/usuarios` da 500: ruta renombrada a `admin.usuario.store` en el último commit | 1 |
| 2 | Alto | Carreras en pagos: doble pago con doble clic; webhook + admin aprueban dos veces | 2 |
| 3 | Alto | Aprobar un pago de una reserva cancelada la vuelve a confirmar | 2 |
| 4 | Alto | Webhook marca el evento como procesado antes de procesarlo: un fallo lo pierde | 2 |
| 5 | Alto | Pago con tarjeta que exige 3DS nunca se completa: el front ignora `requires_action` | 3 |
| 6 | Alto | La reserva no guarda la modalidad: contratos de arriendo sobre reservas de compra (valor mensual = precio de venta) y la venta del inmueble reservado queda bloqueada | 4 |
| 7 | Medio | `reserva.monto_reserva`, `pago.monto` y `venta.precio_venta` son `decimal(12,2)` pero `inmueble.precio_venta` es `decimal(15,2)` | 4 |
| 8 | Medio | Enumeración de usuarios en «olvidé mi contraseña»; sin throttling en registro, recuperación, mensajes, reservas, citas y Stripe | 5 |
| 9 | Medio | Sin cabeceras de seguridad (clickjacking, sniffing); disco `local` con `serve => true` sin uso | 5 |
| 10 | Medio | Login sensible a mayúsculas en el correo; cambio de contraseña no invalida «recuérdame» | 5 |
| 11 | Medio | Adjuntos del chat en disco público (cualquiera con la URL los ve); asesor/admin pueden «iniciar» conversación como si fueran clientes | 6 |
| 12 | Medio | Inyección de fórmulas en el Excel de reportes (`=…` en títulos/notaría se ejecuta) | 7 |
| 13 | Medio | Enlaces de notificación: el cliente va a `admin.contratos.show` (403), el staff va a la vista de cliente de la reserva, las citas no enlazan | 8 |
| 14 | Medio | Crear inmueble como Reservado/Ocupado sin respaldo; editar observación de cita no realizada; escritura no se puede subir en venta cerrada; reemplazo de PDF borra el anterior antes de guardar el nuevo; fecha de cita mal formada da 500; `reservas.show` muestra tarjetas del cliente a quien no es el dueño | 9 |
| 15 | Bajo | Reportes: `resumen()` carga todas las filas con relaciones solo para contar/sumar (×4 en el índice) | 7 |
| 16 | Bajo | Código muerto: páginas `mis-arriendos`, `mis-compras`, `cambiar-password`, `perfil/tarjetas` duplican los modales del perfil y no están enlazadas; `chart.js` sin uso | 10 |
| 17 | Bajo | `database/bdinmlaravel.sql` (hashes de contraseñas) sin ignorar en git | 10 |
| 18 | UX | No existe modo oscuro | 11 |

Decisiones tomadas sin consultar (deducibles del código):
- El admin conserva la posibilidad de forzar «Disponible» (está documentado como escape intencional en `Inmueble::admiteEstado`).
- Los listados del panel siguen cargando todo y paginando en cliente (`tabla.js` filtra/exporta en cliente); a la escala de una inmobiliaria es correcto y cambiarlo rompería búsqueda y exportación.
- InfinityFree no ofrece cron ni worker de colas: se documenta en el resumen final como riesgo de despliegue, no se cambia el código.

---

### Task 1: Ruta de alta de usuarios

**Files:**
- Modify: `routes/web.php:151`
- Test: `tests/Feature/Admin/GestionUsuariosTest.php` (ya cubre el caso; hoy falla)

- [ ] Renombrar la ruta a `usuarios.store` (nombre completo `admin.usuarios.store`, el que usan la vista y el test).
- [ ] `php artisan test --filter=GestionUsuariosTest` → PASS.

### Task 2: Integridad y concurrencia de pagos

**Files:**
- Modify: `app/Servicios/PagoService.php`, `app/Servicios/StripeCardService.php`, `app/Servicios/ReservaService.php`, `app/Models/WebhookEvento.php`, `app/Http/Controllers/StripeWebhookController.php`
- Test: `tests/Feature/PagoTest.php`

**Interfaces:**
- Produces: `PagoService::aprobar(Pago, ?User)` y `rechazar(Pago, ?User, string)` re-leen el pago y la reserva con `lockForUpdate()` dentro de la transacción y lanzan `ValidationException` si el pago ya no admite revisión o si la reserva no está en `ProcesandoPago`.
- Produces: `PagoService::registrar()` y `StripeCardService::pagarConTarjeta()` bloquean la fila de la reserva y re-evalúan `admiteNuevoPago()` dentro de la transacción.
- Produces: `ReservaService::cancelar()` marca como `Rechazado` (motivo «Reserva cancelada.») los pagos en revisión.
- Produces: `WebhookEvento::procesarUnaVez(string $pasarela, string $eventoId, string $tipo, Closure $accion): bool` — transacción con bloqueo de la fila; marca `procesado_en` solo si `$accion` termina sin excepción.

- [ ] Tests: `test_aprobar_un_pago_de_una_reserva_cancelada_falla`, `test_cancelar_la_reserva_rechaza_el_pago_en_revision`, `test_un_pago_ya_aprobado_no_se_aprueba_dos_veces` (aprobar con instancia obsoleta), `test_webhook_que_falla_no_queda_marcado_como_procesado`.
- [ ] Ejecutar → FAIL. Implementar. Ejecutar → PASS.
- [ ] En el webhook, un `payment_intent.succeeded` cuyo pago ya no es revisable se reporta (`report`) y se notifica al staff para reembolso, sin 500.

### Task 3: Completar pagos con autenticación 3DS

**Files:**
- Modify: `app/Http/Controllers/ReservaController.php`, `app/Servicios/StripeCardService.php`, `routes/web.php`, `resources/views/reservas/show.blade.php` (o su partial de pagos), `resources/js/tarjetas.js`
- Test: `tests/Feature/PagoTest.php`

**Interfaces:**
- Produces: `StripeCardService::sincronizarPago(Pago $pago): string` — consulta el PaymentIntent (`referencia_pasarela`) y aplica `aprobar`/`rechazar` según `succeeded` / `requires_payment_method|canceled`; devuelve el estado de Stripe. Idempotente frente al webhook (si el pago ya no es revisable, no hace nada).
- Produces: ruta `POST reservas/{reserva}/pagos/{pago}/sincronizar` → `reservas.pagos.sincronizar` (dueño de la reserva, pago de esa reserva).
- `pagarConTarjeta` con `requires_action` flashea `stripe_client_secret` y `stripe_pago_id`; la vista los expone en `data-*`; `tarjetas.js` llama `stripe.handleNextAction({clientSecret})` y luego envía el formulario de sincronización.

- [ ] Test: `test_sincronizar_un_pago_ajeno_esta_prohibido` y `test_sincronizar_exige_que_el_pago_pertenezca_a_la_reserva` (sin red: autorización antes de llamar a Stripe).
- [ ] Implementar; verificar que el flujo sin JS sigue mostrando el mensaje informativo.

### Task 4: Modalidad de la reserva y columnas monetarias

**Files:**
- Create: `database/migrations/2026_10_22_000001_add_modalidad_to_reserva_table.php` (columna `modalidad` enum venta/arriendo nullable + backfill desde `inmueble.modalidad` cuando no es «ambos», con «arriendo» si existe contrato)
- Create: `database/migrations/2026_10_22_000002_ampliar_columnas_monetarias.php` (`reserva.monto_reserva`, `pago.monto`, `venta.precio_venta` → `decimal(15,2)`)
- Modify: `app/Models/Reserva.php` (fillable, cast, `scopeDeArriendo`, `scopeDeCompra`), `app/Servicios/ReservaService.php` (guardar modalidad elegida), `app/Servicios/ContratoService.php` (solo arriendo), `app/Http/Controllers/Admin/ContratoController.php` (elegibles solo arriendo), `app/Servicios/VentaService.php` + `app/Http/Controllers/Asesor/VentaController.php` (inmueble reservado por compra confirmada del mismo cliente es vendible), `app/Http/Controllers/PerfilController.php` (arriendos/compras por modalidad de reserva), `database/factories/ReservaFactory.php`
- Test: `tests/Feature/ContratoTest.php`, `tests/Feature/VentaTest.php`, `tests/Feature/ReservaTest.php`

**Interfaces:**
- Produces: `Reserva::$modalidad` (cast `ModalidadInmueble`), `Reserva::scopeDeArriendo(Builder)`.
- Produces: `VentaService::registrar()` acepta inmueble `Reservado` si existe reserva `Confirmada` de compra del mismo `usuario_id`.

- [ ] Tests: `test_la_reserva_guarda_la_modalidad_elegida`, `test_no_se_emite_contrato_sobre_una_reserva_de_compra`, `test_se_registra_la_venta_de_un_inmueble_reservado_para_compra_por_el_mismo_cliente`, `test_no_se_registra_la_venta_si_lo_reservo_otro_cliente`.
- [ ] FAIL → implementar → PASS; `php artisan migrate` en local (SQLite de tests ya lo cubre).

### Task 5: Endurecimiento de autenticación y plataforma

**Files:**
- Modify: `app/Http/Controllers/Auth/PasswordResetLinkController.php` (respuesta genérica siempre), `app/Http/Requests/Auth/LoginRequest.php` (normalizar correo), `app/Http/Controllers/Auth/PasswordController.php` (rotar `remember_token` y regenerar sesión), `routes/web.php` + `routes/auth.php` (throttle), `bootstrap/app.php`, `config/filesystems.php` (`serve => false`)
- Create: `app/Http/Middleware/AgregarCabecerasSeguridad.php` (`X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy: camera=(), microphone=(), geolocation=()`)
- Test: `tests/Feature/Auth/AutenticacionTest.php`, `tests/Feature/RutasProtegidasTest.php`

- [ ] Tests: `test_recuperar_contrasena_no_revela_si_el_correo_existe`, `test_login_ignora_mayusculas_del_correo`, `test_las_respuestas_incluyen_cabeceras_de_seguridad`, `test_el_registro_esta_limitado_por_frecuencia`.
- [ ] Throttles: `throttle:6,1` en registro, olvidé/restablecer contraseña; `throttle:30,1` en envío/inicio de mensajes, reservas, citas, pagos y endpoints Stripe.

### Task 6: Mensajería

**Files:**
- Modify: `app/Politicas/ConversacionPolicy.php` (`iniciar` → solo cliente), `app/Http/Controllers/MensajeController.php`, `app/Servicios/MensajeService.php` (adjuntos al disco privado vía `ArchivoPrivadoService`), `app/Models/Mensaje.php` (`adjunto_publico` → ruta autorizada; rutas legacy `storage/` se siguen sirviendo como antes), `routes/web.php` (`GET mensajes/adjuntos/{mensaje}` → `mensajes.adjunto`)
- Test: `tests/Feature/MensajeTest.php`

- [ ] Tests: `test_un_asesor_no_puede_iniciar_conversacion_como_cliente`, `test_el_adjunto_solo_lo_ve_quien_participa`, `test_el_adjunto_se_guarda_fuera_del_disco_publico`.

### Task 7: Reportes

**Files:**
- Modify: `app/Servicios/Reportes/ExportadorExcel.php` (celdas de texto con `setCellValueExplicit(..., DataType::TYPE_STRING)`), `ReporteReservaciones.php`, `ReportePagos.php`, `ReporteContratos.php`, `ReporteVentas.php` (`resumen()` con agregados SQL agrupados por estado), `ReporteIntegral.php` (comentarios en español, inyectar fábrica)
- Test: `tests/Feature/ReporteTest.php`

- [ ] Tests: `test_el_excel_no_interpreta_formulas_de_los_datos` (título `=1+1` se guarda como texto), los tests de resumen existentes siguen en verde.

### Task 8: Enlaces de notificación según rol

**Files:**
- Create: `app/Soporte/EnlaceNotificacion.php` — `public static function para(Notificacion $n, User $u): ?string`
- Modify: `resources/views/notificaciones/index.blade.php`
- Test: `tests/Feature/NotificacionTest.php`

Mapa: reserva → cliente `reservas.show` / staff `admin.reservas.show`; contrato → cliente `perfil.edit` / admin `admin.contratos.show`; cita → cliente `citas.index` / asesor `asesor.citas.show` / admin `admin.citas.show`; inmueble → `inmuebles.show`; conversacion → `mensajes.show`.

- [ ] Test: `test_el_cliente_no_recibe_enlaces_al_panel`.

### Task 9: Reglas de negocio puntuales

**Files:** `app/Http/Requests/Admin/InmuebleRequest.php`, `app/Servicios/CitaService.php`, `app/Politicas/VentaPolicy.php` + `app/Http/Controllers/Asesor/VentaController.php` + `resources/views/asesor/ventas/show.blade.php`, `app/Servicios/ContratoService.php` + `app/Servicios/VentaService.php` (guardar nuevo y luego borrar anterior), `app/Http/Requests/SolicitarCitaRequest.php`, `app/Http/Controllers/ReservaController.php`
- Test: archivos de feature correspondientes.

- [ ] Tests: `test_no_se_crea_un_inmueble_ocupado_sin_respaldo`, `test_no_se_edita_la_observacion_de_una_cita_no_realizada`, `test_se_sube_la_escritura_de_una_venta_cerrada`, `test_una_fecha_de_cita_mal_formada_es_error_de_validacion`.
- [ ] Nueva habilidad `VentaPolicy::adjuntarEscritura` (admin o asesor de la venta, venta no cancelada).

### Task 10: Limpieza

- Eliminar rutas/métodos/vistas sin uso: `perfil.arriendos`, `perfil.compras`, `password.edit` (GET), `perfil.tarjetas.index` + `perfil/mis-arriendos`, `perfil/mis-compras`, `perfil/cambiar-password`, `perfil/tarjetas/index` (verificar antes que nada las enlaza).
- Quitar `chart.js` de `package.json`.
- `.gitignore`: `/database/*.sql`.
- Verificar con `php artisan route:list` y la suite.

### Task 11: Modo oscuro y repaso UX/accesibilidad

**Files:** `resources/css/app.css`, `resources/views/layouts/{app,panel,auth}.blade.php`, `resources/views/layouts/partials/nav.blade.php` + sidebars, `resources/js/ui.js`

- Tokens semánticos (`--color-card`, `--color-line`, `--color-ink`, `--color-muted`, `--color-surface*`) definidos en `:root` y redefinidos bajo `[data-theme="dark"]`; reemplazar `bg-white`/colores fijos de superficie en `app.css` por tokens.
- Script inline en `<head>` que aplica el tema guardado (`localStorage`) o `prefers-color-scheme` antes del primer pintado; botón de alternancia con `aria-pressed` en nav y paneles.
- Verificación visual en navegador: catálogo, ficha, login, perfil+modales, panel admin (tablas, modales), chat — en claro/oscuro y 375 px.

### Task 12: Verificación final

- [ ] `vendor/bin/pint --test`
- [ ] `php artisan test` → todo verde
- [ ] `npm run build` sin errores
- [ ] Smoke test en navegador de los flujos tocados.
