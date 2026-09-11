{{--
    Portada (artboard 01). Va en la primera página, que @page :first deja sin
    márgenes: dompdf recoloca los marcos fijos con los márgenes de cada página,
    así que aquí el header y el footer caen fuera del papel y no se ven.

    Identidad = filete verde de 10px + un solo bloque tipográfico grande. Nada
    que dependa de imagen, degradado ni fuente externa.
--}}
@php
    $cliente = $reporte['cliente'];
    $nombreCliente = $cliente['empresa'] ?: ($cliente['nombre'] ?: 'Cliente sin nombre');
    $sitio = $reporte['sitio_web'] ?: $cliente['sitio_web'];
    $fuentes = array_values(array_filter($reporte['fuentes'] ?? [], fn ($f) => trim((string) $f) !== ''));
    $version = trim(implode(' · ', array_filter([$reporte['version_etiqueta'] ?? '', $reporte['estado'] ?? ''])));
@endphp

<table class="w">
    <tr><td class="cv-rule-a"></td></tr>
    <tr><td class="cv-rule-b"></td></tr>
</table>

<div class="cv-band-1">
    <table class="w">
        <tr>
            <td>
                {{-- Logo en base64 (el porqué está en App\Support\LogoPdf),
                     compartido con el PDF de propuestas. --}}
                @php $logo = \App\Support\LogoPdf::dataUri(); @endphp
                @if ($logo)
                    <img class="cv-logo" src="{{ $logo }}" alt="RankPro Solutions">
                @else
                    <div class="cv-brand">RANKPRO</div>
                @endif
                <div class="cv-brand-sub">RANKPROSOLUTIONS.COM.MX</div>
            </td>
            <td class="cv-folio">@if ($reporte['numero']) FOLIO {{ mb_strtoupper($reporte['numero']) }} @endif</td>
        </tr>
    </table>
</div>

<div class="cv-band-2">
    <div class="cv-kicker">{{ mb_strtoupper($reporte['titulo']) }}</div>
    <div class="cv-titulo">{{ $nombreCliente }}</div>
    @if ($sitio)
        <div class="cv-sub">{{ $sitio }}</div>
    @endif

    <table class="w" style="margin-top: 44px;">
        <tr>
            <td style="width: 96px;"><div class="cv-hr-verde"></div></td>
            <td><div class="cv-hr-gris"></div></td>
        </tr>
    </table>

    <table class="w cv-meta">
        <tr>
            <td style="width: 224px;">
                <div class="cv-lbl">PERIODO CUBIERTO</div>
                <div class="cv-val">
                    {{ $reporte['periodo_label'] ?: '—' }}<br>
                    <span class="cv-val-sec">{{ $reporte['dias_periodo'] }} días con datos</span>
                </div>
            </td>
            <td style="width: 224px;">
                <div class="cv-lbl">FECHA DE EMISIÓN</div>
                <div class="cv-val">
                    {{ $reporte['fecha_emision'] ?: '—' }}<br>
                    <span class="cv-val-sec">{{ $version !== '' ? $version : 'sin versión declarada' }}</span>
                </div>
            </td>
            <td style="width: 200px; padding-right: 0;">
                <div class="cv-lbl">FUENTES</div>
                <div class="cv-lista">
                    @forelse ($fuentes as $fuente)
                        {{ $fuente }}@if (!$loop->last)<br>@endif
                    @empty
                        <span class="nd">n/d</span>
                    @endforelse
                </div>
            </td>
        </tr>
    </table>
</div>

<div class="cv-band-3">
    <table class="w cv-pie">
        <tr>
            <td>
                Documento preparado por RankPro Solutions para uso interno del cliente.<br>
                Contiene datos de rendimiento no públicos.
            </td>
            <td class="cv-pie-r">
                @if ($reporte['comparativa_label'])
                    VS. {{ mb_strtoupper($reporte['comparativa_label']) }}
                @endif
            </td>
        </tr>
    </table>
</div>
