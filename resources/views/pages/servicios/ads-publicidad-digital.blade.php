{{--
    /servicios/sem-google-ads/publicidad-digital (SY-03). Metadatos y FAQ en
    App\Support\LandingsAds. Ángulo propio: estrategia de publicidad digital
    (canal, inversión y medición). No repetir aquí la auditoría ni el detalle de
    Google Ads: competiría con la página padre por las mismas búsquedas.
--}}
@extends('layouts.app')

@section('title', $landing['meta_title'])
@section('description', $landing['meta_description'])
@section('canonical', route('servicios.ads', $landing['slug']))

@push('styles')
    @vite(['resources/css/web/pages.css', 'resources/css/web/servicios-conversion.css'])
@endpush

@push('scripts')
    @vite('resources/js/servicios-conversion.js')
@endpush

@php
    $urlPadre = route('servicios.show', $servicio['slug']);
    $urlLanding = route('servicios.ads', $landing['slug']);
    $urlWeb = route('servicios.show', 'desarrollo-web');
    $urlSocial = route('servicios.show', 'social-media');
    $wa = 'https://wa.me/525562230266?text='.rawurlencode('Hola RankPro, quiero revisar mi estrategia de publicidad digital.');
    $correo = 'administracion@rankprosolutions.com.mx';

    $etapas = [
        ['Diagnóstico', 'Auditamos la cuenta o definimos la meta si no existe'],
        ['Estrategia', 'Canal, presupuesto, mensajes y página de destino'],
        ['Lanzamiento', 'Primeras campañas en 7 días desde el kickoff'],
        ['Medición', 'GA4, GTM y conversiones bien definidas'],
        ['Optimización', 'Pujas, términos y anuncios, mes a mes'],
    ];

    $ico = fn ($p, $s = 16) => '<svg xmlns="http://www.w3.org/2000/svg" width="'.$s.'" height="'.$s.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$p.'</svg>';
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
                    'serviceType' => 'Google Ads',
                    'url' => $urlLanding,
                    'provider' => ['@id' => url('/#organization')],
                    'areaServed' => ['@type' => 'Country', 'name' => 'México'],
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
                        <a href="{{ $urlPadre }}" class="cv-btn cv-btn--primary">Auditar mi cuenta gratis {!! $ico($icoFlecha, 17) !!}</a>
                        <a href="{{ $wa }}" class="cv-btn cv-btn--ghost" target="_blank" rel="noopener nofollow">Escribir por WhatsApp</a>
                    </div>
                </div>
            </div>
        </section>

        <section class="cv-section cv-section--tight" aria-labelledby="que-es">
            <div class="container">
                <div class="cv-head" style="margin-bottom:0;">
                    <h2 id="que-es">Qué es la publicidad digital con enfoque en resultados</h2>
                    <p>Es publicar anuncios en plataformas digitales y optimizarlos con datos. La diferencia con «hacer anuncios» está en la estrategia, el seguimiento y el reporte.</p>
                </div>
            </div>
        </section>

        <section class="cv-section cv-section--alt cv-section--tight" aria-labelledby="estrategia">
            <div class="container" style="max-width:56rem;">
                <div class="cv-head">
                    <h2 id="estrategia">Cómo armamos una estrategia de publicidad digital</h2>
                </div>
                <div style="overflow-x:auto;" tabindex="0" role="region" aria-labelledby="estrategia">
                    <table style="width:100%;border-collapse:collapse;font-size:0.9375rem;color:var(--ink);">
                        <thead>
                            <tr style="border-bottom:2px solid var(--border);text-align:left;">
                                <th scope="col" style="padding:0.75rem;">Etapa</th>
                                <th scope="col" style="padding:0.75rem;">Qué hacemos</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($etapas as [$etapa, $detalle])
                                <tr style="border-bottom:1px solid var(--border);">
                                    <th scope="row" style="padding:0.75rem;text-align:left;">{{ $etapa }}</th>
                                    <td style="padding:0.75rem;">{{ $detalle }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="cv-section cv-section--tight" aria-labelledby="canal">
            <div class="container">
                <div class="cv-head">
                    <h2 id="canal">¿Qué canal conviene primero?</h2>
                    <p>Una agencia de publicidad digital no debería empezar por la plataforma, sino por cómo compra tu cliente.</p>
                </div>
                <div class="cv-cards">
                    <div class="cv-card-soft">
                        <h3>Búsqueda en Google Ads</h3>
                        <p>Cuando la gente ya busca lo que ofreces. El detalle está en nuestro servicio de <a href="{{ $urlPadre }}">agencia de Google Ads</a>.</p>
                    </div>
                    <div class="cv-card-soft">
                        <h3>Display y YouTube</h3>
                        <p>Para remarketing y alcance: recordarle a quien ya te visitó, o mostrarte a un público nuevo.</p>
                    </div>
                    <div class="cv-card-soft">
                        <h3>Redes sociales</h3>
                        <p>Cuando necesitas generar interés donde todavía no hay búsquedas. Lo llevamos desde <a href="{{ $urlSocial }}">Social Media</a>.</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="cv-section cv-section--alt cv-section--tight" aria-labelledby="reporte">
            <div class="container">
                <div class="cv-head" style="margin-bottom:0;">
                    <h2 id="reporte">Qué reportamos</h2>
                    <p>Costo por lead, leads calificados, ventas atribuidas y ROAS. No presumimos impresiones. Sin contratos forzosos y con la cuenta a tu nombre.</p>
                </div>
            </div>
        </section>

        <section class="cv-section cv-section--tight" aria-labelledby="pagina">
            <div class="container">
                <div class="cv-head" style="margin-bottom:0;">
                    <h2 id="pagina">La publicidad no sustituye una página que convierta</h2>
                    <p>
                        Si la página es lenta o confusa, pagas por clics que no compran. Conoce nuestro <a href="{{ $urlWeb }}">servicio de desarrollo web</a>@foreach ($guias as $slugGuia => $textoGuia) y la guía <a href="{{ route('blog.show', $slugGuia) }}">{{ $textoGuia }}</a>@endforeach.
                    </p>
                </div>
            </div>
        </section>

        <section class="cv-section cv-section--alt cv-section--tight" aria-labelledby="faq">
            <div class="container">
                <div class="cv-head">
                    <h2 id="faq">Preguntas frecuentes</h2>
                </div>
                <div class="cv-faq">
                    @foreach ($landing['faqs'] as $i => $faq)
                        <div class="cv-faq__item" style="background:#ffffff;">
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
                    <h2>Decide el canal con datos</h2>
                    <p>Revisamos tu cuenta, o tu meta si aún no tienes una, y te decimos por dónde empezar.</p>
                    <div class="cv-actions">
                        <a href="{{ $urlPadre }}" class="cv-btn cv-btn--white">Auditar mi cuenta gratis {!! $ico($icoFlecha, 17) !!}</a>
                        <a href="mailto:{{ $correo }}?subject={{ rawurlencode('Publicidad digital') }}" class="cv-btn cv-btn--outline-light">Escribir por correo</a>
                    </div>
                </div>
            </div>
        </section>
    </main>

    @include('components.footer')
@endsection
