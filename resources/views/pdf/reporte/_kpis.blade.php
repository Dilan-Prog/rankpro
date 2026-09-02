{{--
    kpis (artboard 02). Dos escalones: los principales en 38px con su
    comparativa, y los secundarios en una banda de superficie #F5F7FA. El
    Armador ya los separa por el flag `destacado` en derivados.principales y
    derivados.secundarios; aquí no se decide qué es principal.

    Debajo, el bloque de ALCANCE: filete superior de 2px y mono en versalitas,
    sin fondo de color, para que lea como nota al margen sin competir con las
    cifras. Sale de $reporte['notas_alcance'], que es del reporte, no de la
    sección.
--}}
@php
    $fmt = app(App\Services\Reportes\Armador::class);
    $derivados = $seccion['derivados'] ?? [];
    $principales = $derivados['principales'] ?? [];
    $secundarios = $derivados['secundarios'] ?? [];

    if (empty($principales) && empty($secundarios)) {
        $principales = $seccion['contenido']['items'] ?? [];
    }

    $vacio = fn ($v) => $v === null || $v === '' || (is_array($v) && $v === []);
    $pinta = function ($item) use ($fmt, $vacio) {
        return $vacio($item['valor'] ?? null) ? null : $fmt->formatear($item['valor'], $item['formato'] ?? 'texto');
    };

    $filasP = array_chunk($principales, 4);
    $filasS = array_chunk($secundarios, 5);

    $notas = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) ($reporte['notas_alcance'] ?? ''))), fn ($l) => $l !== ''));
    $mitad = (int) ceil(count($notas) / 2);
    $notasIzq = array_slice($notas, 0, $mitad);
    $notasDer = array_slice($notas, $mitad);
@endphp

@if (!empty($seccion['contenido']['introduccion']))
    <div class="sec-intro" style="padding-top: 0;">{{ $seccion['contenido']['introduccion'] }}</div>
@endif

@if (empty($principales) && empty($secundarios))
    <div class="empty">Sin indicadores capturados.</div>
@else
    @if ($reporte['comparativa_label'])
        <div class="kicker" style="margin-top: 8px;">INDICADORES PRINCIPALES · VS. {{ mb_strtoupper($reporte['comparativa_label']) }}</div>
    @else
        <div class="kicker" style="margin-top: 8px;">INDICADORES PRINCIPALES</div>
    @endif

    @foreach ($filasP as $fila)
        @php $ancho = (int) floor(100 / max(1, count($fila))); @endphp
        <table class="w kpi-p avoid" style="margin-top: 14px;">
            <tr>
                @foreach ($fila as $i => $kpi)
                    @php $valor = $pinta($kpi); @endphp
                    <td class="{{ $i === 0 ? 'k0' : '' }}" style="width: {{ $ancho }}%;">
                        <div class="kpi-label">{{ mb_strtoupper($kpi['label'] ?? '—') }}</div>
                        <div class="kpi-valor">
                            @if ($valor === null || $valor === '—')<span class="nd">n/d</span>@else{{ $valor }}@endif
                            @if (!empty($kpi['desactualizado']))
                                <span class="marca-fecha">{{ $kpi['desactualizado'] }}</span>
                            @endif
                        </div>
                        @if (!empty($kpi['comparativo']))
                            <div class="kpi-comp kpi-comp-{{ $kpi['direccion'] ?? 'neutral' }}">{{ $kpi['comparativo'] }}</div>
                        @endif
                        @if (!empty($kpi['detalle']))
                            <div class="kpi-s-label">{{ $kpi['detalle'] }}</div>
                        @endif
                    </td>
                @endforeach
            </tr>
        </table>
    @endforeach

    @foreach ($filasS as $fila)
        @php $ancho = (int) floor(100 / max(1, count($fila))); @endphp
        <table class="w kpi-s avoid">
            <tr>
                @foreach ($fila as $i => $kpi)
                    @php $valor = $pinta($kpi); @endphp
                    <td class="{{ $loop->last ? 'ultima' : '' }}" style="width: {{ $ancho }}%;">
                        <div class="kpi-s-valor">
                            @if ($valor === null || $valor === '—')<span class="nd">n/d</span>@else{{ $valor }}@endif
                            @if (!empty($kpi['detalle']))
                                <span class="kpi-s-detalle">{{ $kpi['detalle'] }}</span>
                            @endif
                            @if (!empty($kpi['desactualizado']))
                                <span class="marca-fecha">{{ $kpi['desactualizado'] }}</span>
                            @endif
                        </div>
                        <div class="kpi-s-label">{{ $kpi['label'] ?? '—' }}</div>
                    </td>
                @endforeach
            </tr>
        </table>
    @endforeach
@endif

@if (!empty($notas))
    <table class="w alcance avoid">
        <tr>
            <td colspan="2" class="al-rotulo">ALCANCE DEL REPORTE · ADVERTENCIAS Y EXCLUSIONES</td>
        </tr>
        <tr>
            <td class="al-col" style="width: 50%;">
                @foreach ($notasIzq as $linea)
                    {{ $linea }}@if (!$loop->last)<br>@endif
                @endforeach
            </td>
            <td class="al-col" style="width: 50%; padding-right: 0;">
                @foreach ($notasDer as $linea)
                    {{ $linea }}@if (!$loop->last)<br>@endif
                @endforeach
            </td>
        </tr>
    </table>
@endif

@if (!empty($seccion['contenido']['nota']))
    <div class="nota">{{ $seccion['contenido']['nota'] }}</div>
@endif
