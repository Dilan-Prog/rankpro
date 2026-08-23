{{--
    Parcial SEO: datos estructurados (JSON-LD, schema.org) globales del sitio.
    Se emite un solo @graph con Organization + WebSite.

    Las páginas individuales pueden inyectar su propio schema con:
        @push('jsonld')
            <script type="application/ld+json">...</script>
        @endpush

    PENDIENTE — LocalBusiness todavía NO se incluye porque no hay dirección física
    registrada en el proyecto. Para poder añadirlo hacen falta:
        - streetAddress    (calle y número)
        - addressLocality  (ciudad / municipio)
        - addressRegion    (estado, p. ej. "Michoacán")
        - postalCode       (código postal)
        - addressCountry   ("MX")
        - openingHours     (p. ej. "Mo-Fr 09:00-18:00")
        - geo (latitude / longitude) — opcional pero recomendado para el mapa
    Cuando el dueño los proporcione, añadir un nodo LocalBusiness al @graph con
    @id = url('/#localbusiness') y enlazarlo a la Organization vía "parentOrganization".
--}}
@php
    $seoOrganizationId = url('/#organization');
    $seoWebsiteId = url('/#website');

    $seoGraph = [
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'Organization',
                '@id' => $seoOrganizationId,
                'name' => 'RankPro',
                'alternateName' => 'RankPro Solutions',
                'url' => url('/'),
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => asset('images/rankpro-logo-black.png'),
                    'caption' => 'RankPro',
                ],
                'image' => asset('images/rankpro-logo-black.png'),
                'description' => 'Agencia de marketing digital en México: SEM, SEO, desarrollo web y optimización de velocidad.',
                'email' => 'administracion@rankprosolutions.com.mx',
                'telephone' => '+527341036410',
                'areaServed' => [
                    '@type' => 'Country',
                    'name' => 'MX',
                ],
                'contactPoint' => [
                    '@type' => 'ContactPoint',
                    'contactType' => 'customer service',
                    'email' => 'administracion@rankprosolutions.com.mx',
                    'telephone' => '+527341036410',
                    'areaServed' => 'MX',
                    'availableLanguage' => ['es-MX'],
                ],
                // PENDIENTE: el dueño debe añadir aquí las URLs de sus perfiles sociales
                // (Facebook, Instagram, LinkedIn, X, YouTube, TikTok...). Ej.:
                // 'sameAs' => ['https://www.facebook.com/rankpro', 'https://www.instagram.com/rankpro'],
                'sameAs' => [],
            ],
            [
                '@type' => 'WebSite',
                '@id' => $seoWebsiteId,
                'name' => 'RankPro',
                'alternateName' => 'RankPro Solutions',
                'url' => url('/'),
                'inLanguage' => 'es-MX',
                'publisher' => [
                    '@id' => $seoOrganizationId,
                ],
            ],
        ],
    ];
@endphp
<script type="application/ld+json">{!! json_encode($seoGraph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
