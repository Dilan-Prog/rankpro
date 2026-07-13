<tr data-grupo-id="{{ $g->id }}"
    data-grupo-nombre="{{ $g->nombre }}"
    data-grupo-audiencia="{{ $g->audiencia }}"
    data-grupo-presupuesto="{{ $g->presupuesto }}"
    data-grupo-keywords="{{ implode(', ', $g->keywords ?? []) }}"
    data-grupo-estado="{{ $g->estado }}">
    <td>{{ $g->nombre }}</td>
    <td><span style="font-size:var(--text-xs); color:var(--color-muted-foreground);">{{ $g->audiencia ?? '—' }}</span></td>
    <td class="u-mono">${{ number_format($g->presupuesto) }}</td>
    <td>
        <div style="display:flex; flex-wrap:wrap; gap:4px;">
            @forelse ($g->keywords ?? [] as $kw)
                <span style="padding:2px 6px; border-radius:4px; background:var(--color-secondary); border:1px solid var(--color-border); font-size:var(--text-xs);">{{ $kw }}</span>
            @empty
                <span style="font-size:var(--text-xs); color:var(--color-muted-foreground);">—</span>
            @endforelse
        </div>
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
