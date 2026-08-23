@php
    $stats = [
        ['value' => '+200', 'label' => 'Clientes satisfechos'],
        ['value' => '4 Años', 'label' => 'De experiencia en México'],
        ['value' => '98%', 'label' => 'Tasa de retención'],
        ['value' => '$120M+', 'label' => 'En ventas generadas'],
    ];

    $featureCards = [
        ['icon' => 'target', 'title' => 'SEM · Ads', 'desc' => 'Campañas que convierten desde el día uno', 'percent' => 92],
        ['icon' => 'search', 'title' => 'SEO Orgánico', 'desc' => 'Top 3 en Google en menos de 6 meses', 'percent' => 87],
        ['icon' => 'code', 'title' => 'Web a Medida', 'desc' => 'Sitios rápidos, bellos y funcionales', 'percent' => 95],
        ['icon' => 'gauge', 'title' => 'PageSpeed 90+', 'desc' => 'Rendimiento técnico de élite', 'percent' => 94],
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
                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15.477 12.89 1.515 8.526a.5.5 0 0 1-.81.47l-3.58-2.687a1 1 0 0 0-1.197 0l-3.586 2.686a.5.5 0 0 1-.81-.469l1.514-8.526"></path><circle cx="12" cy="8" r="6"></circle></svg>
                AGENCIA CERTIFICADA · MÉXICO
            </div>

            <div class="hero__heading">
                <h1>Agencia de Marketing<br aria-hidden="true"> <span class="text-brand">Digital</span> en México</h1>
                <p class="hero__subtitle">Tu Socio Estratégico para el Éxito Digital</p>
            </div>

            <p class="hero__description">Impulsamos marcas mexicanas con Google Ads, SEO orgánico, desarrollo web y optimización de velocidad. Resultados medibles, crecimiento real.</p>

            <div class="hero__actions">
                <button class="btn btn-primary">
                    Agendar Consultoría Gratuita
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg>
                </button>
                <a href="https://wa.me/527341036410" class="btn btn-outline">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"></path></svg>
                    Escríbenos
                </a>
            </div>

            <ul class="hero__stats" aria-label="RankPro en cifras">
                @foreach ($stats as $stat)
                    <li class="hero__stat">
                        <div class="hero__stat-value">{{ $stat['value'] }}</div>
                        <div class="hero__stat-label">{{ $stat['label'] }}</div>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="hero__cards">
            @foreach ($featureCards as $card)
                <div class="feature-card">
                    <div class="feature-card__icon" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false">{!! $icons[$card['icon']] !!}</svg>
                    </div>
                    <h2 class="feature-card__title">{{ $card['title'] }}</h2>
                    <div class="feature-card__desc">{{ $card['desc'] }}</div>
                    <div class="feature-card__progress">
                        <div class="feature-card__progress-labels">
                            <span>Rendimiento</span>
                            <span class="text-brand">{{ $card['percent'] }}%</span>
                        </div>
                        <div class="feature-card__bar" role="img" aria-label="Nivel de rendimiento de {{ $card['title'] }}: {{ $card['percent'] }} por ciento">
                            <div class="feature-card__bar-fill" style="width: {{ $card['percent'] }}%;"></div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
