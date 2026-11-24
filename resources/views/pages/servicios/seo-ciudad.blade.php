{{--
    Landing de SEO por ciudad, hija de /servicios/seo-organico. El texto propio
    de cada ciudad vive en App\Support\CiudadesSeo; aquí solo está el marco.

    Canónica a sí misma, nunca a la página padre: si apuntara allí, Google la
    trataría como duplicado. El schema es Service con areaServed de ciudad, sin
    LocalBusiness: no hay oficina verificable en la ciudad.
--}}
@extends('layouts.app')

@section('title', $ciudad['meta_title'])
@section('description', $ciudad['meta_description'])
@section('canonical', route('servicios.ciudad', $ciudad['slug']))

@push('styles')
    @vite(['resources/css/web/pages.css', 'resources/css/web/servicios-conversion.css'])
@endpush

@push('scripts')
    @vite('resources/js/servicios-conversion.js')
@endpush

@php
    $urlPadre = route('servicios.show', $servicio['slug']);
    $urlCiudad = route('servicios.ciudad', $ciudad['slug']);
    $wa = 'https://wa.me/527341036410';
    $waAuditoria = $wa.'?text='.rawurlencode('Hola RankPro, quiero una auditoría SEO de mi sitio en '.$ciudad['nombre'].'.');
    $ctaAuditoria = 'Pedir mi auditoría SEO en '.$ciudad['nombre'];

    $ico = fn ($p, $s = 16) => '<svg xmlns="http://www.w3.org/2000/svg" width="'.$s.'" height="'.$s.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$p.'</svg>';
    $icoFlecha = '<path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path>';
    $icoPin = '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path><circle cx="12" cy="10" r="3"></circle>';
    $icoWa = '<path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"></path>';
    $icoTel = '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>';

    $sevIcono = [
        'critico' => '<circle cx="12" cy="12" r="10"></circle><path d="m15 9-6 6"></path><path d="m9 9 6 6"></path>',
        'medio' => '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"></path><path d="M12 9v4"></path><path d="M12 17h.01"></path>',
        'ok' => '<circle cx="12" cy="12" r="10"></circle><path d="m9 12 2 2 4-4"></path>',
    ];
    $sevTexto = ['critico' => 'Alto impacto', 'medio' => 'Medio', 'ok' => 'Base'];
@endphp

