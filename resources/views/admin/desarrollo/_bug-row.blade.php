<tr data-bug-id="{{ $bug->id }}"
    data-bug-titulo="{{ $bug->titulo }}"
    data-bug-descripcion="{{ $bug->descripcion }}"
    data-bug-prioridad="{{ $bug->prioridad }}"
    data-bug-estado="{{ $bug->estado->value }}"
    data-bug-responsable-id="{{ $bug->responsable_id }}"
    data-bug-fecha-resolucion="{{ $bug->fecha_resolucion?->format('Y-m-d') }}">
    <td>{{ $bug->titulo }}</td>
    <td><x-badge :status="$bug->prioridad" /></td>
    <td data-bug-estado-cell><x-badge :status="$bug->estado" /></td>
    <td>{{ $bug->responsable?->name ?? '—' }}</td>
    <td class="u-mono" style="font-size:var(--text-xs); color:var(--color-muted-foreground);">{{ fecha_mx($bug->fecha_resolucion) }}</td>
    <td>
        <div style="display:flex; gap:4px;">
            <button type="button" class="btn--icon" title="Editar" data-edit-bug="{{ $bug->id }}">
                <i class="fa-solid fa-pen"></i>
            </button>
            <button type="button" class="btn--icon" title="Eliminar" style="color:var(--text-danger);" data-delete-bug="{{ $bug->id }}">
                <i class="fa-solid fa-trash"></i>
            </button>
        </div>
    </td>
</tr>
