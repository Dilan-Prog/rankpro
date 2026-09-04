@extends('layouts.app')

@section('title', 'Servicios de Marketing Digital en México | RankPro')
@section('description', 'Google Ads, SEO orgánico, desarrollo web, optimización de Core Web Vitals, analítica digital y redes sociales para empresas en México.')
@section('canonical', route('servicios.index'))

@push('styles')
    @vite('resources/css/web/pages.css')
@endpush

@push('jsonld')
    <script type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'BreadcrumbList',
                    'itemListElement' => [
                        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Inicio', 'item' => url('/')],
                        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Servicios', 'item' => route('servicios.index')],
                    ],
                ],
                [
                    '@type' => 'ItemList',
                    'name' => 'Servicios de marketing digital de RankPro',
                    'itemListElement' => collect($servicios)->values()->map(fn ($s, $i) => [
                        '@type' => 'ListItem',
                        'position' => $i + 1,
                        'name' => $s['nombre'],
                        'url' => route('servicios.show', $s['slug']),
                    ])->all(),
                ],
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
    </script>
@endpush

@section('content')
    @include('components.topbar')
    @include('components.navbar')

    <main>
        <section class="page-hero">
            <div class="container">
                <nav class="breadcrumb" aria-label="Ruta de navegación">
                    <ol>
                        <li><a href="{{ url('/') }}">Inicio</a></li>
                        <li aria-hidden="true" class="breadcrumb__sep">/</li>
                        <li><span aria-current="page">Servicios</span></li>
                    </ol>
                </nav>

                <div class="page-hero__inner">
                    <h1>Servicios de Marketing Digital</h1>
                    <p class="page-hero__lead">
                        Trabajamos seis frentes que se refuerzan entre sí: captación pagada, posicionamiento
                        orgánico, la plataforma donde aterriza el tráfico, su velocidad, la medición que
                        permite decidir y la construcción de marca en redes. No todos aplican a todos los
                        negocios, y parte de nuestro trabajo es decirte cuáles sí.
                    </p>
                    <div class="page-hero__actions">
                        <a href="{{ route('contacto') }}" class="btn btn-primary">Solicitar diagnóstico</a>
                        <a href="https://wa.me/527341036410" class="btn btn-outline" rel="noopener nofollow">Escribir por WhatsApp</a>
                    </div>
                </div>
            </div>
        </section>

        <section class="services" aria-labelledby="lista-servicios">
            <div class="container">
                <h2 id="lista-servicios" class="sr-only" style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap;">Listado de servicios</h2>

                <div class="services__grid">
                    @foreach ($servicios as $servicio)
                        <a class="service-card" href="{{ route('servicios.show', $servicio['slug']) }}" style="text-decoration: none; display: block;">
                            <div class="service-card__icon {{ $servicio['gradient'] }}">
                                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $servicio['icon'] !!}</svg>
                            </div>
                            <h3 class="service-card__title">{{ $servicio['nombre'] }}</h3>
                            <p class="service-card__desc">{{ $servicio['resumen'] }}</p>
                            <div class="service-card__tags">
                                @foreach ($servicio['tags'] as $tag)
                                    <span class="service-card__tag">{{ $tag }}</span>
                                @endforeach
                            </div>
                            <div class="service-card__link">
                                Ver el servicio
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"></path></svg>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="page-section page-section--alt">
            <div class="container">
                <div class="page-section__inner prose">
                    <h2>Cómo elegir por dónde empezar</h2>
                    <p>
                        La pregunta más frecuente que recibimos no es cuánto cuesta cada servicio, sino cuál
                        conviene primero. La respuesta depende de tu punto de partida, y suele seguir una
                        lógica sencilla.
                    </p>
                    <p>
                        Si necesitas clientes en el corto plazo y ya existe demanda buscando lo que vendes,
                        <a href="{{ route('servicios.show', 'sem-google-ads') }}">Google Ads</a> es el camino
                        más directo. Si el objetivo es reducir la dependencia de la publicidad y construir un
                        activo a mediano plazo, la inversión va hacia
                        <a href="{{ route('servicios.show', 'seo-organico') }}">SEO orgánico</a>. Cuando el
                        sitio actual es el obstáculo —lento, difícil de editar o sin páginas por servicio—,
                        lo primero es <a href="{{ route('servicios.show', 'desarrollo-web') }}">desarrollo web</a>
                        o una <a href="{{ route('servicios.show', 'pagespeed-core-web-vitals') }}">optimización de velocidad</a>.
                    </p>
                    <p>
                        Hay un servicio que casi siempre va antes que los demás:
                        <a href="{{ route('servicios.show', 'analytics-data') }}">analítica y medición</a>.
                        Sin conversiones bien configuradas es imposible saber si una campaña funciona o si el
                        contenido está trayendo clientes, y todo lo demás se vuelve una discusión de opiniones.
                        Por eso lo revisamos al inicio de cualquier proyecto, incluso cuando nos contrataste
                        para otra cosa.
                    </p>
                    <p>
                        Y si tu categoría depende del descubrimiento y de la marca más que de la búsqueda
                        deliberada, <a href="{{ route('servicios.show', 'social-media') }}">redes sociales</a>
                        entra al frente de la estrategia. Si no estás seguro de en qué caso estás, esa es
                        exactamente la conversación que tenemos en el diagnóstico inicial.
                    </p>
                </div>
            </div>
        </section>

        <section class="page-section">
            <div class="container">
                <div class="page-cta">
                    <h2>Empecemos por entender tu caso</h2>
                    <p>
                        Cuéntanos qué vendes, a quién y qué has intentado hasta ahora. Con eso podemos decirte
                        con honestidad qué servicio tiene sentido y cuál todavía no.
                    </p>
                    <div class="page-cta__actions">
                        <a href="{{ route('contacto') }}" class="btn btn-light">Ir a contacto</a>
                        <a href="https://wa.me/527341036410" class="btn btn-ghost" rel="noopener nofollow">WhatsApp +52 734 103 6410</a>
                    </div>
                </div>
            </div>
        </section>
    </main>

    @include('components.footer')
@endsection
