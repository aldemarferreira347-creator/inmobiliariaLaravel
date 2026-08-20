# Módulo 04: Perfil, Favoritos e Historial

> **Propósito**: Gestión del perfil del usuario autenticado (información personal, avatar fotográfico), administración de inmuebles favoritos (guardado/eliminación toggle) y visualización consolidada del historial de operaciones del cliente (arriendos vigentes con contratos y compras de inmuebles con escrituras notariales).

---

## 1. Mapa de Componentes y Trazabilidad

| Capa | Archivo / Recurso | Función y Responsabilidad |
|---|---|---|
| **Rutas** | `routes/web.php` (grupo `auth`) | ├── `GET /perfil` (`perfil.edit` - Centro unificado de cuenta y modales).<br>├── `PATCH /perfil` (`perfil.update` - Actualización de datos básicos).<br>├── `POST /perfil/foto` (`perfil.foto.store` - Subida de avatar).<br>├── `DELETE /perfil/foto` (`perfil.foto.destroy` - Eliminación de avatar).<br>├── `GET /favoritos` (`favoritos.index` - Cuadrícula de inmuebles guardados).<br>├── `POST /favoritos/{id}` (`favoritos.toggle` - Guardar/quitar favorito).<br>├── `GET /mis-arriendos` (`perfil.arriendos` - Historial de arriendos).<br>└── `GET /mis-compras` (`perfil.compras` - Historial de compras cerradas). |
| **Controladores** | `app/Http/Controllers/` | ├── `PerfilController.php` (Datos, avatar, integración de tarjetas y vistas de historial).<br>└── `FavoritoController.php` (Toggle asíncrono/síncrono y listado de favoritos). |
| **Form Requests** | `app/Http/Requests/` | ├── `PerfilUpdateRequest.php` (Valida nombre, email único ignorando al usuario actual y teléfono).<br>└── `FotoPerfilRequest.php` (Valida formato jpeg, png, webp, dimensiones y tamaño máx: 2048 KB). |
| **Servicios** | `app/Servicios/` | ├── `AvatarService.php` (Persistencia en `storage/app/public/avatares`, eliminación segura de imagen previa y actualización de columna).<br>└── `StripeCardService.php` (Listado de métodos de pago guardados del cliente). |
| **Modelos & Tablas** | `app/Models/` | ├── `User.php` (`usuario`, campo `foto_perfil`, relación `favoritos()`).<br>├── `Inmueble.php` (Tabla pivot `favorito`).<br>├── `Reserva.php` / `Contrato.php` (Arriendos).<br>└── `Venta.php` (Compras). |
| **Vistas** | `resources/views/perfil/` | ├── `edit.blade.php` (Panel de perfil con modales de seguridad, tarjetas y documentos).<br>├── `favoritos.blade.php` (Listado de favoritos con paginación).<br>├── `mis-arriendos.blade.php` (Tabla de arriendos, estado de contrato y descarga).<br>└── `mis-compras.blade.php` (Tabla de compras, asesor responsable y escritura). |

---

## 2. Esquema de Base de Datos y Tabla Pivot

```sql
-- Tabla pivot favorito
CREATE TABLE `favorito` (
  `id_usuario` BIGINT UNSIGNED NOT NULL,
  `id_inmueble` BIGINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_usuario`, `id_inmueble`),
  FOREIGN KEY (`id_usuario`) REFERENCES `usuario`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`id_inmueble`) REFERENCES `inmueble`(`id`) ON DELETE CASCADE,
  INDEX `idx_favorito_inmueble` (`id_inmueble`)
);
```

---

## 3. Flujos de Trazabilidad Detallados

### 3.1 Carga Centralizada del Perfil (`PerfilController::edit`)

```
[Usuario] GET /perfil
   │
   ▼
[Middleware: auth]
   │
   ▼
[PerfilController::edit()]
   ├── Obtiene $usuario = Auth::user()
   ├── Cuenta total favoritos: $usuario->favoritos()->count()
   ├── Carga reservas de arriendo:
   │     $usuario->reservas()
   │       ->where('estado', EstadoReserva::Confirmada)
   │       ->whereHas('inmueble', fn($q) => $q->whereIn('modalidad', [ModalidadInmueble::Arriendo, ModalidadInmueble::Ambos]))
   │       ->with(['inmueble', 'contrato'])
   │       ->recientes()->get()
   ├── Carga compras: $usuario->ventas()->with(['inmueble', 'asesor'])->latest('fecha_venta')->get()
   ├── Consulta métodos Stripe: StripeCardService::listar($usuario)
   └── Renderiza view('perfil.edit') pasando datos para la página y sus modales
```

### 3.2 Gestión del Avatar (`AvatarService`)

```
[Usuario] POST /perfil/foto (archivo 'foto')
   │
   ▼
[FotoPerfilRequest] ──► Valida dimensiones, tipo mime y peso <= 2MB
   │
   ▼
[PerfilController::actualizarFoto()]
   ├── AvatarService::reemplazar($user, $archivo):
   │     ├── Si $user->foto_perfil existe:
   │     │     Storage::disk('public')->delete($user->foto_perfil) (Borra archivo anterior)
   │     ├── Guarda nuevo archivo: $ruta = $archivo->store('avatares', 'public')
   │     └── $user->update(['foto_perfil' => $ruta])
   └── Redirige con flash: "Foto de perfil actualizada correctamente"
```

### 3.3 Guardado / Retiro de Favorito (`FavoritoController::toggle`)

```
[Usuario] POST /favoritos/{inmueble}
   │
   ▼
[FavoritoController::toggle()]
   ├── $resultado = $usuario->favoritos()->toggle($inmueble->id)
   │     └── Retorna array con ['attached' => [id]] o ['detached' => [id]]
   ├── Si la petición es AJAX/JSON:
   │     Retorna JSON: ['esFavorito' => !empty($resultado['attached']), 'total' => $usuario->favoritos()->count()]
   └── Si es petición HTML estándar:
         Retorna redirect()->back() con flash de estado
```

---

## 4. Reglas de Negocio y Casos de Uso

1. **Arquitectura de Vista Única y Modales**:
   - Para maximizar la fluidez del usuario, `/perfil` concentra la gestión de contraseñas, tarjetas y datos en modales dinámicos sin requerir múltiples recargas de pantalla completa.
2. **Sincronización con Documentos Privados**:
   - Desde `mis-arriendos` y `mis-compras`, la interfaz enlaza de forma directa y transparente con los endpoints seguros de `DescargaController` (`/contratos/{id}/descargar` y `/ventas/{id}/escritura`).
3. **Limpieza Automática en Cascada**:
   - Si un inmueble se elimina o se desactiva, la clave foránea `ON DELETE CASCADE` de la tabla `favorito` garantiza que los favoritos huérfanos se depuren inmediatamente sin corromper las listas de los usuarios.
