{{--
    Sidebar navigation. Active state is resolved server-side via
    request()->routeIs(), no JS needed to highlight the current module.
    Link groups come from App\Support\Navigation (shared with
    CommandPaletteIndex so the module list only lives in one place).
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
        @foreach (\App\Support\Navigation::groups() as $groupLabel => $links)
            <div class="sidebar__group">
                <span class="sidebar__group-label">{{ $groupLabel }}</span>
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
