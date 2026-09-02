{{--
    tabla (artboards 04 y 05).

    · Zebra tenue (#F9FAFC) emitida fila por fila con $i % 2 desde Blade, nunca
      con :nth-child, que dompdf resuelve mal. Sólo líneas horizontales de 1px.
    · El <thead> se repite solo en cada página: el encabezado negro reaparece al
      cortar sin que haya que decírselo a dompdf.
    · Fila destacada: fondo #ECFBF4, filete izquierdo verde de 3px y primera
      celda en negrita. La marca la trae la fila, no se decide aquí.
    · Valor ausente: n/d en gris, nunca celda vacía ni 0.
    · Filas excluidas: bloque aparte tras una banda con la nota, con cuatro
      señales simultáneas —gris #94A3B8, tachado, borde inferior discontinuo y
      filete izquierdo gris— más el rótulo EXCLUIDA; el motivo va junto al
      valor, no en una columna nueva. Su TOTAL EXCLUIDO lleva filete de 1px y
      gris, jerárquicamente por debajo del TOTAL COMPUTADO de 2px.

    Aquí no se suma nada: los totales llegan hechos en derivados.
--}}
@php
    $fmt = app(App\Services\Reportes\Armador::class);
    $contenido = $seccion['contenido'];
    $columnas = $contenido['columnas'] ?? [];
    $derivados = $seccion['derivados'] ?? [];
    $filas = $contenido['filas'] ?? [];
    $excluidas = $contenido['filas_excluidas'] ?? [];
    $totales = $derivados['totales'] ?? [];
    $totalesExcluidas = $derivados['totales_excluidas'] ?? [];

    $numericos = ['numero', 'decimal', 'porcentaje', 'moneda'];
    $anchos = ['numero' => 56, 'decimal' => 56, 'porcentaje' => 60, 'moneda' => 72, 'fecha' => 72, 'nivel' => 76];

    // Con `table-layout: fixed` (ver reporte.blade.php) manda el ancho declarado,
    // asi que hay que declararlos todos: lo que no se declare se reparte a
    // partes iguales, y la primera columna —la que identifica la fila, casi
    // siempre una URL o una consulta— necesita mas sitio que las demas. De ahi
    // que pese el doble al repartir el espacio sobrante.
    $anchoCaja = 704;
    $columnasTexto = [];
    $ocupado = 0;

    foreach ($columnas as $i => $col) {
        $t = $col['tipo'] ?? 'texto';
        if (isset($anchos[$t])) {
            $ocupado += $anchos[$t];
        } else {
            $columnasTexto[] = $i;
        }
    }

    $pesos = [];
    foreach ($columnasTexto as $n => $i) {
        $pesos[$i] = $n === 0 ? 2 : 1;
    }

    $sumaPesos = array_sum($pesos) ?: 1;
    $libre = max(120, $anchoCaja - $ocupado);

    $anchoDe = function (int $i, array $col) use ($anchos, $pesos, $libre, $sumaPesos) {
        $t = $col['tipo'] ?? 'texto';

        return isset($anchos[$t])
            ? $anchos[$t]
            : (int) floor($libre * ($pesos[$i] ?? 1) / $sumaPesos);
    };

    $alineacion = function (array $col) use ($numericos) {
        $a = $col['alineacion'] ?? '';
        if ($a === 'derecha') return 'right';
        if ($a === 'centro') return 'center';
        if ($a === 'izquierda') return 'left';
        return in_array($col['tipo'] ?? 'texto', array_merge($numericos, ['nivel']), true) ? 'right' : 'left';
    };

    $celda = function ($valor, string $tipo) use ($fmt) {
        if ($valor === null || $valor === '' || (is_array($valor) && $valor === [])) {
            return null;
        }
        $pintado = $fmt->formatear($valor, $tipo);

        return $pintado === '—' ? null : $pintado;
    };

    $claseNivel = function ($valor) {
        return match (mb_strtoupper(trim((string) $valor))) {
            'ALTA', 'ALTO' => 'opp-alta',
            'MEDIA', 'MEDIO' => 'opp-media',
            'BAJA', 'BAJO' => 'opp-baja',
            default => '',
        };
    };

    $rotuloFilas = $columnas ? mb_strtolower((string) ($columnas[0]['titulo'] ?? 'filas')) : 'filas';
    $conteo = (int) ($derivados['conteo'] ?? count($filas));
    $conteoExcl = (int) ($derivados['conteo_excluidas'] ?? count($excluidas));
    $plural = fn (int $n, string $s) => $n === 1 || str_ends_with($s, 's') ? $s : $s.'s';
    $hayTotales = !empty(array_filter($totales, fn ($v) => $v !== null && $v !== ''));
    $hayTotalesExcl = !empty(array_filter($totalesExcluidas, fn ($v) => $v !== null && $v !== ''));
    $ultima = count($columnas) - 1;
@endphp

@if (empty($columnas))
    <div class="empty">Esta tabla no tiene columnas configuradas.</div>
@elseif (empty($filas) && empty($excluidas))
    <div class="empty">Sin filas capturadas.</div>
@else
    @if (!empty($contenido['introduccion']))
        <div class="sec-intro" style="padding-top: 0; font-size: 12px;">{{ $contenido['introduccion'] }}</div>
    @endif

    <table class="t" style="margin-top: 14px;">
        <thead>
            <tr>
                @foreach ($columnas as $i => $col)
                    <td style="text-align: {{ $alineacion($col) }}; width: {{ $anchoDe($i, $col) }}px;">{{ mb_strtoupper($col['titulo'] ?? $col['clave'] ?? '') }}</td>
                @endforeach
            </tr>
        </thead>
        <tbody>
            {{-- Filas computadas --}}
            @foreach ($filas as $i => $fila)
                @php
                    $valores = is_array($fila['valores'] ?? null) ? $fila['valores'] : $fila;
                    $destacada = (bool) ($fila['destacada'] ?? false);
                @endphp
                <tr class="{{ $destacada ? 'destacada' : ($i % 2 ? 'zebra' : '') }}">
                    @foreach ($columnas as $c => $col)
                        @php
                            $tipo = $col['tipo'] ?? 'texto';
                            $texto = $celda($valores[$col['clave'] ?? ''] ?? null, $tipo);
                            $esNum = in_array($tipo, $numericos, true);
                        @endphp
                        <td class="{{ $c === 0 ? 'c0' : '' }} {{ $esNum ? 'num' : '' }} {{ !$esNum && $tipo !== 'nivel' && $c > 0 ? 'txt2' : '' }}"
                            style="text-align: {{ $alineacion($col) }};">
                            @if ($texto === null)
                                <span class="nd">n/d</span>
                            @elseif ($tipo === 'nivel')
                                <span class="opp {{ $claseNivel($texto) }}">{{ mb_strtoupper($texto) }}</span>
                            @else
                                {{ $texto }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach

            {{-- TOTAL COMPUTADO --}}
            @if ($hayTotales)
                <tr class="total">
                    @foreach ($columnas as $c => $col)
                        @php
                            $clave = $col['clave'] ?? '';
                            $tieneTotal = array_key_exists($clave, $totales) && $totales[$clave] !== null && $totales[$clave] !== '';
                        @endphp
                        <td style="text-align: {{ $c === 0 ? 'left' : $alineacion($col) }};">
                            @if ($c === 0 && !$tieneTotal)
                                {{ $excluidas ? 'TOTAL COMPUTADO' : 'TOTAL' }} · {{ $conteo }} {{ $plural($conteo, $rotuloFilas) }}
                            @elseif ($tieneTotal)
                                {{ $fmt->formatear($totales[$clave], $col['tipo'] ?? 'texto') }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endif

            {{-- Bloque de filas excluidas --}}
            @if (!empty($excluidas))
                <tr class="banda-excluidas">
                    <td colspan="{{ count($columnas) }}">
                        <table class="w">
                            <tr>
                                <td class="be-l">FILAS EXCLUIDAS · NO SUMAN AL TOTAL</td>
                                <td class="be-r">{{ $contenido['nota_excluidas'] ?: 'Criterio de exclusión declarado en la sección Metodología' }}</td>
                            </tr>
                        </table>
                    </td>
                </tr>

                @foreach ($excluidas as $fila)
                    @php
                        $valores = is_array($fila['valores'] ?? null) ? $fila['valores'] : $fila;
                        $motivo = trim((string) ($fila['motivo'] ?? ''));
                    @endphp
                    <tr class="excluida">
                        @foreach ($columnas as $c => $col)
                            @php
                                $tipo = $col['tipo'] ?? 'texto';
                                $texto = $celda($valores[$col['clave'] ?? ''] ?? null, $tipo);
                                $esNum = in_array($tipo, $numericos, true);
                                $rotuloAqui = $c === $ultima && !$esNum;
                            @endphp
                            <td class="{{ $c === 0 ? 'c0' : '' }} {{ $esNum ? 'num' : '' }}"
                                style="text-align: {{ $rotuloAqui ? 'right' : $alineacion($col) }};">
                                @if ($rotuloAqui)
                                    <span class="tag-excluida">EXCLUIDA</span>
                                @elseif ($texto === null)
                                    <span class="nd">n/d</span>
                                @elseif ($c === 0)
                                    <span class="tachado">{{ $texto }}</span>@if ($motivo !== '') <span class="motivo">· {{ $motivo }}</span>@endif
                                @else
                                    {{ $texto }}
                                @endif
                                @if ($c === $ultima && $esNum)
                                    <div class="tag-excluida">EXCLUIDA</div>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach

                @if ($hayTotalesExcl)
                    <tr class="total-excluido">
                        @foreach ($columnas as $c => $col)
                            @php
                                $clave = $col['clave'] ?? '';
                                $tieneTotal = array_key_exists($clave, $totalesExcluidas) && $totalesExcluidas[$clave] !== null && $totalesExcluidas[$clave] !== '';
                            @endphp
                            <td style="text-align: {{ $c === 0 ? 'left' : $alineacion($col) }};">
                                @if ($c === 0 && !$tieneTotal)
                                    TOTAL EXCLUIDO · {{ $conteoExcl }} {{ $plural($conteoExcl, $rotuloFilas) }}
                                @elseif ($tieneTotal)
                                    {{ $fmt->formatear($totalesExcluidas[$clave], $col['tipo'] ?? 'texto') }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endif
            @endif
        </tbody>
    </table>

    @if (!empty($contenido['nota']))
        <table class="w avoid" style="margin-top: 22px; border-top: 2px solid #1A2332;">
            <tr><td style="padding: 11px 0 0; font-size: 11px; color: #64748B; line-height: 1.55;">{{ $contenido['nota'] }}</td></tr>
        </table>
    @endif
@endif
