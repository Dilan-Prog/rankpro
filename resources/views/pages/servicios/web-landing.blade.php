{{--
    Landing de desarrollo web por ciudad o sector, hija de
    /servicios/desarrollo-web. El texto vive en App\Support\LandingsWeb; aquí
    solo está el marco. Canónica a sí misma y sin LocalBusiness: no hay oficina
    verificable en la ciudad.
--}}
@extends('layouts.app')

@section('title', $landing['meta_title'])
@section('description', $landing['meta_description'])
@section('canonical', route('servicios.web', $landing['slug']))
@section('body_class', 'rd rd-servicio rd-landing')

@push('styles')
    @vite(['resources/css/web/pages.css', 'resources/css/web/servicios-conversion.css', 'resources/css/web/rediseno/base.css', 'resources/css/web/rediseno/servicios.css'])
@endpush

@push('scripts')
    @vite('resources/js/servicios-conversion.js')
@endpush

@php
    $urlPadre = route('servicios.show', $servicio['slug']);
    $urlLanding = route('servicios.web', $landing['slug']);
    $urlConfigurador = $urlPadre.'#configurador';
    $wa = 'https://wa.me/525562230266';

    $ico = fn ($p, $s = 16) => '<svg xmlns="http://www.w3.org/2000/svg" width="'.$s.'" height="'.$s.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$p.'</svg>';
    $icoCheck = '<circle cx="12" cy="12" r="10"></circle><path d="m9 12 2 2 4-4"></path>';
    $icoCruz = '<circle cx="12" cy="12" r="10"></circle><path d="m15 9-6 6"></path><path d="m9 9 6 6"></path>';
    $icoFlecha = '<path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path>';
    $icoWa = '<path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"></path>';
    $icoTel = '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>';
@endphp

@push('jsonld')
    <script type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'Service',
                    '@id' => $urlLanding.'#service',
                    'name' => $landing['schema_nombre'],
                    'description' => $landing['meta_description'],
                    'serviceType' => 'Desarrollo web',
                    'url' => $urlLanding,
                    'provider' => ['@id' => url('/#organization')],
                    'areaServed' => $landing['area'],
                ],
                [
                    '@type' => 'FAQPage',
                    'mainEntity' => array_map(static fn (array $f): array => [
                        '@type' => 'Question',
                        'name' => $f['p'],
                        'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['r']],
                    ], $landing['faqs']),
                ],
                [
                    '@type' => 'BreadcrumbList',
                    'itemListElement' => [
                        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Inicio', 'item' => url('/')],
                        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Servicios', 'item' => route('servicios.index')],
                        ['@type' => 'ListItem', 'position' => 3, 'name' => $servicio['nombre'], 'item' => $urlPadre],
                        ['@type' => 'ListItem', 'position' => 4, 'name' => $landing['nombre'], 'item' => $urlLanding],
                    ],
                ],
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
    </script>
@endpush

