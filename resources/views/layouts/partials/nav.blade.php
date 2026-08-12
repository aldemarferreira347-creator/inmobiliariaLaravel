{{-- Navegación principal del sitio público --}}
<header>
    <div class="container">
        <nav aria-label="Navegación principal">
            <a href="{{ route('inicio') }}" class="logo">
                <span class="logo-badge"><x-icon name="building" class="h-5 w-5" /></span>
                Inmobiliaria García
            </a>

            <button class="menu-toggle" id="menuToggle" aria-label="Abrir menú" aria-expanded="false"
                aria-controls="navLinks">
                <x-icon name="menu" class="h-5 w-5" />
            </button>

            <ul class="nav-links" id="navLinks">
                <li>
                    <a href="{{ route('inicio') }}" @class(['active' => request()->routeIs('inicio')])>Inicio</a>
                </li>
                <li>
                    <a href="{{ route('inmuebles.index') }}"
                        @class(['active' => request()->routeIs('inmuebles.*')])>Inmuebles</a>
                </li>

                @auth
                    @if (auth()->user()->esAdministrador())
                        <li>
                            <a href="{{ route('admin.inmuebles.index') }}"
                                @class(['active' => request()->routeIs('admin.*')])>Panel</a>
                        </li>
                    @endif

                    @if (auth()->user()->esCliente())
                        <li>
                            <a href="{{ route('reservas.index') }}"
                                @class(['active' => request()->routeIs('reservas.*')])>Mis reservas</a>
                        </li>
                    @endif

                    <li>
                        <a href="{{ route('mensajes.index') }}" @class(['active' => request()->routeIs('mensajes.*')])>
                            Mensajes
                            <span class="nav-badge hidden" data-badge-mensajes>0</span>
                        </a>
                    </li>

                    <li>
                        <a href="{{ route('notificaciones.index') }}"
                            @class(['nav-link-icon', 'active' => request()->routeIs('notificaciones.*')])
                            aria-label="Notificaciones" title="Notificaciones">
                            <x-icon name="bell" class="h-5 w-5" />
                            <span class="nav-badge hidden" data-badge-notificaciones>0</span>
                        </a>
                    </li>

                    <li>
                        <a href="{{ route('perfil.edit') }}"
                            @class(['active' => request()->routeIs('perfil.*') || request()->routeIs('favoritos.*')])>Perfil</a>
                    </li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="btn-primary btn-nav-logout">
                                <x-icon name="log-out" class="h-4 w-4" /> Cerrar sesión
                            </button>
                        </form>
                    </li>
                @else
                    <li>
                        <a href="{{ route('register') }}"
                            @class(['active' => request()->routeIs('register')])>Registrarse</a>
                    </li>
                    <li>
                        <a href="{{ route('login') }}" @class(['btn-primary', 'active' => request()->routeIs('login')])>
                            <x-icon name="log-in" class="h-4 w-4" /> Iniciar sesión
                        </a>
                    </li>
                @endauth
            </ul>
        </nav>
    </div>
</header>
