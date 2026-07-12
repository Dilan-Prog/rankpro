@php
    $services = [
        [
            'icon' => '<circle cx="12" cy="12" r="10"></circle><circle cx="12" cy="12" r="6"></circle><circle cx="12" cy="12" r="2"></circle>',
            'gradient' => 'gradient-1',
            'title' => 'SEM & Google Ads',
            'desc' => 'Campañas de búsqueda, display y shopping para maximizar tu ROI.',
            'tags' => ['Google Ads', 'Shopping', 'Display'],
        ],
        [
            'icon' => '<circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path>',
            'gradient' => 'gradient-2',
            'title' => 'SEO Orgánico',
            'desc' => 'Estrategia de contenido, link building y SEO técnico para el Top 3.',
            'tags' => ['On-Page', 'Off-Page', 'Técnico'],
        ],
        [
            'icon' => '<path d="m18 16 4-4-4-4"></path><path d="m6 8-4 4 4 4"></path><path d="m14.5 4-5 16"></path>',
            'gradient' => 'gradient-3',
            'title' => 'Desarrollo Web',
            'desc' => 'Sitios web y tiendas online de alto rendimiento con diseño único.',
            'tags' => ['React', 'Shopify', 'WordPress'],
        ],
        [
            'icon' => '<path d="m12 14 4-4"></path><path d="M3.34 19a10 10 0 1 1 17.32 0"></path>',
            'gradient' => 'gradient-4',
            'title' => 'PageSpeed & Core Web Vitals',
            'desc' => 'Optimización técnica para alcanzar 90+ en Lighthouse.',
            'tags' => ['Performance', 'CWV', 'Lighthouse'],
        ],
        [
            'icon' => '<line x1="18" x2="18" y1="20" y2="10"></line><line x1="12" x2="12" y1="20" y2="4"></line><line x1="6" x2="6" y1="20" y2="14"></line>',
            'gradient' => 'gradient-5',
            'title' => 'Analytics & Data',
            'desc' => 'GA4, Tag Manager y dashboards para decisiones basadas en datos reales.',
            'tags' => ['GA4', 'Looker Studio', 'GTM'],
        ],
        [
            'icon' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path>',
            'gradient' => 'gradient-6',
            'title' => 'Social Media',
            'desc' => 'Gestión profesional de redes: contenido, pauta y crecimiento de comunidad.',
            'tags' => ['Instagram', 'Facebook', 'LinkedIn'],
        ],
    ];
@endphp

<section class="services">
    <div class="container">
        <div class="section-header">
            <div class="section-badge">
                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 14a1 1 0 0 1-.78-1.63l9.9-10.2a.5.5 0 0 1 .86.46l-1.92 6.02A1 1 0 0 0 13 10h7a1 1 0 0 1 .78 1.63l-9.9 10.2a.5.5 0 0 1-.86-.46l1.92-6.02A1 1 0 0 0 11 14z"></path></svg>
                NUESTROS SERVICIOS
            </div>
            <h2>Todo lo que necesitas para <span class="text-brand">dominar el mundo digital</span></h2>
            <p>Combinamos estrategia, creatividad y tecnología para llevar tu marca al siguiente nivel en México.</p>
        </div>

        <div class="services__grid">
            @foreach ($services as $service)
                <div class="service-card">
                    <div class="service-card__icon {{ $service['gradient'] }}">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $service['icon'] !!}</svg>
                    </div>
                    <h3 class="service-card__title">{{ $service['title'] }}</h3>
                    <p class="service-card__desc">{{ $service['desc'] }}</p>
                    <div class="service-card__tags">
                        @foreach ($service['tags'] as $tag)
                            <span class="service-card__tag">{{ $tag }}</span>
                        @endforeach
                    </div>
                    <div class="service-card__link">
                        Ver más
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"></path></svg>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
