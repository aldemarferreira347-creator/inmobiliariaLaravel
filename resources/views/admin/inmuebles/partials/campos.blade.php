{{--
    Campos del inmueble, compartidos por el alta (modal del listado) y la
    edición (un modal por fila del listado, HU-04.2). `$inmueble` llega nulo
    al crear.

    Como el alta y cada edición viven ahora en la misma página (todas dentro
    de modales, uno por inmueble), este parcial se repite muchas veces en un
    mismo documento. Dos cosas que eso rompería si no se manejan aquí:

      1. IDs duplicados: dos <input id="titulo"> en la misma página son HTML
         inválido y arruinan la asociación <label for="">. Por eso cada campo
         lleva el prefijo `$idPrefix` derivado de a qué formulario pertenece.
      2. Fuga de `old()` entre formularios: old('titulo') es global a la
         petición, no por formulario. Si el alta falla su validación, un
         `old('titulo', ...)` sin filtrar aparecería también dentro del modal
         de edición de cualquier inmueble que el usuario abra después, con el
         título equivocado. `$val()` solo aplica old() cuando el marcador
         oculto `_formulario_inmueble` confirma que el error es de ESTE
         formulario en particular.
--}}
@php
    $formularioId = $inmueble ? 'editar-'.$inmueble->id : 'crear';
    $idPrefix = $formularioId.'-';
    $huboErrorEnEsteFormulario = old('_formulario_inmueble') === $formularioId;
    $val = fn (string $campo, $default = null) => $huboErrorEnEsteFormulario ? old($campo, $default) : $default;
    $modalidadActual = $val('modalidad', $inmueble?->modalidad->value ?? \App\Enumerados\ModalidadInmueble::Venta->value);
@endphp

<input type="hidden" name="_formulario_inmueble" value="{{ $formularioId }}">

