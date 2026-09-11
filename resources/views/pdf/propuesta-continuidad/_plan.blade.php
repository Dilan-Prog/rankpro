{{-- Página 4 — "3. El Plan Continuidad en detalle".
     Cada bloque sale si está visible ($visible, catálogo `plan.*`) Y tiene contenido. --}}
<div class="sec">
    <div class="sec-titulo">3. El Plan Continuidad en detalle</div>

    <table class="w plan-nombre-box" style="margin-top: 14px;"><tr>
        <td class="plan-nombre">{{ mb_strtoupper($plan['nombre'] ?? 'PLAN CONTINUIDAD') }}</td>
    </tr></table>
    @if ($visible('plan.barra_precio'))
        <table class="w plan-resumen-box"><tr>
            <td class="plan-resumen">
                @if ($precioMensual !== null)
                    ${{ number_format((float) $precioMensual, 0) }} MXN/mes
                @endif
                {{-- `plan.barra_desglose` es solo el tramo «hrs × $/hr»: permite
                     mostrar el precio sin revelar la tarifa por hora. --}}
                @if ($visible('plan.barra_desglose') && $horasMensuales)
                    · {{ $horasMensuales }} hrs × ${{ number_format((float) $tarifaHora, 0) }}/hr
                @endif
                @if (!empty($plan['vigencia_inicio_texto']) || !empty($plan['vigencia_fin_texto']))
                    · Vigencia: {{ $plan['vigencia_inicio_texto'] ?? '' }} — {{ $plan['vigencia_fin_texto'] ?? '' }}
                @endif
            </td>
        </tr></table>
    @endif

    @php
        $verIncluido = $visible('plan.alcance_incluido');
        $verNoIncluido = $visible('plan.alcance_no_incluido');
        // Las dos columnas van al 50% por CSS (table.alcance td); si solo queda
        // una, se le fuerza el 100% inline para que no deje medio ancho vacío.
        $anchoAlcance = ($verIncluido && $verNoIncluido) ? '' : 'width: 100%;';
    @endphp
    @if ($verIncluido || $verNoIncluido)
        <table class="w alcance" style="margin-top: 4px;">
            <tr>
                @if ($verIncluido)
                    <td style="{{ $anchoAlcance }}">
                        <div class="alcance-titulo">Alcance incluido</div>
                        @foreach (($plan['alcance_incluido'] ?? []) as $item)
                            @if (trim((string) $item) !== '')
                                <div class="alcance-item">• {{ $item }}</div>
                            @endif
                        @endforeach
                    </td>
                @endif
                @if ($verNoIncluido)
                    <td style="{{ $anchoAlcance }}">
                        <div class="alcance-titulo">Alcance NO incluido <span style="font-weight: normal; color: #9AA3AD;">(reservado para Sprint 2)</span></div>
                        @foreach (($plan['alcance_no_incluido'] ?? []) as $item)
                            @if (trim((string) $item) !== '')
                                <div class="alcance-item excluido">✕ {{ $item }}</div>
                            @endif
                        @endforeach
                    </td>
                @endif
            </tr>
        </table>
    @endif

    @php
        $meses = $plan['meses'] ?? [];
        // Sin la columna HRS, su 10% se reparte entre las columnas de texto
        // (table-layout: fixed exige que los anchos sumen el 100%).
        $verHoras = $visible('plan.meses_horas');
    @endphp
    @if ($visible('plan.meses') && !empty($meses))
        <div class="sec-heading">Plan de ejecución mes a mes</div>
        <table class="t">
            <thead><tr>
                <td style="width: 10%;">MES</td>
                <td style="width: 16%;">CALENDARIO</td>
                <td style="width: {{ $verHoras ? 40 : 44 }}%;">ACTIVIDADES CLAVE</td>
                <td style="width: {{ $verHoras ? 24 : 30 }}%;">ENTREGABLE</td>
                @if ($verHoras)
                    <td style="width: 10%;">HRS</td>
                @endif
            </tr></thead>
            <tbody>
                @foreach ($meses as $fila)
                    <tr>
                        <td style="font-weight: bold; color: #0FA37F;">{{ $fila['mes_label'] ?? '' }}</td>
                        <td>{{ $fila['calendario'] ?? '' }}</td>
                        <td>{{ $fila['actividades'] ?? '' }}</td>
                        <td>{{ $fila['entregable'] ?? '' }}</td>
                        @if ($verHoras)
                            <td class="num">{{ $fila['horas'] ?? '' }}</td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
