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

    $objetivos = [
        ['Captar demanda activa', 'Búsqueda en Google Ads', 'Cuando tu cliente escribe en Google el problema o el producto y quiere resolverlo pronto'],
        ['Dar a conocer la marca', 'Display, YouTube o redes sociales', 'Cuando casi nadie te busca por nombre o lanzas algo que aún no se conoce'],
        ['Volver a contactar visitantes', 'Remarketing en Display y YouTube', 'Cuando recibes visitas que comparan y tardan en decidir'],
        ['Generar mensajes o leads', 'Pauta en Meta', 'Cuando la venta se cierra conversando y tienes quien conteste'],
        ['Llegar a quien decide en una empresa', 'Pauta en LinkedIn', 'Cuando vendes a otras empresas y conoces el puesto o la industria'],
    ];

    $metricas = [
        ['Costo por lead o por venta', 'Cuánto cuesta cada oportunidad, por canal y por campaña.'],
        ['Leads calificados', 'Cuántos contactos eran de verdad clientes posibles, según tu equipo.'],
        ['Ventas atribuidas y ROAS', 'Qué parte de lo vendido se puede ligar a los anuncios, con los límites de cada plataforma.'],
        ['Calidad del tráfico', 'Qué hicieron los visitantes al llegar: si leyeron, llamaron, escribieron o se fueron.'],
    ];

    $errores = [
        ['Pautar sin un objetivo', 'Pedir ventas, seguidores y reconocimiento en la misma campaña deja a la plataforma sin saber qué buscar.'],
        ['Invertir sin seguimiento', 'Si nadie registra la llamada o el mensaje, no hay forma de saber qué anuncio los produjo.'],
        ['Cambiar todo cada día', 'Los anuncios necesitan tiempo y datos; mover presupuesto, textos y público a la vez borra el aprendizaje.'],
        ['Repartir poco en muchos canales', 'Un presupuesto dividido en cinco plataformas rara vez junta información suficiente en alguna.'],
        ['Tardar en contestar', 'Un contacto que espera hasta mañana suele haber hablado ya con otro proveedor.'],
        ['Juzgar por los clics', 'Un anuncio barato que atrae a quien no compra sale más caro que uno con pocos clics útiles.'],
    ];

    $celda = 'padding:0.75rem;vertical-align:top;';

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
            <div class="container" style="max-width:60rem;">
                <div class="cv-head">
                    <h2 id="canal">Qué canal usar según tu objetivo</h2>
                    <p>Una agencia de publicidad digital no debería empezar por la plataforma, sino por cómo compra tu cliente.</p>
                </div>
                <div style="overflow-x:auto;" tabindex="0" role="region" aria-labelledby="canal">
                    <table style="width:100%;min-width:36rem;border-collapse:collapse;font-size:0.9375rem;color:var(--ink);">
                        <thead>
                            <tr style="border-bottom:2px solid var(--border);text-align:left;">
                                <th scope="col" style="{{ $celda }}">Objetivo</th>
                                <th scope="col" style="{{ $celda }}">Canal recomendado</th>
                                <th scope="col" style="{{ $celda }}">Cuándo conviene</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($objetivos as [$objetivo, $canal, $cuando])
                                <tr style="border-bottom:1px solid var(--border);">
                                    <th scope="row" style="{{ $celda }}text-align:left;">{{ $objetivo }}</th>
                                    <td style="{{ $celda }}">{{ $canal }}</td>
                                    <td style="{{ $celda }}">{{ $cuando }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <h3 style="margin-top:2.5rem;font-size:1.125rem;font-weight:700;color:var(--ink);">Cómo decidimos el canal</h3>
                <p style="margin-top:0.75rem;line-height:1.7;color:var(--muted);">Miramos cuatro cosas. El tipo de negocio: no se anuncia igual un despacho que una tienda. La etapa de compra: si tu cliente apenas descubre el problema o ya compara precios. El presupuesto: si alcanza para reunir datos en un canal antes de abrir otro. Y tu capacidad de atender: de poco sirve duplicar los mensajes si el equipo ya no puede con los que recibe.</p>
            </div>
        </section>

        <section class="cv-section cv-section--alt cv-section--tight" aria-labelledby="relacion">
            <div class="container">
                <div class="cv-head">
                    <h2 id="relacion">Cómo se relaciona con Google Ads y redes sociales</h2>
                </div>
                <div style="display:grid;gap:1.5rem;grid-template-columns:repeat(auto-fit,minmax(min(100%,22rem),1fr));">
                    <div class="cv-card-soft" style="background:#ffffff;">
                        <h3>Google Ads, para la demanda que ya existe</h3>
                        <p>Suele ser la primera pieza, porque permite medir pronto si hay interés. En esta página decidimos qué papel juega dentro del plan; cómo se arma y se optimiza una cuenta está en <a href="{{ $urlPadre }}">agencia de Google Ads</a>.</p>
                    </div>
                    <div class="cv-card-soft" style="background:#ffffff;">
                        <h3>Redes sociales, para crear interés</h3>
                        <p>La pauta en redes pone tu oferta frente a personas que todavía no la buscaban y rinde más con contenido constante detrás. La parte de contenido, comunidad y campañas pagadas vive en <a href="{{ $urlSocial }}">Social Media</a>.</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="cv-section cv-section--tight" aria-labelledby="reporte">
            <div class="container" style="max-width:56rem;">
                <div class="cv-head" style="margin-bottom:1.5rem;">
                    <h2 id="reporte">Qué reportamos</h2>
                    <p>No presumimos impresiones. Sin contratos forzosos y con la cuenta a tu nombre, revisamos contigo estas métricas:</p>
                </div>
                <ul style="display:grid;gap:0.75rem;padding-left:1.25rem;line-height:1.7;color:var(--muted);">
                    @foreach ($metricas as [$metrica, $detalle])
                        <li><strong style="color:var(--ink);">{{ $metrica }}.</strong> {{ $detalle }}</li>
                    @endforeach
                </ul>
                <p style="margin-top:1.25rem;line-height:1.7;color:var(--muted);">
                    El seguimiento se configura con
                    @isset($guias['google-analytics-4'])<a href="{{ route('blog.show', 'google-analytics-4') }}">Google Analytics 4</a>@else Google Analytics 4 @endisset
                    y
                    @isset($guias['google-tag-manager'])<a href="{{ route('blog.show', 'google-tag-manager') }}">Google Tag Manager</a>@else Google Tag Manager @endisset
                    antes de publicar el primer anuncio, para que cada formulario, llamada o mensaje quede registrado una sola vez.
                </p>
            </div>
        </section>

        <section class="cv-section cv-section--alt cv-section--tight" aria-labelledby="pagina">
            <div class="container" style="max-width:56rem;">
                <div class="cv-head" style="margin-bottom:1.5rem;">
                    <h2 id="pagina">La publicidad no sustituye una página que convierta</h2>
                    <p>Si la página es lenta o confusa, pagas por clics que no compran.</p>
                </div>
                <p style="line-height:1.7;color:var(--muted);">Antes de subir la inversión revisamos que la página repita la promesa del anuncio, cargue bien con datos móviles y deje claro el siguiente paso. Si el problema es la velocidad, se corrige con <a href="{{ route('servicios.show', 'pagespeed-core-web-vitals') }}">optimización de PageSpeed y Core Web Vitals</a>; si hace falta una página nueva, con nuestro <a href="{{ $urlWeb }}">servicio de desarrollo web</a>.@isset($guias['cuanto-cuesta-google-ads']) Para calcular la parte de medios, lee <a href="{{ route('blog.show', 'cuanto-cuesta-google-ads') }}">{{ $guias['cuanto-cuesta-google-ads'] }}</a>@endisset</p>
            </div>
        </section>

        <section class="cv-section cv-section--tight" aria-labelledby="errores">
            <div class="container" style="max-width:56rem;">
                <div class="cv-head" style="margin-bottom:1.5rem;">
                    <h2 id="errores">Errores comunes al invertir en publicidad</h2>
                </div>
                <ul style="display:grid;gap:0.75rem;padding-left:1.25rem;line-height:1.7;color:var(--muted);">
                    @foreach ($errores as [$error, $porque])
                        <li><strong style="color:var(--ink);">{{ $error }}.</strong> {{ $porque }}</li>
                    @endforeach
                </ul>
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
