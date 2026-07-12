<tr data-backlink-id="{{ $b->id }}">
    <td><span class="u-mono" style="color:var(--color-primary); font-size:var(--text-xs)">{{ $b->url_destino }}</span></td>
    <td>{{ $b->url_origen }}</td>
    <td class="u-mono"><strong style="color:var(--text-success)">{{ $b->da_dr ?? '—' }}</strong></td>
    <td style="text-transform:capitalize;">{{ $b->tipo }}</td>
    <td><x-badge :status="$b->estado" /></td>
    <td class="u-mono" style="font-size:var(--text-xs); color:var(--color-muted-foreground)">{{ $b->fecha_conseguido?->format('Y-m-d') ?? '—' }}</td>
    <td>
        <button type="button" class="btn--icon" title="Eliminar" style="color:var(--text-danger);" data-delete-backlink="{{ $b->id }}">
            <i class="fa-solid fa-trash"></i>
        </button>
    </td>
</tr>
