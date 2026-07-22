<tr data-grupo-id="{{ $g->id }}"
    data-grupo-nombre="{{ $g->nombre }}"
    data-grupo-audiencia="{{ $g->audiencia }}"
    data-grupo-presupuesto="{{ $g->presupuesto }}"
    data-grupo-estado="{{ $g->estado }}"
    data-grupo-keywords-json="{{ $g->keywords->toJson() }}"
    data-grupo-columnas-json="{{ $g->columnasPersonalizadas->toJson() }}">
    <td>{{ $g->nombre }}</td>
    <td><span style="font-size:var(--text-xs); color:var(--color-muted-foreground);">{{ $g->audiencia ?? '—' }}</span></td>
    <td class="u-mono">${{ number_format($g->presupuesto) }}</td>
    <td>
        @if ($g->keywords->isEmpty())
            <span style="font-size:var(--text-xs); color:var(--color-muted-foreground);">Sin palabras clave</span>
        @else
            <span class="badge badge--info">{{ $g->keywords->count() }} {{ $g->keywords->count() === 1 ? 'palabra clave' : 'palabras clave' }}</span>
        @endif
    </td>
    <td><x-badge :status="$g->estado" /></td>
    <td>
        <div style="display:flex; gap:4px;">
            <button type="button" class="btn--icon" title="Editar" data-edit-grupo="{{ $g->id }}">
                <i class="fa-solid fa-pen"></i>
            </button>
            <button type="button" class="btn--icon" title="Eliminar" style="color:var(--text-danger);" data-delete-grupo="{{ $g->id }}">
                <i class="fa-solid fa-trash"></i>
            </button>
        </div>
    </td>
</tr>
