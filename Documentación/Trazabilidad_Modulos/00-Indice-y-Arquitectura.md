# Índice General y Arquitectura del Sistema

> **Sistema Inmobiliaria Laravel** — Guía ejecutiva y mapa de trazabilidad modular.
> Ubicación: `Documentación/Trazabilidad_Modulos/`

---

## 1. Arquitectura General y Estándares

El proyecto está desarrollado sobre **Laravel 11+ (PHP 8.2+)** siguiendo un patrón arquitectónico por capas desacoplado (Clean / Layered Architecture). Se prioriza la separación estricta de responsabilidades: los controladores se mantienen delgados (*skinny controllers*), las validaciones residen en Form Requests dedicados, la lógica de negocio y transacciones se concentran en Servicios de Dominio, y la persistencia se gestiona a través de Modelos Eloquent fuertemente tipados con Enums nativos de PHP.

```
┌──────────────────────────────────────────────────────────────────────────────────┐
│                      FLUJO GENERAL DE UNA PETICIÓN HTTP                          │
└──────────────────────────────────────────────────────────────────────────────────┘
   Petición HTTP (Navegador / Pasarela Stripe / Cliente Móvil)
      │
      ▼
   [1] RUTAS (routes/web.php / routes/auth.php)
      │   Valida nombre de ruta, parámetros (Route Model Binding) y verbos HTTP
      ▼
   [2] MIDDLEWARE PIPELINE (bootstrap/app.php)
      │   ├── EnsureUserIsActive (global 'web' -> invalida sesión si estado!=activo)
      │   ├── Authenticate ('auth' -> redirige a /login si no autenticado)
      │   └── EnsureUserHasRole ('rol:admin,asesor' -> valida códigos de rol)
      ▼
   [3] FORM REQUEST (app/Http/Requests/**)
      │   Valida reglas de formato, tipos, unicidad, archivos y rate limiting
      ▼
   [4] CONTROLADOR (app/Http/Controllers/**)
      │   ├── Invoca Policy ($this->authorize o Gate::allows)
      │   └── Delega ejecución al Servicio de Dominio
      ▼
   [5] SERVICIO DE DOMINIO (app/Servicios/**)
      │   Ejecuta lógica pura de negocio, DB::transaction(), cálculo de estados,
      │   gestión de archivos privados/públicos y disparo de Notificaciones/Auditoría
      ▼
   [6] MODELO ELOQUENT (app/Models/**) & BASE DE DATOS
      │   Scopes, Relaciones, Mutadores, Casts a Enums nativos y Hooks
      ▼
   [7] RESPUESTA AL CLIENTE
          ├── Vista Blade (resources/views/** con Tailwind / Vanilla CSS)
          ├── JSON asíncrono (Endpoints de Polling / AJAX)
          └── Descarga Streamed / Binaria (PDFs y Excel protegidos)
```

---

## 2. Convenciones de Código y Namespaces en Español

A diferencia de las convenciones en inglés habituales en Laravel estándar, este proyecto implementa namespaces explícitos en español para alinearse con el modelo de dominio del negocio:

* **`app/Enumerados/`** (15 Enums backed `string`): Tipado estricto para estados y modalidades (`EstadoReserva`, `EstadoCita`, `EstadoContrato`, `RolUsuario`, `ModalidadInmueble`, `TipoReporte`, etc.).
* **`app/Politicas/`** (7 Policies): Registradas explícitamente en `AppServiceProvider` (`InmueblePolicy`, `ReservaPolicy`, `CitaPolicy`, `ContratoPolicy`, `VentaPolicy`, `ConversacionPolicy`, `UserPolicy`).
* **`app/Servicios/`** (12 Servicios + subsistema `Reportes/`): Manejadores de casos de uso complejos (`ReservaService`, `PagoService`, `CitaService`, `ContratoService`, `VentaService`, `StripeCardService`, etc.).
* **`app/Http/Requests/`**: Agrupados en raíz, `Admin/`, `Asesor/` y `Auth/`.
* **`app/Soporte/`**: Clases utilitarias (`Iconos::class` con trazos SVG Lucide y `RangosPrecio::class`).
* **`app/Reglas/`**: Validaciones complejas reutilizables (`PasswordSegura::class`).

---

## 3. Matriz de Módulos, Componentes y Cobertura

