<tr data-metrica-id="{{ $m->id }}"
    data-metrica-mes="{{ $m->mes }}"
    data-metrica-anio="{{ $m->anio }}"
    data-metrica-inversion-real="{{ $m->inversion_real }}"
    data-metrica-impresiones="{{ $m->impresiones }}"
    data-metrica-clics="{{ $m->clics }}"
    data-metrica-ctr="{{ $m->ctr }}"
    data-metrica-cpc="{{ $m->cpc }}"
    data-metrica-conversiones="{{ $m->conversiones }}"
    data-metrica-cpl="{{ $m->cpl }}"
    data-metrica-cpa="{{ $m->cpa }}"
    data-metrica-roas="{{ $m->roas }}"
    data-metrica-valor-conversion="{{ $m->valor_conversion }}">
    <td class="u-mono">{{ str_pad($m->mes, 2, '0', STR_PAD_LEFT) }}/{{ $m->anio }}</td>
    <td class="u-mono">${{ number_format($m->inversion_real) }}</td>
    <td class="u-mono">{{ number_format($m->impresiones) }}</td>
    <td class="u-mono">{{ number_format($m->clics) }}</td>
    <td class="u-mono">{{ $m->ctr !== null ? $m->ctr.'%' : '—' }}</td>
    <td class="u-mono">{{ $m->cpc !== null ? '$'.$m->cpc : '—' }}</td>
    <td class="u-mono">{{ $m->conversiones }}</td>
    <td class="u-mono">{{ $m->cpl !== null ? '$'.$m->cpl : '—' }}</td>
    <td class="u-mono">{{ $m->cpa !== null ? '$'.$m->cpa : '—' }}</td>
    <td class="u-mono"><strong style="color:{{ ($m->roas ?? 0) >= 5 ? 'var(--text-success)' : (($m->roas ?? 0) >= 3 ? 'var(--text-warning)' : 'inherit') }}">{{ $m->roas !== null ? $m->roas.'x' : '—' }}</strong></td>
    <td>
        <div style="display:flex; gap:4px;">
            <button type="button" class="btn--icon" title="Editar" data-edit-metrica="{{ $m->id }}">
                <i class="fa-solid fa-pen"></i>
            </button>
            <button type="button" class="btn--icon" title="Eliminar" style="color:var(--text-danger);" data-delete-metrica="{{ $m->id }}">
                <i class="fa-solid fa-trash"></i>
            </button>
        </div>
    </td>
</tr>
