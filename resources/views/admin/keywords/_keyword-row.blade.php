{{--
    One keyword <tr>, shared by a lista's sub-table (built inline in
    _lista-row.blade.php) and the "Sin lista asignada" table in
    index.blade.php — mirrors admin.seo._posicion-row/_contenido-row's
    single-row-partial convention. $k is a Keyword::toRow() array.
    The data-* attributes on the row are read directly by keywords.js's
    recomputeListaAggregates() so it doesn't need to re-parse data-keyword
    JSON for every row on every keyword mutation. keywordRowCellsHtml() en
    keywords.js reconstruye este mismo marcado tras un guardado AJAX.
--}}
@php
    $delta = ($k['posicion_actual'] !== null && $k['posicion_anterior'] !== null)
        ? $k['posicion_anterior'] - $k['posicion_actual']
        : null;
@endphp
<tr class="is-clickable" data-keyword-row data-keyword-id="{{ $k['id'] }}"
    data-lista-id="{{ $k['lista_id'] ?? '' }}"
    data-cliente-id="{{ $k['cliente_id'] }}"
    data-volumen="{{ $k['volumen_busqueda'] ?? 0 }}"
    data-dificultad="{{ $k['dificultad'] ?? '' }}"
    data-cpc="{{ $k['cpc_estimado'] ?? '' }}"
    data-posicion="{{ $k['posicion_actual'] ?? '' }}"
    data-herramienta="{{ $k['herramienta_origen'] ?? '' }}"
    data-keyword="{{ json_encode($k) }}">
    <td><button type="button" class="kwx-kw-btn" data-open-keyword-detail title="Ver ficha de la keyword">{{ $k['keyword'] }}</button></td>
    <td><span class="kwx-tenue">{{ \App\Support\Labels::tipoKeyword($k['tipo']) }}</span></td>
    <td class="u-mono">{{ number_format($k['volumen_busqueda'] ?? 0) }}</td>
    <td>@include('admin.keywords._kd', ['valor' => $k['dificultad']])</td>
    <td class="u-mono kwx-verde">{{ $k['cpc_estimado'] > 0 ? '$'.number_format($k['cpc_estimado'], 2) : '—' }}</td>
    <td><span class="kwx-tenue">{{ \App\Support\Labels::intencion($k['intencion']) }}</span></td>
    <td>@if (! empty($k['url_asignada']))<span class="u-mono kwx-url" title="{{ $k['url_asignada'] }}">{{ $k['url_asignada'] }}</span>@else<span class="kwx-nulo">—</span>@endif</td>
    <td class="u-mono">
        @if ($k['posicion_actual'])
            <span class="kwx-pos"><strong>#{{ $k['posicion_actual'] }}</strong>@if ($delta !== null) <span class="kwx-pos__delta" title="Cambio frente a la medición anterior">({{ $delta > 0 ? '+' : '' }}{{ $delta }})</span>@endif</span>
        @else
            <span class="kwx-nulo">—</span>
        @endif
    </td>
    <td><span class="kwx-tenue">{{ \App\Support\Labels::herramientaOrigen($k['herramienta_origen']) }}</span></td>
    <td>
        <div class="kwx-acciones">
            <button type="button" class="kwx-icono" title="Editar" aria-label="Editar {{ $k['keyword'] }}" data-edit-keyword="{{ $k['id'] }}">
                <i class="fa-solid fa-pen"></i>
            </button>
            <button type="button" class="kwx-icono kwx-icono--peligro" title="Eliminar" aria-label="Eliminar {{ $k['keyword'] }}" data-delete-keyword="{{ $k['id'] }}">
                <i class="fa-solid fa-trash"></i>
            </button>
        </div>
    </td>
</tr>