| # | Módulo | Documento | Modelos Eloquent | Servicios Clave | Historias de Usuario (HU) |
|---|---|---|---|---|---|
| 01 | **Autenticación y Seguridad** | [`01-Autenticacion-y-Seguridad.md`](01-Autenticacion-y-Seguridad.md) | `User`, `Role` | — | HU-03, HU-05, HU-24, HU-25.2 |
| 02 | **Usuarios y Permisos** | [`02-Gestion-de-Usuarios-y-Permisos.md`](02-Gestion-de-Usuarios-y-Permisos.md) | `User`, `Role`, `Permiso` | — | HU-16, HU-26 |
| 03 | **Catálogo e Inmuebles** | [`03-Catalogo-e-Inmuebles.md`](03-Catalogo-e-Inmuebles.md) | `Inmueble`, `ImagenInmueble` | `ImagenInmuebleService` | HU-01, HU-02, HU-04, HU-08 |
| 04 | **Perfil, Favoritos e Historial** | [`04-Perfil-Favoritos-e-Historial.md`](04-Perfil-Favoritos-e-Historial.md) | `User`, `Inmueble` (pivot `favorito`) | `AvatarService` | HU-18, HU-19, HU-25 |
| 05 | **Reservas y Pagos** | [`05-Reservas-y-Pagos.md`](05-Reservas-y-Pagos.md) | `Reserva`, `HistorialReserva`, `Pago`, `MetodoPagoGuardado`, `WebhookEvento` | `ReservaService`, `PagoService`, `StripeCardService` | HU-07, HU-09, HU-20, HU-23 |
| 06 | **Contratos de Arriendo** | [`06-Contratos-de-Arriendo.md`](06-Contratos-de-Arriendo.md) | `Contrato`, `Inmueble`, `User` | `ContratoService`, `ArchivoPrivadoService` | HU-17, HU-19 |
| 07 | **Ventas de Inmuebles** | [`07-Ventas.md`](07-Ventas.md) | `Venta`, `Inmueble`, `User` | `VentaService`, `ArchivoPrivadoService` | HU-14 |
| 08 | **Citas y Agenda de Visitas** | [`08-Citas-y-Agenda.md`](08-Citas-y-Agenda.md) | `Cita`, `CitaHistorial`, `ObservacionCita`, `ConfigFranjaCita` | `CitaService` | HU-10, HU-11, HU-12, HU-27 |
| 09 | **Mensajería y Chat** | [`09-Mensajeria-Chat.md`](09-Mensajeria-Chat.md) | `Conversacion`, `Mensaje` | `MensajeService` | HU-13 |
| 10 | **Notificaciones** | [`10-Notificaciones.md`](10-Notificaciones.md) | `Notificacion`, `User` | `NotificacionService`, `Mail\Aviso` | HU-15, HU-22 |
| 11 | **Reportes y Exportación** | [`11-Reportes-y-Exportacion.md`](11-Reportes-y-Exportacion.md) | Agregaciones Eloquent múltiples | `FabricaReportes`, `ExportadorExcel`, `ExportadorPdf` | HU-06, HU-21 |
| 12 | **Auditoría y Tareas Programadas** | [`12-Auditoria-y-Tareas-Programadas.md`](12-Auditoria-y-Tareas-Programadas.md) | `Auditoria`, `HistorialReserva`, `CitaHistorial` | Comandos `reservas:expirar`, `contratos:vencer` | Transversal |

---

## 4. Matriz de Autorización y Rutas Protegidas

```
┌──────────────────────────────┬───────────────────────────────┬────────────────────────────────────────────┐
│ TIPO DE RUTA                 │ MIDDLEWARE APLICADO           │ DESTINO / PANTALLA PRINCIPAL               │
├──────────────────────────────┼───────────────────────────────┼────────────────────────────────────────────┤
│ Públicas / Catálogo          │ (Ninguno o 'guest' en auth)   │ Catálogo general, Detalle inmueble, Login  │
│ Clientes / Autenticados      │ 'auth', 'activo'              │ /perfil, /mis-reservas, /mis-citas, chat   │
│ Asesores Comerciales         │ 'auth', 'activo', 'rol:asesor'│ /asesor/citas (Agenda personalizada)       │
│ Asesores y Administradores   │ 'auth', 'activo',             │ /asesor/ventas (Gestión de compraventas)   │
│                              │ 'rol:asesor,administrador'    │                                            │
│ Panel Administrador          │ 'auth', 'activo',             │ /admin/* (Inmuebles, Usuarios, Contratos,  │
│                              │ 'rol:administrador'           │ Reservas, Franjas, Reportes)               │
│ Webhook Pasarela (Stripe)    │ (Exento CSRF, validado HMAC)  │ POST /stripe/webhook                       │
└──────────────────────────────┴───────────────────────────────┴────────────────────────────────────────────┘
```

---

## 5. Modelo de Almacenamiento de Archivos (Storage)

El sistema divide el almacenamiento en dos categorías de seguridad física:

1. **Disco Público (`storage/app/public/` $\rightarrow$ `public/storage`)**:
   - `inmuebles/`: Imágenes de la galería de propiedades (`.webp`, `.jpg`, `.png`).
   - `avatares/`: Fotos de perfil de usuarios.
   - Acceso: Directo vía HTTP mediante el symlink creado por `php artisan storage:link`.

2. **Disco Privado (`storage/app/` no expuesto públicamente)**:
   - `contratos/`: PDFs de contratos firmados.
   - `escrituras/`: PDFs de escrituras notariales de ventas.
   - `adjuntos_mensajes/`: Archivos intercambiados en el chat.
   - `comprobantes_pagos/`: Recibos de transferencias manuales.
   - Acceso: Exclusivamente a través de [`DescargaController`](file:///c:/laragon/www/inmobiliarialaravel/app/Http/Controllers/DescargaController.php) validado con Policies (`$this->authorize`).
