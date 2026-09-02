{{--
    plan (artboard 07). derivados.acciones ya viene ordenado: prioridad primero,
    score descendente dentro de cada prioridad.

    Los badges de prioridad son el ÚNICO elemento del sistema con fondo sólido
    de color, y por eso funcionan como ancla de escaneo: la columna izquierda se
    lee como una escalera roja → ámbar → azul → gris.

    El score no se deja como cifra suelta: cifra + barra de 44px (ancho
    porcentual, div sobre div) para comparar de un vistazo sin releer números.
    La barra se escala contra 5,0, el techo de la escala impacto ÷ esfuerzo
    declarada en Metodología; no es un dato nuevo, es la misma cifra dibujada.
--}}
@php
    $acciones = $seccion['derivados']['acciones'] ?? [];
    $porPrioridad = $seccion['derivados']['por_prioridad'] ?? [];
    $prioridades = ['p0', 'p1', 'p2', 'p3'];

    // Glosa de cada prioridad: no viene del Armador, es copia fija del sistema.
    $glosa = [
        'p0' => 'Bloquea todo lo demás',
        'p1' => 'Impacto directo este mes',
        'p2' => 'Mejora acumulativa',
        'p3' => 'Opcional, cuando haya holgura',
    ];
@endphp

@if (empty($acciones))
    <div class="empty">Sin acciones planificadas.</div>
@else
    <table class="w" style="margin-top: 10px;">
        <tr>
            <td style="width: 430px; padding-right: 24px; font-size: 12px; color: #64748B; line-height: 1.55;">
                {{ $seccion['contenido']['introduccion'] ?? '' }}
                {{ count($acciones) }} acciones ordenadas por prioridad y, dentro de cada prioridad, por score.
            </td>
            <td style="width: 274px;">
                <table class="w como-leerlo">
                    <tr><td><b style="color: #1A2332;">Cómo leerlo:</b> P0 = esta semana · P1 = este mes · P2 = este trimestre · P3 = cuando haya holgura.</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="t plan" style="margin-top: 16px;">
        <thead>
            <tr>
                <td style="width: 44px;">PRIO</td>
                <td>ACCIÓN</td>
                <td style="width: 76px;">ÁREA</td>
                <td style="width: 42px; text-align: right;">IMP.</td>
                <td style="width: 42px; text-align: right;">ESF.</td>
                <td style="width: 90px;">SCORE</td>
                <td style="width: 110px;">KPI DE ÉXITO</td>
            </tr>
        </thead>
        <tbody>
            @foreach ($acciones as $i => $accion)
                @php
                    $pri = in_array($accion['prioridad'] ?? '', $prioridades, true) ? $accion['prioridad'] : '';
                    $score = (float) ($accion['score'] ?? 0);
                    $barra = max(0, min(100, (int) round($score / 5 * 100)));
                @endphp
                <tr class="{{ $i % 2 ? 'zebra' : '' }}">
                    <td><span class="pri {{ $pri !== '' ? 'pri-'.$pri : '' }}">{{ mb_strtoupper($accion['prioridad_label'] ?? '—') }}</span></td>
                    <td>
                        {{ $accion['accion'] ?? '—' }}
                        @if (!empty($accion['evidencia']))
                            <div class="h-evidencia">{{ $accion['evidencia'] }}</div>
                        @endif
                    </td>
                    <td class="pl-area">@if (!empty($accion['area'])){{ $accion['area'] }}@else<span class="nd">n/d</span>@endif</td>
                    <td class="num">@if ((int) ($accion['impacto'] ?? 0)){{ (int) $accion['impacto'] }}@else<span class="nd">n/d</span>@endif</td>
                    <td class="num" style="color: #64748B;">@if ((int) ($accion['esfuerzo'] ?? 0)){{ (int) $accion['esfuerzo'] }}@else<span class="nd">n/d</span>@endif</td>
                    <td>
                        <table class="score"><tr>
                            <td class="sc-n">{{ number_format($score, 1, ',', '.') }}</td>
                            <td class="sc-b">
                                <div class="barra-fondo"><div class="barra-llena" style="width: {{ $barra }}%;"></div></div>
                            </td>
                        </tr></table>
                    </td>
                    <td class="pl-kpi">@if (!empty($accion['kpi'])){{ $accion['kpi'] }}@else<span class="nd">n/d</span>@endif</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="w escala avoid">
        <tr>
            <td class="es-izq">
                <div class="kicker-gris">ESCALA DE PRIORIDAD</div>
                <table class="escala-lista" style="width: 216px; margin-top: 8px;">
                    @foreach ($prioridades as $p)
                        @if (array_key_exists($p, $porPrioridad))
                            <tr>
                                <td class="el-b"><span class="pri pri-{{ $p }}">{{ mb_strtoupper($p) }}</span></td>
                                <td>{{ $glosa[$p] }} · {{ $porPrioridad[$p] }}</td>
                            </tr>
                        @endif
                    @endforeach
                </table>
            </td>
            <td class="es-der">
                @if (!empty($seccion['contenido']['leyenda']))
                    <div class="kicker-gris">PRIMERO ESTO</div>
                    <div style="padding-top: 8px;">{{ $seccion['contenido']['leyenda'] }}</div>
                @endif
            </td>
        </tr>
    </table>
@endif
