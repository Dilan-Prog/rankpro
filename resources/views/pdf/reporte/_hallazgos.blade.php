{{--
    hallazgos (artboard 02). Cada hallazgo es una tabla de tres celdas
    —número · badge de severidad · título + evidencia— con border-bottom. Se
    repite sin límite y corta por página sin romperse porque cada bloque es
    autocontenido.

    El badge de severidad usa contorno + fondo tenue: aparece pocas veces por
    reporte y puede permitirse esa tinta (regla del artboard 09).
    derivados.items ya viene ordenado y con 'indice'.
--}}
@php
    $items = $seccion['derivados']['items'] ?? [];
    $severidades = ['critico', 'alto', 'medio', 'informativo'];
@endphp

@if (empty($items))
    <div class="empty">Sin hallazgos registrados.</div>
@else
    <div class="kicker" style="margin-top: 8px;">HALLAZGOS · {{ count($items) }} CLASIFICADOS POR IMPACTO</div>

    @foreach ($items as $item)
        @php $sev = in_array($item['severidad'] ?? '', $severidades, true) ? $item['severidad'] : ''; @endphp
        <table class="hallazgo avoid">
            <tr>
                <td class="h-n">{{ $item['indice'] ?? $loop->iteration }}</td>
                <td class="h-sev">
                    <span class="sev {{ $sev !== '' ? 'sev-'.$sev : '' }}">{{ mb_strtoupper($item['severidad_label'] ?? 'SIN CLASIFICAR') }}</span>
                </td>
                <td class="h-txt">
                    <div class="h-titulo">{{ $item['titulo'] ?? '—' }}</div>
                    @if (!empty($item['evidencia']))
                        <div class="h-evidencia">Evidencia: {{ $item['evidencia'] }}</div>
                    @endif
                </td>
            </tr>
        </table>
    @endforeach
@endif

@if (!empty($seccion['contenido']['nota']))
    <div class="nota">{{ $seccion['contenido']['nota'] }}</div>
@endif
