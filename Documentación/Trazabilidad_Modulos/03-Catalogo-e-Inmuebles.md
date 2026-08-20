# Módulo 03: Catálogo e Inmuebles

> **Propósito**: Publicación y exploración pública del portafolio inmobiliario (búsqueda con filtros avanzados y ficha técnica) y administración integral desde el panel (CRUD de propiedades, generación automática de códigos, control de estados y gestión de galería fotográfica optimizada).

---

## 1. Mapa de Componentes y Trazabilidad

| Capa | Archivo / Recurso | Función y Responsabilidad |
|---|---|---|
| **Rutas Públicas** | `routes/web.php` | ├── `GET /` (`inicio` - Inmuebles destacados y recientes).<br>├── `GET /inmuebles` (`inmuebles.index` - Catálogo filtrable paginado).<br>└── `GET /inmuebles/{id}` (`inmuebles.show` - Ficha técnica y galería). |
| **Rutas Admin** | `routes/web.php` (`/admin`) | ├── `admin.inmuebles.*` (Resource salvo create/edit por arquitectura de modales).<br>├── `PATCH /admin/imagenes/{id}/principal` (`admin.imagenes.principal`).<br>└── `DELETE /admin/imagenes/{id}` (`admin.imagenes.destroy`). |
| **Controladores** | `app/Http/Controllers/` | ├── `InmuebleController.php` (Catálogo público y ficha técnica).<br>├── `Admin/InmuebleController.php` (CRUD administrativo con modales).<br>└── `Admin/ImagenInmuebleController.php` (Gestión individual de fotos). |
| **Form Requests** | `app/Http/Requests/` | ├── `FiltroInmuebleRequest.php` (Sanitización de filtros: tipo, modalidad, ciudad, precio min/max, habitaciones, baños).<br>└── `Admin/InmuebleRequest.php` (Validación de datos técnicos, coordenadas y subida múltiple de imágenes). |
| **Servicios** | `app/Servicios/` | `ImagenInmuebleService.php` (Almacenamiento en `storage/app/public/inmuebles`, redimensionamiento, asignación de portada `es_principal` y eliminación física). |
| **Políticas** | `app/Politicas/InmueblePolicy.php` | Métodos: `viewAny`, `view`, `create`, `update`, `delete`. |
| **Modelos & Tablas** | `app/Models/` | ├── `Inmueble.php` (`inmueble` - Scopes: `disponibles`, `recientes`, `filtrar`; Métodos: `generarCodigo()`, `tieneReservasActivas()`, `estadoCalculado()`).<br>└── `ImagenInmueble.php` (`imageninmueble` - Relación `inmueble`, scope `principal`). |
| **Enums** | `app/Enumerados/` | `TipoInmueble.php`, `ModalidadInmueble.php`, `EstadoInmueble.php`. |
| **Vistas** | `resources/views/` | ├── `inmuebles/` (`inicio.blade.php`, `index.blade.php`, `show.blade.php`).<br>└── `admin/inmuebles/` (`index.blade.php`, `show.blade.php`). |

---

## 2. Esquema de Base de Datos y Mapeo

```sql
-- Tabla inmueble
CREATE TABLE `inmueble` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `codigo` VARCHAR(30) NOT NULL UNIQUE, -- Ej: 'INM-00045' autogenerado
  `titulo` VARCHAR(200) NOT NULL,
  `descripcion` TEXT NOT NULL,
  `tipo` VARCHAR(50) NOT NULL, -- 'casa', 'apartamento', 'local', 'oficina', 'lote'
  `modalidad` VARCHAR(50) NOT NULL, -- 'arriendo', 'venta', 'ambas'
  `precio` DECIMAL(12,2) NULL, -- Canon de arriendo mensual
  `precio_venta` DECIMAL(14,2) NULL, -- Precio de venta total
  `estado` VARCHAR(30) NOT NULL DEFAULT 'disponible', -- 'disponible', 'reservado', 'arrendado', 'vendido', 'inactivo'
  `ciudad` VARCHAR(100) NOT NULL,
  `direccion` VARCHAR(200) NOT NULL,
  `habitaciones` SMALLINT UNSIGNED NULL DEFAULT 0,
  `banos` SMALLINT UNSIGNED NULL DEFAULT 0,
  `area_m2` DECIMAL(8,2) NOT NULL,
  `estrato` TINYINT UNSIGNED NULL,
  `garajes` SMALLINT UNSIGNED NULL DEFAULT 0,
  `latitud` DECIMAL(10,8) NULL,
  `longitud` DECIMAL(11,8) NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  INDEX `idx_inmueble_estado_modalidad` (`estado`, `modalidad`),
  INDEX `idx_inmueble_ciudad_tipo` (`ciudad`, `tipo`),
  INDEX `idx_inmueble_precios` (`precio`, `precio_venta`)
);

-- Tabla imageninmueble
CREATE TABLE `imageninmueble` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `id_inmueble` BIGINT UNSIGNED NOT NULL,
  `ruta` VARCHAR(255) NOT NULL,
  `es_principal` BOOLEAN NOT NULL DEFAULT 0,
  `orden` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL,
  FOREIGN KEY (`id_inmueble`) REFERENCES `inmueble`(`id`) ON DELETE CASCADE
);
```

