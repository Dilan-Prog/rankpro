<div class="comunicacion-item" data-comunicacion-id="{{ $comunicacion->id }}">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap: var(--space-3);">
        <div>
            <div class="u-mono" style="font-size:var(--text-xs); color:var(--color-muted-foreground);">{{ $comunicacion->fecha->format('Y-m-d') }}</div>
            <p style="margin-top:4px; font-size:var(--text-sm);">{{ $comunicacion->resumen }}</p>
            @if ($comunicacion->aprobaciones)
                <p style="margin-top:4px; font-size:var(--text-xs); color:var(--text-success);"><i class="fa-solid fa-circle-check"></i> {{ $comunicacion->aprobaciones }}</p>
            @endif
        </div>
        <button type="button" class="btn--icon" title="Eliminar" style="color:var(--text-danger);" data-delete-comunicacion="{{ $comunicacion->id }}">
            <i class="fa-solid fa-trash"></i>
        </button>
    </div>
</div>
