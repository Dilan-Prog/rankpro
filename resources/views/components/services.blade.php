@php
    // Los servicios se derivan del catalogo (App\Support\Servicios), la misma fuente
    // que alimentan el megamenu, el footer y el sitemap. Estaban duplicados a mano
    // aqui y la lista se desincronizo: faltaba "Automatizacion de Procesos", que si
    // tiene landing propia. Se usa todos() en vez de navegacion() porque esta seccion
    // necesita ademas 'tags', que navegacion() no expone.
    $services = array_values(array_map(static fn (array $s): array => [
        'icon' => $s['icon'],
        'gradient' => $s['gradient'],
        'slug' => $s['slug'],
        'title' => $s['nombre'],
        'desc' => $s['resumen'],
        'tags' => $s['tags'],
    ], \App\Support\Servicios::todos()));
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
                    <a href="{{ route('servicios.show', $service['slug']) }}"
                       class="service-card__link"
                       style="text-decoration:none"
                       aria-label="Ver más sobre {{ $service['title'] }}">
                        Ver más
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"></path></svg>
                    </a>
                </div>
            @endforeach
        </div>
    </div>
</section>
