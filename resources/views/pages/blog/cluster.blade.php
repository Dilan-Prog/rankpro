@extends('layouts.app')

@php
    $paginaActual = $articulos->currentPage();
    $sufijoPagina = $paginaActual > 1 ? ' · Página '.$paginaActual : '';

    // La pagina 1 no lleva ?page=1: seria una URL duplicada de la limpia.
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

@section('title', $cluster['meta_title'].$sufijoPagina)
@section('description', $cluster['meta_description'])

{{-- CRITICO: sin este override todas las paginas del cluster declararian la
     misma canonica (url()->current() descarta el query string). Las paginas 2+
     son indexables: nada de noindex ni de canonica apuntando a la pagina 1. --}}
@section('canonical', $urlPagina($paginaActual))

@push('styles')
    @vite(['resources/css/web/pages.css', 'resources/css/web/blog.css'])
@endpush

@push('head')
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
                    '@type' => 'CollectionPage',
                    '@id' => route('blog.cluster', $cluster['slug']).'#collection',
                    'name' => $cluster['nombre'],
                    'description' => $cluster['meta_description'],
                    'url' => route('blog.cluster', $cluster['slug']),
                    'inLanguage' => 'es-MX',
                    'isPartOf' => ['@id' => route('blog.index').'#blog'],
                    'publisher' => ['@id' => url('/#organization')],
                ],
                [
                    '@type' => 'BreadcrumbList',
                    'itemListElement' => [
                        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Inicio', 'item' => url('/')],
                        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Blog', 'item' => route('blog.index')],
                        ['@type' => 'ListItem', 'position' => 3, 'name' => $cluster['nombre'], 'item' => route('blog.cluster', $cluster['slug'])],
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
                        <li><a href="{{ route('blog.index') }}">Blog</a></li>
                        <li aria-hidden="true" class="breadcrumb__sep">/</li>
                        <li><span aria-current="page">{{ $cluster['nombre'] }}</span></li>
                    </ol>
                </nav>

                <div class="page-hero__inner">
                    <div class="section-badge">{{ Str::upper($cluster['nombre_corto']) }}</div>
                    <h1>{{ $cluster['h1'] }}</h1>
                    <p class="page-hero__lead">{{ $cluster['descripcion'] }}</p>
                </div>
            </div>
        </section>

        @if ($servicio)
            {{-- El enlace al hub es la conversion del cluster: sin el, el blog
                 seria un silo que no aporta a las paginas que venden. --}}
            <section class="page-section" aria-labelledby="hub">
                <div class="container">
                    <a class="hub-banner" href="{{ $servicio['url'] }}">
                        <span class="hub-banner__icono {{ $servicio['gradient'] }}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $servicio['icon'] !!}</svg>
                        </span>
                        <span class="hub-banner__texto">
                            <span class="hub-banner__kicker" id="hub">Servicio relacionado</span>
                            <span class="hub-banner__nombre">{{ $servicio['nombre'] }}</span>
                            <span class="hub-banner__resumen">{{ $servicio['resumen'] }}</span>
                        </span>
                        <span class="hub-banner__cta">
                            Ver el servicio
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"></path></svg>
                        </span>
                    </a>
                </div>
            </section>
        @endif

        <section class="page-section page-section--alt" aria-labelledby="articulos">
            <div class="container">
                <h2 class="blog-subtitulo" id="articulos">
                    Artículos de {{ $cluster['nombre_corto'] }}@if ($paginaActual > 1) · página {{ $paginaActual }}@endif
                </h2>

                @if ($articulos->isEmpty())
                    <p class="blog-vacio">
                        Todavía no hay artículos en este tema.
                        <a href="{{ route('blog.index') }}">Ver todo el blog</a>.
                    </p>
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

        <section class="page-section" aria-labelledby="otros-temas">
            <div class="container">
                <h2 class="blog-subtitulo" id="otros-temas">Otros temas del blog</h2>
                <ul class="cluster-grid">
                    @foreach ($clusters as $c)
                        @continue($c['slug'] === $cluster['slug'])
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
    </main>

    @include('components.footer')
@endsection
