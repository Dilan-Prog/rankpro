{{--
    Listado publico de casos de exito (/casos-de-exito).

    Recibe $casos (CasosExito::todos()) y $conteos (CasosExito::conteos()).

    El filtro por sector y por servicio es CSS puro: radios visualmente
    ocultos seguidos (hermanos ~) de la barra de filtros y de la rejilla.
    Las reglas que dependen de los ids (ocultar tarjetas, pintar el pill
    activo, mostrar el estado vacio) se generan aqui abajo con los datos
    reales, para que sin JavaScript la pagina siga mostrando todos los casos.

    Los contadores dicen la verdad ("Desarrollo web 0"): el diseno lo pide
    asi en vez de esconder los filtros vacios.
--}}
@extends('layouts.app')

@section('title', 'Casos de éxito: SEO y Google Ads con cifras | RankPro')
@section('description', 'Casos de éxito de RankPro con cifras verificables: ocupación hotelera, retorno sobre inversión y tráfico orgánico, cada uno con su fuente indicada.')
@section('canonical', route('casos.index'))

@push('styles')
    @vite(['resources/css/web/servicios-conversion.css', 'resources/css/web/casos.css'])
@endpush

@php
    $sectores = \App\Support\CasosExito::SECTORES;
    $servicios = \App\Support\CasosExito::SERVICIOS;
    $total = count($casos);

    // Combinaciones sector x servicio sin resultados: activan el estado vacio.
    $cuenta = function (?string $sector, ?string $servicio) use ($casos): int {
        return count(array_filter($casos, fn ($c) =>
            ($sector === null || $c['sector'] === $sector)
            && ($servicio === null || in_array($servicio, $c['servicios'], true))
        ));
    };
    $vacias = [];
    foreach (array_merge([null], array_keys($sectores)) as $s) {
        foreach (array_merge([null], array_keys($servicios)) as $v) {
            if ($cuenta($s, $v) === 0) {
                $vacias[] = ['cs-fs-'.($s ?? 'todos'), 'cs-fv-'.($v ?? 'todos')];
            }
        }
    }
    $idsFiltro = array_merge(
        ['cs-fs-todos', 'cs-fv-todos'],
        array_map(fn ($k) => 'cs-fs-'.$k, array_keys($sectores)),
        array_map(fn ($k) => 'cs-fv-'.$k, array_keys($servicios)),
    );

    $reglas = [];
    foreach ($idsFiltro as $id) {
        $reglas[] = "#{$id}:checked ~ .cs-filtros [for=\"{$id}\"]{background:var(--ink);border-color:var(--ink);color:#fff}";
        $reglas[] = "#{$id}:checked ~ .cs-filtros [for=\"{$id}\"] .cs-pill__n{color:rgba(255,255,255,.55)}";
        $reglas[] = "#{$id}:focus-visible ~ .cs-filtros [for=\"{$id}\"]{outline:2px solid var(--brand);outline-offset:2px}";
    }
    foreach (array_keys($sectores) as $k) {
        $reglas[] = "#cs-fs-{$k}:checked ~ .cs-listado .cs-card:not([data-sector=\"{$k}\"]){display:none}";
    }
    foreach (array_keys($servicios) as $k) {
        $reglas[] = "#cs-fv-{$k}:checked ~ .cs-listado .cs-card:not([data-servicios~=\"{$k}\"]){display:none}";
    }
    foreach ($vacias as [$a, $b]) {
        $reglas[] = "#{$a}:checked ~ #{$b}:checked ~ .cs-listado .cs-vacio{display:block}";
    }
@endphp

@push('head')
    <style>{!! implode('', $reglas) !!}</style>
@endpush

@push('jsonld')
    <script type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'CollectionPage',
                    '@id' => route('casos.index').'#webpage',
                    'url' => route('casos.index'),
                    'name' => 'Casos de éxito de RankPro',
                    'description' => 'Casos de éxito de SEO y Google Ads publicados con cifras verificables y su fuente indicada.',
                    'inLanguage' => 'es-MX',
                    'isPartOf' => ['@id' => url('/#website')],
                    'publisher' => ['@id' => url('/#organization')],
                    'mainEntity' => [
                        '@type' => 'ItemList',
                        'itemListOrder' => 'https://schema.org/ItemListOrderAscending',
                        'numberOfItems' => $total,
                        'itemListElement' => collect($casos)->values()->map(fn ($c, $i) => [
                            '@type' => 'ListItem',
                            'position' => $i + 1,
                            'name' => $c['titulo'],
                            'url' => route('casos.show', $c['slug']),
                        ])->all(),
                    ],
                ],
                [
                    '@type' => 'BreadcrumbList',
                    'itemListElement' => [
                        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Inicio', 'item' => url('/')],
                        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Casos de éxito', 'item' => route('casos.index')],
                    ],
                ],
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
    </script>
