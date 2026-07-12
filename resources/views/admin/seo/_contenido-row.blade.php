<tr data-contenido-id="{{ $ct->id }}"
    data-contenido-titulo="{{ $ct->titulo }}"
    data-contenido-keyword-objetivo="{{ $ct->keyword_objetivo }}"
    data-contenido-url="{{ $ct->url }}"
    data-contenido-trafico-generado="{{ $ct->trafico_generado }}"
    data-contenido-estado="{{ $ct->estado }}">
    <td>{{ $ct->titulo }}</td>
    <td><span style="font-size:var(--text-xs); color:var(--color-muted-foreground);">{{ $ct->keyword_objetivo ?? '—' }}</span></td>
    <td><span class="u-mono" style="color:var(--color-primary); font-size:var(--text-xs)">{{ $ct->url ?? '—' }}</span></td>
    <td class="u-mono">{{ number_format($ct->trafico_generado ?? 0) }}</td>
    <td><x-badge :status="$ct->estado" /></td>
    <td>
        <div style="display:flex; gap:4px;">
            <button type="button" class="btn--icon" title="Editar" data-edit-contenido="{{ $ct->id }}">
                <i class="fa-solid fa-pen"></i>
            </button>
            <button type="button" class="btn--icon" title="Eliminar" style="color:var(--text-danger);" data-delete-contenido="{{ $ct->id }}">
                <i class="fa-solid fa-trash"></i>
            </button>
        </div>
    </td>
</tr>
