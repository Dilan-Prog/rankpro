@php
    $testimonials = [
        [
            'quote' => 'RankPro transformó nuestra presencia digital. En 4 meses triplicamos el tráfico orgánico y nuestras ventas online subieron un 180%. El equipo es excepcional.',
            'initials' => 'SR',
            'avatar_bg' => '#0F9D6E',
            'name' => 'Sofía Ramírez',
            'role' => 'CEO, FashionMX',
        ],
        [
            'quote' => 'Llevamos 2 años con RankPro y los resultados hablan solos: 40% menos costo por adquisición, 3x más leads calificados y un ROI de 520% en Google Ads.',
            'initials' => 'CM',
            'avatar_bg' => '#1A2332',
            'name' => 'Carlos Mendoza',
            'role' => 'Director de Marketing, TechCorp MX',
        ],
        [
            'quote' => 'Antes teníamos apenas 5 citas nuevas al mes. Ahora gestionamos más de 60 gracias a la estrategia SEO local y los anuncios de Google que RankPro configuró.',
            'initials' => 'AG',
            'avatar_bg' => '#059669',
            'name' => 'Ana Gutiérrez',
            'role' => 'Fundadora, Clínica Estética Lumina',
        ],
    ];

    $starPath = '<path d="M11.525 2.295a.53.53 0 0 1 .95 0l2.31 4.679a2.123 2.123 0 0 0 1.595 1.16l5.166.756a.53.53 0 0 1 .294.904l-3.736 3.638a2.123 2.123 0 0 0-.611 1.878l.882 5.14a.53.53 0 0 1-.771.56l-4.618-2.428a2.122 2.122 0 0 0-1.973 0L6.396 21.01a.53.53 0 0 1-.77-.56l.881-5.139a2.122 2.122 0 0 0-.611-1.879L2.16 9.795a.53.53 0 0 1 .294-.906l5.165-.755a2.122 2.122 0 0 0 1.597-1.16z"></path>';
@endphp

<section class="testimonials" id="testimonios" aria-labelledby="testimonios-titulo">
    <div class="container">
        <div class="section-header">
            <div class="section-badge">
                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">{!! $starPath !!}</svg>
                CASOS DE ÉXITO
            </div>
            <h2 id="testimonios-titulo">Lo que dicen nuestros <span class="text-brand">clientes</span></h2>
        </div>

        <div class="testimonials__grid">
            @foreach ($testimonials as $t)
                <figure class="testimonial-card">
                    <div class="testimonial-card__stars" role="img" aria-label="Calificación: 5 de 5 estrellas">
                        @for ($i = 0; $i < 5; $i++)
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">{!! $starPath !!}</svg>
                        @endfor
                    </div>
                    <blockquote class="testimonial-card__quote">“{{ $t['quote'] }}”</blockquote>
                    <figcaption class="testimonial-card__author">
                        <svg class="testimonial-card__avatar" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 44 44" width="44" height="44" role="img" aria-hidden="true" focusable="false">
                            <circle cx="22" cy="22" r="22" fill="{{ $t['avatar_bg'] }}"></circle>
                            <text x="22" y="22" text-anchor="middle" dominant-baseline="central" fill="#ffffff" font-family="Manrope, sans-serif" font-size="16" font-weight="700">{{ $t['initials'] }}</text>
                        </svg>
                        <div>
                            <div class="testimonial-card__name">{{ $t['name'] }}</div>
                            <div class="testimonial-card__role">{{ $t['role'] }}</div>
                        </div>
                    </figcaption>
                </figure>
            @endforeach
        </div>
    </div>
</section>