@endpush

@section('content')
    @include('components.topbar')
    @include('components.navbar')

    <main class="cs">
        <section class="cs-cabecera">
            <div class="container cs-cabecera__inner">
                <div class="cs-cabecera__texto">
                    <span class="cv-badge">Casos de éxito</span>
                    <h1>Lo que nuestros clientes han logrado con Google</h1>
                    <p class="cs-cabecera__lead">Publicamos pocos casos y los publicamos completos. Cada cifra que verás aquí tiene una fuente indicada: Google Search Console, la plataforma de anuncios o datos entregados por el propio cliente.</p>
                </div>
                <aside class="cs-cabecera__nota">Solo nombramos y enlazamos a clientes que dieron su consentimiento. Los demás se publican por sector, sin nombre ni logo.</aside>
            </div>
        </section>

        <div class="cs-catalogo">
            {{-- Radios del filtro. Van antes de la barra y de la rejilla porque
                 el selector ~ solo alcanza a los hermanos posteriores. --}}
            <input class="cs-filtro__input" type="radio" name="cs-sector" id="cs-fs-todos" checked>
            @foreach ($sectores as $clave => $sector)
                <input class="cs-filtro__input" type="radio" name="cs-sector" id="cs-fs-{{ $clave }}">
            @endforeach
            <input class="cs-filtro__input" type="radio" name="cs-servicio" id="cs-fv-todos" checked>
            @foreach ($servicios as $clave => $label)
                <input class="cs-filtro__input" type="radio" name="cs-servicio" id="cs-fv-{{ $clave }}">
            @endforeach

            <div class="cs-filtros" role="group" aria-label="Filtrar casos">
                <div class="container cs-filtros__inner">
                    <div class="cs-filtros__grupo">
                        <span class="cs-filtros__label">Sector</span>
                        <div class="cs-filtros__pills">
                            <label class="cs-pill" for="cs-fs-todos">Todos <span class="cs-pill__n">{{ $total }}</span></label>
                            @foreach ($sectores as $clave => $sector)
                                @php $n = $conteos['sectores'][$clave] ?? 0; @endphp
                                <label class="cs-pill" for="cs-fs-{{ $clave }}">{{ $sector['label'] }} <span class="cs-pill__n {{ $n === 0 ? 'cs-pill__n--cero' : '' }}">{{ $n }}</span></label>
                            @endforeach
                        </div>
                    </div>
                    <div class="cs-filtros__grupo">
                        <span class="cs-filtros__label">Servicio</span>
                        <div class="cs-filtros__pills">
                            <label class="cs-pill" for="cs-fv-todos">Todos <span class="cs-pill__n">{{ $total }}</span></label>
                            @foreach ($servicios as $clave => $label)
                                @php $n = $conteos['servicios'][$clave] ?? 0; @endphp
                                <label class="cs-pill" for="cs-fv-{{ $clave }}">{{ $label }} <span class="cs-pill__n {{ $n === 0 ? 'cs-pill__n--cero' : '' }}">{{ $n }}</span></label>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <section class="cs-listado" aria-label="Casos publicados">
                <div class="container">
                    <div class="cs-grid">
                        @foreach ($casos as $caso)
                            @include('pages.casos._tarjeta', ['caso' => $caso])
                        @endforeach
                    </div>

                    <p class="cs-vacio"><strong>Todavía no hay un caso publicado con esa combinación.</strong><br>Si trabajas en ese sector, te mostramos resultados en una llamada con las capturas de Search Console a la vista.</p>

                    {{-- Cuarta celda editorial: cierra la rejilla en lugar de dejar un hueco. --}}
                    <div class="cs-cierre">
                        <div class="cs-cierre__texto">
                            <p class="cs-cierre__titulo">Publicamos un caso cuando el cliente lo autoriza y las cifras se pueden sostener</p>
                            <p class="cs-cierre__p">Por eso no verás decenas de casos, sino los que podemos sostener. Si quieres revisar resultados de un sector que aún no está aquí, los mostramos en una llamada con las capturas de Search Console a la vista.</p>
                        </div>
                        <a href="{{ route('agendar.mostrar') }}" class="cv-btn cs-btn--ink">Agendar diagnóstico</a>
                    </div>
                </div>
            </section>
        </div>
    </main>

    @include('components.footer')
@endsection
