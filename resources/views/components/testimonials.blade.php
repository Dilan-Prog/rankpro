@php
    $testimonials = [
        [
            'quote' => '“RankPro transformó nuestra presencia digital. En 4 meses triplicamos el tráfico orgánico y nuestras ventas online subieron un 180%. El equipo es excepcional.”',
            'avatar' => 'https://images.unsplash.com/photo-1580489944761-15a19d654956?w=80&h=80&fit=crop&auto=format',
            'name' => 'Sofía Ramírez',
            'role' => 'CEO, FashionMX',
        ],
        [
            'quote' => '“Llevamos 2 años con RankPro y los resultados hablan solos: 40% menos costo por adquisición, 3x más leads calificados y un ROI de 520% en Google Ads.”',
            'avatar' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=80&h=80&fit=crop&auto=format',
            'name' => 'Carlos Mendoza',
            'role' => 'Director de Marketing, TechCorp MX',
        ],
        [
            'quote' => '“Antes teníamos apenas 5 citas nuevas al mes. Ahora gestionamos más de 60 gracias a la estrategia SEO local y los anuncios de Google que RankPro configuró.”',
            'avatar' => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=80&h=80&fit=crop&auto=format',
            'name' => 'Ana Gutiérrez',
            'role' => 'Fundadora, Clínica Estética Lumina',
        ],
    ];

    $starPath = '<path d="M11.525 2.295a.53.53 0 0 1 .95 0l2.31 4.679a2.123 2.123 0 0 0 1.595 1.16l5.166.756a.53.53 0 0 1 .294.904l-3.736 3.638a2.123 2.123 0 0 0-.611 1.878l.882 5.14a.53.53 0 0 1-.771.56l-4.618-2.428a2.122 2.122 0 0 0-1.973 0L6.396 21.01a.53.53 0 0 1-.77-.56l.881-5.139a2.122 2.122 0 0 0-.611-1.879L2.16 9.795a.53.53 0 0 1 .294-.906l5.165-.755a2.122 2.122 0 0 0 1.597-1.16z"></path>';
@endphp

<section class="testimonials">
    <div class="container">
        <div class="section-header">
            <div class="section-badge">
                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $starPath !!}</svg>
                CASOS DE ÉXITO
            </div>
            <h2>Lo que dicen nuestros <span class="text-brand">clientes</span></h2>
        </div>

        <div class="testimonials__grid">
            @foreach ($testimonials as $t)
                <div class="testimonial-card">
                    <div class="testimonial-card__stars">
                        @for ($i = 0; $i < 5; $i++)
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $starPath !!}</svg>
                        @endfor
                    </div>
                    <p class="testimonial-card__quote">{{ $t['quote'] }}</p>
                    <div class="testimonial-card__author">
                        <img src="{{ $t['avatar'] }}" alt="{{ $t['name'] }}" class="testimonial-card__avatar">
                        <div>
                            <div class="testimonial-card__name">{{ $t['name'] }}</div>
                            <div class="testimonial-card__role">{{ $t['role'] }}</div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
