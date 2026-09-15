{{--
    Detalle de un caso de exito (/casos-de-exito/{slug}).

    Recibe $caso (forma documentada en CasosExito::todos()) y $otros.

    Dos variantes que decide el dato, no la vista:
      - Con identidad (`nombre` y `url`): hero verde, iniciales, enlace saliente
        con rel="noopener", ficha del caso y cita firmada.
      - Anonimizado (`nombre` o `url` nulos): hero tinta con trama, marco
        punteado, recuadro "Por que no ves el nombre" y, en lugar de la cita,
        el bloque "Como verificamos este caso" con las fuentes de los KPIs.

    Cada bloque narrativo se pinta solo si tiene contenido real y la
    numeracion (01, 02...) se calcula sobre los bloques que existen. En la
    solucion, `null` = "No formo parte de este proyecto"; `[]` = se omite.
--}}
@extends('layouts.app')

@php
    $sectores = \App\Support\CasosExito::SECTORES;
    $serviciosCat = \App\Support\CasosExito::SERVICIOS;
    $fuentes = \App\Support\CasosExito::FUENTES;

    $sector = $sectores[$caso['sector']];
    $anonimo = $caso['nombre'] === null || $caso['url'] === null;
    $quien = $anonimo ? 'Sector '.lcfirst($sector['label']) : $caso['nombre'];

    // SERP: title <= 60 y description entre 70 y 160 caracteres. El resumen
    // puede pasarse de 160; se recorta en la ultima palabra completa.
    $tituloSeo = 'Caso de éxito: '.$quien.' | RankPro';
    $descripcion = $caso['resumen'];
    if (mb_strlen($descripcion) > 160) {
        $descripcion = mb_substr($descripcion, 0, 157);
        $descripcion = mb_substr($descripcion, 0, mb_strrpos($descripcion, ' ')).'…';
    }

    $fuenteAbrev = ['gsc' => 'Search Console', 'ads' => 'Google Ads', 'cliente' => 'Datos del cliente'];

    // Bloques presentes, en el orden del diseno, para numerarlos 01, 02...
    $solucion = [];
    foreach ($serviciosCat as $clave => $label) {
        if (! array_key_exists($clave, $caso['solucion'] ?? [])) {
            continue;
        }
        $bullets = $caso['solucion'][$clave];
        if ($bullets === null || $bullets !== []) {
            $solucion[$clave] = $bullets;
        }
    }
    $haySolucion = (bool) array_filter($solucion, fn ($b) => is_array($b) && $b !== []);

    $bloques = [];
    if ($caso['reto']) { $bloques[] = 'reto'; }
    if ($haySolucion) { $bloques[] = 'solucion'; }
    if ($caso['resultados']) { $bloques[] = 'resultados'; }
    if ($caso['evolucion']) { $bloques[] = 'evolucion'; }
    $numero = fn (string $b) => str_pad((string) (array_search($b, $bloques, true) + 1), 2, '0', STR_PAD_LEFT);

    // Fuentes de verificacion (caso anonimizado): las que aparecen en los KPIs,
    // con las cifras que respalda cada una.
    $verificacion = [];
    foreach ($caso['kpis'] as $kpi) {
        $verificacion[$kpi['fuente']][] = $kpi['label'];
    }

    // Enlace secundario del CTA: la landing del primer servicio del caso.
    $landing = ['seo' => 'seo-organico', 'ads' => 'sem-google-ads', 'desarrollo' => 'desarrollo-web', 'automatizacion' => 'automatizacion-de-procesos'];
    $servicioPrincipal = $caso['servicios'][0] ?? null;
    $urlServicio = $servicioPrincipal && isset($landing[$servicioPrincipal]) && isset(\App\Support\Servicios::todos()[$landing[$servicioPrincipal]])
        ? route('servicios.show', $landing[$servicioPrincipal])
        : null;

    $urlCaso = route('casos.show', $caso['slug']);
@endphp

@section('title', $tituloSeo)
@section('description', $descripcion)
@section('canonical', $urlCaso)

@push('styles')
    @vite(['resources/css/web/servicios-conversion.css', 'resources/css/web/casos.css'])
@endpush

