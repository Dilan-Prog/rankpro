{{-- Paginacion de RankPro.
     Derivada de la vista `tailwind` de Illuminate\Pagination, sin Tailwind:
     usa las clases y custom properties del sitio (resources/css/web/blog.css).

     Reglas SEO que esta vista respeta y no deben romperse:
       - Los enlaces son <a href> reales, nunca botones con JS: si el enlace no
         existe en el HTML, Google no descubre las paginas profundas.
       - La pagina actual se marca con aria-current="page", no se oculta.
       - Las paginas deshabilitadas son <span>, no <a> a "#". --}}
@php
    // /blog y /blog?page=1 son la misma pagina. Laravel emite ?page=1 en el
    // enlace "anterior" y en el numero 1; se limpia para no crear una URL
    // duplicada que hay que canonicalizar despues.
    $urlSinPagina1 = static function (?string $url): ?string {
        if (! $url) {
            return $url;
        }
        [$base, $query] = array_pad(explode('?', $url, 2), 2, '');
        parse_str($query, $params);
        if (($params['page'] ?? null) !== '1') {
            return $url;
        }
        unset($params['page']);

        return $params ? $base.'?'.http_build_query($params) : $base;
    };
@endphp

@if ($paginator->hasPages())
    <nav class="paginacion" role="navigation" aria-label="Paginación">
        <ul class="paginacion__lista">
            {{-- Anterior --}}
            @if ($paginator->onFirstPage())
                <li>
                    <span class="paginacion__enlace paginacion__enlace--inactivo" aria-disabled="true">
                        <span aria-hidden="true">&laquo;</span>
                        <span class="paginacion__texto">Anterior</span>
                    </span>
                </li>
            @else
                <li>
                    <a class="paginacion__enlace" href="{{ $urlSinPagina1($paginator->previousPageUrl()) }}" rel="prev">
                        <span aria-hidden="true">&laquo;</span>
                        <span class="paginacion__texto">Anterior</span>
                    </a>
                </li>
            @endif

            {{-- Numeros --}}
            @foreach ($elements as $element)
                @if (is_string($element))
                    <li>
                        <span class="paginacion__puntos" aria-hidden="true">{{ $element }}</span>
                    </li>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <li>
                                <span class="paginacion__enlace paginacion__enlace--activo" aria-current="page">{{ $page }}</span>
                            </li>
                        @else
                            <li>
                                <a class="paginacion__enlace" href="{{ $urlSinPagina1($url) }}" aria-label="Ir a la página {{ $page }}">{{ $page }}</a>
                            </li>
                        @endif
                    @endforeach
                @endif
            @endforeach

            {{-- Siguiente --}}
            @if ($paginator->hasMorePages())
                <li>
                    <a class="paginacion__enlace" href="{{ $paginator->nextPageUrl() }}" rel="next">
                        <span class="paginacion__texto">Siguiente</span>
                        <span aria-hidden="true">&raquo;</span>
                    </a>
                </li>
            @else
                <li>
                    <span class="paginacion__enlace paginacion__enlace--inactivo" aria-disabled="true">
                        <span class="paginacion__texto">Siguiente</span>
                        <span aria-hidden="true">&raquo;</span>
                    </span>
                </li>
            @endif
        </ul>

        <p class="paginacion__resumen">
            Página {{ $paginator->currentPage() }} de {{ $paginator->lastPage() }}
            · {{ $paginator->total() }} {{ $paginator->total() === 1 ? 'artículo' : 'artículos' }}
        </p>
    </nav>
@endif
