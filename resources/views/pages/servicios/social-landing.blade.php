{{--
    Landing de redes sociales por ciudad, hija de /servicios/social-media. El
    texto vive en App\Support\LandingsSocial; aquí solo está el marco. Canónica a
    sí misma y sin LocalBusiness: no hay oficina verificable en la ciudad.
--}}
@extends('layouts.app')

@section('title', $landing['meta_title'])
@section('description', $landing['meta_description'])
@section('canonical', route('servicios.social', $landing['slug']))

@push('styles')
    @vite(['resources/css/web/pages.css', 'resources/css/web/servicios-conversion.css'])
@endpush

@push('scripts')
    @vite('resources/js/servicios-conversion.js')
@endpush

@php
    $urlPadre = route('servicios.show', $servicio['slug']);
    $urlLanding = route('servicios.social', $landing['slug']);
    $wa = 'https://wa.me/525562230266?text='.rawurlencode('Hola RankPro, quiero cotizar redes sociales en '.$landing['nombre'].'.');
    $rejilla = 'display:grid;gap:1.5rem;grid-template-columns:repeat(auto-fit,minmax(min(100%,18rem),1fr));';

    $ico = fn ($p, $s = 16) => '<svg xmlns="http://www.w3.org/2000/svg" width="'.$s.'" height="'.$s.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$p.'</svg>';
    $icoCheck = '<circle cx="12" cy="12" r="10"></circle><path d="m9 12 2 2 4-4"></path>';
    $icoFlecha = '<path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path>';
@endphp

@push('jsonld')
    <script type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'Service',
                    '@id' => $urlLanding.'#service',
                    'name' => $landing['h1'],
                    'description' => $landing['meta_description'],
                    'serviceType' => 'Gestión de redes sociales',
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

                <div style="max-width:48rem;">
                    <h1 class="cv-title">{{ $landing['h1'] }}</h1>
                    <p class="cv-lead" style="max-width:44rem;">{{ $landing['intro'] }}</p>
                    <div class="cv-actions">
                        <a href="{{ $wa }}" class="cv-btn cv-btn--primary" target="_blank" rel="noopener nofollow">Cotizar mi estrategia de redes {!! $ico($icoFlecha, 17) !!}</a>
                        <a href="{{ route('agendar.mostrar') }}" class="cv-btn cv-btn--ghost">Prefiero agendar una llamada</a>
                    </div>
                </div>
            </div>
        </section>

        @foreach ($landing['secciones'] as $i => $seccion)
            <section class="cv-section cv-section--tight {{ $i % 2 ? 'cv-section--alt' : '' }}" aria-labelledby="seccion-{{ $i }}">
                <div class="container" @empty($seccion['puntos']) style="max-width:48rem;" @endempty>
                    <div class="cv-head" style="margin-bottom:1.5rem;">
                        <h2 id="seccion-{{ $i }}">{{ $seccion['titulo'] }}</h2>
                    </div>
                    @foreach ($seccion['parrafos'] ?? [] as $parrafo)
                        <p style="margin-top:1rem;line-height:1.7;color:var(--muted);">{{ $parrafo }}</p>
                    @endforeach
                    @isset($seccion['lista'])
                        <ul class="cv-checklist">
                            @foreach ($seccion['lista'] as $punto)
                                <li>{!! $ico($icoCheck, 18) !!} <span>{{ $punto }}</span></li>
                            @endforeach
                        </ul>
                    @endisset
                    @isset($seccion['puntos'])
                        <div style="{{ $rejilla }}">
                            @foreach ($seccion['puntos'] as $punto)
                                <div class="cv-card-soft" style="background:#ffffff;">
                                    <h3>{{ $punto['t'] }}</h3>
                                    <p>
                                        {{ $punto['d'] }}
                                        @isset($punto['servicio'])
                                            <a href="{{ route('servicios.show', $punto['servicio']) }}">{{ $punto['ancla'] }}</a>.
                                        @endisset
                                    </p>
                                </div>
                            @endforeach
                        </div>
                    @endisset
                </div>
            </section>
        @endforeach

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
                            <div class="cv-faq__panel" id="faq-{{ $i }}" @if($i !== 0) hidden @endif>
                                <p>{{ $faq['r'] }}</p>
                                @if (isset($faq['guia'], $guias[$faq['guia']]))
                                    <p>Más detalle en <a href="{{ route('blog.show', $faq['guia']) }}">{{ $guias[$faq['guia']] }}</a>.</p>
                                @endif
                            </div>
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
                        <a href="{{ $wa }}" class="cv-btn cv-btn--white" target="_blank" rel="noopener nofollow">Cotizar mi estrategia de redes {!! $ico($icoFlecha, 17) !!}</a>
                        <a href="{{ route('servicios.show', 'sem-google-ads') }}" class="cv-btn cv-btn--outline-light">Ver servicio de SEM</a>
                    </div>
                </div>
            </div>
        </section>

        <section class="cv-section cv-section--alt cv-section--tight" aria-labelledby="mas">
            <div class="container">
                <div class="cv-head cv-head--left" style="margin-bottom:2rem;">
                    <h2 id="mas" style="font-size:1.5rem;">Más información</h2>
                </div>
                <ul style="display:flex;flex-wrap:wrap;gap:0.75rem;list-style:none;padding:0;">
                    <li><a class="cv-chip" style="text-decoration:none;" href="{{ $urlPadre }}">Servicio de gestión de redes sociales</a></li>
                    @foreach ($landing['guias'] ?? ['cuanto-cuesta-manejo-de-redes-sociales', 'community-manager', 'social-media-ads'] as $slugGuia)
                        @isset($guias[$slugGuia])
                            <li><a class="cv-chip" style="text-decoration:none;" href="{{ route('blog.show', $slugGuia) }}">{{ $guias[$slugGuia] }}</a></li>
                        @endisset
                    @endforeach
                    @foreach ($afines as $otra)
                        <li><a class="cv-chip" style="text-decoration:none;" href="{{ $otra['url'] }}">{{ $otra['label'] }}</a></li>
                    @endforeach
                </ul>
            </div>
        </section>
    </main>

    @include('components.footer')
@endsection
