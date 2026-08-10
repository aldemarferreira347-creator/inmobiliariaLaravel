{{--
    Edición de inmueble (HU-04.2), en modal — mismo patrón que el alta
    (modal-crear.blade.php), uno de estos por cada fila del listado. Se
    reabre solo si: (a) la validación de ESTE inmueble en particular falló,
    (b) se llegó desde el detalle con ?editar={id} (show.blade.php), o
    (c) se acaba de subir/borrar una imagen de este inmueble
    (Admin\ImagenInmuebleController deja la pista en la sesión).
--}}
@php
    $formularioId = 'editar-'.$inmueble->id;
    $seReabreParaEste = (old('_formulario_inmueble') === $formularioId && $errors->any())
        || (int) request('editar') === $inmueble->id
        || (int) session('reabrir_modal_inmueble') === $inmueble->id;
@endphp

<div class="modal-overlay" id="modal-{{ $formularioId }}" @if ($seReabreParaEste) data-modal-abierto @endif>
    <div class="modal-box modal-box--lg">
        <div class="modal-box-header">
            <h2>Editar inmueble</h2>
            <button type="button" class="modal-box-close" data-modal-cerrar="modal-{{ $formularioId }}" aria-label="Cerrar">
                <x-icon name="x" class="h-4 w-4" />
            </button>
        </div>

        <form method="POST" action="{{ route('admin.inmuebles.update', $inmueble) }}" enctype="multipart/form-data"
            data-confirmar data-confirmar-tono="exito"
            data-confirmar-titulo="¿Guardar los cambios de este inmueble?"
            data-confirmar-texto="Los nuevos datos quedarán visibles de inmediato en el catálogo público."
            data-confirmar-boton="Sí, guardar cambios">
            @csrf
            @method('PUT')

            @include('admin.inmuebles.partials.campos', ['inmueble' => $inmueble])

            <div class="gallery-section">
                <h3 class="gallery-title">Galería de imágenes</h3>

                @if ($inmueble->imagenes->isNotEmpty())
                    <div class="img-gallery">
                        @foreach ($inmueble->imagenes as $imagen)
                            <div @class(['img-gallery-item', 'img-gallery-item--principal' => $imagen->es_principal])>
                                @if ($imagen->es_principal)
                                    <div class="img-principal-tag">Portada</div>
                                @endif

                                <img src="{{ $imagen->url_publica }}" alt="" aria-hidden="true" class="img-thumb">

                                <div class="img-gallery-actions">
                                    @unless ($imagen->es_principal)
                                        <button type="submit" class="img-action-btn img-action-btn--star"
                                            form="portada-{{ $imagen->id }}" title="Hacer portada">
                                            <x-icon name="star" class="h-4 w-4" />
                                        </button>
                                    @endunless

                                    <button type="submit" class="img-action-btn img-action-btn--danger"
                                        form="borrar-imagen-{{ $imagen->id }}" title="Eliminar imagen">
                                        <x-icon name="trash-2" class="h-4 w-4" />
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="subtitle">No hay imágenes registradas para este inmueble.</p>
                @endif

                <div class="form-group">
                    <label for="{{ $formularioId }}-imagenes">Agregar nuevas imágenes
                        <span class="text-opcional">(JPG, PNG o WebP · hasta 10 imágenes · máx. 5 MB c/u)</span>
                    </label>
                    <input type="file" id="{{ $formularioId }}-imagenes" name="imagenes[]" class="input-file-custom" multiple
                        accept="image/jpeg,image/png,image/webp">
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-modal-cancel" data-modal-cerrar="modal-{{ $formularioId }}">Cancelar</button>
                <button type="submit" class="btn-panel-primary">
                    <x-icon name="save" class="h-4 w-4" /> Guardar cambios
                </button>
            </div>
        </form>

        {{--
            Las acciones sobre la galería van en formularios propios, fuera del
            formulario principal: HTML no permite anidar <form>, y así cada
            imagen se gestiona sin arrastrar el resto de los campos.
        --}}
        @foreach ($inmueble->imagenes as $imagen)
            @unless ($imagen->es_principal)
                <form id="portada-{{ $imagen->id }}" method="POST"
                    action="{{ route('admin.imagenes.principal', $imagen) }}" class="hidden">
                    @csrf
                    @method('PATCH')
                </form>
            @endunless

            <form id="borrar-imagen-{{ $imagen->id }}" method="POST"
                action="{{ route('admin.imagenes.destroy', $imagen) }}" class="hidden" data-confirmar
                data-confirmar-titulo="¿Eliminar esta imagen?"
                data-confirmar-texto="Se borrará también del servidor y no se puede recuperar."
                data-confirmar-boton="Sí, eliminar">
                @csrf
                @method('DELETE')
            </form>
        @endforeach
    </div>
</div>
