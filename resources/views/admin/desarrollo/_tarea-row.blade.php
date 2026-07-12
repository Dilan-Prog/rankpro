<tr data-tarea-id="{{ $tarea->id }}"
    data-tarea-titulo="{{ $tarea->titulo }}"
    data-tarea-descripcion="{{ $tarea->descripcion }}"
    data-tarea-responsable="{{ $tarea->responsable }}"
    data-tarea-prioridad="{{ $tarea->prioridad }}"
    data-tarea-estado="{{ $tarea->estado->value }}"
    data-tarea-fecha-limite="{{ $tarea->fecha_limite?->format('Y-m-d') }}">
    <td>{{ $tarea->titulo }}</td>
    <td><span style="font-size:var(--text-xs); color:var(--color-muted-foreground);">{{ $tarea->responsable ?? '—' }}</span></td>
    <td><x-badge :status="$tarea->prioridad" /></td>
    <td><x-badge :status="$tarea->estado" /></td>
    <td class="u-mono" style="font-size:var(--text-xs); color:var(--color-muted-foreground);">{{ $tarea->fecha_limite?->format('Y-m-d') ?? '—' }}</td>
    <td>
        <div style="display:flex; gap:4px;">
            <button type="button" class="btn--icon" title="Editar" data-edit-tarea="{{ $tarea->id }}">
                <i class="fa-solid fa-pen"></i>
            </button>
            <button type="button" class="btn--icon" title="Eliminar" style="color:var(--text-danger);" data-delete-tarea="{{ $tarea->id }}">
                <i class="fa-solid fa-trash"></i>
            </button>
        </div>
    </td>
</tr>
