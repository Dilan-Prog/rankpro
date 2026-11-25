{{--
    Landing de conversion para SEO Organico.

    Traducida del prototipo React/Tailwind a Blade + CSS del sitio, con dos
    cambios deliberados que se acordaron antes de implementar:

      1. Se elimino el "diagnostico en vivo". El prototipo pedia el dominio del
         visitante, simulaba un escaneo y presentaba hallazgos FIJOS e inventados
         ("38 paginas compiten por la misma keyword", "LCP de 4.8 s"...) como si
         los hubiera medido, con la mitad bloqueados tras pedir correo y telefono.
         Nada de eso se media: solo habia una funcion hash() del dominio.
      2. Los mismos bloques visuales se conservan, pero reformulados con
         honestidad: la lista de hallazgos es ahora "que revisamos en una
         auditoria", y la grafica y la vista previa de Google estan etiquetadas
         como ejemplo, no como datos del visitante.

    El telefono del prototipo (+52 55 1234 5678) era un marcador de posicion;
    aqui se usa el numero real de RankPro.
--}}
@extends('layouts.app')

@section('title', $servicio['meta_title'])
@section('description', $servicio['meta_description'])
@section('canonical', route('servicios.show', $servicio['slug']))
@section('body_class', 'rd rd-servicio')

@push('styles')
    @vite(['resources/css/web/pages.css', 'resources/css/web/servicios-conversion.css', 'resources/css/web/rediseno/base.css', 'resources/css/web/rediseno/servicios.css'])
@endpush

@push('scripts')
    @vite('resources/js/servicios-conversion.js')
@endpush

@include('pages.servicios._schema')

