@php
    $checkIcon = '<path d="M21.801 10A10 10 0 1 1 17 3.335"></path><path d="m9 11 3 3L22 4"></path>';

    $plans = [
        [
            'name' => 'Starter',
            'price' => '$8,900',
            'desc' => 'Para PYMEs que inician su transformación',
            'features' => ['SEO On-Page básico', '1 campaña Google Ads', 'Reporte mensual', 'Soporte por email', 'Auditoría inicial gratuita'],
            'highlighted' => false,
        ],
        [
            'name' => 'Pro',
            'price' => '$16,900',
            'desc' => 'Para empresas que buscan escalar resultados',
            'features' => ['SEO On-Page y Off-Page completo', '3 campañas Google Ads + Meta Ads', 'Reportes quincenales', 'Soporte prioritario', 'Gestión de redes sociales', 'Dashboard RankDash Pro'],
            'highlighted' => true,
            'badge' => 'Más popular',
        ],
        [
            'name' => 'Enterprise',
            'price' => 'A medida',
            'desc' => 'Para marcas con operación nacional',
            'features' => ['Estrategia 360° personalizada', 'Campañas multicanal ilimitadas', 'Reportes semanales en vivo', 'Account manager dedicado', 'Desarrollo web incluido', 'SLA de soporte 24/7'],
            'highlighted' => false,
        ],
    ];
@endphp

<section class="pricing">
    <div class="container pricing__container">
        <div class="section-header">
            <div class="section-badge">
                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"></path></svg>
                PLANES Y PRECIOS
            </div>
            <h2>Invierte en crecimiento real</h2>
            <p>Sin contratos de permanencia. Resultados medibles desde el primer mes.</p>
        </div>

        <div class="pricing__grid">
            @foreach ($plans as $plan)
                <div class="pricing-card {{ $plan['highlighted'] ? 'pricing-card--highlighted' : '' }}">
                    @if ($plan['highlighted'])
                        <div class="pricing-card__badge">
                            <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11.525 2.295a.53.53 0 0 1 .95 0l2.31 4.679a2.123 2.123 0 0 0 1.595 1.16l5.166.756a.53.53 0 0 1 .294.904l-3.736 3.638a2.123 2.123 0 0 0-.611 1.878l.882 5.14a.53.53 0 0 1-.771.56l-4.618-2.428a2.122 2.122 0 0 0-1.973 0L6.396 21.01a.53.53 0 0 1-.77-.56l.881-5.139a2.122 2.122 0 0 0-.611-1.879L2.16 9.795a.53.53 0 0 1 .294-.906l5.165-.755a2.122 2.122 0 0 0 1.597-1.16z"></path></svg>
                            {{ $plan['badge'] }}
                        </div>
                    @endif

                    <h3 class="pricing-card__name">{{ $plan['name'] }}</h3>
                    <div class="pricing-card__price">
                        <span class="pricing-card__amount">{{ $plan['price'] }}</span>
                        @if ($plan['price'] !== 'A medida')
                            <span class="pricing-card__period">/mes</span>
                        @endif
                    </div>
                    <p class="pricing-card__desc">{{ $plan['desc'] }}</p>

                    <div class="pricing-card__features">
                        @foreach ($plan['features'] as $feature)
                            <div class="pricing-card__feature">
                                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $checkIcon !!}</svg>
                                <span>{{ $feature }}</span>
                            </div>
                        @endforeach
                    </div>

                    <button class="pricing-card__btn">Comenzar ahora</button>
                </div>
            @endforeach
        </div>
    </div>
</section>
