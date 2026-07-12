{{--
    Sidebar navigation. Active state is resolved server-side via
    request()->routeIs(), no JS needed to highlight the current module.
--}}
<aside class="sidebar" id="sidebar">
    <div class="sidebar__brand">
        <div class="sidebar__brand-row">
            <span class="sidebar__logo"><i class="fa-solid fa-rocket"></i></span>
            <div>
                <div class="sidebar__brand-name">AgencyOS</div>
                <div class="sidebar__brand-plan">Pro Plan</div>
            </div>
        </div>
    </div>

    <nav class="sidebar__nav" aria-label="Navegación principal">
        @php
            $links = [
                ['route' => 'admin.dashboard', 'active' => 'admin.dashboard', 'icon' => 'fa-gauge-high', 'label' => 'Dashboard'],
                ['route' => 'admin.clientes.index', 'active' => 'admin.clientes.*', 'icon' => 'fa-users', 'label' => 'CRM Clientes'],
                ['route' => 'admin.servicios.index', 'active' => 'admin.servicios.*', 'icon' => 'fa-briefcase', 'label' => 'Servicios'],
                ['route' => 'admin.seo.index', 'active' => 'admin.seo.*', 'icon' => 'fa-magnifying-glass', 'label' => 'Módulo SEO'],
                ['route' => 'admin.keywords.index', 'active' => 'admin.keywords.*', 'icon' => 'fa-key', 'label' => 'Keywords'],
                ['route' => 'admin.ads.index', 'active' => 'admin.ads.*', 'icon' => 'fa-bullhorn', 'label' => 'Módulo Ads'],
                ['route' => 'admin.desarrollo.index', 'active' => 'admin.desarrollo.*', 'icon' => 'fa-code', 'label' => 'Desarrollo'],
                ['route' => 'admin.finanzas.index', 'active' => 'admin.finanzas.*', 'icon' => 'fa-dollar-sign', 'label' => 'Finanzas'],
                ['route' => 'admin.archivos.index', 'active' => 'admin.archivos.*', 'icon' => 'fa-folder-open', 'label' => 'Archivos'],
                ['route' => 'admin.roles.index', 'active' => 'admin.roles.*', 'icon' => 'fa-user-shield', 'label' => 'Roles y Usuarios'],
            ];
        @endphp
        <ul class="sidebar__list">
            @foreach ($links as $link)
                <li>
                    <a class="sidebar__link @if (request()->routeIs($link['active'])) is-active @endif"
                       href="{{ Route::has($link['route']) ? route($link['route']) : '#' }}">
                        <i class="fa-solid {{ $link['icon'] }}"></i>
                        <span class="sidebar__link-label">{{ $link['label'] }}</span>
                    </a>
                </li>
            @endforeach
            <li>
                <a class="sidebar__link is-locked @if (request()->routeIs('admin.integraciones.*')) is-active @endif"
                   href="{{ Route::has('admin.integraciones.index') ? route('admin.integraciones.index') : '#' }}">
                    <i class="fa-solid fa-plug"></i>
                    <span class="sidebar__link-label">Integraciones</span>
                    <i class="fa-solid fa-lock sidebar__lock-icon"></i>
                </a>
            </li>
        </ul>
    </nav>

    <div class="sidebar__footer">
        <div class="sidebar__user">
            @php
                $initials = collect(explode(' ', auth()->user()->name ?? 'Usuario'))
                    ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
                    ->take(2)
                    ->join('');
            @endphp
            <span class="sidebar__avatar">{{ $initials }}</span>
            <div class="sidebar__user-info">
                <div class="sidebar__user-name">{{ auth()->user()->name ?? 'Usuario' }}</div>
                <div class="sidebar__user-role">{{ auth()->user()->role->label ?? 'Sin rol' }}</div>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="sidebar__logout" title="Cerrar sesión" aria-label="Cerrar sesión">
                    <i class="fa-solid fa-right-from-bracket"></i>
                </button>
            </form>
        </div>
    </div>
</aside>
<div class="sidebar-backdrop" id="sidebarBackdrop" hidden></div>
