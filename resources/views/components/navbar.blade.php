@php
    $navLinks = [
        ['label' => 'Inicio', 'has_dropdown' => false],
        ['label' => 'Nosotros', 'has_dropdown' => false],
        ['label' => 'Servicios', 'has_dropdown' => true],
        ['label' => 'Productos', 'has_dropdown' => true],
        ['label' => 'Promociones', 'has_dropdown' => false],
        ['label' => 'Blog', 'has_dropdown' => false],
    ];
@endphp

<nav id="navbar" class="navbar">
    <div class="container navbar__inner">
        <a href="{{ route('home') }}" class="navbar__brand">
            <div class="navbar__logo">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z"></path><path d="m12 15-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z"></path><path d="M9 12H4s.55-3.03 2-4c1.62-1.08 5 0 5 0"></path><path d="M12 15v5s3.03-.55 4-2c1.08-1.62 0-5 0-5"></path></svg>
            </div>
            <div>
                <div class="navbar__brand-name">RankPro</div>
                <div class="navbar__brand-tagline">Digital Solutions &amp; Services</div>
            </div>
        </a>

        <div class="navbar__links">
            @foreach ($navLinks as $link)
                <button class="navbar__link nav-dropdown-trigger">
                    {{ $link['label'] }}
                    @if ($link['has_dropdown'])
                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="navbar__chevron"><path d="m6 9 6 6 6-6"></path></svg>
                    @endif
                </button>
            @endforeach
        </div>

        <div class="navbar__actions">
            <a href="{{ route('login') }}" class="navbar__login">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                Iniciar sesión
            </a>
            <button class="navbar__cta">Agendar gratis</button>
        </div>

        <button id="mobile-menu-toggle" class="navbar__toggle" aria-label="Abrir menú" aria-expanded="false">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" x2="20" y1="12" y2="12"></line><line x1="4" x2="20" y1="6" y2="6"></line><line x1="4" x2="20" y1="18" y2="18"></line></svg>
        </button>
    </div>

    <div id="mobile-menu" class="navbar__mobile">
        @foreach ($navLinks as $link)
            <button class="navbar__mobile-link">{{ $link['label'] }}</button>
        @endforeach
        <div class="navbar__mobile-actions">
            <button class="navbar__mobile-login">Iniciar sesión</button>
            <button class="navbar__mobile-cta">Agendar gratis</button>
        </div>
    </div>
</nav>
