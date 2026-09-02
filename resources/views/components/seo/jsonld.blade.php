{{--
    Parcial SEO: datos estructurados (JSON-LD, schema.org) globales del sitio.
    Se emite un solo @graph con Organization + WebSite (+ WebPage en la portada).

    Las páginas individuales pueden inyectar su propio schema con:
        @push('jsonld')
            <script type="application/ld+json">...</script>
        @endpush

    POR QUÉ Organization Y NO ProfessionalService:
    Aquí hubo ProfessionalService bajo la premisa de que "no exige PostalAddress".
    Es falsa: Google trata CUALQUIER subtipo de LocalBusiness -ProfessionalService
    incluido- como negocio local y exige address. Search Console lo reportó como
    "Local Business / Se requiere un valor para el campo address" (sep-2026).

    Como no hay dirección física verificable, el tipo correcto es Organization,
    que no la exige. No se pierde nada: la decisión de negocio ya era saltarse el
    SEO local el año 1, así que la elegibilidad para el pack local no aplicaba.

    Por eso tampoco se emiten aggregateRating ni review: no hay reseñas reales y
    publicarlas inventadas es motivo de acción manual de Google.

    CUANDO HAYA DIRECCIÓN VERIFICABLE, para recuperar el SEO local hay que hacer
    las dos cosas a la vez, no una:
      1. Volver el @type a ProfessionalService (o LocalBusiness).
      2. Añadir "address" (PostalAddress con streetAddress, addressLocality,
         addressRegion, postalCode, addressCountry "MX"), y opcionalmente "geo",
         "openingHoursSpecification", "priceRange" y "currenciesAccepted", que
         son propiedades de LocalBusiness y por eso se retiraron de aquí.

    El @id (url('/#organization')) NO cambia aunque cambie el @type: contacto,
    nosotros y las landings de servicio ya lo referencian.
--}}
@php
    $seoOrganizationId = url('/#organization');
    $seoWebsiteId = url('/#website');
    $seoHomeWebPageId = url('/#webpage');

    // Áreas de expertise: los nombres del catálogo (fuente única de verdad) más los
    // términos por los que se nos busca. Sin duplicados y sin inventar disciplinas.
    $seoKnowsAbout = array_values(array_unique(array_merge(
        array_column(\App\Support\Servicios::todos(), 'nombre'),
        [
            'SEO',
            'Google Ads',
            'Google Analytics 4',
            'Google Tag Manager',
            'Core Web Vitals',
            'Desarrollo web',
            'Marketing digital',
            'Comercio electrónico',
            'Automatización con n8n',
        ]
    )));

    // Perfiles sociales: se leen de config/services.php (alimentado por .env).
    // Mientras las cuentas no existan las variables van vacías y NO se emite
    // sameAs: declarar perfiles inexistentes es peor que no declarar nada.
    $seoSameAs = array_values(array_filter(
        array_map(static fn ($url): string => trim((string) $url), array_values((array) config('services.social', []))),
        static fn (string $url): bool => $url !== '' && filter_var($url, FILTER_VALIDATE_URL) !== false
    ));

    $seoBusiness = [
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
        'slogan' => 'Marketing digital medible para empresas en México.',
        'email' => 'administracion@rankprosolutions.com.mx',
        'telephone' => '+527341036410',
        'knowsAbout' => $seoKnowsAbout,
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
    ];

    // NO se incluye foundingDate: en el repo no hay una fecha de fundación
    // declarada (nosotros.blade.php solo dice "4 años de experiencia", que no es
    // una fecha verificable). Añadirla cuando el dueño la confirme.

    if ($seoSameAs !== []) {
        $seoBusiness['sameAs'] = $seoSameAs;
    }

    $seoGraph = [
        '@context' => 'https://schema.org',
        '@graph' => [
            $seoBusiness,
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
                // Sin potentialAction/SearchAction: el sitio no tiene buscador interno
                // (no existe ninguna ruta de búsqueda en routes/web.php). Declarar una
                // SearchAction que apunta a una URL inexistente es peor que omitirla.
            ],
        ],
    ];

    // WebPage solo en la portada. El resto de páginas inyecta el suyo por @push.
    if (request()->routeIs('home')) {
        $seoGraph['@graph'][] = [
            '@type' => 'WebPage',
            '@id' => $seoHomeWebPageId,
            'url' => url('/'),
            'name' => 'RankPro | Agencia de Marketing Digital en México',
            'description' => 'Agencia de marketing digital en México: SEM, SEO, desarrollo web y optimización de velocidad.',
            'inLanguage' => 'es-MX',
            'isPartOf' => ['@id' => $seoWebsiteId],
            'about' => ['@id' => $seoOrganizationId],
            'publisher' => ['@id' => $seoOrganizationId],
            'primaryImageOfPage' => [
                '@type' => 'ImageObject',
                'url' => asset('images/rankpro-logo-black.png'),
            ],
            // Sin breadcrumb: la portada es la raíz y un BreadcrumbList de un solo
            // elemento no aporta nada y Google lo ignora.
        ];
    }
@endphp
<script type="application/ld+json">{!! json_encode($seoGraph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
