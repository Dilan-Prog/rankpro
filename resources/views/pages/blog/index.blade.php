@extends('layouts.app')

@php
    $paginaActual = $articulos->currentPage();
    $sufijoPagina = $paginaActual > 1 ? ' · Página '.$paginaActual : '';

    // /blog y /blog?page=1 son la misma pagina: la canonica (y el enlace
    // "anterior" desde la 2) tienen que ser la version limpia, o se duplica.
    $sinPagina1 = static function (?string $url): ?string {
        if (! $url) {
            return $url;
        }
        [$base, $query] = array_pad(explode('?', $url, 2), 2, '');
        parse_str($query, $params);
        unset($params['page']);

        return $params ? $base.'?'.http_build_query($params) : $base;
    };

    $urlPagina = static fn (int $p) => $p <= 1
        ? $sinPagina1($articulos->url(1))
        : $articulos->url($p);
@endphp

@section('title', 'Blog de marketing digital'.$sufijoPagina.' | RankPro')
@section('description', 'Guías prácticas de SEO, Google Ads, analítica, velocidad web, desarrollo y redes sociales, escritas para negocios en México.')

{{-- CRITICO: el valor por defecto de components/seo/meta.blade.php es
     url()->current(), que descarta el query string. Sin este override, /blog,
     /blog?page=2 y /blog?page=3 declararian la misma canonica y Google las
     colapsaria en una sola, perdiendo los articulos de las paginas profundas.
     Las paginas 2+ son indexables: aqui no hay noindex ni canonica a la 1. --}}
@section('canonical', $urlPagina($paginaActual))

@push('styles')
    @vite(['resources/css/web/pages.css', 'resources/css/web/blog.css'])
@endpush

@push('head')
    {{-- Google ya no usa rel prev/next, Bing si, y cuestan cero. --}}
    @if ($articulos->previousPageUrl())
        <link rel="prev" href="{{ $urlPagina($paginaActual - 1) }}">
    @endif
    @if ($articulos->nextPageUrl())
        <link rel="next" href="{{ $articulos->nextPageUrl() }}">
    @endif
@endpush

@push('jsonld')
    <script type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'Blog',
                    '@id' => route('blog.index').'#blog',
                    'name' => 'Blog de RankPro',
                    'description' => 'Guías de marketing digital para negocios en México.',
                    'url' => route('blog.index'),
                    'inLanguage' => 'es-MX',
                    'publisher' => ['@id' => url('/#organization')],
                ],
                [
                    '@type' => 'BreadcrumbList',
                    'itemListElement' => [
                        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Inicio', 'item' => url('/')],
                        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Blog', 'item' => route('blog.index')],
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
        <section class="page-hero">
            <div class="container">
                <nav class="breadcrumb" aria-label="Ruta de navegación">
                    <ol>
                        <li><a href="{{ url('/') }}">Inicio</a></li>
                        <li aria-hidden="true" class="breadcrumb__sep">/</li>
                        <li><span aria-current="page">Blog</span></li>
                    </ol>
                </nav>

                <div class="page-hero__inner">
                    <div class="section-badge">BLOG</div>
                    {{-- Un solo h1 por pagina. --}}
                    <h1>Marketing digital explicado sin humo</h1>
                    <p class="page-hero__lead">
                        Guías prácticas de SEO, Google Ads, analítica, velocidad web, desarrollo y redes
                        sociales. Escritas con datos y ejemplos de negocios reales en México, para que
                        puedas decidir qué hacer aunque no nos contrates.
                    </p>
                </div>
            </div>
        </section>

        <section class="page-section" aria-labelledby="temas">
            <div class="container">
                <h2 class="blog-subtitulo" id="temas">Temas del blog</h2>
                <ul class="cluster-grid">
                    @foreach ($clusters as $c)
                        <li>
                            <a class="cluster-card" href="{{ $c['url'] }}">
                                <span class="cluster-card__barra {{ $c['gradient'] }}" aria-hidden="true"></span>
                                <span class="cluster-card__nombre">{{ $c['nombre'] }}</span>
                                <span class="cluster-card__desc">{{ $c['descripcion'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>

        <section class="page-section page-section--alt" aria-labelledby="ultimos">
            <div class="container">
                <h2 class="blog-subtitulo" id="ultimos">
                    @if ($paginaActual > 1)
                        Artículos · página {{ $paginaActual }}
                    @else
                        Últimos artículos
                    @endif
                </h2>

                @if ($articulos->isEmpty())
                    <p class="blog-vacio">Todavía no hay artículos publicados. Vuelve pronto.</p>
                @else
                    <div class="articulo-grid">
                        @foreach ($articulos as $articulo)
                            <x-blog.tarjeta :articulo="$articulo" />
                        @endforeach
                    </div>

                    {{ $articulos->links() }}
                @endif
            </div>
        </section>

        <section class="page-section">
            <div class="container">
                <div class="page-cta">
                    <h2>¿Prefieres que lo hagamos nosotros?</h2>
                    <p>
                        Cuéntanos en qué está tu negocio y te respondemos con un diagnóstico inicial y una
                        propuesta de alcance concreta, sin compromiso.
                    </p>
                    <div class="page-cta__actions">
                        <a href="{{ route('contacto') }}" class="btn btn-light">Solicitar propuesta</a>
                        <a href="{{ route('servicios.index') }}" class="btn btn-ghost">Ver servicios</a>
                    </div>
                </div>
            </div>
        </section>
    </main>

    @include('components.footer')
@endsection
