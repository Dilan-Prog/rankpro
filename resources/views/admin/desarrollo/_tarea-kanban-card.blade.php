{{--
    Same dataset the table row (_tarea-row.blade.php) carries, so the
    existing edit-modal population code in desarrollo.js works unchanged
    whether the click came from a table row or a kanban card, and so a
    drag-drop estado change can PUT the full record (TareaController@update
    requires titulo/prioridad/estado, not just the changed field).
--}}
<div class="kanban__card" draggable="true" data-kanban-card
    data-tarea-id="{{ $tarea->id }}"
    data-tarea-titulo="{{ $tarea->titulo }}"
    data-tarea-descripcion="{{ $tarea->descripcion }}"
    data-tarea-responsable="{{ $tarea->responsable }}"
    data-tarea-prioridad="{{ $tarea->prioridad }}"
    data-tarea-estado="{{ $tarea->estado->value }}"
    data-tarea-fecha-limite="{{ $tarea->fecha_limite?->format('Y-m-d') }}">
    <div class="kanban__card-top">
        <span class="kanban__card-title" title="{{ $tarea->titulo }}">{{ $tarea->titulo }}</span>
        <x-badge :status="$tarea->prioridad" />
    </div>
    <div class="kanban__card-meta">
        <span>{{ $tarea->responsable ?? 'Sin responsable' }}</span>
        <span class="u-mono">{{ $tarea->fecha_limite?->format('d/m/y') ?? '—' }}</span>
    </div>
    <div style="display:flex; justify-content:flex-end; gap:4px; margin-top: var(--space-2);">
        <button type="button" class="btn--icon" title="Editar" data-edit-tarea="{{ $tarea->id }}">
            <i class="fa-solid fa-pen"></i>
        </button>
        <button type="button" class="btn--icon" title="Eliminar" style="color:var(--text-danger);" data-delete-tarea="{{ $tarea->id }}">
            <i class="fa-solid fa-trash"></i>
        </button>
    </div>
</div>
