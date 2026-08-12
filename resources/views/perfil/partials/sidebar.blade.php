{{-- Columna izquierda del perfil: avatar, menú de la cuenta y cierre de sesión --}}
<aside class="perfil-sidebar">
    <div class="perfil-avatar">
        <div class="avatar-wrapper">
            <img src="{{ $usuario->foto }}" alt="Foto de {{ $usuario->nombre }}" id="avatar-img"
                class="avatar-img-preview">

            <button type="button" class="avatar-change-btn" aria-label="Cambiar foto de perfil"
                onclick="document.getElementById('foto-input').click()">
                <x-icon name="camera" class="h-4 w-4" />
            </button>
        </div>

        <h2>{{ $usuario->nombre }}</h2>
        <span class="user-role">{{ $usuario->rol->etiqueta() }}</span>

        @if ($usuario->tieneFotoPropia())
            <form method="POST" action="{{ route('perfil.foto.destroy') }}" class="form-mt-8" data-confirmar
                data-confirmar-titulo="¿Eliminar la foto de perfil?"
                data-confirmar-texto="Volverás a mostrar el avatar con tus iniciales."
                data-confirmar-boton="Sí, eliminar">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn-logout btn-logout-sm">
                    <x-icon name="trash-2" class="h-4 w-4" /> Eliminar foto
                </button>
            </form>
        @endif
    </div>

    {{-- Formulario oculto: se envía solo al elegir un archivo --}}
    <form id="form-foto" method="POST" action="{{ route('perfil.foto.store') }}" enctype="multipart/form-data"
        class="hidden">
        @csrf
        <input type="file" id="foto-input" name="foto" accept="image/jpeg,image/png,image/webp">
    </form>

    <nav class="perfil-menu">
        <a href="{{ route('perfil.edit') }}" @class(['active' => request()->routeIs('perfil.edit')])>
            <x-icon name="id-card" class="h-4 w-4" /> Mi perfil
        </a>
        <a href="{{ route('reservas.index') }}" @class(['active' => request()->routeIs('reservas.*')])>
            <x-icon name="clipboard-list" class="h-4 w-4" /> Mis reservas
        </a>
        <button type="button" data-modal-abrir="modal-arriendos">
            <x-icon name="key" class="h-4 w-4" /> Mis arriendos
        </button>
        <button type="button" data-modal-abrir="modal-compras">
            <x-icon name="tag" class="h-4 w-4" /> Mis compras
        </button>
        <a href="{{ route('citas.index') }}" @class(['active' => request()->routeIs('citas.*')])>
            <x-icon name="calendar" class="h-4 w-4" /> Mis citas
        </a>
        <button type="button" data-modal-abrir="modal-tarjetas">
            <x-icon name="credit-card" class="h-4 w-4" /> Mis tarjetas
        </button>
        <a href="{{ route('favoritos.index') }}" @class(['active' => request()->routeIs('favoritos.*')])>
            <x-icon name="star" class="h-4 w-4" /> Mis favoritos
        </a>
        <a href="{{ route('mensajes.index') }}" @class(['active' => request()->routeIs('mensajes.*')])>
            <x-icon name="message-square" class="h-4 w-4" /> Mensajes
            <span class="nav-badge hidden" data-badge-mensajes>0</span>
        </a>
        <a href="{{ route('notificaciones.index') }}" @class(['active' => request()->routeIs('notificaciones.*')])>
            <x-icon name="bell" class="h-4 w-4" /> Notificaciones
            <span class="nav-badge hidden" data-badge-notificaciones>0</span>
        </a>
        <button type="button" data-modal-abrir="modal-password">
            <x-icon name="lock" class="h-4 w-4" /> Cambiar contraseña
        </button>

        @if ($usuario->esAdministrador())
            <a href="{{ route('admin.inmuebles.index') }}" class="panel-link">
                <x-icon name="settings" class="h-4 w-4" /> Panel de administración
            </a>
        @endif
    </nav>

    <div class="perfil-actions">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn-logout">
                <x-icon name="log-out" class="h-4 w-4" /> Cerrar sesión
            </button>
        </form>
    </div>
</aside>
