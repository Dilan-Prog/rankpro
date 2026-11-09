{{--
    Versión para cliente: UNA página. Recibe $reporte y $resumen de Armador::armarCliente
    (lista blanca: título, folio, área, cliente, periodo y el resumen). No existe aquí
    ningún dato interno que pintar, ni siquiera por accidente.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $reporte['titulo'] }}</title>
    <style>
        @page { margin: 40px 48px 36px; }
        body { margin: 0; padding: 0; font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1A2332; line-height: 1.45; }
        table { border-collapse: collapse; }
        td, th { vertical-align: top; }
        .w { width: 100%; }
        .rc-marca { height: 6px; background: #0F9D6E; font-size: 1px; line-height: 1px; }
        .rc-cab td { vertical-align: middle; padding: 14px 0 10px; }
        .rc-logo { width: 130px; height: 42px; }
        .rc-folio { text-align: right; font-size: 10px; font-weight: bold; letter-spacing: 1.2px; color: #64748B; }
        .rc-kicker { font-size: 10px; font-weight: bold; letter-spacing: 1.6px; color: #0F9D6E; }
        .rc-titulo { font-size: 20px; font-weight: bold; padding-top: 3px; }
        .rc-sub { font-size: 11px; color: #64748B; padding-top: 2px; }
        .rc-pie { margin-top: 22px; border-top: 1px solid #E5E7EB; padding-top: 6px; font-size: 9px; color: #94A3B8; }
@include('pdf.reporte._resumen_cliente_estilos')
    </style>
</head>
<body>
<table class="w"><tr><td class="rc-marca"></td></tr></table>

<table class="w rc-cab">
    <tr>
        <td>
            @php $logo = \App\Support\LogoPdf::dataUri(); @endphp
            @if ($logo)
                <img class="rc-logo" src="{{ $logo }}" alt="RankPro Solutions">
            @else
                <b>RANKPRO</b>
            @endif
        </td>
        <td class="rc-folio">@if ($reporte['numero']) FOLIO {{ mb_strtoupper($reporte['numero']) }} @endif</td>
    </tr>
</table>

<div class="rc-kicker">REPORTE {{ mb_strtoupper($reporte['area_label']) }} · {{ mb_strtoupper($reporte['periodo_label']) }}</div>
<div class="rc-titulo">{{ $reporte['cliente_nombre'] }}</div>
<div class="rc-sub">{{ $reporte['titulo'] }}@if ($reporte['fecha_emision']) · Emitido el {{ $reporte['fecha_emision'] }}@endif</div>

@include('pdf.reporte._resumen_cuerpo', ['resumen' => $resumen ?? []])

<div class="rc-pie">RankPro Solutions · rankprosolutions.com.mx</div>
</body>
</html>
