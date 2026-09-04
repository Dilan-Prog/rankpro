@extends('layouts.app')

@php
    /** @var \App\Models\Articulo $articulo */
    $autorNombre = $autor?->name ?? 'Equipo RankPro';
    $ogImage = $articulo->og_image ?: $articulo->imagen_destacada;
@endphp

@section('title', $articulo->meta_title ?: $articulo->titulo.' | RankPro')
@section('description', $articulo->meta_description ?: Str::limit($articulo->resumen, 155, ''))
@section('canonical', $articulo->url())
@section('og_type', 'article')
@if ($ogImage)
    @section('og_image', $ogImage)
@endif

@push('styles')
    @vite(['resources/css/web/pages.css', 'resources/css/web/blog.css'])
@endpush

@push('scripts')
    @vite('resources/js/blog-publico.js')
@endpush

@push('head')
    {{-- Open Graph de articulo. og:type lo emite components/seo/meta via
         @section('og_type'); estas son las propiedades especificas. --}}
    @if ($articulo->fecha_publicacion)
        <meta property="article:published_time" content="{{ $articulo->fecha_publicacion->toIso8601String() }}">
    @endif
    @if ($articulo->fechaEfectiva())
        <meta property="article:modified_time" content="{{ $articulo->fechaEfectiva()->toIso8601String() }}">
    @endif
    <meta property="article:author" content="{{ $autorNombre }}">
    @if ($cluster)
        <meta property="article:section" content="{{ $cluster['nombre'] }}">
    @endif
@endpush

@include('pages.blog._schema')

@section('content')
    @include('components.topbar')
    @include('components.navbar')

    <main>
        <article class="articulo">
            <header class="articulo__cabecera">
                <div class="container">
                    <nav class="breadcrumb" aria-label="Ruta de navegación">
                        <ol>
                            <li><a href="{{ url('/') }}">Inicio</a></li>
                            <li aria-hidden="true" class="breadcrumb__sep">/</li>
                            <li><a href="{{ route('blog.index') }}">Blog</a></li>
                            @if ($cluster)
                                <li aria-hidden="true" class="breadcrumb__sep">/</li>
                                <li><a href="{{ route('blog.cluster', $cluster['slug']) }}">{{ $cluster['nombre_corto'] }}</a></li>
                            @endif
                            <li aria-hidden="true" class="breadcrumb__sep">/</li>
                            <li><span aria-current="page">{{ Str::limit($articulo->titulo, 40) }}</span></li>
                        </ol>
                    </nav>

                    <div class="articulo__cabecera-inner">
                        @if ($cluster)
                            <a class="articulo__cluster {{ $cluster['gradient'] }}" href="{{ route('blog.cluster', $cluster['slug']) }}">
                                {{ $cluster['nombre'] }}
                            </a>
                        @endif

                        {{-- Unico h1 de la pagina. --}}
                        <h1 class="articulo__titulo">{{ $articulo->titulo }}</h1>

                        @if ($articulo->resumen)
                            <p class="articulo__resumen">{{ $articulo->resumen }}</p>
                        @endif

                        <div class="articulo__meta">
                            {{-- El enlace del autor apunta a /nosotros: las fichas
                                 individuales (/nosotros/{autor}) no existen todavia y
                                 enlazarlas seria generar 404 a proposito. --}}
                            <a class="articulo__autor" href="{{ url('/nosotros') }}" rel="author">{{ $autorNombre }}</a>
                            <span aria-hidden="true">·</span>
                            @if ($articulo->fecha_publicacion)
                                <time datetime="{{ $articulo->fecha_publicacion->toDateString() }}">
                                    {{ $articulo->fecha_publicacion->translatedFormat('j \d\e F \d\e Y') }}
                                </time>
                                <span aria-hidden="true">·</span>
                            @endif
                            <span>{{ $articulo->minutosLectura() }} min de lectura</span>
                        </div>

                        @if ($articulo->fueActualizado())
                            <p class="articulo__actualizado">
                                Actualizado el
                                <time datetime="{{ $articulo->fecha_actualizacion->toDateString() }}">
                                    {{ $articulo->fecha_actualizacion->translatedFormat('j \d\e F \d\e Y') }}
                                </time>
                            </p>
                        @endif
                    </div>
                </div>
            </header>

            @if ($articulo->imagen_destacada)
                <div class="container">
                    {{-- Hero del articulo: fetchpriority alto y SIN lazy (es el LCP).
                         width/height explicitos para no romper el CLS 0.000. --}}
                    <figure class="articulo__hero">
                        <img src="{{ $articulo->imagen_destacada }}"
                             alt="{{ $articulo->imagen_alt ?? $articulo->titulo }}"
                             width="1200" height="630"
                             fetchpriority="high" decoding="async">
                    </figure>
                </div>
            @endif

            <div class="container">
                <div class="articulo__layout">
                    <aside class="articulo__lateral">
                        <x-blog.toc :toc="$articulo->toc ?? []" />
                    </aside>

                    <div class="articulo__cuerpo">
                        {{-- contenido_html ya viene renderizado y saneado por
                             RenderizadorMarkdown (html_input: 'escape'). No se
                             renderiza Markdown en la vista. --}}
                        <div class="prosa-articulo">
                            {!! $articulo->contenido_html !!}
                        </div>

                        <x-blog.cta-servicio :servicios="$servicios" />

                        @if ($cluster)
                            <p class="articulo__volver">
                                <a href="{{ route('blog.cluster', $cluster['slug']) }}">
                                    &larr; Ver todos los artículos de {{ $cluster['nombre'] }}
                                </a>
                            </p>
                        @endif
                    </div>
                </div>
            </div>

            @if ($relacionados->isNotEmpty())
                <section class="page-section page-section--alt" aria-labelledby="relacionados">
                    <div class="container">
                        <h2 class="blog-subtitulo" id="relacionados">Sigue leyendo</h2>
                        <div class="articulo-grid">
                            @foreach ($relacionados as $relacionado)
                                <x-blog.tarjeta :articulo="$relacionado" />
                            @endforeach
                        </div>
                    </div>
                </section>
            @endif

            <section class="page-section">
                <div class="container">
                    <div class="page-cta">
                        <h2>¿Quieres que lo revisemos en tu caso?</h2>
                        <p>
                            Escríbenos con el contexto de tu negocio y te respondemos con un diagnóstico
                            inicial y una propuesta de alcance concreta, sin compromiso.
                        </p>
                        <div class="page-cta__actions">
                            <a href="{{ route('contacto') }}" class="btn btn-light">Solicitar propuesta</a>
                            <a href="https://wa.me/527341036410" class="btn btn-ghost" rel="noopener nofollow">WhatsApp +52 734 103 6410</a>
                        </div>
                    </div>
                </div>
            </section>
        </article>
    </main>

    @include('components.footer')
@endsection
