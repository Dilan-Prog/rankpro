{{-- Página 2 — "1. Situación actual del sitio".
     Cada bloque sale si está visible ($visible, catálogo `situacion.*`) Y tiene contenido. --}}
<div class="sec">
    <div class="sec-titulo">1. Situación actual del sitio</div>
    @if ($visible('situacion.intro') && !empty($situacion['resumen_texto']))
        <div class="sec-intro">{{ $situacion['resumen_texto'] }}</div>
    @endif

    @php $kpis = $situacion['kpis'] ?? []; @endphp
    @if ($visible('situacion.kpis') && !empty($kpis))
        <table class="w kpi-row" style="margin-top: 14px;">
            <tr>
                @foreach ($kpis as $kpi)
                    <td>
                        <div class="kpi-valor" style="color: {{ $kpi['color'] ?? '#0E1B2A' }};">{{ $kpi['valor'] ?? '—' }}</div>
                        <div class="kpi-label">{{ $kpi['etiqueta'] ?? '' }}</div>
                    </td>
                @endforeach
            </tr>
        </table>
    @endif

    @if ($visible('situacion.comparacion') && !empty($tablaComparacion))
        <div class="sec-heading">
            Comparación de periodos
            @php $periodo = $situacion['periodo_comparacion'] ?? []; @endphp
            @if (!empty($periodo['label_1']) || !empty($periodo['label_2']))
                <span style="font-weight: normal; color: #64748B;">— {{ $periodo['label_1'] ?? '' }} vs {{ $periodo['label_2'] ?? '' }}</span>
            @endif
        </div>
        <table class="t">
            <thead><tr>
                <td style="width: 34%;">MÉTRICA</td>
                <td style="width: 22%;">{{ $periodo['label_1'] ?? 'PERIODO 1' }}</td>
                <td style="width: 22%;">{{ $periodo['label_2'] ?? 'PERIODO 2' }}</td>
                <td style="width: 22%;">VARIACIÓN</td>
            </tr></thead>
            <tbody>
                @foreach ($tablaComparacion as $fila)
                    <tr>
                        <td>{{ $fila['metrica'] ?? '' }}</td>
                        <td>{{ $fila['valor_1'] ?? '—' }}</td>
                        <td>{{ $fila['valor_2'] ?? '—' }}</td>
                        <td class="{{ $fila['sube'] === null ? 'delta-nd' : ($fila['sube'] ? 'delta-up' : 'delta-down') }}">
                            {{ $fila['delta'] ?? '—' }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @php $consultas = $situacion['tabla_consultas'] ?? []; @endphp
    @if ($visible('situacion.consultas') && !empty($consultas))
        <div class="sec-heading">Posiciones destacadas y oportunidades inmediatas</div>
        <table class="t">
            <thead><tr>
                <td style="width: 34%;">CONSULTA</td>
                <td style="width: 12%;">POS.</td>
                <td style="width: 16%;">IMPRESIONES</td>
                <td style="width: 12%;">CLICS</td>
                <td style="width: 26%;">OPORTUNIDAD</td>
            </tr></thead>
            <tbody>
                @foreach ($consultas as $fila)
                    @php $opp = mb_strtoupper($fila['oportunidad'] ?? ''); @endphp
                    <tr @class(['destacada' => str_contains($opp, 'MANTENER') || str_contains($opp, '✓')])>
                        <td>{{ $fila['consulta'] ?? '' }}</td>
                        <td class="num">{{ $fila['posicion'] ?? '' }}</td>
                        <td class="num">{{ $fila['impresiones'] ?? '' }}</td>
                        <td class="num">{{ $fila['clics'] ?? '' }}</td>
                        <td>{{ $fila['oportunidad'] ?? '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if ($visible('situacion.insight') && !empty($situacion['insight_texto']))
        <table class="w callout callout-teal"><tr>
            <td class="cal-filete"></td>
            <td class="cal-cuerpo">
                <div class="cal-titulo">Insight crítico</div>
                <div class="cal-texto">{{ $situacion['insight_texto'] }}</div>
            </td>
        </tr></table>
    @endif
</div>
