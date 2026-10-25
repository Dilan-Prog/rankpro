{{--
    Gráfica de área en SVG inline (sin librerías): el reporte mensual ya usa Chart.js, pero aquí
    basta con una serie y así la pestaña pinta aunque el CDN no cargue. Los meses sin dato
    cortan la línea en vez de interpolarse.
--}}
@if (! $serie)
    <div class="empty-state">
        <div class="empty-state__icon"><i class="fa-solid fa-chart-line"></i></div>
        <p class="empty-state__text">Sin tráfico orgánico registrado todavía.</p>
        <button type="button" class="btn btn--secondary" data-tab-link="reporte">Capturar meses en Reporte</button>
    </div>
@else
    @php
        $meses = $serie['meses'];
        $n = count($meses);
        $ancho = 640; $alto = 240; $izq = 46; $der = 14; $arr = 14; $aba = 32;
        $maxValor = max(array_filter(array_column($meses, 'valor'), fn ($v) => $v !== null));
        // Techo "redondo" (1, 2, 5 × 10^n) para que las líneas guía caigan en cifras legibles.
        $potencia = 10 ** (strlen((string) max($maxValor, 1)) - 1);
        $techo = $potencia * 10;
        foreach ([1, 2, 5, 10] as $mult) {
            if ($potencia * $mult >= $maxValor) { $techo = $potencia * $mult; break; }
        }
        $X = fn ($i) => $n === 1 ? $izq + ($ancho - $izq - $der) / 2 : $izq + ($i / ($n - 1)) * ($ancho - $izq - $der);
        $Y = fn ($v) => $arr + (1 - $v / $techo) * ($alto - $arr - $aba);
        $base = $alto - $aba;
        $corto = fn ($v) => $v >= 1000 ? rtrim(rtrim(number_format($v / 1000, 1), '0'), '.').'K' : (string) $v;

        // Tramos contiguos con dato: cada uno es una línea (y un área si tiene 2+ puntos).
        $tramos = []; $actual = [];
        foreach ($meses as $i => $m) {
            if ($m['valor'] === null) {
                if ($actual) { $tramos[] = $actual; $actual = []; }
                continue;
            }
            $actual[] = [$i, $m['valor']];
        }
        if ($actual) { $tramos[] = $actual; }

        $resumenAria = 'Tráfico orgánico mensual de '.$cuenta.': '.collect($meses)->filter(fn ($m) => $m['valor'] !== null)
            ->map(fn ($m) => $m['etiqueta'].' '.$m['anio'].': '.number_format($m['valor']))->implode('; ').'.';
    @endphp
    <div class="seo-grafica">
        <svg class="seo-grafica__svg" viewBox="0 0 {{ $ancho }} {{ $alto }}" role="img" aria-label="{{ $resumenAria }}" preserveAspectRatio="xMidYMid meet">
            <title>Tráfico orgánico — {{ $cuenta }}</title>
            @for ($g = 0; $g <= 4; $g++)
                @php $valorGuia = $techo * $g / 4; $yg = round($Y($valorGuia), 1); @endphp
                <line class="seo-grafica__guia" x1="{{ $izq }}" x2="{{ $ancho - $der }}" y1="{{ $yg }}" y2="{{ $yg }}" />
                <text class="seo-grafica__eje" x="{{ $izq - 8 }}" y="{{ $yg + 4 }}" text-anchor="end">{{ $corto((int) $valorGuia) }}</text>
            @endfor
            @foreach ($meses as $i => $m)
                @php $anioNuevo = $i === 0 || $m['anio'] !== $meses[$i - 1]['anio']; @endphp
                <text class="seo-grafica__eje" x="{{ round($X($i), 1) }}" y="{{ $alto - 10 }}" text-anchor="middle">{{ $m['etiqueta'] }}{{ $anioNuevo ? ' '.substr((string) $m['anio'], -2) : '' }}</text>
            @endforeach
            @foreach ($tramos as $tramo)
                @if (count($tramo) > 1)
                    @php
                        $linea = collect($tramo)->map(fn ($p, $k) => ($k === 0 ? 'M' : 'L').round($X($p[0]), 1).' '.round($Y($p[1]), 1))->implode(' ');
                        $area = $linea.' L'.round($X(end($tramo)[0]), 1).' '.$base.' L'.round($X($tramo[0][0]), 1).' '.$base.' Z';
                    @endphp
                    <path class="seo-grafica__area" d="{{ $area }}" />
                    <path class="seo-grafica__linea" d="{{ $linea }}" />
                @endif
            @endforeach
            @foreach ($meses as $i => $m)
                @if ($m['valor'] !== null)
                    <circle class="seo-grafica__punto" cx="{{ round($X($i), 1) }}" cy="{{ round($Y($m['valor']), 1) }}" r="3.5"><title>{{ $m['etiqueta'] }} {{ $m['anio'] }}: {{ number_format($m['valor']) }} visitas orgánicas</title></circle>
                @endif
            @endforeach
        </svg>
    </div>
@endif
