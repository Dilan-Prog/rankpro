{{--
    ficha (artboard 06) — las 17 columnas, resueltas.

    Una tabla de 17 columnas en 704px daría 41px por columna: ilegible e
    imposible de cortar por página. Se resuelve en dos pasos, y por eso este
    parcial nunca pinta una tabla ancha.

    (1) Semáforo: derivados.semaforo agrupa los campos en pocas dimensiones —una
        fila por registro, una columna por dimensión—. Es lo único que hay que
        mirar para localizar el problema entre muchas URL de un vistazo.
    (2) Ficha por registro, girada 90°: los campos pasan a ser celdas de ~175px
        en dos filas, con etiqueta, valor y estado propio. Cada ficha es un
        bloque autocontenido con page-break-inside: avoid, así que corta por
        página sin partirse. Ninguna cifra se pierde; cambia el eje.
--}}
@php
    $contenido = $seccion['contenido'];
    $campos = $contenido['campos'] ?? [];
    $registros = $contenido['registros'] ?? [];
    $semaforo = $seccion['derivados']['semaforo'] ?? [];
    $dimensiones = $semaforo['dimensiones'] ?? [];
    $filasSemaforo = $semaforo['filas'] ?? [];

    $estados = ['ok' => 'OK', 'revisar' => 'REV', 'critico' => 'ERR', 'nd' => 'n/d'];
    $rotulo = fn ($e) => $estados[$e] ?? 'n/d';
    $clase = fn ($e) => 'est-'.(array_key_exists((string) $e, $estados) ? $e : 'nd');

    $porFila = 4;
@endphp

@if (!empty($contenido['introduccion']))
    <div class="sec-intro" style="padding-top: 0; font-size: 12px;">{{ $contenido['introduccion'] }}</div>
@endif

@if (!empty($filasSemaforo) && !empty($dimensiones))
    <div class="kicker" style="margin-top: 20px;">1 · SEMÁFORO POR REGISTRO</div>
    <table class="t semaforo" style="margin-top: 8px;">
        <thead>
            <tr>
                <td>{{ mb_strtoupper($contenido['titulo_registro'] ?? 'REGISTRO') }}</td>
                @foreach ($dimensiones as $dim)
                    <td style="width: 70px; text-align: center;">{{ mb_strtoupper($dim['titulo'] ?? $dim['clave'] ?? '') }}</td>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($filasSemaforo as $i => $fila)
                <tr class="{{ $i % 2 ? 'zebra' : '' }}">
                    <td>{{ $fila['titulo'] ?? '—' }}</td>
                    @foreach ($dimensiones as $dim)
                        @php $e = $fila['estados'][$dim['clave'] ?? ''] ?? 'nd'; @endphp
                        <td class="sm-est">
                            <table style="width: 44px; margin: 0 auto;"><tr>
                                <td class="est {{ $clase($e) }}" style="border-bottom: none; padding: 3px 0;">{{ $rotulo($e) }}</td>
                            </tr></table>
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
    <div class="leyenda-semaforo">
        <b style="color: #0F9D6E;">OK</b> cumple ·
        <b style="color: #B45309;">REV</b> revisar ·
        <b style="color: #EF4444;">ERR</b> incumple ·
        <b style="color: #94A3B8;">n/d</b> sin dato
    </div>
@endif

@if (empty($registros))
    <div class="empty">Sin registros capturados.</div>
@else
    <div class="kicker">2 · FICHA COMPLETA · {{ count($campos) }} CAMPOS POR REGISTRO</div>

    @foreach ($registros as $registro)
        @php
            $valores = is_array($registro['valores'] ?? null) ? $registro['valores'] : [];
            $grupos = array_chunk($campos, $porFila);
            $estadoRegistro = $registro['estado'] ?? '';
        @endphp
        <table class="ficha">
            <tr>
                <td class="fi-cab" colspan="{{ $porFila }}">
                    <table class="w">
                        <tr>
                            <td class="fi-url">{{ $registro['titulo'] ?? '—' }}</td>
                            <td class="fi-veredicto">
                                @if (($registro['veredicto'] ?? '') !== '' || $estadoRegistro !== '')
                                    <span class="est {{ $clase($estadoRegistro) }}" style="padding: 3px 7px;">{{ mb_strtoupper($registro['veredicto'] ?? $rotulo($estadoRegistro)) }}</span>
                                @endif
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
            @foreach ($grupos as $grupo)
                <tr>
                    @foreach ($grupo as $campo)
                        @php
                            $bruto = $valores[$campo['clave'] ?? ''] ?? null;
                            $valor = is_array($bruto) ? ($bruto['valor'] ?? null) : $bruto;
                            $estado = is_array($bruto) ? ($bruto['estado'] ?? '') : '';
                        @endphp
                        <td class="fi-campo" style="width: {{ (int) floor(100 / $porFila) }}%;">
                            <div class="fi-k">{{ mb_strtoupper($campo['titulo'] ?? $campo['clave'] ?? '') }}</div>
                            <div class="fi-v">
                                @if ($valor === null || $valor === '')<span class="nd">n/d</span>@else{{ is_scalar($valor) ? $valor : '—' }}@endif
                            </div>
                            @if ($estado !== '')
                                <div class="fi-s fi-s-{{ array_key_exists((string) $estado, $estados) ? $estado : 'nd' }}">{{ $rotulo($estado) }}</div>
                            @endif
                        </td>
                    @endforeach
                    @for ($i = count($grupo); $i < $porFila; $i++)
                        <td class="fi-campo" style="width: {{ (int) floor(100 / $porFila) }}%;"></td>
                    @endfor
                </tr>
            @endforeach
        </table>
    @endforeach
@endif

@if (!empty($contenido['nota']))
    <div class="nota">{{ $contenido['nota'] }}</div>
@endif
