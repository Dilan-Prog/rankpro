{{-- Página 4 — "3. El Plan Continuidad en detalle" --}}
<div class="sec">
    <div class="sec-titulo">3. El Plan Continuidad en detalle</div>

    <table class="w plan-nombre-box" style="margin-top: 14px;"><tr>
        <td class="plan-nombre">{{ mb_strtoupper($plan['nombre'] ?? 'PLAN CONTINUIDAD') }}</td>
    </tr></table>
    <table class="w plan-resumen-box"><tr>
        <td class="plan-resumen">
            @if ($precioMensual !== null)
                ${{ number_format((float) $precioMensual, 0) }} MXN/mes
            @endif
            @if ($horasMensuales)
                · {{ $horasMensuales }} hrs × ${{ number_format((float) $tarifaHora, 0) }}/hr
            @endif
            @if (!empty($plan['vigencia_inicio_texto']) || !empty($plan['vigencia_fin_texto']))
                · Vigencia: {{ $plan['vigencia_inicio_texto'] ?? '' }} — {{ $plan['vigencia_fin_texto'] ?? '' }}
            @endif
        </td>
    </tr></table>

    <table class="w alcance" style="margin-top: 4px;">
        <tr>
            <td>
                <div class="alcance-titulo">Alcance incluido</div>
                @foreach (($plan['alcance_incluido'] ?? []) as $item)
                    @if (trim((string) $item) !== '')
                        <div class="alcance-item">• {{ $item }}</div>
                    @endif
                @endforeach
            </td>
            <td>
                <div class="alcance-titulo">Alcance NO incluido <span style="font-weight: normal; color: #9AA3AD;">(reservado para Sprint 2)</span></div>
                @foreach (($plan['alcance_no_incluido'] ?? []) as $item)
                    @if (trim((string) $item) !== '')
                        <div class="alcance-item excluido">✕ {{ $item }}</div>
                    @endif
                @endforeach
            </td>
        </tr>
    </table>

    @php $meses = $plan['meses'] ?? []; @endphp
    @if (!empty($meses))
        <div class="sec-heading">Plan de ejecución mes a mes</div>
        <table class="t">
            <thead><tr>
                <td style="width: 10%;">MES</td>
                <td style="width: 16%;">CALENDARIO</td>
                <td style="width: 40%;">ACTIVIDADES CLAVE</td>
                <td style="width: 24%;">ENTREGABLE</td>
                <td style="width: 10%;">HRS</td>
            </tr></thead>
            <tbody>
                @foreach ($meses as $fila)
                    <tr>
                        <td style="font-weight: bold; color: #0FA37F;">{{ $fila['mes_label'] ?? '' }}</td>
                        <td>{{ $fila['calendario'] ?? '' }}</td>
                        <td>{{ $fila['actividades'] ?? '' }}</td>
                        <td>{{ $fila['entregable'] ?? '' }}</td>
                        <td class="num">{{ $fila['horas'] ?? '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