---

## 3. Flujos de Trazabilidad Detallados

### 3.1 Consulta de Catálogo con Filtros Combinados (HU-01 / HU-02)

```
[Cliente] GET /inmuebles?modalidad=arriendo&ciudad=Bogota&precio_max=3500000&habitaciones=3
   │
   ▼
[FiltroInmuebleRequest] ──► Normaliza tipos y sanea cadenas
   │
   ▼
[InmuebleController::index()]
   ├── Inmueble::query()
   │     ├── ->disponibles() (Scope: where('estado', EstadoInmueble::Disponible))
   │     ├── ->filtrar($request->validated()) (Scopes dinámicos: whereModalidad, whereCiudad, wherePrecioEntre, etc.)
   │     ├── ->with(['imagenPrincipal']) (Eager loading para evitar N+1 queries)
   │     └── ->paginate(12)->withQueryString()
   └── Renderiza view('inmuebles.index', ['inmuebles' => $inmuebles])
```

### 3.2 Registro de Inmueble y Subida de Galería (HU-04.1 / HU-08)

```
[Administrador] POST /admin/inmuebles
   │
   ▼
[Middleware: auth + rol:administrador]
   │
   ▼
[InmuebleRequest] ──► Valida campos numéricos, áreas, precios e imágenes (mimes:jpg,jpeg,png,webp|max:5120)
   │
   ▼
[Admin\InmuebleController::store()]
   ├── DB::transaction():
   │     ├── $inmueble = Inmueble::create([
   │     │      ...$request->safe()->except('imagenes'),
   │     │      'codigo' => Inmueble::generarCodigo(), // Ej: INM-00104
   │     │   ])
   │     └── ImagenInmuebleService::agregar($inmueble, $request->file('imagenes', [])):
   │           ├── Itera cada archivo y lo guarda en: storage/app/public/inmuebles/{hash}.webp
   │           ├── Crea registros en tabla 'imageninmueble'
   │           └── Si es la primera imagen del inmueble, setea 'es_principal = 1'
   └── Redirige con flash confirmando código asignado
```

### 3.3 Eliminación Protegida de Propiedades (HU-04.5)

```
[Administrador] DELETE /admin/inmuebles/{id}
   │
   ▼
[InmueblePolicy::delete()] ──► Verifica rol administrador
   │
   ▼
[Admin\InmuebleController::destroy()]
   ├── $inmueble->tieneReservasActivas()
   │     ├── ¿Existen reservas en estado 'pendiente_pago' o 'confirmada'?
   │     ├── SI: Aborta con error (Protege histórico de reservas vigentes)
   │     └── NO: DB::transaction():
   │               ├── ImagenInmuebleService::eliminarTodas($inmueble) (Borra archivos físicos de storage)
   │               └── $inmueble->delete() (Cascada en BD elimina registros de 'imageninmueble')
```

---

## 4. Reglas de Negocio y Optimizaciones

1. **Prevención de Problemas N+1**:
   - En el listado administrativo `Admin\InmuebleController::index()`, como cada fila renderiza un modal con su galería fotográfica completa, se ejecuta obligatoriamente `Inmueble::recientes()->with('imagenes')->get()`.
2. **Generación Atómica de Códigos**:
   - El método `Inmueble::generarCodigo()` consulta el último ID autonumérico o secuencia para armar códigos estandarizados (`INM-XXXXX`) bloqueados en la transacción para evitar colisiones.
3. **Sincronización de Imagen Principal**:
   - Al marcar una imagen como principal vía `PATCH /admin/imagenes/{id}/principal`, `ImagenInmuebleService` ejecuta una transacción que actualiza `es_principal = 0` en todas las fotos de ese inmueble y setea `es_principal = 1` exclusivamente en la seleccionada.