@php
    $wa = 'https://wa.me/525562230266';
    $waAuditoria = $wa . '?text=' . rawurlencode('Hola RankPro, quiero una auditoría SEO de mi sitio.');
    $correo = 'administracion@rankprosolutions.com.mx';

    // Los mismos puntos del prototipo, pero presentados como lo que revisamos
    // en una auditoria real, no como hallazgos ya medidos del visitante.
    $revisiones = [
        ['sev' => 'critico', 'label' => 'Alto impacto', 'title' => 'Canibalización entre páginas', 'detail' => 'Varias URLs compitiendo por la misma keyword: Google no sabe cuál mostrar y termina bajando las dos.'],
        ['sev' => 'critico', 'label' => 'Alto impacto', 'title' => 'Core Web Vitals en móvil', 'detail' => 'Medimos LCP, INP y CLS con datos de campo. Cada segundo de más cuesta conversiones orgánicas.'],
        ['sev' => 'critico', 'label' => 'Alto impacto', 'title' => 'Enlaces internos hacia redirecciones', 'detail' => 'La autoridad se diluye antes de llegar a las páginas que venden.'],
        ['sev' => 'critico', 'label' => 'Alto impacto', 'title' => 'Datos estructurados ausentes', 'detail' => 'Sin schema en producto y FAQ te quedas fuera de los resultados enriquecidos donde se gana el clic.'],
        ['sev' => 'medio', 'label' => 'Medio', 'title' => 'Cobertura de intención comercial', 'detail' => 'Comparamos tu contenido con el de tus competidores: comparativas, precios y páginas de decisión.'],
        ['sev' => 'medio', 'label' => 'Medio', 'title' => 'Títulos y descripciones duplicados', 'detail' => 'Plantillas sin variables: Google reescribe tus títulos por su cuenta.'],
        ['sev' => 'medio', 'label' => 'Medio', 'title' => 'Perfil de enlaces frente al líder', 'detail' => 'Cuántos dominios de referencia te faltan para competir por tus keywords cabeza.'],
        ['sev' => 'ok', 'label' => 'Base', 'title' => 'Estructura de URLs y sitemap', 'detail' => 'Si la base técnica está sana se puede construir encima sin rehacer el sitio.'],
    ];

    $frentes = [
        ['ico' => '<path d="m16 18 6-6-6-6"></path><path d="m8 6-6 6 6 6"></path>', 't' => 'SEO técnico', 'd' => 'Rastreo, indexación, Core Web Vitals, enlaces internos, redirecciones, datos estructurados y sitemap. Es la base: si Google no puede leer tu sitio, el contenido no rinde.'],
        ['ico' => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"></path><path d="M14 2v6h6"></path><path d="M16 13H8"></path><path d="M16 17H8"></path>', 't' => 'SEO de contenidos', 'd' => 'Arquitectura de contenido y páginas pensadas para la intención de búsqueda: servicio, guías, comparativos y páginas por ciudad. Cada pieza tiene una keyword objetivo y una URL propia, sin canibalizar a otra.'],
        ['ico' => '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>', 't' => 'SEO de autoridad', 'd' => 'Perfil de enlaces frente a tu competencia directa y menciones de calidad. Se hace despacio y con criterio, sin paquetes de enlaces.'],
    ];

    // Solo las ciudades con landing publicada.
    $ciudades = \App\Support\CiudadesSeo::enlaces();

    $pasos = [
        ['n' => '01', 't' => 'Auditamos', 'd' => 'Acceso de solo lectura a tu sitio real.'],
        ['n' => '02', 't' => 'Te mostramos', 'd' => 'Hallazgos priorizados por impacto y por brecha frente a tu competencia.'],
        ['n' => '03', 't' => 'Tú decides', 'd' => 'Ejecutarlo con nosotros o con tu equipo. Sin compromiso.'],
    ];

    // Curva de ejemplo: proyectos propios, no del visitante.
    $gap = [
        ['m' => 'Mes 0', 'tu' => 1200, 'rp' => 1200],
        ['m' => 'Mes 3', 'tu' => 1280, 'rp' => 2400],
        ['m' => 'Mes 6', 'tu' => 1310, 'rp' => 4100],
        ['m' => 'Mes 9', 'tu' => 1350, 'rp' => 6200],
        ['m' => 'Mes 12', 'tu' => 1400, 'rp' => 8600],
    ];

    $ico = fn ($p, $s = 16) => '<svg xmlns="http://www.w3.org/2000/svg" width="'.$s.'" height="'.$s.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$p.'</svg>';
    $icoCheck = '<circle cx="12" cy="12" r="10"></circle><path d="m9 12 2 2 4-4"></path>';
    $icoFlecha = '<path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path>';
    $icoWa = '<path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"></path>';
    $icoTel = '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>';
    $icoBusca = '<circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path>';
    $icoAlerta = '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"></path><path d="M12 9v4"></path><path d="M12 17h.01"></path>';
    $icoX = '<circle cx="12" cy="12" r="10"></circle><path d="m15 9-6 6"></path><path d="m9 9 6 6"></path>';

    $sevIcono = ['critico' => $icoX, 'medio' => $icoAlerta, 'ok' => $icoCheck];

    // Puntos de la grafica de brecha, calculados en PHP para que se sirva
    // renderizada y no dependa de JavaScript.
    $W = 640; $H = 300; $PAD = 40;
    $maxV = max(array_column($gap, 'rp'));
    $pt = function (string $k) use ($gap, $W, $H, $PAD, $maxV) {
        $n = count($gap) - 1;
        return array_map(function ($i) use ($gap, $k, $W, $H, $PAD, $maxV, $n) {
            $x = $PAD + $i * (($W - $PAD * 2) / $n);
            $y = $H - $PAD - ($gap[$i][$k] / $maxV) * ($H - $PAD * 2);
            return round($x, 1) . ',' . round($y, 1);
        }, range(0, $n));
    };
    $lineaTu = implode(' ', $pt('tu'));
    $lineaRp = implode(' ', $pt('rp'));
    $areaRp = 'M ' . str_replace(',', ' ', explode(' ', $lineaRp)[0]) . ' L ' . implode(' L ', array_map(fn ($p) => str_replace(',', ' ', $p), $pt('rp'))) . ' L ' . ($W - $PAD) . ' ' . ($H - $PAD) . ' L ' . $PAD . ' ' . ($H - $PAD) . ' Z';
@endphp

@section('content')
    @include('components.topbar')
    @include('components.navbar')

    <main>
        {{-- ---------------------------------------------------------- hero --}}
        <section class="cv-hero">
            <div class="container">
                <nav class="breadcrumb" aria-label="Ruta de navegación">
                    <ol>
                        <li><a href="{{ route('home') }}">Inicio</a></li>
                        <li><span class="breadcrumb__sep" aria-hidden="true">/</span></li>
                        <li><a href="{{ route('servicios.index') }}">Servicios</a></li>
                        <li><span class="breadcrumb__sep" aria-hidden="true">/</span></li>
                        <li><span aria-current="page">{{ $servicio['nombre'] }}</span></li>
                    </ol>
                </nav>

                <div class="cv-hero__grid">
                <div>
                    <span class="cv-badge">{!! $ico($icoBusca, 12) !!} Agencia de servicios SEO</span>

                    <h1 class="cv-title">Agencia SEO en México que sabe qué te <span class="text-brand">frena</span> en Google</h1>

                    <p class="cv-sub">Sabemos qué te está frenando en Google. Y cómo arreglarlo.</p>

                    <p class="cv-lead">Somos una agencia SEO en México especializada en posicionamiento web para empresas. Combinamos SEO técnico, de contenidos y de autoridad, y medimos cada avance en Google Search Console. Empezamos con una auditoría que te queda a ti, sin compromiso.</p>

                    <div class="cv-actions">
                        <a href="{{ $waAuditoria }}" class="cv-btn cv-btn--primary" target="_blank" rel="noopener nofollow">Pedir mi auditoría SEO {!! $ico($icoFlecha, 17) !!}</a>
                        <a href="mailto:{{ $correo }}?subject={{ rawurlencode('Auditoría SEO') }}" class="cv-btn cv-btn--ghost">Escribir por correo</a>
                    </div>

                    <p class="cv-nota">Sin llamadas de venta para empezar · Solo pedimos acceso de lectura · El informe es tuyo</p>
                </div>
                @include('components.ilustracion-servicio', ['tipo' => 'seo'])
                </div>
            </div>
        </section>

        {{-- Prueba social de apoyo: sectores con caso publicado (ver CasosExito). --}}
        @include('pages.servicios._casos-strip')

        {{-- ------------------------------------------ qué incluye el servicio --}}
        <section class="cv-section" aria-labelledby="que-incluye">
            <div class="container">
                <div class="cv-head">
                    <h2 id="que-incluye">Servicio de posicionamiento SEO: qué hace una agencia SEO por tu sitio</h2>
                    <p>Un buen servicio de posicionamiento SEO no es una lista de palabras clave. Es ordenar tu sitio para que Google entienda qué vendes, a quién y por qué mereces aparecer primero. Como agencia de marketing digital y SEO, trabajamos en tres frentes:</p>
                </div>
                <div class="cv-cards">
                    @foreach ($frentes as $f)
                        <div class="cv-card-soft">
                            <div class="cv-card-soft__icon">{!! $ico($f['ico'], 20) !!}</div>
                            <h3>{{ $f['t'] }}</h3>
                            <p>{{ $f['d'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- --------------------------------------------------------- pasos --}}
        <section class="cv-section cv-section--oscura" aria-labelledby="proceso">
            <div class="container">
                <div class="cv-head">
                    <h2 id="proceso">Cómo trabaja nuestra agencia SEO</h2>
                    <p>Antes de contratar una agencia de posicionamiento SEO conviene saber qué vas a recibir y cuándo decides.</p>
                </div>
                <div class="cv-steps">
                    @foreach ($pasos as $p)
                        <div class="cv-step">
                            <div class="cv-step__n">{{ $p['n'] }}</div>
                            <div class="cv-step__t">{{ $p['t'] }}</div>
                            <p>{{ $p['d'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- ------------------------------------------------ qué revisamos --}}
        <section class="cv-section">
            <div class="container">
                <div class="cv-head">
                    <span class="cv-badge">{!! $ico($icoBusca, 12) !!} El alcance de la auditoría</span>
                    <h2>Qué revisamos en tu sitio</h2>
                    <p>Estos son los frentes que audita nuestro equipo. Cuáles de ellos te están afectando —y con qué números— solo se sabe después de revisar tu sitio: no lo adivinamos.</p>
                </div>

                <ul class="cv-findings">
                    @foreach ($revisiones as $r)
                        <li class="cv-finding cv-finding--{{ $r['sev'] }}">
                            <span class="cv-finding__icon">{!! $ico($sevIcono[$r['sev']], 18) !!}</span>
                            <div>
                                <div class="cv-finding__title">{{ $r['title'] }}</div>
                                <p class="cv-finding__detail">{{ $r['detail'] }}</p>
                                <span class="cv-finding__sev">{{ $r['label'] }}</span>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>

        {{-- --------------------------------------------------- brecha de tráfico --}}
        <section class="cv-section">
            <div class="container">
                <div class="cv-chart">
                    <div class="cv-chart__head">
                        <div>
                            <span class="cv-badge">{!! $ico('<polyline points="22 7 13.5 15.5 8.5 10.5 2 17"></polyline><polyline points="16 7 22 7 22 13"></polyline>', 12) !!} Ejemplo de proyecto</span>
                            <h2 class="cv-calc__title">Lo que se deja sobre la mesa cada mes</h2>
                            <p class="cv-chart__hint" style="max-width:32rem;">Curva típica de un proyecto de SEO tras corregir los frentes de arriba, frente a un sitio que no cambia nada. <strong>Es un ejemplo ilustrativo, no datos de un cliente ni una proyección de tu sitio.</strong></p>
                        </div>
                    </div>

                    <div class="cv-chart__canvas">
                        <svg viewBox="0 0 640 300" role="img" aria-label="Ejemplo ilustrativo de sesiones orgánicas al mes durante 12 meses: sin cambios frente a con trabajo de SEO">
                            <defs>
                                <linearGradient id="seoUp" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="#0F9D6E" stop-opacity="0.3"></stop>
                                    <stop offset="100%" stop-color="#0F9D6E" stop-opacity="0.02"></stop>
                                </linearGradient>
                            </defs>
                            <text x="0" y="18" font-size="12" fill="#5F6E67">Sesiones orgánicas al mes (ejemplo)</text>
                            @for ($i = 0; $i < 5; $i++)
                                <line x1="40" x2="600" y1="{{ 40 + $i * 55 }}" y2="{{ 40 + $i * 55 }}" stroke="rgba(0,0,0,0.06)" stroke-dasharray="4 4"></line>
                                <text x="32" y="{{ 44 + $i * 55 }}" text-anchor="end" font-size="12" fill="#5F6E67">{{ number_format(round($maxV * (1 - $i / 4))) }}</text>
                            @endfor
                            <path d="{{ $areaRp }}" fill="url(#seoUp)"></path>
                            <polyline points="{{ $lineaTu }}" fill="none" stroke="#9CA3AF" stroke-width="2" stroke-dasharray="5 4"></polyline>
                            <polyline points="{{ $lineaRp }}" fill="none" stroke="#0F9D6E" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></polyline>
                            @foreach ($gap as $i => $g)
                                <text x="{{ round(40 + $i * ((640 - 80) / 4), 1) }}" y="292" text-anchor="middle" font-size="12" fill="#5F6E67">{{ $g['m'] }}</text>
                            @endforeach
                        </svg>
                    </div>

                    <p class="cv-chart__note">
                        <span style="display:inline-block;width:1.25rem;border-top:2px dashed #9CA3AF;vertical-align:middle;"></span> Sin cambios
                        &nbsp;&nbsp;
                        <span style="display:inline-block;width:1.25rem;border-top:3px solid var(--brand);vertical-align:middle;"></span> Con trabajo de SEO sostenido
                    </p>
                </div>
            </div>
        </section>

        {{-- Casos de exito con datos reales (ver App\Support\CasosExito). --}}
        @include('pages.servicios._casos-exito', ['casosTitulo' => 'Resultados de nuestro servicio de SEO'])

        {{-- ------------------------------------------------ vista previa SERP --}}
        <section class="cv-section cv-section--tight">
            <div class="container cv-angosto">
                <h2 class="cv-calc__title">Cómo cambia tu resultado en Google</h2>
                <p class="cv-chart__hint">Antes y después de reescribir títulos, meta descripciones y datos estructurados. Ejemplo con un negocio ficticio.</p>

                <div class="cv-serp">
                    <div class="cv-serp__item">
                        <div class="cv-serp__tag">Hoy</div>
                        <div class="cv-serp__url">tuempresa.com.mx › inicio</div>
                        <div class="cv-serp__title">Inicio | Tuempresa</div>
                        <div class="cv-serp__desc">Bienvenido a nuestro sitio web. Somos una empresa comprometida con la calidad y el servicio…</div>
                    </div>
                    <div class="cv-serp__item cv-serp__item--after">
                        <div class="cv-serp__tag">Con RankPro</div>
                        <div class="cv-serp__url">tuempresa.com.mx › servicios › cdmx</div>
                        <div class="cv-serp__title">Servicio en CDMX con entrega en 24 h | Tuempresa</div>
                        <div class="cv-serp__desc">Cotiza en línea en 2 minutos. Garantía de 12 meses y facturación inmediata.</div>
                        <div class="cv-serp__chips">
                            @foreach (['Reseñas ★', 'Precios', 'FAQ enriquecidas'] as $chip)
                                <span class="cv-serp__chip">{{ $chip }}</span>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- ----------------------------------------------------- cobertura --}}
        <section class="cv-section cv-section--alt cv-section--tight" aria-labelledby="cobertura">
            <div class="container">
                <div class="cv-head">
                    <h2 id="cobertura">Agencia SEO para empresas en todo México</h2>
                    <p>
                        Trabajamos de forma remota con empresas de cualquier estado.
                        {{-- Sin landings publicadas no se anuncia una lista que no existe. --}}
                        @if ($ciudades === [])
                            Si buscas una agencia de SEO en tu ciudad, podemos atenderte estés donde estés.
                        @else
                            Si buscas una agencia de SEO en tu ciudad, {{ count($ciudades) === 1 ? 'esta es la página' : 'estas son las páginas' }} con el detalle local:
                        @endif
                    </p>
                </div>
                @if ($ciudades !== [])
                    <ul class="cv-pildoras">
                        @foreach ($ciudades as $ciudad)
                            <li><a class="cv-chip" href="{{ $ciudad['url'] }}">{{ $ciudad['label'] }}</a></li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </section>

        {{-- ------------------------------------------------------ guías del blog --}}
        {{-- Hub -> spoke: primero las guías fijas del plan (solo las publicadas) y
             debajo los artículos recientes que no estén ya en esa lista. Sin
             artículos no se pinta nada, para no dejar un bloque vacío. --}}
        @php
            $recientes = $articulos->reject(fn ($a) => isset($guias[$a->slug]));
        @endphp
        @if ($guias !== [] || $recientes->isNotEmpty())
            <section class="cv-section cv-section--tight" aria-labelledby="guias">
                <div class="container">
                    <div class="cv-head">
                        <h2 id="guias">Antes de contratar, conviene entender el SEO</h2>
                    </div>
                    @if ($guias !== [])
                        <ul class="cv-guias">
                            @foreach ($guias as $slugGuia => $textoGuia)
                                <li><a href="{{ route('blog.show', $slugGuia) }}">{{ $textoGuia }}</a></li>
                            @endforeach
                        </ul>
                    @endif
                    @if ($recientes->isNotEmpty())
                        <div class="cv-cards">
                            @foreach ($recientes as $articulo)
                                <x-blog.tarjeta :articulo="$articulo" />
                            @endforeach
                        </div>
                    @endif
                    <p class="cv-ver-mas">
                        <a href="{{ route('blog.index') }}">Ver todas las guías del blog</a>
                    </p>
                </div>
            </section>
        @endif

        {{-- ------------------------------------------------------ preguntas --}}
        <section class="cv-section cv-section--alt">
            <div class="container">
                <div class="cv-head">
                    <h2>Preguntas frecuentes sobre el servicio de SEO</h2>
                </div>

                <div class="cv-faq">
                    @foreach ($servicio['faqs'] as $i => $faq)
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
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- ------------------------------------------------------ CTA final --}}
        <section class="cv-section cv-section--brand">
            <div class="container">
                <div class="cv-final">
                    <h2>Empieza por saber dónde estás parado</h2>
                    <p>La mayoría de las pérdidas de tráfico son de contenido y de técnica, no de presupuesto. La auditoría te muestra cuáles son las tuyas.</p>
                    <div class="cv-actions">
                        <a href="{{ $waAuditoria }}" class="cv-btn cv-btn--white" target="_blank" rel="noopener nofollow">Pedir mi auditoría SEO {!! $ico($icoFlecha, 17) !!}</a>
                        <a href="{{ route('agendar.mostrar') }}" class="cv-btn cv-btn--outline-light">Prefiero agendar una llamada</a>
                    </div>
                </div>
            </div>
        </section>

        {{-- ------------------------------------------- otros servicios --}}
        <section class="cv-section cv-section--alt cv-section--tight">
            <div class="container">
                <div class="cv-head">
                    <h2>Otros servicios</h2>
                </div>
                <div class="cv-cards">
                    @foreach (array_slice($otrosServicios, 0, 3) as $otro)
                        <a class="cv-card-soft" href="{{ route('servicios.show', $otro['slug']) }}" style="text-decoration:none;">
                            <div class="cv-card-soft__icon">{!! $ico($otro['icon'], 20) !!}</div>
                            <h3>{{ $otro['nombre'] }}</h3>
                            <p>{{ $otro['resumen'] }}</p>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    </main>

    <div class="cv-bar">
        <a href="tel:+525562230266" class="cv-bar__icon" aria-label="Llamar a RankPro">{!! $ico($icoTel, 18) !!}</a>
        <a href="{{ $wa }}" class="cv-bar__icon cv-bar__icon--wa" aria-label="Escribir por WhatsApp" target="_blank" rel="noopener nofollow">{!! $ico($icoWa, 18) !!}</a>
        <a href="{{ $waAuditoria }}" class="cv-btn cv-btn--primary cv-bar__cta" target="_blank" rel="noopener nofollow">Auditoría SEO {!! $ico($icoFlecha, 16) !!}</a>
    </div>

    @include('components.footer')
@endsection
