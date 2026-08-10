{{-- Alta de inmueble (HU-04.1). Se reabre solo si la validación falla EN ESTE formulario
     (y no en el modal de edición de algún inmueble de la misma página). --}}
<div class="modal-overlay" id="modal-inmueble" @if ($errors->any() && old('_formulario_inmueble') === 'crear') data-modal-abierto @endif>
    <div class="modal-box modal-box--lg">
        <div class="modal-box-header">
            <h2>Registrar inmueble</h2>
            <button type="button" class="modal-box-close" data-modal-cerrar="modal-inmueble" aria-label="Cerrar">
                <x-icon name="x" class="h-4 w-4" />
            </button>
        </div>

        <form method="POST" action="{{ route('admin.inmuebles.store') }}" enctype="multipart/form-data">
            @csrf

            @include('admin.inmuebles.partials.campos', ['inmueble' => null])

            <div class="gallery-section">
                <h3 class="gallery-title">Imágenes del inmueble</h3>

                <div class="form-group">
                    <label for="imagenes">Agregar imágenes
                        <span class="text-opcional">(JPG, PNG o WebP · hasta 10 imágenes · máx. 5 MB c/u)</span>
                    </label>
                    <input type="file" id="imagenes" name="imagenes[]" class="input-file-custom" multiple
                        accept="image/jpeg,image/png,image/webp">
                    <small class="field-note">La primera imagen se usará como portada.</small>
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-modal-cancel" data-modal-cerrar="modal-inmueble">Cancelar</button>
                <button type="submit" class="btn-panel-primary">
                    <x-icon name="save" class="h-4 w-4" /> Registrar inmueble
                </button>
            </div>
        </form>
    </div>
</div>
