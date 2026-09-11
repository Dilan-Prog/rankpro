{{-- Página 5 — "4. Condiciones y renegociación al mes 3".
     Cada bloque sale si está visible ($visible, catálogo `condiciones.*`) Y tiene contenido. --}}
<div class="sec">
    <div class="sec-titulo">4. Condiciones y renegociación al mes 3</div>

    @php $filas = $condiciones['condiciones'] ?? []; @endphp
    @if ($visible('condiciones.tabla') && !empty($filas))
        <table class="w pares" style="margin-top: 14px;">
            @foreach ($filas as $fila)
                @if (trim((string) ($fila['etiqueta'] ?? '')) !== '' || trim((string) ($fila['valor'] ?? '')) !== '')
                    <tr>
                        <td class="pa-k">{{ $fila['etiqueta'] ?? '' }}</td>
                        <td class="pa-v">{{ $fila['valor'] ?? '' }}</td>
                    </tr>
                @endif
            @endforeach
        </table>
    @endif

    @php $reneg = array_values(array_filter($condiciones['opciones_renegociacion'] ?? [], fn ($o) => trim((string) ($o['nombre'] ?? '')) !== '' || trim((string) ($o['descripcion'] ?? '')) !== '')); @endphp
    @if ($visible('condiciones.renegociacion') && !empty($reneg))
        <div class="sec-heading">Renegociación al mes 3</div>
        <table class="w reneg">
            <tr>
                @foreach ($reneg as $i => $opcion)
                    <td>
                        <div class="reneg-letra">{{ chr(65 + $i) }})</div>
                        <div class="reneg-nombre">{{ $opcion['nombre'] ?? '' }}</div>
                        <div class="reneg-desc">{{ $opcion['descripcion'] ?? '' }}</div>
                    </td>
                @endforeach
            </tr>
        </table>
    @endif

    @php $proyeccion = $condiciones['tabla_proyeccion'] ?? []; @endphp
    @if ($visible('condiciones.proyeccion') && !empty($proyeccion))
        @php $baseLabel = $situacion['periodo_comparacion']['label_2'] ?? 'periodo base'; @endphp
        <div class="sec-heading">Proyección de resultados esperados</div>
        <table class="t">
            <thead><tr>
                <td style="width: 30%;">MÉTRICA</td>
                <td style="width: 24%;">{{ mb_strtoupper($baseLabel) }} (BASE)</td>
                <td style="width: 24%;">{{ mb_strtoupper($condiciones['proyeccion_periodo_label'] ?? 'PROYECCIÓN') }}</td>
                <td style="width: 22%;">ESCENARIO</td>
            </tr></thead>
            <tbody>
                @foreach ($proyeccion as $fila)
                    <tr>
                        <td>{{ $fila['metrica'] ?? '' }}</td>
                        <td>{{ $fila['base'] ?? '' }}</td>
                        <td class="proj-highlight">{{ $fila['proyeccion'] ?? '' }}</td>
                        <td>{{ $fila['escenario'] ?? '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="disclaimer">
            Los rangos son proyecciones basadas en la tendencia observada y en el alcance contratado. No constituyen una garantía de resultados; el posicionamiento orgánico depende de factores externos como la competencia y los cambios de algoritmo de Google.
        </div>
    @endif

    {{-- Texto fijo (no editable), así que aquí solo manda el interruptor. --}}
    @if ($visible('condiciones.contacto'))
        <table class="w callout callout-teal" style="margin-top: 18px;"><tr>
            <td class="cal-filete"></td>
            <td class="cal-cuerpo">
                <div class="cal-titulo">Para confirmar esta propuesta</div>
                <div class="cal-texto">
                    Responde este documento por correo o escribe directamente al equipo.<br>
                    Ing. Dilan Yovani · contacto@rankprosolutions.com.mx · rankprosolutions.com.mx
                </div>
            </td>
        </tr></table>
    @endif
</div>
