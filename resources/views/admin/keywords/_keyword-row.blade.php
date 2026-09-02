{{--
    One keyword <tr>, shared by a lista's sub-table (built inline in
    _lista-row.blade.php) and the "Sin lista asignada" table in
    index.blade.php — mirrors admin.seo._posicion-row/_contenido-row's
    single-row-partial convention. $k is a Keyword::toRow() array.
    The data-* attributes on the row are read directly by keywords.js's
    recomputeListaAggregates() so it doesn't need to re-parse data-keyword
    JSON for every row on every keyword mutation.
--}}
@php
    $delta = ($k['posicion_actual'] !== null && $k['posicion_anterior'] !== null)
        ? $k['posicion_anterior'] - $k['posicion_actual']
        : null;
    $deltaColor = $delta === null
        ? 'var(--color-muted-foreground)'
        : ($delta > 0 ? 'var(--text-success)' : ($delta < 0 ? 'var(--text-danger)' : 'var(--color-muted-foreground)'));
@endphp
<tr data-keyword-row data-keyword-id="{{ $k['id'] }}"
    data-lista-id="{{ $k['lista_id'] ?? '' }}"
    data-cliente-id="{{ $k['cliente_id'] }}"
    data-volumen="{{ $k['volumen_busqueda'] ?? 0 }}"
    data-dificultad="{{ $k['dificultad'] ?? '' }}"
    data-cpc="{{ $k['cpc_estimado'] ?? '' }}"
    data-posicion="{{ $k['posicion_actual'] ?? '' }}"
    data-herramienta="{{ $k['herramienta_origen'] ?? '' }}"
    data-keyword="{{ json_encode($k) }}">
    <td><div style="font-weight:500">{{ $k['keyword'] }}</div></td>
    <td><span style="text-transform:capitalize; font-size:var(--text-xs); color:var(--color-muted-foreground)">{{ \App\Support\Labels::tipoKeyword($k['tipo']) }}</span></td>
    <td class="u-mono">{{ number_format($k['volumen_busqueda'] ?? 0) }}</td>
    <td class="u-mono">{{ $k['dificultad'] ?? '—' }}</td>
    <td class="u-mono" style="color:var(--text-success)">{{ $k['cpc_estimado'] > 0 ? '$'.number_format($k['cpc_estimado'], 2) : '—' }}</td>
    <td><span style="font-size:var(--text-xs); color:var(--color-muted-foreground)">{{ \App\Support\Labels::intencion($k['intencion']) }}</span></td>
    <td><span class="u-mono" style="font-size:var(--text-xs); color:var(--color-primary);">{{ $k['url_asignada'] ?? '—' }}</span></td>
    <td class="u-mono">
        {{ $k['posicion_actual'] ? '#'.$k['posicion_actual'] : '—' }}
        @if ($delta !== null)
            <span style="font-size:var(--text-xs); color:{{ $deltaColor }};">({{ $delta > 0 ? '+' : '' }}{{ $delta }})</span>
        @endif
    </td>
    <td><span style="font-size:var(--text-xs); color:var(--color-muted-foreground)">{{ \App\Support\Labels::herramientaOrigen($k['herramienta_origen']) }}</span></td>
    <td>
        <div style="display:flex; gap:4px;">
            <button type="button" class="btn--icon" title="Editar" data-edit-keyword="{{ $k['id'] }}">
                <i class="fa-solid fa-pen"></i>
            </button>
            <button type="button" class="btn--icon" title="Eliminar" style="color:var(--text-danger);" data-delete-keyword="{{ $k['id'] }}">
                <i class="fa-solid fa-trash"></i>
            </button>
        </div>
    </td>
</tr>
