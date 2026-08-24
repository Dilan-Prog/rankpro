@props(['articulo'])

@php
    /** @var \App\Models\Articulo $articulo */
    $info = $articulo->clusterInfo();
@endphp

<article class="articulo-card">
    @if ($articulo->imagen_destacada)
        <a class="articulo-card__media" href="{{ $articulo->url() }}" tabindex="-1" aria-hidden="true">
            {{-- width/height explicitos: el sitio mide CLS 0.000 y una imagen sin
                 dimensiones lo rompe en cuanto carga tarde. --}}
            <img src="{{ $articulo->imagen_destacada }}"
                 alt="{{ $articulo->imagen_alt ?? '' }}"
                 width="640" height="360" loading="lazy" decoding="async">
        </a>
    @endif

    <div class="articulo-card__cuerpo">
        @if ($info)
            <a class="articulo-card__cluster {{ $info['gradient'] }}" href="{{ route('blog.cluster', $info['slug']) }}">
                {{ $info['nombre_corto'] }}
            </a>
        @endif

        <h3 class="articulo-card__titulo">
            <a href="{{ $articulo->url() }}">{{ $articulo->titulo }}</a>
        </h3>

        @if ($articulo->resumen)
            <p class="articulo-card__resumen">{{ Str::limit($articulo->resumen, 140) }}</p>
        @endif

        <p class="articulo-card__meta">
            @if ($articulo->fecha_publicacion)
                <time datetime="{{ $articulo->fecha_publicacion->toDateString() }}">
                    {{ $articulo->fecha_publicacion->translatedFormat('j M Y') }}
                </time>
                <span aria-hidden="true">·</span>
            @endif
            <span>{{ $articulo->minutosLectura() }} min de lectura</span>
        </p>
    </div>
</article>
