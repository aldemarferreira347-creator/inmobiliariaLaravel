<a href="{{ route('admin.inmuebles.index') }}" @class(['active' => request()->routeIs('admin.inmuebles.*')])>
    <x-icon name="building" class="nav-icon" /> Gestión de Inmuebles
</a>
<a href="{{ route('admin.usuarios.index') }}" @class(['active' => request()->routeIs('admin.usuarios.*')])>
    <x-icon name="users" class="nav-icon" /> Gestión de Clientes
</a>
<a href="{{ route('admin.reservas.index') }}" @class(['active' => request()->routeIs('admin.reservas.*')])>
    <x-icon name="clipboard-list" class="nav-icon" /> Reservas
</a>
<a href="{{ route('admin.contratos.index') }}" @class(['active' => request()->routeIs('admin.contratos.*')])>
    <x-icon name="file-text" class="nav-icon" /> Contratos
</a>
<a href="{{ route('admin.citas.index') }}" @class(['active' => request()->routeIs('admin.citas.*')])>
    <x-icon name="calendar" class="nav-icon" /> Gestión de Citas
</a>
<a href="{{ route('admin.franjas.index') }}" @class(['active' => request()->routeIs('admin.franjas.*')])>
    <x-icon name="clock" class="nav-icon" /> Franjas de Citas
</a>
<a href="{{ route('admin.reportes.index') }}" @class(['active' => request()->routeIs('admin.reportes.*')])>
    <x-icon name="bar-chart" class="nav-icon" /> Reportes
</a>
<a href="{{ route('mensajes.index') }}" @class(['active' => request()->routeIs('mensajes.*')])>
    <x-icon name="message-square" class="nav-icon" /> Mensajes
    <span class="nav-badge hidden" data-badge-mensajes>0</span>
</a>
<a href="{{ route('inmuebles.index') }}" @class(['active' => request()->routeIs('inmuebles.*')])>
    <x-icon name="search" class="nav-icon" /> Ver catálogo
</a>
<a href="{{ route('admin.notificaciones.create') }}" @class(['active' => request()->routeIs('admin.notificaciones.*')])>
    <x-icon name="megaphone" class="nav-icon" /> Enviar Notificación
</a>
<a href="{{ route('asesor.ventas.index') }}" @class(['active' => request()->routeIs('asesor.ventas.*')])>
    <x-icon name="dollar-sign" class="nav-icon" /> Ventas
</a>
<a href="{{ route('admin.permisos.index') }}" @class(['active' => request()->routeIs('admin.permisos.*')])>
    <x-icon name="shield" class="nav-icon" /> Roles y Permisos
</a>
