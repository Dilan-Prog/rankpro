{{--
    Sidebar navigation. Active state is resolved server-side via
    request()->routeIs(), no JS needed to highlight the current module.
    Link groups come from App\Support\Navigation (shared with
    CommandPaletteIndex so the module list only lives in one place).
--}}
<aside class="sidebar" id="sidebar">
    <div class="sidebar__brand">
        {{-- /admin redirige al Dashboard; enlazar a una ruta concreta daria 403 a quien no tenga ese permiso. --}}
        <a href="{{ url('/admin') }}" class="sidebar__brand-link" aria-label="RankPro: ir al inicio">
            {{-- El logotipo negro es para el tema claro y el blanco para el oscuro; con el menu colapsado (62px) solo cabe el icono. --}}
            <picture class="sidebar__logo-full sidebar__logo-full--oscuro">
                <source srcset="{{ asset('images/rankpro-logo-white.webp') }}" type="image/webp">
                <img src="{{ asset('images/rankpro-logo-white.png') }}" alt="RankPro" width="493" height="160">
            </picture>
            <picture class="sidebar__logo-full sidebar__logo-full--claro">
                <source srcset="{{ asset('images/rankpro-logo-black.webp') }}" type="image/webp">
                <img src="{{ asset('images/rankpro-logo-black.png') }}" alt="RankPro" width="493" height="160">
            </picture>
            <img class="sidebar__logo-icon" src="{{ asset('images/icon-192.png') }}" alt="RankPro" width="32" height="32">
        </a>
    </div>

    <nav class="sidebar__nav" aria-label="Navegación principal">
        @foreach (\App\Support\Navigation::groups() as $groupLabel => $links)
            <div class="sidebar__group">
                <span class="sidebar__group-label">{{ $groupLabel }}</span>
                @if ($groupLabel === \App\Support\Navigation::GRUPO_MARCA)
                    {{-- Cliente activo: los módulos de esta sección (Identidad, Audiencia, SEO Plan, Contenido) trabajan con él. --}}
                    <form method="POST" action="{{ route('admin.cliente-activo') }}" class="sidebar__cliente">
                        @csrf
                        <select name="cliente_id" class="sidebar__cliente-select" aria-label="Cliente activo" onchange="this.form.submit()">
                            <option value="">Todos los clientes</option>
                            @foreach (\App\Support\ClienteActivo::opciones() as $clienteOpcion)
                                <option value="{{ $clienteOpcion->id }}" @selected($clienteOpcion->id === \App\Support\ClienteActivo::id())>{{ $clienteOpcion->empresa ?: $clienteOpcion->nombre }}</option>
                            @endforeach
                        </select>
                    </form>
                @endif
                <ul class="sidebar__list">
                    @foreach ($links as $link)
                        <li>
                            <a class="sidebar__link @if (request()->routeIs($link['active'])) is-active @endif"
                               href="{{ Route::has($link['route']) ? route($link['route']) : '#' }}"
                               title="{{ $link['label'] }}">
                                <i class="fa-solid {{ $link['icon'] }}"></i>
                                <span class="sidebar__link-label">{{ $link['label'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </nav>

    <button type="button" class="sidebar__collapse-toggle" id="sidebarCollapseToggle" title="Colapsar menú" aria-label="Colapsar menú">
        <i class="fa-solid fa-angles-left"></i>
        <span>Colapsar</span>
    </button>

    <div class="sidebar__footer">
        @php
            $initials = \App\Support\Labels::initials(auth()->user()->name ?? 'Usuario');
        @endphp
        <button type="button" class="sidebar__user" data-dropdown-trigger="userMenuSidebar" aria-haspopup="true">
            <span class="sidebar__avatar">{{ $initials }}</span>
            <div class="sidebar__user-info">
                <div class="sidebar__user-name">{{ auth()->user()->name ?? 'Usuario' }}</div>
                <div class="sidebar__user-role">{{ auth()->user()->role->label ?? 'Sin rol' }}</div>
            </div>
        </button>
        <x-user-menu id="userMenuSidebar" class="user-menu--sidebar" />
    </div>
</aside>
<div class="sidebar-backdrop" id="sidebarBackdrop" hidden></div>
