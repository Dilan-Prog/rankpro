@php
    $metrics = [
        ['label' => 'Posición SEO promedio', 'value' => '#2.4', 'change' => '↑ +0.8'],
        ['label' => 'ROI Google Ads', 'value' => '420%', 'change' => '↑ +35%'],
        ['label' => 'Tráfico orgánico/mes', 'value' => '42,891', 'change' => '↑ +18%'],
        ['label' => 'Costo por lead', 'value' => '$85 MXN', 'change' => '↓ -22%'],
    ];
@endphp

<section class="dashboard-cta">
    <div class="container">
        <div class="dashboard-cta__panel">
            <div class="dashboard-cta__blob dashboard-cta__blob--1"></div>
            <div class="dashboard-cta__blob dashboard-cta__blob--2"></div>

            <div class="dashboard-cta__grid">
                <div>
                    <p class="dashboard-cta__eyebrow">RankDash Pro</p>
                    <h2 class="dashboard-cta__title">Gestiona todas tus campañas desde un solo lugar</h2>
                    <p class="dashboard-cta__desc">Panel centralizado con visibilidad total sobre SEO, Ads, redes sociales y analíticas. Reportes automáticos, alertas inteligentes.</p>
                    <div class="dashboard-cta__actions">
                        <button class="dashboard-cta__btn dashboard-cta__btn--light">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="6 3 20 12 6 21 6 3"></polygon></svg>
                            Ver demo gratuita
                        </button>
                        <button class="dashboard-cta__btn dashboard-cta__btn--ghost">Empezar gratis 14 días</button>
                    </div>
                </div>

                <div class="dashboard-cta__mock">
                    <div class="dashboard-cta__mock-bar">
                        <div class="dashboard-cta__dot dashboard-cta__dot--red"></div>
                        <div class="dashboard-cta__dot dashboard-cta__dot--yellow"></div>
                        <div class="dashboard-cta__dot dashboard-cta__dot--green"></div>
                        <div class="dashboard-cta__mock-url">rankpro.mx/dashboard</div>
                    </div>
                    <div class="dashboard-cta__metrics">
                        @foreach ($metrics as $metric)
                            <div class="dashboard-cta__metric">
                                <span class="dashboard-cta__metric-label">{{ $metric['label'] }}</span>
                                <div class="dashboard-cta__metric-values">
                                    <span class="dashboard-cta__metric-value">{{ $metric['value'] }}</span>
                                    <span class="dashboard-cta__metric-change">{{ $metric['change'] }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
