# Módulo 09: Mensajería y Chat

> **Propósito**: Canal de mensajería interactiva entre clientes y asesores/administradores contextualizado por propiedad inmobiliaria, con soporte de conversaciones persistentes, envío de adjuntos, lectura automática, indicador global de mensajes no leídos y actualización asíncrona mediante sondeo (polling en `resources/js/chat.js`).

---

## 1. Mapa de Componentes y Trazabilidad

| Capa | Archivo / Recurso | Función y Responsabilidad |
|---|---|---|
| **Rutas** | `routes/web.php` (grupo `auth`) | ├── `GET /mensajes` (`mensajes.index` - Bandeja de hilos).<br>├── `GET /mensajes/{id}` (`mensajes.show` - Panel de chat activo).<br>├── `POST /mensajes/{id}` (`mensajes.store` - Envío de mensaje/adjunto).<br>├── `POST /inmuebles/{id}/contactar` (`mensajes.iniciar` - Inicio rápido).<br>├── `GET /mensajes/sin-leer` (`mensajes.sin-leer` - Contador para navbar).<br>├── `GET /mensajes/{id}/nuevos` (`mensajes.nuevos` - Endpoint de polling).<br>└── `GET /mensajes/{id}/anteriores` (`mensajes.anteriores` - Paginación hacia arriba). |
| **Controladores** | `app/Http/Controllers/` | `MensajeController.php` (Orquesta la carga de conversaciones, envío sincrónico/AJAX, y respuestas JSON para la reactividad en frontend). |
| **Form Requests** | `app/Http/Requests/` | `EnviarMensajeRequest.php` (Valida que exista texto o archivo adjunto válido de tipo imagen o PDF, máx 5MB). |
| **Servicios** | `app/Servicios/` | `MensajeService.php` (Asignación balanceada de asesor por carga, persistencia de hilos, gestión de adjuntos, marcación atómica de lectura y alertas). |
| **Políticas** | `app/Politicas/ConversacionPolicy.php` | Método `participate`. Bloquea el acceso a cualquier usuario que no sea el cliente titular, el asesor asignado o un administrador. |
| **Modelos & Tablas** | `app/Models/` | ├── `Conversacion.php` (`conversacion`, vincula `id_inmueble`, `id_cliente`, `id_asesor`, `ultimo_mensaje_en`).<br>└── `Mensaje.php` (`mensaje`, campos: `conversacion_id`, `emisor_id`, `contenido`, `adjunto_url`, `leido_en`). |
| **Frontend & JS** | `resources/js/chat.js` | Sondeo periódico AJAX cada 3 segundos, auto-scroll inteligente al final del chat y envío asíncrono sin recarga de pantalla. |
| **Vistas** | `resources/views/mensajes/` | `index.blade.php` (Bandeja con buscador de hilos), `panel.blade.php` (Área de mensajes, panel de inmueble lateral y caja de texto). |

---

## 2. Esquema de Base de Datos y Relaciones

```sql
-- Tabla conversacion
CREATE TABLE `conversacion` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `cliente_id` BIGINT UNSIGNED NOT NULL,
  `asesor_id` BIGINT UNSIGNED NOT NULL,
  `inmueble_id` BIGINT UNSIGNED NOT NULL,
  `ultimo_mensaje_en` TIMESTAMP NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  FOREIGN KEY (`cliente_id`) REFERENCES `usuario`(`id`),
  FOREIGN KEY (`asesor_id`) REFERENCES `usuario`(`id`),
  FOREIGN KEY (`inmueble_id`) REFERENCES `inmueble`(`id`),
  UNIQUE `uk_conversacion_cliente_inmueble` (`cliente_id`, `inmueble_id`),
  INDEX `idx_conversacion_ultimo_mensaje` (`ultimo_mensaje_en`)
);

-- Tabla mensaje
CREATE TABLE `mensaje` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `conversacion_id` BIGINT UNSIGNED NOT NULL,
  `emisor_id` BIGINT UNSIGNED NOT NULL,
  `contenido` TEXT NULL,
  `adjunto_url` VARCHAR(255) NULL,
  `leido_en` TIMESTAMP NULL,
  `created_at` TIMESTAMP NULL,
  FOREIGN KEY (`conversacion_id`) REFERENCES `conversacion`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`emisor_id`) REFERENCES `usuario`(`id`),
  INDEX `idx_mensaje_leido` (`conversacion_id`, `leido_en`)
);
```

---

## 3. Flujos de Trazabilidad Detallados

### 3.1 Inicio de Conversación desde la Ficha de un Inmueble (HU-13.1)

```
[Cliente] POST /inmuebles/{id}/contactar
   │
   ▼
[MensajeController::iniciar()]
   │
   ▼
[MensajeService::abrirConversacion()]
   ├── Busca conversación existente: Conversacion::where('cliente_id', $user->id)->where('inmueble_id', $inmueble->id)->first()
   ├── Si existe: La reutiliza de inmediato
   └── Si no existe:
         ├── Determina asesor: $this->asesorPara($cliente)
         │     ├── Si el cliente ya tiene un asesor en otra conversación -> Mantiene el mismo
         │     └── Si es nuevo -> Asigna el asesor activo con menor número de conversaciones abiertas (Balanceo)
         ├── Crea registro en 'conversacion'
         └── Redirige a route('mensajes.show', $conversacion->id)
```

### 3.2 Envío de Mensaje con Polling Reactivo (HU-13.2)

```
[Usuario] POST /mensajes/{id} (contenido: "Buenas tardes...", adjunto: foto.jpg)
   │
   ▼
[ConversacionPolicy::participate()] ──► Verifica que pertenezca a la conversación
   │
   ▼
[EnviarMensajeRequest] ──► Valida texto o archivo válido
   │
   ▼
[MensajeService::enviar()]
   ├── DB::transaction():
   │     ├── Guarda adjunto si existe en: storage/app/public/chat/
   │     ├── Inserta en 'mensaje' con emisor_id = Auth::id()
   │     └── Actualiza 'conversacion.ultimo_mensaje_en' = now()
   ├── NotificacionService::paraUsuario($interlocutor, 'Nuevo mensaje', ...) [conCorreo: true]
   └── Retorna JSON con el mensaje formateado
   │
   ▼
[Frontend: chat.js]
   ├── Renderiza el mensaje localmente al instante (Optimistic UI)
   └── El cliente receptor obtiene el mensaje automáticamente en el siguiente ciclo de polling:
         GET /mensajes/{id}/nuevos?ultimo_id=123 ──► Renderiza burbuja y reproduce sonido suave
```

### 3.3 Marcación Automática de Lectura

```
[Usuario] GET /mensajes/{id}
   │
   ▼
[MensajeController::show()]
   ├── MensajeService::marcarLeidos($conversacion, $usuarioActual)
   │     └── UPDATE mensaje SET leido_en = NOW() WHERE conversacion_id = :id AND emisor_id != :userId AND leido_en IS NULL
   └── Renderiza view('mensajes.panel')
```

---

## 4. Reglas de Negocio y Optimizaciones

1. **Balanceo de Carga de Asesores**:
   - `MensajeService::asesorPara()` evita la asignación aleatoria ingenua; primero intenta mantener la continuidad del asesor con el cliente y, si es la primera interacción, consulta la carga de trabajo activa para distribuir equitativamente las consultas entrantes.
2. **Polling Ligero y Eficiente**:
   - El endpoint `/mensajes/{id}/nuevos` consulta únicamente `WHERE id > :ultimo_id` ordenado ascendentemente. Al retornar un JSON ultraliviano, minimiza el impacto en el servidor Apache/PHP y en el ancho de banda del cliente.