@section('content')
    @include('components.topbar')
    @include('components.navbar')

    <main>
        <section class="cv-hero">
            <div class="container">
                <nav class="breadcrumb" aria-label="Ruta de navegación">
                    <ol>
                        <li><a href="{{ route('home') }}">Inicio</a></li>
                        <li><span class="breadcrumb__sep" aria-hidden="true">/</span></li>
                        <li><a href="{{ route('servicios.index') }}">Servicios</a></li>
                        <li><span class="breadcrumb__sep" aria-hidden="true">/</span></li>
                        <li><a href="{{ $urlPadre }}">{{ $servicio['nombre'] }}</a></li>
                        <li><span class="breadcrumb__sep" aria-hidden="true">/</span></li>
                        <li><span aria-current="page">{{ $landing['nombre'] }}</span></li>
                    </ol>
                </nav>

                <div class="cv-hero__grid">
                <div>
                    <h1 class="cv-title">{{ $landing['h1'] }}</h1>
                    <p class="cv-lead">{{ $landing['intro'] }}</p>
                    <p class="cv-dato">{{ $landing['precio'] }}</p>

                    <div class="cv-actions">
                        <a href="{{ $urlConfigurador }}" class="cv-btn cv-btn--primary">Armar mi cotización {!! $ico($icoFlecha, 17) !!}</a>
                        <a href="{{ route('agendar.mostrar') }}" class="cv-btn cv-btn--ghost">Prefiero agendar una llamada</a>
                    </div>
                </div>
                @include('components.ilustracion-servicio', ['tipo' => 'web'])
                </div>
            </div>
        </section>

        <section class="cv-section cv-section--tight" aria-labelledby="necesidad">
            <div class="container">
                <div class="cv-head">
                    <h2 id="necesidad">{{ $landing['necesidad']['titulo'] }}</h2>
                    <p>{{ $landing['necesidad']['texto'] }}</p>
                </div>
            </div>
        </section>

        <section class="cv-section cv-section--tight" aria-labelledby="sector">
            <div class="container cv-angosto">
                <div class="cv-head">
                    <h2 id="sector">{{ $landing['sector']['titulo'] }}</h2>
                    @foreach ($landing['sector']['puntos'] as $parrafo)
                        <p>{{ $parrafo }}</p>
                    @endforeach
                </div>
            </div>
        </section>

        @isset($landing['tipos'])
        <section class="cv-section cv-section--tight" aria-labelledby="tipos">
            <div class="container cv-angosto">
                <div class="cv-head">
                    <h2 id="tipos">{{ $landing['tipos']['titulo'] }}</h2>
                </div>
                <ul class="cv-checklist">
                    @foreach ($landing['tipos']['puntos'] as $punto)
                        <li>{!! $ico($icoCheck, 18) !!} <span>{{ $punto }}</span></li>
                    @endforeach
                </ul>
            </div>
        </section>

        @endisset

        <section class="cv-section cv-section--alt cv-section--tight" aria-labelledby="publico">
            <div class="container cv-angosto">
                <div class="cv-head">
                    <h2 id="publico">{{ $landing['publico']['titulo'] }}</h2>
                </div>
                <ul class="cv-checklist">
                    @foreach ($landing['publico']['puntos'] as $punto)
                        <li>{!! $ico($icoCheck, 18) !!} <span>{{ $punto }}</span></li>
                    @endforeach
                </ul>
            </div>
        </section>

        <section class="cv-section cv-section--tight" aria-labelledby="proyectos">
            <div class="container">
                <div class="cv-head">
                    <h2 id="proyectos">{{ $landing['proyectos']['titulo'] }}</h2>
                </div>
                <div class="cv-rejilla cv-rejilla--3">
                    @foreach ($landing['proyectos']['puntos'] as $punto)
                        <div class="cv-card-soft">
                            <h3>{{ $punto['t'] }}</h3>
                            <p>{{ $punto['d'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="cv-section cv-section--alt cv-section--tight" aria-labelledby="cuidados">
            <div class="container">
                <div class="cv-head">
                    <h2 id="cuidados">{{ $landing['cuidados']['titulo'] }}</h2>
                </div>
                <div class="cv-rejilla cv-rejilla--3">
                    @foreach ($landing['cuidados']['puntos'] as $punto)
                        <div class="cv-card-soft">
                            <h3>{{ $punto['t'] }}</h3>
                            <p>
                                {{ $punto['d'] }}
                                @isset($punto['servicio'])
                                    <a href="{{ route('servicios.show', $punto['servicio']) }}">{{ $punto['ancla'] }}</a>.
                                @endisset
                                @if (isset($punto['guia'], $guias[$punto['guia']]))
                                    Más en <a href="{{ route('blog.show', $punto['guia']) }}">{{ $guias[$punto['guia']] }}</a>.
                                @endif
                            </p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="cv-section cv-section--tight" aria-labelledby="trabajo">
            <div class="container">
                <div class="cv-head">
                    <h2 id="trabajo">{{ $landing['trabajo']['titulo'] }}</h2>
                    <p>{{ $landing['trabajo']['texto'] }} <a href="{{ $urlPadre }}">{{ $landing['trabajo']['enlace'] }}</a>.</p>
                </div>
            </div>
        </section>

        <section class="cv-section cv-section--alt cv-section--tight" aria-labelledby="etapas">
            <div class="container">
                <div class="cv-head">
                    <h2 id="etapas">{{ $landing['etapas']['titulo'] }}</h2>
                </div>
                <ol class="cv-rejilla cv-rejilla--3">
                    @foreach ($landing['etapas']['puntos'] as $punto)
                        <li class="cv-card-soft">
                            <h3>{{ $punto['t'] }}</h3>
                            <p>{{ $punto['d'] }}</p>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>

        <section class="cv-section cv-section--tight" aria-labelledby="errores">
            <div class="container cv-angosto">
                <div class="cv-head">
                    <h2 id="errores">{{ $landing['errores']['titulo'] }}</h2>
                </div>
                <ul class="cv-checklist">
                    @foreach ($landing['errores']['puntos'] as $punto)
                        <li>{!! $ico($icoCruz, 18) !!} <span>{{ $punto }}</span></li>
                    @endforeach
                </ul>
            </div>
        </section>

        <section class="cv-section cv-section--alt cv-section--tight" aria-labelledby="casos">
            <div class="container">
                <div class="cv-head">
                    <h2 id="casos">{{ $landing['casos']['titulo'] }}</h2>
                    <p>{{ $landing['casos']['texto'] }}</p>
                </div>
                <div class="cv-rejilla cv-rejilla--3">
                    @foreach ($landing['casos']['puntos'] as $caso)
                        <div class="cv-card-soft">
                            <h3><a href="{{ route('casos.show', $caso['slug']) }}">{{ $caso['t'] }}</a></h3>
                            <p>{{ $caso['d'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="cv-section cv-section--tight" aria-labelledby="faq">
            <div class="container">
                <div class="cv-head">
                    <h2 id="faq">Preguntas frecuentes</h2>
                </div>
                <div class="cv-faq">
                    @foreach ($landing['faqs'] as $i => $faq)
                        <div class="cv-faq__item">
                            <button type="button" class="cv-faq__trigger" aria-expanded="{{ $i === 0 ? 'true' : 'false' }}" aria-controls="faq-{{ $i }}">
                                <span>{{ $faq['p'] }}</span>
                                {!! $ico('<path d="m6 9 6 6 6-6"></path>', 18) !!}
                            </button>
                            <div class="cv-faq__panel" id="faq-{{ $i }}" @if($i !== 0) hidden @endif>{{ $faq['r'] }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="cv-section cv-section--brand">
            <div class="container">
                <div class="cv-final">
                    <h2>{{ $landing['cierre']['titulo'] }}</h2>
                    <p>{{ $landing['cierre']['texto'] }}</p>
                    <div class="cv-actions">
                        <a href="{{ $urlConfigurador }}" class="cv-btn cv-btn--white">Armar mi cotización {!! $ico($icoFlecha, 17) !!}</a>
                        <a href="{{ route('agendar.mostrar') }}" class="cv-btn cv-btn--outline-light">Prefiero agendar una llamada</a>
                    </div>
                </div>
            </div>
        </section>

        <section class="cv-section cv-section--alt cv-section--tight" aria-labelledby="revisa">
            <div class="container">
                <div class="cv-head">
                    <h2 id="revisa">Revisa también</h2>
                </div>
                <ul class="cv-pildoras">
                    <li><a class="cv-chip" href="{{ $urlPadre }}">Servicio de desarrollo web</a></li>
                    @foreach (['cuanto-cuesta-una-pagina-web', 'como-crear-una-pagina-web', ...($landing['guias'] ?? [])] as $slugGuia)
                        @isset($guias[$slugGuia])
                            <li><a class="cv-chip" href="{{ route('blog.show', $slugGuia) }}">{{ $guias[$slugGuia] }}</a></li>
                        @endisset
                    @endforeach
                    <li><a class="cv-chip" href="{{ route('servicios.show', 'pagespeed-core-web-vitals') }}">Velocidad web</a></li>
                    <li><a class="cv-chip" href="{{ route('servicios.show', 'seo-organico') }}">SEO orgánico</a></li>
                    @isset($landing['seo_ciudad'])
                        <li><a class="cv-chip" href="{{ route('servicios.ciudad', $landing['seo_ciudad']) }}">SEO en {{ $landing['nombre'] }}</a></li>
                    @endisset
                    @foreach ($otrasLandings as $otra)
                        <li><a class="cv-chip" href="{{ $otra['url'] }}">{{ $otra['label'] }}</a></li>
                    @endforeach
                </ul>
            </div>
        </section>
    </main>

    <div class="cv-bar">
        <a href="tel:+525562230266" class="cv-bar__icon" aria-label="Llamar a RankPro">{!! $ico($icoTel, 18) !!}</a>
        <a href="{{ $wa }}" class="cv-bar__icon cv-bar__icon--wa" aria-label="Escribir por WhatsApp" target="_blank" rel="noopener nofollow">{!! $ico($icoWa, 18) !!}</a>
        <a href="{{ $urlConfigurador }}" class="cv-btn cv-btn--primary cv-bar__cta">Cotizar ahora {!! $ico($icoFlecha, 16) !!}</a>
    </div>

    @include('components.footer')
@endsection
