@php
    // NOTA SEO: "Productos", "Promociones" y "Blog" se retiraron del menú porque esas
    // páginas todavía no existen. Enlazar a destinos inexistentes (o a "#") perjudica
    // el rastreo y la experiencia. Vuelve a añadirlos aquí en cuanto tengan URL real.
    $navLinks = [
        ['label' => 'Inicio', 'url' => route('home'), 'route' => 'home'],
        ['label' => 'Nosotros', 'url' => route('nosotros'), 'route' => 'nosotros'],
        [
            'label' => 'Servicios',
            'url' => route('servicios.index'),
            'route' => 'servicios.*',
            'children' => [
                ['label' => 'SEM & Google Ads', 'url' => route('servicios.show', 'sem-google-ads')],
                ['label' => 'SEO Orgánico', 'url' => route('servicios.show', 'seo-organico')],
                ['label' => 'Desarrollo Web', 'url' => route('servicios.show', 'desarrollo-web')],
                ['label' => 'PageSpeed & Core Web Vitals', 'url' => route('servicios.show', 'pagespeed-core-web-vitals')],
                ['label' => 'Analytics & Data', 'url' => route('servicios.show', 'analytics-data')],
                ['label' => 'Social Media', 'url' => route('servicios.show', 'social-media')],
            ],
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
                <li>
                    <a href="{{ $link['url'] }}"
                       class="navbar__link{{ !empty($link['children']) ? ' nav-dropdown-trigger' : '' }}"
                      
                       @if (!empty($link['children'])) aria-haspopup="true" aria-expanded="false" @endif
                       @if ($isActive) aria-current="page" @endif>
                        {{ $link['label'] }}
                        @if (!empty($link['children']))
                            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="navbar__chevron"><path d="m6 9 6 6 6-6"></path></svg>
                        @endif
                    </a>
                    @if (!empty($link['children']))
                        {{-- Submenú siempre presente en el HTML para que Google lo rastree;
                             se oculta solo visualmente vía .navbar__dropdown en navbar.css. --}}
                        <ul class="navbar__dropdown">
                            @foreach ($link['children'] as $child)
                                <li>
                                    <a href="{{ $child['url'] }}" class="navbar__dropdown-link">{{ $child['label'] }}</a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </li>
            @endforeach
        </ul>

        <div class="navbar__actions">
            <a href="{{ route('login') }}" class="navbar__login">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                Iniciar sesión
            </a>
            <a href="{{ route('contacto') }}" class="navbar__cta">Agendar gratis</a>
        </div>

        <button id="mobile-menu-toggle" class="navbar__toggle" type="button" aria-label="Abrir menú" aria-controls="mobile-menu" aria-expanded="false">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" x2="20" y1="12" y2="12"></line><line x1="4" x2="20" y1="6" y2="6"></line><line x1="4" x2="20" y1="18" y2="18"></line></svg>
        </button>
    </div>

    <div id="mobile-menu" class="navbar__mobile">
        @foreach ($navLinks as $link)
            <a href="{{ $link['url'] }}" class="navbar__mobile-link" @if (request()->routeIs($link['route'])) aria-current="page" @endif>{{ $link['label'] }}</a>
        @endforeach
        <div class="navbar__mobile-actions">
            <a href="{{ route('login') }}" class="navbar__mobile-login">Iniciar sesión</a>
            <a href="{{ route('contacto') }}" class="navbar__mobile-cta">Agendar gratis</a>
        </div>
    </div>
</nav>
