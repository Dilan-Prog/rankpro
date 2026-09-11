{{--
    Portada (página 1). @page :first deja esta página sin márgenes (ver
    propuesta-continuidad.blade.php), así que el fondo oscuro cubre la hoja
    completa. Igual que pdf/reporte/_portada.blade.php: la vertical no se
    "centra" con flex (dompdf no lo soporta) sino con padding-top progresivo
    entre bloques, mismo truco ya usado ahí.

    La portada no tiene interruptor de sección, pero sí por bloque
    (`portada.*` en Visibilidad): cada uno se imprime si está visible Y tiene
    contenido, por eso los @if de contenido se conservan debajo de los de
    visibilidad.
--}}
{{-- Portada azul marino: va la version blanca del logo. --}}
@php $logo = \App\Support\LogoPdf::dataUri('blanco'); @endphp
<table class="w" style="height: 1056px;">
    <tr>
        <td class="cv-rule"></td>
        <td class="cv-page">
            <div class="cv-body">
                <table class="w">
                    <tr>
                        <td style="width: 60%;">
                            {{-- Respaldo en texto si el PNG no está en el servidor:
                                 mejor una portada con marca tipográfica que un
                                 hueco o un icono de imagen rota. --}}
                            @if ($logo)
                                <img class="cv-logo" src="{{ $logo }}" alt="RankPro Solutions">
                            @else
                                <div class="cv-brand">RankPro</div>
                                <div class="cv-brand-sub">DIGITAL SOLUTIONS &amp; SERVICES</div>
                            @endif
                        </td>
                        <td style="width: 40%;" class="cv-brand-url">
                            @if ($visible('portada.sitio_web'))
                                {{ $resumen['sitio_web'] ?? $cliente->nombre }}
                            @endif
                        </td>
                    </tr>
                </table>
                <div class="cv-hr"></div>

                <table class="cv-badge-box"><tr><td class="cv-badge-txt">PROPUESTA DE CONTINUIDAD SEO</td></tr></table>

                <div class="cv-titulo">
                    <div class="l1">{{ $clienteNombre1 }}</div>
                    @if ($clienteNombre2 !== '')
                        <div class="l2">{{ $clienteNombre2 }}</div>
                    @endif
                </div>
                @if ($visible('portada.subtitulo'))
                    <div class="cv-subtitulo">{{ $resumen['subtitulo_plan'] ?? '' }}</div>
                @endif
                @if ($visible('portada.vigencia'))
                    <div class="cv-vigencia">{{ $resumen['vigencia_label'] ?? '' }}</div>
                @endif

                {{-- El desglose «N horas × $T/hr · M meses» vive dentro de la caja
                     de precio: apagar `portada.precio` se lo lleva también. --}}
                @if ($visible('portada.precio'))
                    <table class="w cv-precio-box">
                        <tr><td>
                            <div class="cv-precio">
                                @if ($precioMensual !== null)
                                    ${{ number_format((float) $precioMensual, 0) }} MXN/mes
                                @endif
                            </div>
                            @if ($visible('portada.desglose'))
                                <div class="cv-precio-sub">
                                    @if ($horasMensuales)
                                        {{ $horasMensuales }} horas × ${{ number_format((float) $tarifaHora, 0) }}/hr
                                    @endif
                                    @if (!empty($plan['duracion_meses']))
                                        · {{ $plan['duracion_meses'] }} meses fijos
                                    @endif
                                </div>
                            @endif
                        </td></tr>
                    </table>
                @endif

                @php $hero = $resumen['estadisticas_destacadas'] ?? []; @endphp
                @if ($visible('portada.estadisticas') && !empty($hero))
                    <table class="w cv-hero-box">
                        <tr>
                            @foreach ($hero as $i => $item)
                                <td @if ($i === count($hero) - 1) class="ultima" @endif>
                                    <div class="cv-hero-valor">{{ $item['valor'] ?? '' }}</div>
                                    <div class="cv-hero-label">{{ $item['etiqueta'] ?? '' }}</div>
                                    <div class="cv-hero-nota">{{ $item['nota'] ?? '' }}</div>
                                </td>
                            @endforeach
                        </tr>
                    </table>
                @endif
            </div>

            <div class="cv-footer" style="padding-top: 210px;">
                <div class="cv-footer-lbl">Preparado por</div>
                <div class="cv-footer-nombre">Ing. Dilan Yovani · RankPro Solutions</div>
                <div class="cv-footer-folio">Folio {{ $folio }} · {{ $fechaEmision }}</div>
            </div>
        </td>
    </tr>
</table>