@push('jsonld')
    <script type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@graph' => array_values(array_filter([
                [
                    '@type' => 'Article',
                    '@id' => $urlCaso.'#article',
                    'headline' => $caso['titulo'],
                    'description' => $descripcion,
                    'url' => $urlCaso,
                    'inLanguage' => 'es-MX',
                    'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $urlCaso],
                    'isPartOf' => ['@id' => url('/#website')],
                    'author' => ['@id' => url('/#organization')],
                    'publisher' => ['@id' => url('/#organization')],
                    'articleSection' => 'Casos de éxito',
                    'keywords' => array_values(array_map(fn ($s) => $serviciosCat[$s] ?? $s, $caso['servicios'])),
                    'about' => $anonimo
                        ? ['@type' => 'Thing', 'name' => $sector['label']]
                        : ['@type' => 'Organization', 'name' => $caso['nombre'], 'url' => $caso['url']],
                ],
                [
                    '@type' => 'BreadcrumbList',
                    'itemListElement' => [
                        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Inicio', 'item' => url('/')],
                        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Casos de éxito', 'item' => route('casos.index')],
                        ['@type' => 'ListItem', 'position' => 3, 'name' => $quien, 'item' => $urlCaso],
                    ],
                ],
            ])),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
    </script>
@endpush

