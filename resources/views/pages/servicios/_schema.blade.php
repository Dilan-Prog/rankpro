{{-- Schema compartido por la plantilla generica y por las landings propias.
     Espera $servicio con slug, nombre, meta_description y faqs. --}}
@push('jsonld')
    <script type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'Service',
                    'name' => $servicio['nombre'],
                    'description' => $servicio['meta_description'],
                    'serviceType' => $servicio['nombre'],
                    'url' => route('servicios.show', $servicio['slug']),
                    'provider' => ['@id' => url('/#organization')],
                    'areaServed' => [
                        '@type' => 'Country',
                        'name' => 'México',
                        'identifier' => 'MX',
                    ],
                    'availableChannel' => [
                        '@type' => 'ServiceChannel',
                        'serviceUrl' => route('contacto'),
                    ],
                ],
                [
                    '@type' => 'FAQPage',
                    'mainEntity' => collect($servicio['faqs'])->map(fn ($faq) => [
                        '@type' => 'Question',
                        'name' => $faq['p'],
                        'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq['r']],
                    ])->all(),
                ],
                [
                    '@type' => 'BreadcrumbList',
                    'itemListElement' => [
                        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Inicio', 'item' => url('/')],
                        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Servicios', 'item' => route('servicios.index')],
                        ['@type' => 'ListItem', 'position' => 3, 'name' => $servicio['nombre'], 'item' => route('servicios.show', $servicio['slug'])],
                    ],
                ],
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
    </script>
@endpush
