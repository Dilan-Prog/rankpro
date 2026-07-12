@php
    $stats = [
        ['value' => '500+', 'label' => 'Clientes satisfechos'],
        ['value' => '8 años', 'label' => 'De experiencia en México'],
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
                <h1>Agencia de<br>Marketing <span class="text-brand">Digital</span></h1>
                <p class="hero__subtitle">Tu Socio Estratégico para el Éxito Digital</p>
            </div>

            <p class="hero__description">Impulsamos marcas mexicanas con Google Ads, SEO orgánico, desarrollo web y optimización de velocidad. Resultados medibles, crecimiento real.</p>

            <div class="hero__actions">
                <button class="btn btn-primary">
                    Agendar Consultoría Gratuita
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg>
                </button>
                <button class="btn btn-outline">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                    Escríbenos
                </button>
            </div>

            <div class="hero__stats">
                @foreach ($stats as $stat)
                    <div class="hero__stat">
                        <div class="hero__stat-value">{{ $stat['value'] }}</div>
                        <div class="hero__stat-label">{{ $stat['label'] }}</div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="hero__cards">
            @foreach ($featureCards as $card)
                <div class="feature-card">
                    <div class="feature-card__icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $icons[$card['icon']] !!}</svg>
                    </div>
                    <div class="feature-card__title">{{ $card['title'] }}</div>
                    <div class="feature-card__desc">{{ $card['desc'] }}</div>
                    <div class="feature-card__progress">
                        <div class="feature-card__progress-labels">
                            <span>Rendimiento</span>
                            <span class="text-brand">{{ $card['percent'] }}%</span>
                        </div>
                        <div class="feature-card__bar">
                            <div class="feature-card__bar-fill" style="width: {{ $card['percent'] }}%;"></div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
