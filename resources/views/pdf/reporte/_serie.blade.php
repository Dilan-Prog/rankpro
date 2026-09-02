{{--
    serie (artboard 03) — el gráfico sin SVG.

    Una tabla de N celdas con vertical-align: bottom; cada celda lleva un par de
    divs de pocos px de ancho cuya `height` en px la calcula el Armador
    (derivados.barras[*].valores[clave].altura, sobre un alto de gráfico de
    216 px). Blade sólo interpola la altura: aquí no se escala nada.

    La rejilla no puede cruzar las barras —dompdf no compone capas— así que vive
    en el rail de ticks de la izquierda: cuatro filas de 54px con
    border-top: 1px dotted. El eje X son las 7 etiquetas de derivados.etiquetas_x
    en una tabla de anchos fijos, no una por columna. El doble eje se declara en
    la leyenda, que es donde se resuelve la ambigüedad de escala.
--}}
@php
    $fmt = app(App\Services\Reportes\Armador::class);
    $contenido = $seccion['contenido'];
    $derivados = $seccion['derivados'] ?? [];

    $barras = $derivados['barras'] ?? [];
    $ejes = $derivados['ejes'] ?? [];
    $etiquetasX = $derivados['etiquetas_x'] ?? [];
    $agregado = $derivados['agregado'] ?? [];
    $totales = $derivados['totales'] ?? [];

    // Títulos por clave: los trae contenido.series; si falta, se usa la clave.
    $titulos = [];
    foreach ($contenido['series'] ?? [] as $s) {
        $titulos[$s['clave'] ?? ''] = $s['titulo'] ?? ($s['clave'] ?? '');
    }

    // Orden de pintado: primero el eje izquierdo (serie principal, verde marca),
    // después el derecho (secundaria, verde al 20% para que no compita).
    $trazos = [];
    foreach (['izq', 'der'] as $lado) {
        foreach ($ejes[$lado]['claves'] ?? [] as $clave) {
            $trazos[] = [
                'clave' => $clave,
                'lado' => $lado,
                'maximo' => $ejes[$lado]['maximo'] ?? null,
                'titulo' => $titulos[$clave] ?? $clave,
                'color' => count($trazos) === 0 ? 'bar-1' : (count($trazos) === 1 ? 'bar-2' : 'bar-3'),
            ];
        }
    }

    // Rail de ticks: cuatro marcas del eje de mayor recorrido, de arriba abajo.
    $maxRail = 0.0;
    $ejeRail = null;
    foreach ($trazos as $t) {
        if ((float) $t['maximo'] > $maxRail) {
            $maxRail = (float) $t['maximo'];
            $ejeRail = $t;
        }
    }
    $ticks = $maxRail > 0 ? [$maxRail, $maxRail * 0.75, $maxRail * 0.5, $maxRail * 0.25] : [];

    // Ancho de barra: dos barras y su separación tienen que caber en la columna.
    $n = max(1, count($barras));
    $anchoCol = 668 / $n;
    $nTrazos = max(1, count($trazos));
    $anchoBarra = max(1, min(5, (int) floor(($anchoCol - 3) / $nTrazos)));

    $unidad = $contenido['etiqueta_x'] ?: 'registro';
@endphp

@if (empty($barras) || empty($trazos))
    <div class="empty">Sin datos de serie capturados.</div>
