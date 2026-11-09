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
        @php
            // Una sola vez: id() dentro de los bucles hacía una consulta por opción o por enlace.
            $clienteActivoId = \App\Support\ClienteActivo::id();
        @endphp
        @foreach (\App\Support\Navigation::groups() as $groupLabel => $links)
            @php
                $grupoId = 'grupo-' . \Illuminate\Support\Str::slug($groupLabel);
                // El grupo donde estás nunca arranca cerrado: perderías de vista el enlace activo.
                $tieneActivo = collect($links)->contains(fn (array $enlace): bool => \App\Support\Navigation::estaActivo($enlace));
                // «Más» (módulos sin uso todavía) nace cerrado; si el usuario lo abre se recuerda.
                $cerradoPorDefecto = $groupLabel === \App\Support\Navigation::GRUPO_MAS && ! $tieneActivo;
                $seccionPrevia = null;
            @endphp
            <div class="sidebar__group @if ($cerradoPorDefecto) is-cerrado @endif" data-grupo="{{ $groupLabel }}" @if ($cerradoPorDefecto) data-cerrado-por-defecto @endif @if ($tieneActivo) data-tiene-activo @endif>
                <button type="button" class="sidebar__group-toggle" aria-expanded="{{ $cerradoPorDefecto ? 'false' : 'true' }}" aria-controls="{{ $grupoId }}">
                    <span class="sidebar__group-label">{{ $groupLabel }}</span>
                    <i class="fa-solid fa-chevron-down sidebar__group-chevron" aria-hidden="true"></i>
                </button>
                <div class="sidebar__group-body" id="{{ $grupoId }}">
                @if ($groupLabel === \App\Support\Navigation::GRUPO_CLIENTE_ACTIVO)
                    {{-- Cliente activo: los módulos de esta sección trabajan con él (Identidad, Audiencia, SEO Plan y Contenido, solo con él). --}}
                    <form method="POST" action="{{ route('admin.cliente-activo') }}" class="sidebar__cliente">
                        @csrf
                        <select name="cliente_id" class="sidebar__cliente-select" aria-label="Cliente activo" onchange="this.form.submit()">
                            <option value="">Todos los clientes</option>
                            @foreach (\App\Support\ClienteActivo::opciones() as $clienteOpcion)
                                <option value="{{ $clienteOpcion->id }}" @selected($clienteOpcion->id === $clienteActivoId)>{{ $clienteOpcion->empresa ?: $clienteOpcion->nombre }}</option>
                            @endforeach
                        </select>
                    </form>
                    @if ($clienteActivoId === null)
                        <p class="sidebar__cliente-aviso">Elige un cliente arriba</p>
                    @endif
                @endif
                <ul class="sidebar__list">
                    @foreach ($links as $link)
                        @php
                            $pideCliente = \App\Support\Navigation::pideCliente($link);
                            $seccion = $link['seccion'] ?? null;
                        @endphp
                        @if ($seccion !== $seccionPrevia)
                            {{-- Encabezado pequeño del subgrupo; al volver a «sin sección» solo queda un separador. --}}
                            <li class="sidebar__seccion @if ($seccion === null) sidebar__seccion--separador @endif" @if ($seccion === null) role="presentation" @endif>{{ $seccion }}</li>
                            @php
                                $seccionPrevia = $seccion;
                            @endphp
                        @endif
                        <li>
                            <a class="sidebar__link @if (\App\Support\Navigation::estaActivo($link)) is-active @endif @if ($pideCliente) is-sin-cliente @endif"
                               href="{{ Route::has($link['route']) ? \App\Support\Navigation::url($link) : '#' }}"
                               title="{{ $pideCliente ? $link['label'] . ': elige un cliente arriba' : $link['label'] }}">
                                <i class="fa-solid {{ $link['icon'] }}"></i>
                                <span class="sidebar__link-label">{{ $link['label'] }}@if ($pideCliente && ! empty($link['param_cliente'])) <small class="sidebar__link-nota">Elige un cliente</small>@endif</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
                </div>
            </div>
        @endforeach
    </nav>

    {{-- Aplica los grupos abiertos/cerrados antes del primer pintado para que el menú no «salte» al cargar (mismo patrón que el tema y el colapso). --}}
    <script>
        (function () {
            try {
                // La clave anterior guardaba los cerrados con los nombres de grupo viejos y «Agencia» cambió de
                // contenido: se descarta sin leerla y cada grupo arranca en su valor por defecto.
                localStorage.removeItem("agencyos-sidebar-groups");
                var estado = JSON.parse(localStorage.getItem("agencyos-sidebar-estado") || "{}");
                if (estado === null || typeof estado !== "object" || Array.isArray(estado)) estado = {};
                document.querySelectorAll(".sidebar__group").forEach(function (grupo) {
                    var guardado = estado[grupo.dataset.grupo];
                    // Solo manda lo que el usuario decidió; una clave huérfana nunca se consulta.
                    if (guardado !== "abierto" && guardado !== "cerrado") return;
                    var cerrar = guardado === "cerrado" && !grupo.hasAttribute("data-tiene-activo");
                    grupo.classList.toggle("is-cerrado", cerrar);
                    grupo.querySelector(".sidebar__group-toggle").setAttribute("aria-expanded", cerrar ? "false" : "true");
                });
            } catch (e) {}

            // Cada enlace del menú es una carga de página completa: sin esto la barra vuelve arriba
            // y quien está en la sección de abajo pierde su sitio. Se restaura aquí (tras abrir o
            // cerrar los grupos, que cambian la altura) y se guarda desde global.js.
            try {
                var nav = document.querySelector(".sidebar__nav");
                var guardado = parseInt(localStorage.getItem("agencyos-sidebar-scroll") || "0", 10);
                if (nav && guardado > 0) nav.scrollTop = guardado;

                // Si aun así el enlace activo queda fuera de la vista (p. ej. se llegó por otra ruta), se trae.
                // Se repite al terminar de cargar: aquí el pie del menú aún no existe y la barra mide de más.
                var verActivo = function () {
                    var activo = nav && nav.querySelector(".sidebar__link.is-active");
                    if (!activo) return;
                    var caja = activo.getBoundingClientRect();
                    var marco = nav.getBoundingClientRect();
                    if (caja.top < marco.top || caja.bottom > marco.bottom) nav.scrollTop += caja.top - marco.top - (marco.height - caja.height) / 2;
                };
                verActivo();
                document.addEventListener("DOMContentLoaded", verActivo);
            } catch (e) {}
        })();
    </script>

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