@push('jsonld')
    <script type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'Service',
                    '@id' => $urlCiudad.'#service',
                    'name' => 'Agencia SEO en '.$ciudad['nombre'],
                    'description' => $ciudad['meta_description'],
                    'serviceType' => 'SEO orgánico',
                    'url' => $urlCiudad,
                    'provider' => ['@id' => url('/#organization')],
                    'areaServed' => ['@type' => 'City', 'name' => $ciudad['nombre_largo']],
                ],
                [
                    '@type' => 'FAQPage',
                    'mainEntity' => collect($ciudad['faqs'])->map(fn ($faq) => [
                        '@type' => 'Question',
                        'name' => $faq['p'],
                        'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq['r']],
                    ])->all(),
                ],
                [
                    '@type' => 'BreadcrumbList',
                    'itemListElement' => [
                        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Inicio', 'item' => url('/')],
                        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Servicios', 'item' => route('servicios.index')],
                        ['@type' => 'ListItem', 'position' => 3, 'name' => $servicio['nombre'], 'item' => $urlPadre],
                        ['@type' => 'ListItem', 'position' => 4, 'name' => $ciudad['nombre'], 'item' => $urlCiudad],
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
        <section class="cv-section--dark">
            <div class="container">
                <nav class="breadcrumb breadcrumb--light" aria-label="Ruta de navegación">
                    <ol>
                        <li><a href="{{ route('home') }}">Inicio</a></li>
                        <li><span class="breadcrumb__sep" aria-hidden="true">/</span></li>
                        <li><a href="{{ route('servicios.index') }}">Servicios</a></li>
                        <li><span class="breadcrumb__sep" aria-hidden="true">/</span></li>
                        <li><a href="{{ $urlPadre }}">{{ $servicio['nombre'] }}</a></li>
                        <li><span class="breadcrumb__sep" aria-hidden="true">/</span></li>
                        <li><span aria-current="page">{{ $ciudad['nombre'] }}</span></li>
                    </ol>
                </nav>

                <div style="max-width:48rem;">
                    <span class="cv-badge cv-badge--outline">{!! $ico($icoPin, 12) !!} Agencia SEO en {{ $ciudad['nombre'] }}</span>

                    <h1 class="cv-title cv-title--light">{{ $ciudad['h1'] }}</h1>

                    <p class="cv-lead cv-lead--light">{{ $ciudad['intro'] }}</p>

                    <div class="cv-actions">
                        <a href="{{ $waAuditoria }}" class="cv-btn cv-btn--primary" target="_blank" rel="noopener nofollow">{{ $ctaAuditoria }} {!! $ico($icoFlecha, 17) !!}</a>
                        <a href="{{ route('agendar.mostrar') }}" class="cv-btn cv-btn--outline-light">Prefiero agendar una llamada</a>
                    </div>

                    <p style="margin-top:1.5rem;font-size:0.8125rem;color:rgba(255,255,255,0.45);">Sin llamadas de venta para empezar · Solo pedimos acceso de lectura · El informe es tuyo</p>
                </div>
            </div>
        </section>

        <section class="cv-section" aria-labelledby="contexto">
            <div class="container">
                <div class="cv-head">
                    <h2 id="contexto">{{ $ciudad['contexto']['titulo'] }}</h2>
                    <p>{{ $ciudad['contexto']['texto'] }}</p>
                </div>
                {{-- Con 4 puntos la rejilla de 3 dejaría uno suelto. --}}
                <div class="cv-cards" @if (count($ciudad['contexto']['puntos']) === 4) style="grid-template-columns:repeat(auto-fit,minmax(min(100%,26rem),1fr));" @endif>
                    @foreach ($ciudad['contexto']['puntos'] as $punto)
                        <div class="cv-card-soft">
                            <h3>{{ $punto['t'] }}</h3>
                            <p>{{ $punto['d'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="cv-section cv-section--alt" aria-labelledby="servicio">
            <div class="container">
                <div class="cv-head">
                    <h2 id="servicio">Servicio de SEO para empresas en {{ $ciudad['nombre'] }}</h2>
                </div>
                {{-- Son 4 frentes: rejilla propia para que no quede uno suelto en la de 3. --}}
                <div style="display:grid;gap:1.5rem;grid-template-columns:repeat(auto-fit,minmax(min(100%,26rem),1fr));">
                    @foreach ($ciudad['frentes'] as $frente)
                        <div class="cv-card-soft" style="background:#ffffff;">
                            <h3>{{ $frente['t'] }}</h3>
                            <p>{{ $frente['d'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="cv-section cv-section--tight" aria-labelledby="proceso">
            <div class="container">
                <div class="cv-head">
                    <h2 id="proceso">Cómo trabajamos con empresas de {{ $ciudad['nombre'] }}</h2>
                    <p>{{ $ciudad['modalidad'] }}</p>
                </div>
                <div class="cv-steps">
                    @foreach ($ciudad['pasos'] as $i => $paso)
                        <div class="cv-step">
                            <div class="cv-step__n">0{{ $i + 1 }}</div>
                            <div class="cv-step__t">{{ $paso['t'] }}</div>
                            <p>{{ $paso['d'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="cv-section cv-section--alt" aria-labelledby="revisiones">
            <div class="container">
                <div class="cv-head">
                    <h2 id="revisiones">Qué revisamos en tu sitio</h2>
                </div>
                <ul class="cv-findings">
                    @foreach ($ciudad['revisiones'] as $r)
                        <li class="cv-finding cv-finding--{{ $r['sev'] }}">
                            <span class="cv-finding__icon">{!! $ico($sevIcono[$r['sev']], 18) !!}</span>
                            <div>
                                <div class="cv-finding__title">{{ $r['t'] }}</div>
                                <span class="cv-finding__sev">{{ $sevTexto[$r['sev']] }}</span>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>

        {{-- Los casos publicados no son de la ciudad: el texto lo dice para que
             nadie los lea como clientes locales. --}}
        @include('pages.servicios._casos-exito', [
            'casosTitulo' => $ciudad['casos_titulo'] ?? 'Sectores con los que trabajamos',
            'casosTexto' => 'Casos reales de otras partes de México, no de '.$ciudad['nombre'].'. Cada cifra lleva su fuente y solo nombramos a quien lo autorizó.',
        ])

        <section class="cv-section cv-section--tight" aria-labelledby="faq">
            <div class="container">
                <div class="cv-head">
                    <h2 id="faq">Preguntas frecuentes sobre SEO en {{ $ciudad['nombre'] }}</h2>
                </div>
                <div class="cv-faq">
                    @foreach ($ciudad['faqs'] as $i => $faq)
                        <div class="cv-faq__item">
                            <button type="button" class="cv-faq__trigger" aria-expanded="{{ $i === 0 ? 'true' : 'false' }}" aria-controls="faq-{{ $i }}">
                                <span>{{ $faq['p'] }}</span>
                                {!! $ico('<path d="m6 9 6 6 6-6"></path>', 18) !!}
                            </button>
                            <div class="cv-faq__panel" id="faq-{{ $i }}" @if($i !== 0) hidden @endif>
                                {{ $faq['r'] }}
                                @if (isset($faq['guia'], $guias[$faq['guia']]))
                                    Más detalle en <a href="{{ route('blog.show', $faq['guia']) }}">{{ $guias[$faq['guia']] }}</a>.
                                @endif
                                @if ($faq['padre'] ?? false)
                                    Si estás en otra ciudad, revisa
                                    @foreach ($otrasCiudades as $otra)
                                        <a href="{{ $otra['url'] }}">{{ $otra['label'] }}</a>,
                                    @endforeach
                                    @if ($otrasCiudades !== []) o @endif
                                    <a href="{{ $urlPadre }}">la página general del servicio</a>.
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
                    <h2>Empieza por saber dónde estás parado</h2>
                    <p>{{ $ciudad['cierre'] }}</p>
                    <div class="cv-actions">
                        <a href="{{ $waAuditoria }}" class="cv-btn cv-btn--white" target="_blank" rel="noopener nofollow">{{ $ctaAuditoria }} {!! $ico($icoFlecha, 17) !!}</a>
                        <a href="{{ route('agendar.mostrar') }}" class="cv-btn cv-btn--outline-light">Prefiero agendar una llamada</a>
                    </div>
                </div>
            </div>
        </section>

        <section class="cv-section cv-section--alt cv-section--tight" aria-labelledby="otros">
            <div class="container">
                <div class="cv-head cv-head--left" style="margin-bottom:2rem;">
                    <h2 id="otros" style="font-size:1.5rem;">{{ $otrasCiudades === [] ? 'Otros servicios' : 'Otras ciudades y servicios' }}</h2>
                </div>
                <ul style="display:flex;flex-wrap:wrap;gap:0.75rem;list-style:none;padding:0;">
                    @foreach ($otrasCiudades as $otra)
                        <li><a class="cv-chip" style="text-decoration:none;" href="{{ $otra['url'] }}">{{ $otra['label'] }}</a></li>
                    @endforeach
                    <li><a class="cv-chip" style="text-decoration:none;" href="{{ $urlPadre }}">SEO orgánico en todo México</a></li>
                    @foreach ($otrosServicios as $otro)
                        <li><a class="cv-chip" style="text-decoration:none;" href="{{ route('servicios.show', $otro['slug']) }}">{{ $otro['nombre'] }}</a></li>
                    @endforeach
                </ul>
            </div>
        </section>
    </main>

    <div class="cv-bar">
        <a href="tel:+527341036410" class="cv-bar__icon" aria-label="Llamar a RankPro">{!! $ico($icoTel, 18) !!}</a>
        <a href="{{ $wa }}" class="cv-bar__icon cv-bar__icon--wa" aria-label="Escribir por WhatsApp" target="_blank" rel="noopener nofollow">{!! $ico($icoWa, 18) !!}</a>
        <a href="{{ $waAuditoria }}" class="cv-btn cv-btn--primary cv-bar__cta" target="_blank" rel="noopener nofollow">Auditoría SEO {!! $ico($icoFlecha, 16) !!}</a>
    </div>

    @include('components.footer')
@endsection
