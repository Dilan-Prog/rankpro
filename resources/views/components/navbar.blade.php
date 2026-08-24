@php
    use App\Support\Clusters;
    use App\Support\Servicios;
    use Illuminate\Support\Str;

    // NOTA SEO: "Productos" y "Promociones" siguen fuera del menú porque esas páginas
    // todavía no existen. Enlazar a destinos inexistentes (o a "#") perjudica el
    // rastreo y la experiencia. Vuelve a añadirlos aquí en cuanto tengan URL real.
    //
    // Ni los servicios ni los clústeres del blog se listan a mano: vienen de
    // App\Support\Servicios y App\Support\Clusters, las mismas fuentes que alimentan
    // /servicios, /blog y cada página de detalle.
    $servicios = Servicios::navegacion();

    // Los clústeres se normalizan a la forma que espera el megamenú (nombre + resumen).
    // No tienen icono propio, así que la tarjeta pinta el gradiente sin <svg>.
    $clusters = array_map(static fn (array $c): array => [
        'slug' => $c['slug'],
        'nombre' => $c['nombre'],
        'resumen' => $c['descripcion'],
        'gradient' => $c['gradient'],
        'url' => $c['url'],
    ], Clusters::navegacion());

    $navLinks = [
        ['label' => 'Inicio', 'url' => route('home'), 'route' => 'home'],
        ['label' => 'Nosotros', 'url' => route('nosotros'), 'route' => 'nosotros'],
        [
            'label' => 'Servicios',
            'url' => route('servicios.index'),
            'route' => 'servicios.*',
            'children' => $servicios,
            'children_route' => 'servicios.show',
            'aside_title' => '¿No sabes por dónde empezar?',
            'aside_text' => 'Te damos un diagnóstico sin costo y te decimos con honestidad si podemos ayudarte.',
            'aside_link' => ['label' => 'Ver todos los servicios', 'url' => route('servicios.index')],
        ],
        [
            'label' => 'Blog',
            'url' => route('blog.index'),
            'route' => 'blog.*',
            'children' => $clusters,
            'children_route' => 'blog.cluster',
            'aside_title' => 'Guías sin humo',
            'aside_text' => 'Lo que de verdad funciona en SEO, Google Ads y web, explicado con datos y sin promesas imposibles.',
            'aside_link' => ['label' => 'Ver todos los artículos', 'url' => route('blog.index')],
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
                @php
                    $isActive = request()->routeIs($link['route']);
                    // Un id por menú: dos megamenús con el mismo id romperían
                    // aria-controls y getElementById (el segundo sería inalcanzable).
                    $menuId = 'megamenu-' . Str::slug($link['label']);
                @endphp
                <li class="navbar__item{{ !empty($link['children']) ? ' navbar__item--mega' : '' }}">
                    <a href="{{ $link['url'] }}"
                       class="navbar__link{{ !empty($link['children']) ? ' nav-dropdown-trigger' : '' }}"
                       @if (!empty($link['children']))
                           aria-haspopup="true"
                           aria-expanded="false"
                           aria-controls="{{ $menuId }}"
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
                        <div id="{{ $menuId }}" class="megamenu">
                            <div class="megamenu__inner">
                                <ul class="megamenu__grid">
                                    @foreach ($link['children'] as $hijo)
                                        @php
                                            // La ruta de servicio usa {slug} y la de clúster {cluster}:
                                            // se comparan los dos para marcar el elemento activo.
                                            $esActual = request()->routeIs($link['children_route'])
                                                && in_array($hijo['slug'], [request()->route('slug'), request()->route('cluster')], true);
                                        @endphp
                                        <li>
                                            <a href="{{ $hijo['url'] }}" class="megamenu__card"
                                               @if ($esActual) aria-current="page" @endif>
                                                <span class="megamenu__icon {{ $hijo['gradient'] }}" aria-hidden="true">
                                                    @if (!empty($hijo['icon']))
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $hijo['icon'] !!}</svg>
                                                    @endif
                                                </span>
                                                <span class="megamenu__text">
                                                    <span class="megamenu__title">{{ $hijo['nombre'] }}</span>
                                                    <span class="megamenu__desc">{{ $hijo['resumen'] }}</span>
                                                </span>
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>

                                <div class="megamenu__aside">
                                    <p class="megamenu__aside-title">{{ $link['aside_title'] }}</p>
                                    <p class="megamenu__aside-text">{{ $link['aside_text'] }}</p>
                                    <a href="{{ route('contacto') }}" class="megamenu__aside-cta">
                                        Agendar diagnóstico
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg>
                                    </a>
                                    <a href="{{ $link['aside_link']['url'] }}" class="megamenu__aside-link">{{ $link['aside_link']['label'] }}</a>
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
            @php $submenuId = 'mobile-submenu-' . Str::slug($link['label']); @endphp
            @if (empty($link['children']))
                <a href="{{ $link['url'] }}" class="navbar__mobile-link" @if (request()->routeIs($link['route'])) aria-current="page" @endif>{{ $link['label'] }}</a>
            @else
                {{-- Antes los 6 servicios no existían en móvil: eran inalcanzables sin
                     escritorio. Ahora van en un acordeón, enlazados y rastreables.
                     El id se deriva de la etiqueta: Servicios y Blog no pueden
                     compartirlo o getElementById devolvería siempre el primero. --}}
                <div class="navbar__mobile-group">
                    <a href="{{ $link['url'] }}" class="navbar__mobile-link navbar__mobile-link--parent" @if (request()->routeIs($link['route'])) aria-current="page" @endif>{{ $link['label'] }}</a>
                    <button type="button"
                            class="navbar__mobile-toggle mobile-submenu-trigger"
                            aria-controls="{{ $submenuId }}"
                            aria-expanded="false"
                            data-label-mostrar="Mostrar {{ Str::lower($link['label']) }}"
                            data-label-ocultar="Ocultar {{ Str::lower($link['label']) }}"
                            aria-label="Mostrar {{ Str::lower($link['label']) }}">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="navbar__chevron" aria-hidden="true"><path d="m6 9 6 6 6-6"></path></svg>
                    </button>
                </div>
                <ul id="{{ $submenuId }}" class="navbar__mobile-submenu">
                    @foreach ($link['children'] as $hijo)
                        <li>
                            <a href="{{ $hijo['url'] }}" class="navbar__mobile-sublink">{{ $hijo['nombre'] }}</a>
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
