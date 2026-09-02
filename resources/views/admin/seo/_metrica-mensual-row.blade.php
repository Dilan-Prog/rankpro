<tr data-metrica-id="{{ $m->id }}"
    data-metrica-mes="{{ $m->mes }}"
    data-metrica-anio="{{ $m->anio }}"
    data-metrica-trafico-organico="{{ $m->trafico_organico }}"
    data-metrica-keywords-top3="{{ $m->keywords_top3 }}"
    data-metrica-keywords-top10="{{ $m->keywords_top10 }}"
    data-metrica-keywords-top100="{{ $m->keywords_top100 }}"
    data-metrica-backlinks-total="{{ $m->backlinks_total }}"
    data-metrica-errores-resueltos="{{ $m->errores_resueltos }}"
    data-metrica-errores-pendientes="{{ $m->errores_pendientes }}"
    data-metrica-notas="{{ $m->notas }}">
    <td class="u-mono">{{ str_pad($m->mes, 2, '0', STR_PAD_LEFT) }}/{{ $m->anio }}</td>
    <td class="u-mono">#{{ $m->ciclo }}</td>
    <td class="u-mono">{{ number_format($m->trafico_organico) }}</td>
    <td class="u-mono">{{ $m->keywords_top3 }} / {{ $m->keywords_top10 }} / {{ $m->keywords_top100 }}</td>
    <td class="u-mono">{{ number_format($m->backlinks_total) }}</td>
    <td class="u-mono">
        <span style="color:var(--text-success)">{{ $m->errores_resueltos }}</span>
        /
        <span style="color:{{ $m->errores_pendientes > 0 ? 'var(--text-danger)' : 'var(--color-muted-foreground)' }}">{{ $m->errores_pendientes }}</span>
    </td>
    <td>
        <div style="display:flex; gap:4px;">
            <button type="button" class="btn--icon" title="Editar" data-edit-metrica="{{ $m->id }}"><i class="fa-solid fa-pen"></i></button>
            <button type="button" class="btn--icon" title="Eliminar" style="color:var(--text-danger);" data-delete-metrica="{{ $m->id }}"><i class="fa-solid fa-trash"></i></button>
        </div>
    </td>
</tr>
