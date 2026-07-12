<tr data-posicion-id="{{ $p->id }}">
    <td>{{ $p->keyword }}</td>
    <td><span class="u-mono" style="color:var(--color-primary); font-size:var(--text-xs)">{{ $p->url_pagina ?? '—' }}</span></td>
    <td class="u-mono"><strong style="color:{{ $p->posicion_actual <= 3 ? 'var(--text-success)' : ($p->posicion_actual <= 10 ? 'var(--text-warning)' : 'inherit') }}">#{{ $p->posicion_actual ?? '—' }}</strong></td>
    <td class="u-mono" style="color:var(--color-muted-foreground)">#{{ $p->posicion_anterior ?? '—' }}</td>
    <td class="u-mono" style="color:{{ $p->variacion > 0 ? 'var(--text-success)' : ($p->variacion < 0 ? 'var(--text-danger)' : 'inherit') }}">{{ $p->variacion > 0 ? '+' : '' }}{{ $p->variacion }}</td>
    <td class="u-mono">{{ number_format($p->volumen_busqueda ?? 0) }}</td>
    <td class="u-mono">{{ $p->dificultad_keyword ?? '—' }}</td>
    <td><span style="font-size:var(--text-xs); color:var(--color-muted-foreground); text-transform:capitalize;">{{ $p->dispositivo }}</span></td>
    <td>
        <button type="button" class="btn--icon" title="Eliminar" style="color:var(--text-danger);" data-delete-posicion="{{ $p->id }}">
            <i class="fa-solid fa-trash"></i>
        </button>
    </td>
</tr>
