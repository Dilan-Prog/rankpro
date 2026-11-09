{{--
    Cuerpo del resumen para el cliente: 3 logros, 3 retos, próximos pasos y una gráfica clave.
    Recibe $resumen = derivados de Armador (logros/retos/proximos_pasos: lista de textos;
    grafica: {titulo, series, barras}). No calcula nada: las alturas ya vienen del Armador.
    Lo usan el PDF completo, el PDF de 1 página y la vista pública.
--}}
@php
    $logros = $resumen['logros'] ?? [];
    $retos = $resumen['retos'] ?? [];
    $pasos = $resumen['proximos_pasos'] ?? [];
    $g = $resumen['grafica'] ?? [];
    $barras = $g['barras'] ?? [];
    $series = $g['series'] ?? [];
    $hayGrafica = ! empty($barras) && ! empty($series);
    $nBarras = max(1, count($barras));
@endphp

@if (! empty($resumen['aviso']['texto']))
    <div class="rc-aviso">
        <b>DATO DESACTUALIZADO</b>
        {{ $resumen['aviso']['texto'] }}
        @if (! empty($resumen['aviso']['fecha_label']))({{ $resumen['aviso']['fecha_label'] }})@endif
    </div>
@endif

<table class="rc-cols" style="margin-top: 14px;">
    <tr>
        <td class="rc-logros">
            <div class="rc-bloque-titulo">LOGROS DEL MES</div>
            @forelse ($logros as $texto)
                <div class="rc-item">{{ $texto }}</div>
            @empty
                <div class="rc-vacio">Sin logros redactados.</div>
            @endforelse
        </td>
        <td class="rc-der rc-retos">
            <div class="rc-bloque-titulo">RETOS</div>
            @forelse ($retos as $texto)
                <div class="rc-item">{{ $texto }}</div>
            @empty
                <div class="rc-vacio">Sin retos redactados.</div>
            @endforelse
        </td>
    </tr>
</table>

@if (! empty($pasos))
    <div class="rc-pasos">
        <div class="rc-bloque-titulo">PRÓXIMOS PASOS</div>
        @foreach ($pasos as $i => $texto)
            <div class="rc-item"><span class="rc-num">{{ $i + 1 }}.</span> {{ $texto }}</div>
        @endforeach
    </div>
@endif

@if ($hayGrafica)
    <div class="rc-graf-titulo">{{ mb_strtoupper($g['titulo'] ?: 'EVOLUCIÓN') }}</div>
    <div class="rc-leyenda">
        @foreach ($series as $i => $serie)
            <span class="rc-chip rc-chip-{{ $i + 1 }}"></span>{{ $serie['titulo'] }}@if (! $loop->last) &nbsp;&nbsp; @endif
        @endforeach
    </div>

    <table class="rc-barras">
        <tr>
            @foreach ($barras as $barra)
                <td style="width: {{ number_format(100 / $nBarras, 4, '.', '') }}%;">
                    <table class="rc-par"><tr>
                        @foreach ($series as $i => $serie)
                            @php $altura = $barra['valores'][$serie['clave']]['altura'] ?? null; @endphp
                            <td>@if ($altura !== null)<div class="rc-bar rc-bar-{{ $i + 1 }}" style="height: {{ (int) $altura }}px;"></div>@endif</td>
                        @endforeach
                    </tr></table>
                </td>
            @endforeach
        </tr>
    </table>

    <table class="rc-valores">
        <tr>
            @foreach ($barras as $barra)
                <td class="rc-x" style="width: {{ number_format(100 / $nBarras, 4, '.', '') }}%;">{{ $barra['x'] }}</td>
            @endforeach
        </tr>
        @foreach ($series as $serie)
            <tr>
                @foreach ($barras as $barra)
                    <td>{{ $barra['valores'][$serie['clave']]['texto'] ?? '' }}</td>
                @endforeach
            </tr>
        @endforeach
    </table>
@endif
