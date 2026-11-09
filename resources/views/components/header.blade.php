{{--
    Top header bar. Receives the current module's short label as $title
    (passed by layouts/admin.blade.php from the controller's $pageTitle).
--}}
@props(['title' => ''])
<header class="topbar">
    <div class="topbar__left">
        <button class="topbar__menu-btn" id="sidebarToggle" type="button" aria-label="Abrir menú" aria-expanded="false">
            <i class="fa-solid fa-bars"></i>
        </button>
        <span class="topbar__title">{{ $title }}</span>
        @php
            // Visible en toda página: antes solo estaba en el grupo «Marca» del menú, que se puede colapsar.
            $clienteActivoTop = \App\Support\ClienteActivo::modelo();
            // Mismo criterio que el selector del menú (grupo Cliente activo): quien no ve esa sección no debe recibir la lista de clientes.
            $verSelectorCliente = array_key_exists(\App\Support\Navigation::GRUPO_CLIENTE_ACTIVO, \App\Support\Navigation::groups());
        @endphp
        @if ($verSelectorCliente)
        <div class="topbar__cliente @if ($clienteActivoTop) is-activo @endif" data-cliente-activo-barra>
            <form method="POST" action="{{ route('admin.cliente-activo') }}" class="topbar__cliente-form">
                @csrf
                <i class="fa-solid fa-user-tag topbar__cliente-icono" aria-hidden="true"></i>
                <select name="cliente_id" class="topbar__cliente-select" aria-label="Cliente activo" title="Cliente activo" onchange="this.form.submit()">
                    <option value="">Todos los clientes</option>
                    @foreach (\App\Support\ClienteActivo::opciones() as $clienteOpcion)
                        <option value="{{ $clienteOpcion->id }}" @selected($clienteActivoTop && $clienteOpcion->id === $clienteActivoTop->id)>{{ $clienteOpcion->nombre_comercial }}</option>
                    @endforeach
                </select>
            </form>
            @if ($clienteActivoTop)
                <form method="POST" action="{{ route('admin.cliente-activo') }}">
                    @csrf
                    <input type="hidden" name="cliente_id" value="">
                    <button type="submit" class="topbar__cliente-limpiar" title="Quitar el cliente activo">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i> Todos los clientes
                    </button>
                </form>
            @endif
        </div>
        @endif
        <button type="button" class="command-palette-trigger" data-command-palette-trigger>
            <i class="fa-solid fa-magnifying-glass"></i>
            <span class="command-palette-trigger__label">Buscar</span>
            <span class="command-palette-trigger__kbd">⌘K</span>
        </button>
    </div>
    <div class="topbar__actions">
        <button class="topbar__theme" type="button" id="themeToggle" aria-label="Cambiar a modo claro" title="Cambiar tema">
            <i class="fa-solid fa-sun" data-theme-icon="light"></i>
            <i class="fa-solid fa-moon" data-theme-icon="dark"></i>
        </button>
        <button class="topbar__bell" type="button" aria-label="Notificaciones" data-dropdown-trigger="notifPanel" aria-haspopup="true">
            <i class="fa-solid fa-bell"></i>
            <span class="topbar__bell-dot"></span>
        </button>
        <x-notif-panel />
        <div class="topbar__user">
            <button type="button" class="topbar__avatar" data-dropdown-trigger="userMenuHeader" aria-label="Cuenta" aria-haspopup="true">
                {{ \App\Support\Labels::initials(auth()->user()->name ?? 'Usuario') }}
            </button>
            <x-user-menu id="userMenuHeader" />
        </div>
    </div>
</header>