@section('content')
    @include('components.topbar')
    @include('components.navbar')

    <main class="cs">
        {{-- ------------------------------------------------ hero --}}
        <section class="cs-hero {{ $anonimo ? 'cs-hero--anon' : 'cs-hero--identidad' }}">
            <div class="container">
                <nav class="cs-crumbs" aria-label="Ruta de navegación">
                    <ol>
                        <li><a href="{{ url('/') }}">Inicio</a></li>
                        <li aria-hidden="true">·</li>
                        <li><a href="{{ route('casos.index') }}">Casos de éxito</a></li>
                        <li aria-hidden="true">·</li>
                        <li><span aria-current="page">{{ $quien }}</span></li>
                    </ol>
                </nav>

                <div class="cs-hero__inner">
                    <div class="cs-hero__texto">
                        <div class="cs-hero__cliente">
                            @if ($anonimo)
                                <span class="cs-logo cs-logo--anon cs-logo--grande" aria-hidden="true"></span>
                                <div>
                                    <div class="cs-hero__nombre">{{ $sector['label'] }}</div>
                                    <div class="cs-hero__meta">Cliente anonimizado · publicado por sector</div>
                                </div>
                            @else
                                @if (!empty($caso['logo']))
                                    <img class="cs-logo cs-logo--grande cs-logo--img" src="{{ $caso['logo'] }}" alt="" width="125" height="84" onerror="this.hidden=true;this.nextElementSibling.hidden=false">
                                    <span class="cs-logo cs-logo--grande" aria-hidden="true" hidden>{{ $caso['iniciales'] }}</span>
                                @else
                                    <span class="cs-logo cs-logo--grande" aria-hidden="true">{{ $caso['iniciales'] }}</span>
                                @endif
                                <div>
                                    <div class="cs-hero__nombre">{{ $caso['nombre'] }}</div>
                                    <div class="cs-hero__meta">{{ $sector['label'] }}@if ($caso['ubicacion']) · {{ $caso['ubicacion'] }}@endif</div>
                                </div>
                            @endif
                        </div>

                        <h1>{{ $caso['titulo'] }}</h1>
                        <p class="cs-hero__lead">{{ $caso['resumen'] }}</p>

                        <ul class="cs-hero__pills" aria-label="Servicios del proyecto">
                            @foreach ($caso['servicios'] as $s)
                                <li class="cs-hero__pill">{{ $serviciosCat[$s] ?? $s }}</li>
                            @endforeach
                            @if ($caso['duracion'])
                                <li class="cs-hero__pill">{{ $caso['duracion'] }} de trabajo</li>
                            @endif
                            @unless ($anonimo)
                                <li><a class="cs-hero__sitio" href="{{ $caso['url'] }}" target="_blank" rel="noopener">{{ preg_replace('#^https?://(www\.)?|/$#', '', $caso['url']) }} ↗</a></li>
                            @endunless
                        </ul>
                    </div>

                    @if ($anonimo)
                        <aside class="cs-ficha cs-ficha--anon">
                            <p class="cs-ficha__titulo">Por qué no ves el nombre</p>
                            <p class="cs-ficha__p">Este cliente no autorizó el uso público de su marca. Publicamos el caso por sector y sin cita, con las cifras que sus fuentes respaldan.</p>
                            <p class="cs-ficha__p">Las cifras se revisan contigo, con las capturas a la vista, en una llamada.</p>
                        </aside>
                    @else
                        <aside class="cs-ficha">
                            <p class="cs-ficha__titulo">Ficha del caso</p>
                            <dl>
                                <div class="cs-ficha__fila"><dt>Sector</dt><dd>{{ $sector['label'] }}</dd></div>
                                @if ($caso['ubicacion'])
                                    <div class="cs-ficha__fila"><dt>Ubicación</dt><dd>{{ $caso['ubicacion'] }}</dd></div>
                                @endif
                                <div class="cs-ficha__fila"><dt>Servicios</dt><dd>{{ implode(' + ', array_map(fn ($s) => ['seo' => 'SEO', 'ads' => 'Ads', 'desarrollo' => 'Web', 'automatizacion' => 'Automatización'][$s] ?? $s, $caso['servicios'])) }}</dd></div>
                                @if ($caso['duracion'])
                                    <div class="cs-ficha__fila"><dt>Duración</dt><dd>{{ $caso['duracion'] }}</dd></div>
                                @endif
                                <div class="cs-ficha__fila"><dt>Publicación</dt><dd>Autorizada</dd></div>
                            </dl>
                        </aside>
                    @endif
                </div>
            </div>
        </section>

        {{-- ------------------------------------------------ cifras --}}
        @if ($caso['kpis'])
            <section class="cs-kpis" aria-label="Cifras del caso">
                <div class="container">
                    <ul class="cs-kpis__grid" style="--cs-kpis: {{ count($caso['kpis']) }};">
                        @foreach ($caso['kpis'] as $kpi)
                            <li class="cs-kpi">
                                <div class="cs-kpi__valor">{{ $kpi['valor'] }}</div>
                                <div class="cs-kpi__label">{{ $kpi['label'] }}</div>
                                <span class="cs-fuente">Fuente · <span class="cs-fuente__larga">{{ $fuentes[$kpi['fuente']] ?? $kpi['fuente'] }}</span><span class="cs-fuente__corta">{{ $fuenteAbrev[$kpi['fuente']] ?? $fuentes[$kpi['fuente']] ?? $kpi['fuente'] }}</span></span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </section>
        @endif

        {{-- ------------------------------------------------ el reto --}}
        @if ($caso['reto'])
            <section class="cs-bloque" aria-labelledby="reto">
                <div class="container cs-bloque__grid">
                    <div><span class="cs-kicker">{{ $numero('reto') }} · El reto</span></div>
                    <div class="cs-bloque__cuerpo">
                        <h2 id="reto">{{ $caso['reto']['titulo'] }}</h2>
                        <div class="cs-prosa">
                            @foreach ($caso['reto']['parrafos'] as $p)
                                <p>{{ $p }}</p>
                            @endforeach
                        </div>
                    </div>
                </div>
            </section>
        @endif

        {{-- ------------------------------------------------ la solucion --}}
        @if ($haySolucion)
            <section class="cs-bloque" aria-labelledby="solucion">
                <div class="container cs-bloque__grid">
                    <div><span class="cs-kicker">{{ $numero('solucion') }} · La solución</span></div>
                    <div class="cs-bloque__cuerpo">
                        <h2 id="solucion">Qué hicimos, servicio por servicio</h2>
                        @php
                            // Un icono por servicio. Trazos de 24x24 al estilo del resto del
                            // sitio. El de automatizacion es un grafo de nodos: evoca n8n sin
                            // reproducir su logotipo, que es marca de un tercero.
                            $icoServicio = [
                                'desarrollo' => '<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>',
                                'seo' => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
                                'ads' => '<path d="m3 11 18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/>',
                                'automatizacion' => '<circle cx="5" cy="6" r="2.5"/><circle cx="19" cy="6" r="2.5"/><circle cx="12" cy="18" r="2.5"/><path d="M7.5 6h9"/><path d="m6.5 8.2 4.3 7.6"/><path d="m17.5 8.2-4.3 7.6"/>',
                            ];
                            $svgServicio = fn (string $clave) => '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($icoServicio[$clave] ?? '').'</svg>';
                        @endphp
                        <div class="cs-servicios">
                            @foreach ($solucion as $clave => $bullets)
                                @if ($bullets === null)
                                    <div class="cs-servicio cs-servicio--no">
                                        <div class="cs-servicio__cab">
                                            <span class="cs-servicio__badge">{!! $svgServicio($clave) !!}{{ $serviciosCat[$clave] }}</span>
                                            <span class="cs-servicio__icono" aria-hidden="true">{!! $svgServicio($clave) !!}</span>
                                        </div>
                                        <p class="cs-servicio__no">No formó parte de este proyecto.</p>
                                    </div>
                                @else
                                    <div class="cs-servicio">
                                        <div class="cs-servicio__cab">
                                            <span class="cs-servicio__badge">{!! $svgServicio($clave) !!}{{ $serviciosCat[$clave] }}</span>
                                            <span class="cs-servicio__icono" aria-hidden="true">{!! $svgServicio($clave) !!}</span>
                                        </div>
                                        <ul class="cs-servicio__lista">
                                            @foreach ($bullets as $b)
                                                <li>{{ $b }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                </div>
            </section>
        @endif

        {{-- ------------------------------------------------ resultados --}}
        @if ($caso['resultados'])
            <section class="cs-bloque" aria-labelledby="resultados">
                <div class="container cs-bloque__grid">
                    <div><span class="cs-kicker">{{ $numero('resultados') }} · Resultados</span></div>
                    <div class="cs-bloque__cuerpo">
                        <h2 id="resultados">{{ $caso['resultados']['titulo'] }}</h2>
                        <div class="cs-prosa"><p>{{ $caso['resultados']['parrafo'] }}</p></div>
                        @if (! empty($caso['resultados']['puntos']))
                            <ul class="cs-puntos">
                                @foreach ($caso['resultados']['puntos'] as $punto)
                                    <li class="cs-punto"><span><strong>{{ $punto['titulo'] }}</strong> {{ $punto['texto'] }}</span></li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
            </section>
        @endif

        {{-- ------------------------------------------------ evolucion --}}
        @if ($caso['evolucion'])
            <section class="cs-bloque" aria-labelledby="evolucion">
                <div class="container cs-bloque__grid">
                    <div><span class="cs-kicker" id="evolucion">{{ $numero('evolucion') }} · Evolución</span></div>
                    <div class="cs-bloque__cuerpo">
                        <div class="cs-graficas">
                            @foreach ($caso['evolucion'] as $grafica)
                                @include('pages.casos._grafica', ['grafica' => $grafica])
                            @endforeach
                        </div>
                    </div>
                </div>
            </section>
        @endif

        {{-- ------------------------------------------------ cita / verificacion --}}
        @if ($caso['cita'] && ! $anonimo)
            <section class="cs-cita-wrap" aria-label="Testimonio del cliente">
                <div class="container">
                    <figure class="cs-cita">
                        <div class="cs-cita__cuerpo">
                            <div class="cs-cita__comillas" aria-hidden="true">“</div>
                            <blockquote class="cs-cita__texto">{{ $caso['cita']['texto'] }}</blockquote>
                            <figcaption class="cs-cita__autor">
                                <span class="cs-cita__avatar" aria-hidden="true">{{ $caso['iniciales'] }}</span>
                                <div>
                                    <div class="cs-cita__nombre">{{ $caso['cita']['cargo'] ?? $caso['cita']['autor'] }}</div>
                                    @if ($caso['cita']['cargo'])
                                        <div class="cs-cita__cargo">{{ $caso['cita']['autor'] }}@if ($caso['ubicacion']) · {{ $caso['ubicacion'] }}@endif</div>
                                    @elseif ($caso['ubicacion'])
                                        <div class="cs-cita__cargo">{{ $caso['ubicacion'] }}</div>
                                    @endif
                                </div>
                            </figcaption>
                        </div>
                        <div class="cs-cita__sep" aria-hidden="true"></div>
                        <div class="cs-cita__nota">
                            <p class="cs-cita__nota-titulo">Sobre esta cita</p>
                            <p>Publicada con autorización del cliente{{ $caso['cita']['cargo'] ? ', con nombre y cargo' : '' }}. No publicamos testimonios anónimos.</p>
                        </div>
                    </figure>
                </div>
            </section>
        @else
            <section class="cs-verifica-wrap" aria-labelledby="verificacion">
                <div class="container">
                    <div class="cs-verifica">
                        <div>
                            <span class="cs-kicker">Cómo verificamos este caso</span>
                            <h2 id="verificacion">Sin nombre no significa sin pruebas</h2>
                            <p class="cs-verifica__p">Cada cifra de arriba lleva su fuente: {{ implode(', ', array_map(fn ($f) => $fuentes[$f] ?? $f, array_keys($verificacion))) }}. No publicamos la marca porque el cliente no nos autorizó a hacerlo, y no inventamos una cita para llenar el hueco. Las cifras se revisan contigo, con las capturas a la vista, en una llamada.</p>
                        </div>
                        <ul class="cs-verifica__fuentes">
                            @foreach ($verificacion as $fuente => $labels)
                                <li class="cs-verifica__fuente">{{ ucfirst($fuentes[$fuente] ?? $fuente) }}<span>{{ implode(' · ', $labels) }}</span></li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </section>
        @endif

        {{-- ------------------------------------------------ CTA --}}
        <section class="cs-cta-wrap" aria-labelledby="cta">
            <div class="container">
                <div class="cs-cta {{ $anonimo ? 'cs-cta--anon' : '' }}">
                    <div class="cs-cta__texto">
                        <h2 id="cta">{{ $caso['cta']['titulo'] }}</h2>
                        <p>{{ $caso['cta']['texto'] }}</p>
                    </div>
                    <div class="cs-cta__acciones">
                        <a href="{{ route('contacto') }}" class="cv-btn {{ $anonimo ? 'cs-btn--brand' : 'cs-btn--blanco' }}">Agendar diagnóstico</a>
                        @if ($urlServicio)
                            <a href="{{ $urlServicio }}" class="cv-btn cs-btn--linea">Ver servicios de {{ ['seo' => 'SEO', 'ads' => 'Google Ads', 'desarrollo' => 'desarrollo web', 'automatizacion' => 'automatización'][$servicioPrincipal] ?? 'servicios' }}</a>
                        @else
                            <a href="{{ route('casos.index') }}" class="cv-btn cs-btn--linea">Ver otros casos</a>
                        @endif
                    </div>
                </div>
            </div>
        </section>

        {{-- ------------------------------------------------ otros casos --}}
        @if ($otros)
            <section class="cs-otros" aria-labelledby="otros">
                <div class="container">
                    <div class="cs-otros__head">
                        <h2 id="otros">Otros casos</h2>
                        <a class="cs-enlace" href="{{ route('casos.index') }}">Ver todos los casos →</a>
                    </div>
                    <ul class="cs-otros__grid">
                        @foreach ($otros as $otro)
                            @php
                                $otroSector = $sectores[$otro['sector']];
                                $otroAnon = $otro['nombre'] === null || $otro['url'] === null;
                            @endphp
                            <li>
                                <a class="cs-otro" href="{{ route('casos.show', $otro['slug']) }}">
                                    @if (!$otroAnon && !empty($otro['logo']))
                                        <img class="cs-otro__logo cs-otro__logo--img" src="{{ $otro['logo'] }}" alt="" loading="lazy" onerror="this.hidden=true;this.nextElementSibling.hidden=false">
                                        <span class="cs-otro__logo cs-otro__logo--{{ $otroSector['tono'] }}" aria-hidden="true" hidden>{{ $otro['iniciales'] }}</span>
                                    @else
                                        <span class="cs-otro__logo {{ $otroAnon ? 'cs-otro__logo--anon' : 'cs-otro__logo--'.$otroSector['tono'] }}" aria-hidden="true">{{ $otroAnon ? '' : $otro['iniciales'] }}</span>
                                    @endif
                                    <span class="cs-otro__cuerpo">
                                        <span class="cs-sector cs-sector--{{ $otroSector['tono'] }}">{{ $otroSector['label'] }}</span>
                                        <span class="cs-otro__titulo" style="display:block;">{{ $otro['titulo'] }}</span>
                                    </span>
                                    <span class="cs-otro__flecha" aria-hidden="true">→</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </section>
        @endif
    </main>

    @include('components.footer')
@endsection
