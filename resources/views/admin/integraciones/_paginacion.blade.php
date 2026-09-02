{{-- Paginación compartida del panel admin (Blog, Conversiones, Integraciones).
     Sigue siendo paginación de servidor (Illuminate\Pagination\LengthAwarePaginator
     via ->paginate()->withQueryString() en el controlador) — este partial solo
     restila el HTML para que combine con el bloque .table-pagination de
     global.css usado por las tablas con paginación cliente. No convertir a JS. --}}
@if ($paginator->hasPages())
    @php
        $current = $paginator->currentPage();
        $last = $paginator->lastPage();
        $onEachSide = 1;
        $pages = collect();
        for ($i = 1; $i <= $last; $i++) {
            if ($i === 1 || $i === $last || ($i >= $current - $onEachSide && $i <= $current + $onEachSide)) {
                $pages->push($i);
            } elseif ($pages->last() !== '...') {
                $pages->push('...');
            }
        }
    @endphp
    <div class="table-pagination">
        <span class="table-pagination__info">
            {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} de {{ $paginator->total() }}
        </span>
        <div class="table-pagination__pages">
            @if ($paginator->onFirstPage())
                <span class="table-pagination__nav" aria-disabled="true" style="opacity:.5; pointer-events:none;">
                    <i class="fa-solid fa-chevron-left"></i>
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="table-pagination__nav" rel="prev" aria-label="Página anterior">
                    <i class="fa-solid fa-chevron-left"></i>
                </a>
            @endif

            @foreach ($pages as $page)
                @if ($page === '...')
                    <span class="table-pagination__ellipsis">&hellip;</span>
                @elseif ($page == $current)
                    <span class="table-pagination__page is-active" aria-current="page">{{ $page }}</span>
                @else
                    <a href="{{ $paginator->url($page) }}" class="table-pagination__page" aria-label="Ir a la página {{ $page }}">{{ $page }}</a>
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="table-pagination__nav" rel="next" aria-label="Página siguiente">
                    <i class="fa-solid fa-chevron-right"></i>
                </a>
            @else
                <span class="table-pagination__nav" aria-disabled="true" style="opacity:.5; pointer-events:none;">
                    <i class="fa-solid fa-chevron-right"></i>
                </span>
            @endif
        </div>
    </div>
@endif
