@php
    /*
     * Revisión de E-E-A-T: se retiraron del hero las cifras de prueba social que
     * no podían verificarse (clientes, años, retención, ventas generadas), los
     * porcentajes de "Rendimiento" de las tarjetas y las afirmaciones de
     * resultado ("Top 3 en Google en menos de 6 meses", "PageSpeed 90+").
     *
     * En su lugar hay dos bloques comprobables por el propio cliente:
     *  - $compromisos: condiciones de la relación, coherentes con la sección
     *    "Cómo trabajamos" y con las FAQ de la portada.
     *  - $featureCards: cada tarjeta muestra un entregable real tomado del
     *    array 'entregables' de App\Support\Servicios, no una métrica.
     *
     * No se debe añadir aquí ninguna cifra de desempeño, plazo garantizado ni
     * schema de tipo AggregateRating o Review.
     */
    $compromisos = [
        [
            'titulo' => 'Consultoría inicial sin costo',
            'desc' => 'Revisamos tu sitio, tu medición y tus campañas antes de hablar de alcance o de precio.',
        ],
        [
            'titulo' => 'Tus cuentas, a tu nombre',
            'desc' => 'Google Ads, Analytics y Search Console se quedan contigo, trabajes o no con nosotros.',
        ],
        [
            'titulo' => 'Sin garantías de posiciones',
            'desc' => 'Nos comprometemos con el método y el reporte, nunca con un primer lugar en Google.',
        ],
        [
            'titulo' => 'Reporte mensual verificable',
            'desc' => 'Cada número del reporte lo puedes comprobar tú mismo en tus propias plataformas.',
        ],
    ];

    /*
     * 'entregable' proviene literalmente del catálogo de servicios para que el
     * hero no prometa nada distinto a lo que dice la página de cada servicio.
     */
    $featureCards = [
        [
            'icon' => 'target',
            'title' => 'SEM · Google Ads',
            'desc' => 'Estructura de campañas y palabras clave agrupadas por intención de búsqueda.',
            'entregable' => 'Conversiones configuradas en Google Ads y GA4',
        ],
        [
            'icon' => 'search',
            'title' => 'SEO Orgánico',
            'desc' => 'Auditoría técnica, arquitectura de contenido y enlazado interno con criterio.',
            'entregable' => 'Reporte mensual desde tu propia Search Console',
        ],
        [
            'icon' => 'code',
            'title' => 'Desarrollo Web',
            'desc' => 'Sitios responsivos, medibles y preparados para SEO desde la arquitectura.',
            'entregable' => 'GA4, Tag Manager y Search Console probados antes de publicar',
        ],
        [
            'icon' => 'gauge',
            'title' => 'Core Web Vitals',
            'desc' => 'Diagnóstico de LCP, INP y CLS con datos de laboratorio y de campo.',
            'entregable' => 'Medición antes y después, documentada y comparable',
        ],
    ];

    $icons = [
        'target' => '<circle cx="12" cy="12" r="10"></circle><circle cx="12" cy="12" r="6"></circle><circle cx="12" cy="12" r="2"></circle>',
        'search' => '<circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path>',
        'code' => '<path d="m18 16 4-4-4-4"></path><path d="m6 8-4 4 4 4"></path><path d="m14.5 4-5 16"></path>',
        'gauge' => '<path d="m12 14 4-4"></path><path d="M3.34 19a10 10 0 1 1 17.32 0"></path>',
    ];
@endphp

<section class="hero">
    <div class="container hero__grid">
        <div class="hero__content">
            <div class="section-badge">
                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m15.477 12.89 1.515 8.526a.5.5 0 0 1-.81.47l-3.58-2.687a1 1 0 0 0-1.197 0l-3.586 2.686a.5.5 0 0 1-.81-.469l1.514-8.526"></path><circle cx="12" cy="8" r="6"></circle></svg>
                MARKETING DIGITAL · MÉXICO
            </div>

            <div class="hero__heading">
                <h1>Agencia de Marketing<br aria-hidden="true"> <span class="text-brand">Digital</span> en México</h1>
                <p class="hero__subtitle">Tu Socio Estratégico para el Éxito Digital</p>
            </div>

            <p class="hero__description">Impulsamos marcas mexicanas con Google Ads, SEO orgánico, desarrollo web y optimización de velocidad. Resultados medibles, crecimiento real.</p>

            <div class="hero__actions">
                <button class="btn btn-primary">
                    Agendar Consultoría Gratuita
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg>
                </button>
                <a href="https://wa.me/527341036410" class="btn btn-outline">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"></path></svg>
                    Escríbenos por WhatsApp
                </a>
            </div>

            <ul class="hero__commitments" aria-label="Cómo trabajamos con nuestros clientes">
                @foreach ($compromisos as $compromiso)
                    <li class="hero__commitment">
                        <svg class="hero__commitment-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M20 6 9 17l-5-5"></path></svg>
                        <span>
                            <span class="hero__commitment-title">{{ $compromiso['titulo'] }}</span>
                            <span class="hero__commitment-desc">{{ $compromiso['desc'] }}</span>
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>

        <ul class="hero__cards" aria-label="Servicios de RankPro">
            @foreach ($featureCards as $card)
                <li class="feature-card">
                    <div class="feature-card__icon" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false">{!! $icons[$card['icon']] !!}</svg>
                    </div>
                    <p class="feature-card__title">{{ $card['title'] }}</p>
                    <p class="feature-card__desc">{{ $card['desc'] }}</p>
                    <p class="feature-card__deliverable">
                        <span class="feature-card__deliverable-label">Incluye</span>
                        {{ $card['entregable'] }}
                    </p>
                </li>
            @endforeach
        </ul>
    </div>
</section>
