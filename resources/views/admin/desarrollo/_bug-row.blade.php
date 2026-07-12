<tr data-bug-id="{{ $bug->id }}"
    data-bug-titulo="{{ $bug->titulo }}"
    data-bug-descripcion="{{ $bug->descripcion }}"
    data-bug-prioridad="{{ $bug->prioridad }}"
    data-bug-estado="{{ $bug->estado->value }}"
    data-bug-fecha-resolucion="{{ $bug->fecha_resolucion?->format('Y-m-d') }}">
    <td>{{ $bug->titulo }}</td>
    <td><x-badge :status="$bug->prioridad" /></td>
    <td><x-badge :status="$bug->estado" /></td>
    <td class="u-mono" style="font-size:var(--text-xs); color:var(--color-muted-foreground);">{{ $bug->fecha_resolucion?->format('Y-m-d') ?? '—' }}</td>
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
