@if ($paginator->hasPages())
    <div style="display:flex; align-items:center; justify-content:space-between; margin-top: var(--space-4); padding-top: var(--space-4); border-top:1px solid var(--color-border);">
        <span style="font-size:var(--text-xs); color:var(--color-muted-foreground);">
            {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} de {{ $paginator->total() }}
        </span>
        <div style="display:flex; gap: var(--space-2);">
            @if ($paginator->previousPageUrl())
                <a href="{{ $paginator->previousPageUrl() }}" class="btn btn--secondary">Anterior</a>
            @endif
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="btn btn--secondary">Siguiente</a>
            @endif
        </div>
    </div>
@endif
