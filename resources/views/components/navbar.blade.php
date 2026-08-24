@php
    use App\Support\Servicios;

    // NOTA SEO: "Productos", "Promociones" y "Blog" se retiraron del menú porque esas
    // páginas todavía no existen. Enlazar a destinos inexistentes (o a "#") perjudica
    // el rastreo y la experiencia. Vuelve a añadirlos aquí en cuanto tengan URL real.
    //
    // Los servicios NO se listan a mano: vienen de App\Support\Servicios, la misma
    // fuente que alimenta /servicios y cada página de detalle.
    $servicios = Servicios::navegacion();

    $navLinks = [
        ['label' => 'Inicio', 'url' => route('home'), 'route' => 'home'],
        ['label' => 'Nosotros', 'url' => route('nosotros'), 'route' => 'nosotros'],
        [
            'label' => 'Servicios',
            'url' => route('servicios.index'),
            'route' => 'servicios.*',
            'children' => $servicios,
        ],
        ['label' => 'Contacto', 'url' => route('contacto'), 'route' => 'contacto'],
    ];
@endphp

<nav id="navbar" class="navbar" aria-label="Navegación principal">
    <div class="container navbar__inner">
        <a href="{{ route('home') }}" class="navbar__brand">
            <picture class="navbar__brand-picture">
                <source srcset="{{ asset('images/rankpro-logo-black.webp') }}" type="image/webp">
                <img src="{{ asset('images/rankpro-logo-black.png') }}"
                     alt="RankPro · Agencia de Marketing Digital en México"
                     class="navbar__brand-logo"
                     width="493" height="160"
                     fetchpriority="high" decoding="async">
            </picture>
        </a>

        <ul class="navbar__links">
            @foreach ($navLinks as $link)
                @php $isActive = request()->routeIs($link['route']); @endphp
                <li class="navbar__item{{ !empty($link['children']) ? ' navbar__item--mega' : '' }}">
                    <a href="{{ $link['url'] }}"
                       class="navbar__link{{ !empty($link['children']) ? ' nav-dropdown-trigger' : '' }}"
                       @if (!empty($link['children']))
                           aria-haspopup="true"
                           aria-expanded="false"
                           aria-controls="megamenu-servicios"
                       @endif
                       @if ($isActive) aria-current="page" @endif>
                        {{ $link['label'] }}
                        @if (!empty($link['children']))
                            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="navbar__chevron" aria-hidden="true"><path d="m6 9 6 6 6-6"></path></svg>
                        @endif
                    </a>

                    @if (!empty($link['children']))
                        {{-- El megamenú vive siempre en el HTML para que Google lo rastree;
                             solo se oculta visualmente desde navbar.css. --}}
                        <div id="megamenu-servicios" class="megamenu">
                            <div class="megamenu__inner">
                                <ul class="megamenu__grid">
                                    @foreach ($link['children'] as $servicio)
                                        <li>
                                            <a href="{{ $servicio['url'] }}" class="megamenu__card"
                                               @if (request()->routeIs('servicios.show') && request()->route('slug') === $servicio['slug']) aria-current="page" @endif>
                                                <span class="megamenu__icon {{ $servicio['gradient'] }}" aria-hidden="true">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $servicio['icon'] !!}</svg>
                                                </span>
                                                <span class="megamenu__text">
                                                    <span class="megamenu__title">{{ $servicio['nombre'] }}</span>
                                                    <span class="megamenu__desc">{{ $servicio['resumen'] }}</span>
                                                </span>
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>

                                <div class="megamenu__aside">
                                    <p class="megamenu__aside-title">¿No sabes por dónde empezar?</p>
                                    <p class="megamenu__aside-text">Te damos un diagnóstico sin costo y te decimos con honestidad si podemos ayudarte.</p>
                                    <a href="{{ route('contacto') }}" class="megamenu__aside-cta">
                                        Agendar diagnóstico
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg>
                                    </a>
                                    <a href="{{ route('servicios.index') }}" class="megamenu__aside-link">Ver todos los servicios</a>
                                </div>
                            </div>
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>

        <div class="navbar__actions">
            <a href="{{ route('login') }}" class="navbar__login">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                Iniciar sesión
            </a>
            <a href="{{ route('contacto') }}" class="navbar__cta">Agendar gratis</a>
        </div>

        <button id="mobile-menu-toggle" class="navbar__toggle" type="button" aria-label="Abrir menú" aria-controls="mobile-menu" aria-expanded="false">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="4" x2="20" y1="12" y2="12"></line><line x1="4" x2="20" y1="6" y2="6"></line><line x1="4" x2="20" y1="18" y2="18"></line></svg>
        </button>
    </div>

    <div id="mobile-menu" class="navbar__mobile">
        @foreach ($navLinks as $link)
            @if (empty($link['children']))
                <a href="{{ $link['url'] }}" class="navbar__mobile-link" @if (request()->routeIs($link['route'])) aria-current="page" @endif>{{ $link['label'] }}</a>
            @else
                {{-- Antes los 6 servicios no existían en móvil: eran inalcanzables sin
                     escritorio. Ahora van en un acordeón, enlazados y rastreables. --}}
                <div class="navbar__mobile-group">
                    <a href="{{ $link['url'] }}" class="navbar__mobile-link navbar__mobile-link--parent" @if (request()->routeIs($link['route'])) aria-current="page" @endif>{{ $link['label'] }}</a>
                    <button type="button"
                            class="navbar__mobile-toggle mobile-submenu-trigger"
                            aria-controls="mobile-submenu-servicios"
                            aria-expanded="false"
                            aria-label="Mostrar servicios">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="navbar__chevron" aria-hidden="true"><path d="m6 9 6 6 6-6"></path></svg>
                    </button>
                </div>
                <ul id="mobile-submenu-servicios" class="navbar__mobile-submenu">
                    @foreach ($link['children'] as $servicio)
                        <li>
                            <a href="{{ $servicio['url'] }}" class="navbar__mobile-sublink">{{ $servicio['nombre'] }}</a>
                        </li>
                    @endforeach
                </ul>
            @endif
        @endforeach
        <div class="navbar__mobile-actions">
            <a href="{{ route('login') }}" class="navbar__mobile-login">Iniciar sesión</a>
            <a href="{{ route('contacto') }}" class="navbar__mobile-cta">Agendar gratis</a>
        </div>
    </div>
</nav>
