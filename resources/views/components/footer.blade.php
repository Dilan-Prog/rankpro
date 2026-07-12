@php
    $footerColumns = [
        [
            'title' => 'Servicios',
            'links' => ['SEM & Google Ads', 'SEO Orgánico', 'Desarrollo Web', 'Analytics & Data', 'Social Media'],
        ],
        [
            'title' => 'Empresa',
            'links' => ['Nosotros', 'Casos de éxito', 'Blog', 'Promociones', 'Contacto'],
        ],
        [
            'title' => 'Legal',
            'links' => ['Términos y condiciones', 'Política de privacidad', 'Aviso de cookies'],
        ],
    ];
@endphp

<footer class="site-footer">
    <div class="container site-footer__grid">
        <div>
            <div class="site-footer__brand">
                <div class="site-footer__logo">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z"></path><path d="m12 15-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z"></path><path d="M9 12H4s.55-3.03 2-4c1.62-1.08 5 0 5 0"></path><path d="M12 15v5s3.03-.55 4-2c1.08-1.62 0-5 0-5"></path></svg>
                </div>
                <span class="site-footer__brand-name">RankPro</span>
            </div>
            <p class="site-footer__desc">Agencia de marketing digital en México. Resultados medibles, crecimiento real.</p>
        </div>

        @foreach ($footerColumns as $column)
            <div>
                <h4 class="site-footer__col-title">{{ $column['title'] }}</h4>
                <ul class="site-footer__links">
                    @foreach ($column['links'] as $link)
                        <li><a href="#">{{ $link }}</a></li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </div>

    <div class="container site-footer__bottom">
        <p class="site-footer__copy">© {{ date('Y') }} RankPro. Todos los derechos reservados.</p>
        <div class="site-footer__bottom-links">
            <a href="tel:+525512345678">+52 55 1234 5678</a>
            <a href="mailto:hola@rankpro.mx">hola@rankpro.mx</a>
        </div>
    </div>
</footer>