<div class="form-grid">
    <div class="form-group">
        <label for="{{ $idPrefix }}codigo">Código único</label>
        <input type="text" id="{{ $idPrefix }}codigo" class="input-readonly" readonly
            value="{{ $inmueble?->codigo ?? 'Se generará automáticamente' }}"
            title="El código lo asigna el sistema y no se puede modificar">
    </div>

    <div class="form-group">
        <label for="{{ $idPrefix }}tipo">Tipo de inmueble <span class="req">*</span></label>
        <select id="{{ $idPrefix }}tipo" name="tipo" required>
            @foreach (\App\Enumerados\TipoInmueble::cases() as $tipo)
                <option value="{{ $tipo->value }}" @selected($val('tipo', $inmueble?->tipo->value) === $tipo->value)>
                    {{ $tipo->etiqueta() }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="form-group">
        <label for="{{ $idPrefix }}habitaciones">Habitaciones <span class="req">*</span></label>
        <input type="number" id="{{ $idPrefix }}habitaciones" name="habitaciones" min="0" max="50" required
            value="{{ $val('habitaciones', $inmueble?->habitaciones ?? 1) }}">
    </div>

    <div class="form-group">
        <label for="{{ $idPrefix }}banos">Baños <span class="req">*</span></label>
        <input type="number" id="{{ $idPrefix }}banos" name="banos" min="0" max="50" required
            value="{{ $val('banos', $inmueble?->banos ?? 1) }}">
    </div>

    <div class="form-group">
        <label for="{{ $idPrefix }}area">Área (m²) <span class="req">*</span></label>
        <input type="number" id="{{ $idPrefix }}area" name="area" step="0.01" min="1" required
            value="{{ $val('area', $inmueble?->area) }}">
    </div>

    <div class="form-group">
        <label for="{{ $idPrefix }}parqueadero">Parqueadero <span class="req">*</span></label>
        <select id="{{ $idPrefix }}parqueadero" name="parqueadero" required>
            <option value="0" @selected(! (bool) $val('parqueadero', $inmueble?->parqueadero))>No</option>
            <option value="1" @selected((bool) $val('parqueadero', $inmueble?->parqueadero))>Sí</option>
        </select>
    </div>

    <div class="form-group full">
        <label for="{{ $idPrefix }}titulo">Título del inmueble <span class="req">*</span></label>
        <input type="text" id="{{ $idPrefix }}titulo" name="titulo" maxlength="150" required
            value="{{ $val('titulo', $inmueble?->titulo) }}">
    </div>

    <div class="form-group full">
        <label for="{{ $idPrefix }}descripcion">Descripción <span class="req">*</span></label>
        <textarea id="{{ $idPrefix }}descripcion" name="descripcion" rows="3" minlength="{{ \App\Models\Inmueble::DESCRIPCION_MINIMA }}"
            required
            placeholder="Describe brevemente el inmueble (mínimo {{ \App\Models\Inmueble::DESCRIPCION_MINIMA }} caracteres)...">{{ $val('descripcion', $inmueble?->descripcion) }}</textarea>
        <small class="field-note">Mínimo {{ \App\Models\Inmueble::DESCRIPCION_MINIMA }} caracteres.</small>
    </div>

    <div class="form-group">
        <label for="{{ $idPrefix }}ciudad">Ciudad <span class="req">*</span></label>
        <input type="text" id="{{ $idPrefix }}ciudad" name="ciudad" maxlength="100" required
            value="{{ $val('ciudad', $inmueble?->ciudad ?? 'Neiva') }}">
    </div>

    <div class="form-group">
        <label for="{{ $idPrefix }}barrio">Barrio <span class="req">*</span></label>
        <input type="text" id="{{ $idPrefix }}barrio" name="barrio" maxlength="100" required
            value="{{ $val('barrio', $inmueble?->barrio) }}">
    </div>

    <div class="form-group full">
        <label for="{{ $idPrefix }}direccion">Dirección <span class="req">*</span></label>
        <input type="text" id="{{ $idPrefix }}direccion" name="direccion" maxlength="255" required
            value="{{ $val('direccion', $inmueble?->direccion) }}">
    </div>

    <div class="form-group">
        <label for="{{ $idPrefix }}modalidad">Modalidad <span class="req">*</span></label>
        <select id="{{ $idPrefix }}modalidad" name="modalidad" data-modalidad required>
            @foreach (\App\Enumerados\ModalidadInmueble::cases() as $modalidad)
                <option value="{{ $modalidad->value }}" @selected($modalidadActual === $modalidad->value)>
                    {{ $modalidad->etiqueta() }}
                </option>
            @endforeach
        </select>
    </div>

    {{-- La modalidad decide cuál de estos dos campos se muestra y se exige --}}
    <div class="form-group" data-precio="venta">
        <label for="{{ $idPrefix }}precio_venta">Precio de venta</label>
        <input type="number" id="{{ $idPrefix }}precio_venta" name="precio_venta" step="1000" min="0"
            value="{{ $val('precio_venta', $inmueble?->precio_venta) }}">
    </div>

    <div class="form-group" data-precio="arriendo">
        <label for="{{ $idPrefix }}precio_arrendamiento">Precio de arriendo / mes</label>
        <input type="number" id="{{ $idPrefix }}precio_arrendamiento" name="precio_arrendamiento" step="1000" min="0"
            value="{{ $val('precio_arrendamiento', $inmueble?->precio_arrendamiento) }}">
    </div>

    <div class="form-group">
        <label for="{{ $idPrefix }}estado">Estado</label>

        @php($estadosAdmitidos = collect(\App\Enumerados\EstadoInmueble::cases())->filter(fn($estado) => $inmueble?->admiteEstado($estado) ?? $estado === \App\Enumerados\EstadoInmueble::Disponible))

        @if ($estadosAdmitidos->count() === 1)
            {{-- Estado derivado de reservas y contratos: no se puede forzar a mano --}}
            <input type="hidden" name="estado" value="{{ $estadosAdmitidos->first()->value }}">
            <div class="estado-auto">
                <x-estado-badge :estado="$estadosAdmitidos->first()" base="badge-estado" />
                <span class="estado-auto-hint">
                    <x-icon name="settings" class="h-3.5 w-3.5" /> Calculado a partir de reservas y contratos.
                </span>
            </div>
        @else
            <select id="{{ $idPrefix }}estado" name="estado" required>
                @foreach ($estadosAdmitidos as $estado)
                    <option value="{{ $estado->value }}" @selected($val('estado', $inmueble?->estado->value) === $estado->value)>
                        {{ $estado->etiqueta() }}
                    </option>
                @endforeach
            </select>
        @endif
    </div>
</div>
