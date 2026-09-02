{{--
    Same dataset the table row (_bug-row.blade.php) carries — see the note
    in _tarea-kanban-card.blade.php, same reasoning applies here.
--}}
<div class="kanban__card" draggable="true" data-kanban-card
    data-bug-id="{{ $bug->id }}"
    data-bug-titulo="{{ $bug->titulo }}"
    data-bug-descripcion="{{ $bug->descripcion }}"
    data-bug-prioridad="{{ $bug->prioridad }}"
    data-bug-estado="{{ $bug->estado->value }}"
    data-bug-fecha-resolucion="{{ $bug->fecha_resolucion?->format('Y-m-d') }}">
    <div class="kanban__card-top">
        <span class="kanban__card-title" title="{{ $bug->titulo }}">{{ $bug->titulo }}</span>
        <x-badge :status="$bug->prioridad" />
    </div>
    <div class="kanban__card-meta">
        <span class="u-mono">{{ $bug->fecha_resolucion?->format('d/m/y') ?? 'Sin fecha de resolución' }}</span>
    </div>
    <div style="display:flex; justify-content:flex-end; gap:4px; margin-top: var(--space-2);">
        <button type="button" class="btn--icon" title="Editar" data-edit-bug="{{ $bug->id }}">
            <i class="fa-solid fa-pen"></i>
        </button>
        <button type="button" class="btn--icon" title="Eliminar" style="color:var(--text-danger);" data-delete-bug="{{ $bug->id }}">
            <i class="fa-solid fa-trash"></i>
        </button>
    </div>
</div>