@else
    <table class="w serie-leyenda">
        <tr>
            @foreach ($trazos as $t)
                <td style="width: 16px; vertical-align: middle; padding-top: 20px;"><div class="chip {{ $t['color'] }}"></div></td>
                <td style="font-size: 11px; color: #64748B; padding: 20px 26px 0 0; vertical-align: middle;">
                    {{ $t['titulo'] }} (eje {{ $t['lado'] === 'izq' ? 'izq.' : 'der.' }}@if ($t['maximo'] !== null), máx. {{ $fmt->formatear($t['maximo'], 'numero') }}@endif)
                </td>
            @endforeach
            <td class="sl-r">{{ count($barras) }} {{ $unidad }}s · 1 columna = 1 {{ $unidad }}</td>
        </tr>
    </table>

    <table class="grafico avoid">
        <tr>
            <td class="rail">
                <table class="rail-t">
                    @foreach ($ticks as $i => $tick)
                        <tr><td class="{{ $i > 0 ? 'tick' : '' }}">{{ $fmt->formatear($tick, 'numero') }}</td></tr>
                    @endforeach
                </table>
            </td>
            <td class="lienzo">
                <table class="barras">
                    <tr>
                        @foreach ($barras as $barra)
                            <td style="width: {{ number_format(100 / $n, 4, '.', '') }}%;">
                                <table class="par"><tr>
                                    @foreach ($trazos as $t)
                                        @php $altura = (int) ($barra['valores'][$t['clave']]['altura'] ?? 0); @endphp
                                        <td style="padding-right: {{ $loop->last ? 0 : 1 }}px;">
                                            <div class="bar {{ $t['color'] }}" style="width: {{ $anchoBarra }}px; height: {{ max(0, $altura) }}px;"></div>
                                        </td>
                                    @endforeach
                                </tr></table>
                            </td>
                        @endforeach
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td></td>
            <td>
                <table class="w eje-x">
                    <tr>
                        @foreach ($etiquetasX as $etiqueta)
                            <td class="{{ $loop->last ? 'ex-fin' : '' }}" style="width: {{ number_format(100 / max(1, count($etiquetasX)), 4, '.', '') }}%;">{{ $etiqueta['label'] ?? '' }}</td>
                        @endforeach
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    @if (!empty($agregado))
        @php
            $conDias = (bool) array_filter($agregado, fn ($a) => ($a['dias'] ?? null) !== null);
            $conPeso = (bool) array_filter($agregado, fn ($a) => ($a['peso'] ?? null) !== null);
        @endphp
        <div class="kicker">AGREGADO</div>
        <table class="t" style="margin-top: 8px;">
            <thead>
                <tr>
                    <td style="width: 150px;">PERIODO</td>
                    @foreach ($trazos as $t)
                        <td style="width: 76px; text-align: right;">{{ mb_strtoupper($t['titulo']) }}</td>
                    @endforeach
                    @if ($conDias)<td style="width: 70px; text-align: right;">DÍAS</td>@endif
                    @if ($conPeso)<td>PESO</td>@endif
                </tr>
            </thead>
            <tbody>
                @foreach ($agregado as $i => $a)
                    <tr class="{{ $i % 2 ? 'zebra' : '' }}">
                        <td style="font-size: 11px;">{{ $a['label'] ?? '—' }}</td>
                        @foreach ($trazos as $t)
                            @php $v = $a['valores'][$t['clave']] ?? null; @endphp
                            <td class="num" style="font-size: 11px;">
                                @if ($v === null || $v === '')<span class="nd">n/d</span>@else{{ $fmt->formatear($v, 'numero') }}@endif
                            </td>
                        @endforeach
                        @if ($conDias)
                            <td class="num" style="font-size: 11px; color: #64748B;">{{ $a['dias'] ?? '—' }}</td>
                        @endif
                        @if ($conPeso)
                            <td>
                                <div class="barra-fondo" style="height: 8px;">
                                    <div class="barra-llena" style="height: 8px; width: {{ max(0, min(100, (float) ($a['peso'] ?? 0))) }}%;"></div>
                                </div>
                            </td>
                        @endif
                    </tr>
                @endforeach
                <tr class="total">
                    <td>TOTAL</td>
                    @foreach ($trazos as $t)
                        <td style="text-align: right;">{{ $fmt->formatear($totales[$t['clave']] ?? null, 'numero') }}</td>
                    @endforeach
                    @if ($conDias)<td></td>@endif
                    @if ($conPeso)<td></td>@endif
                </tr>
            </tbody>
        </table>
    @endif

    @if (!empty($contenido['lectura']))
        @php
            $parrafos = array_values(array_filter(array_map('trim', preg_split('/\r\n\r\n|\n\n|\r\n|\n/', (string) $contenido['lectura'])), fn ($p) => $p !== ''));
            $mitad = (int) ceil(count($parrafos) / 2);
        @endphp
        <div class="kicker">LECTURA</div>
        <table class="w lectura avoid">
            <tr>
                <td style="width: 50%;">
                    @foreach (array_slice($parrafos, 0, $mitad) as $p)
                        {{ $p }}@if (!$loop->last)<br><br>@endif
                    @endforeach
                </td>
                <td style="width: 50%; padding-right: 0;">
                    @foreach (array_slice($parrafos, $mitad) as $p)
                        {{ $p }}@if (!$loop->last)<br><br>@endif
                    @endforeach
                </td>
            </tr>
        </table>
    @endif
@endif
