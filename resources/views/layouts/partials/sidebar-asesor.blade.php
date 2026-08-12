<a href="{{ route('mensajes.index') }}" @class(['active' => request()->routeIs('mensajes.*')])>
    <x-icon name="message-square" class="nav-icon" /> Mensajes
    <span class="nav-badge hidden" data-badge-mensajes>0</span>
</a>
<a href="{{ route('asesor.citas.index') }}" @class(['active' => request()->routeIs('asesor.citas.*')])>
    <x-icon name="calendar" class="nav-icon" /> Mis citas
</a>
<a href="{{ route('asesor.ventas.index') }}" @class(['active' => request()->routeIs('asesor.ventas.*')])>
    <x-icon name="dollar-sign" class="nav-icon" /> Mis ventas
</a>
