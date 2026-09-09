{{-- Página 3 — "2. ¿Por qué un Plan de Continuidad ahora?" --}}
<div class="sec">
    <div class="sec-titulo">2. ¿Por qué un Plan de Continuidad ahora?</div>
    @if (!empty($contexto['intro_texto']))
        <div class="sec-intro">{{ $contexto['intro_texto'] }}</div>
    @endif

    @php $riesgos = $contexto['tabla_riesgos'] ?? []; @endphp
    @if (!empty($riesgos))
        <table class="t" style="margin-top: 16px;">
            <thead><tr>
                <td style="width: 36%;">RIESGO</td>
                <td style="width: 64%;">IMPACTO SI NO SE ACTÚA</td>
            </tr></thead>
            <tbody>
                @foreach ($riesgos as $fila)
                    <tr class="riesgo">
                        <td style="font-weight: bold; color: #B0343C;">{{ $fila['riesgo'] ?? '' }}</td>
                        <td>{{ $fila['impacto'] ?? '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @php $protege = $contexto['checklist_protege'] ?? []; @endphp
    @if (!empty($protege))
        <div class="sec-heading">Lo que el Plan Continuidad sí protege</div>
        <table class="w check">
            @foreach ($protege as $fila)
                <tr>
                    <td class="chk-mark">✓</td>
                    <td class="chk-titulo" style="width: 160px;">{{ $fila['titulo'] ?? '' }}</td>
                    <td>{{ $fila['descripcion'] ?? '' }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    @if (!empty($contexto['logica_negocio_texto']))
        <table class="w callout callout-amber"><tr>
            <td class="cal-filete"></td>
            <td class="cal-cuerpo">
                <div class="cal-titulo">Lógica de negocio</div>
                <div class="cal-texto">{{ $contexto['logica_negocio_texto'] }}</div>
            </td>
        </tr></table>
    @endif
</div>
