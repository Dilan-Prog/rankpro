<div class="card card--padded"
    data-onpage-id="{{ $accion->id }}"
    data-onpage-url-pagina="{{ $accion->url_pagina }}"
    data-onpage-accion="{{ $accion->accion }}"
    data-onpage-fecha="{{ $accion->fecha?->format('Y-m-d') }}"
    data-onpage-responsable-id="{{ $accion->responsable_id }}"
    data-onpage-estado="{{ $accion->estado->value }}">
    <div style="display:flex; align-items:flex-start; justify-content:space-between; gap: var(--space-3);">
        <div style="min-width:0;">
            <span class="u-mono" style="color:var(--color-primary); font-size:var(--text-xs); word-break:break-all;">{{ $accion->url_pagina }}</span>
            <p style="margin-top: var(--space-2); font-size:var(--text-sm);">{{ $accion->accion }}</p>
            <div style="margin-top: var(--space-2); font-size:var(--text-xs); color:var(--color-muted-foreground); display:flex; gap: var(--space-3); flex-wrap:wrap;">
                <span><i class="fa-solid fa-calendar"></i> {{ $accion->fecha?->format('Y-m-d') ?? '—' }}</span>
                <span><i class="fa-solid fa-user"></i> {{ $accion->responsable?->name ?? 'Sin asignar' }}</span>
            </div>
        </div>
        <div style="display:flex; align-items:flex-start; gap: var(--space-3); flex-shrink:0;">
            <x-badge :status="$accion->estado" />
            <div style="display:flex; gap:4px;">
                <button type="button" class="btn--icon" title="Editar" data-edit-onpage="{{ $accion->id }}"><i class="fa-solid fa-pen"></i></button>
                <button type="button" class="btn--icon" title="Eliminar" style="color:var(--text-danger);" data-delete-onpage="{{ $accion->id }}"><i class="fa-solid fa-trash"></i></button>
            </div>
        </div>
    </div>
</div>
