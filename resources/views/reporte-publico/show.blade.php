{{--
    Vista pública de solo lectura de la versión para cliente. Recibe $reporte y $resumen de
    Armador::armarCliente (lista blanca), $pdfUrl y $expira. Sin layout del sitio ni del panel:
    es un documento aparte, noindex, que no enlaza a nada interno.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <title>{{ $reporte['titulo'] }}</title>
    <style>
        body { margin: 0; background: #F5F7FA; font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; font-size: 14px; color: #1A2332; line-height: 1.5; }
        table { border-collapse: collapse; }
        td, th { vertical-align: top; }
        .w { width: 100%; }
        .pagina { max-width: 820px; margin: 0 auto; padding: 24px 16px 40px; }
        .hoja { background: #fff; border: 1px solid #E5E7EB; border-top: 6px solid #0F9D6E; padding: 24px 28px 28px; }
        .rc-kicker { font-size: 11px; font-weight: bold; letter-spacing: 1.6px; color: #0F9D6E; }
        .rc-titulo { font-size: 24px; font-weight: bold; padding-top: 3px; }
        .rc-sub { font-size: 13px; color: #64748B; padding-top: 2px; }
        .rc-folio { font-size: 11px; font-weight: bold; letter-spacing: 1.2px; color: #64748B; }
        .acciones { margin-top: 18px; font-size: 13px; color: #64748B; }
        .boton { display: inline-block; background: #1A2332; color: #fff; text-decoration: none; padding: 9px 16px; border-radius: 6px; font-weight: 600; }
        .rc-pie { margin-top: 22px; border-top: 1px solid #E5E7EB; padding-top: 8px; font-size: 11px; color: #94A3B8; }
        .rc-item, .rc-vacio, .rc-leyenda, table.rc-valores td { font-size: 14px; }
        table.rc-valores td { font-size: 11px; }
        @media (max-width: 600px) {
            .rc-cols, .rc-cols tbody, .rc-cols tr, .rc-cols td { display: block; width: 100% !important; padding: 0 !important; }
            .rc-cols td.rc-der { margin-top: 14px; }
            .hoja { padding: 18px 14px; }
        }
@include('pdf.reporte._resumen_cliente_estilos')
    </style>
</head>
<body>
<div class="pagina">
    <div class="hoja">
        <div class="rc-folio">RANKPRO{{ $reporte['numero'] ? ' · FOLIO '.mb_strtoupper($reporte['numero']) : '' }}</div>
        <div class="rc-kicker" style="margin-top: 10px;">REPORTE {{ mb_strtoupper($reporte['area_label']) }} · {{ mb_strtoupper($reporte['periodo_label']) }}</div>
        <div class="rc-titulo">{{ $reporte['cliente_nombre'] }}</div>
        <div class="rc-sub">{{ $reporte['titulo'] }}@if ($reporte['fecha_emision']) · Emitido el {{ $reporte['fecha_emision'] }}@endif</div>

        @include('pdf.reporte._resumen_cuerpo', ['resumen' => $resumen])

        <div class="acciones">
            <a class="boton" href="{{ $pdfUrl }}" rel="nofollow">Descargar PDF</a>
            <span style="margin-left: 8px;">Enlace disponible hasta el {{ $expira->format('d/m/Y') }}.</span>
        </div>

        <div class="rc-pie">RankPro Solutions · rankprosolutions.com.mx</div>
    </div>
</div>
</body>
</html>
