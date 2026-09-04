@php
    // NOTA SEO: "Casos de éxito" y "Promociones" siguen fuera porque no existen como
    // página ni como sección con id propio. Vuelve a añadirlos aquí cuando tengan URL real.
    $footerColumns = [
        [
            'title' => 'Servicios',
            'links' => [
                // Se derivan del catalogo: dar de alta un servicio nuevo lo agrega
                // aqui automaticamente, igual que en el megamenu y el sitemap.
                ...array_map(
                    fn (array $s): array => ['label' => $s['nombre'], 'url' => $s['url']],
                    \App\Support\Servicios::navegacion()
                ),
            ],
        ],
        [
            'title' => 'Blog',
            'links' => [
                ['label' => 'Todos los artículos', 'url' => route('blog.index')],
                // Mismo criterio que en Servicios: los clústeres salen del catálogo
                // (App\Support\Clusters), no de una lista escrita a mano aquí.
                ...array_map(
                    fn (array $c): array => ['label' => $c['nombre_corto'], 'url' => $c['url']],
                    \App\Support\Clusters::navegacion()
                ),
            ],
        ],
        [
            'title' => 'Empresa',
            'links' => [
                ['label' => 'Nosotros', 'url' => route('nosotros')],
                ['label' => 'Servicios', 'url' => route('servicios.index')],
                ['label' => 'Contacto', 'url' => route('contacto')],
            ],
        ],
        [
            'title' => 'Legal',
            'links' => [
                ['label' => 'Términos y condiciones', 'url' => route('legal.terminos')],
                ['label' => 'Aviso de privacidad', 'url' => route('legal.privacidad')],
                ['label' => 'Política de cookies', 'url' => route('legal.cookies')],
            ],
        ],
    ];
@endphp

<footer class="site-footer">
    <div class="container site-footer__grid">
        <div>
            <div class="site-footer__brand">
                <picture class="site-footer__brand-picture">
                    <source srcset="{{ asset('images/rankpro-logo-white.webp') }}" type="image/webp">
                    <img src="{{ asset('images/rankpro-logo-white.png') }}"
                         alt="RankPro"
                         class="site-footer__brand-logo"
                         width="493" height="160"
                         loading="lazy" decoding="async">
                </picture>
            </div>
            <p class="site-footer__desc">Agencia de marketing digital y desarrollo de software.</p>
        </div>

        @foreach ($footerColumns as $column)
            <div>
                <h2 class="site-footer__col-title">{{ $column['title'] }}</h2>
                <ul class="site-footer__links">
                    @foreach ($column['links'] as $link)
                        <li><a href="{{ $link['url'] }}">{{ $link['label'] }}</a></li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </div>

    <div class="container site-footer__bottom">
        <p class="site-footer__copy">© {{ date('Y') }} RankPro. Todos los derechos reservados.</p>
        <div class="site-footer__bottom-links">
            <a href="https://wa.me/527341036410" target="_blank" rel="noopener nofollow">WhatsApp</a>
            <a href="mailto:administracion@rankprosolutions.com.mx">administracion@rankprosolutions.com.mx</a>
        </div>
    </div>
</footer>
