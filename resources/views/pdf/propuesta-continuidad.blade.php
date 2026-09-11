{{--
    Propuesta de Continuidad SEO — documento de venta de 5 páginas.

    A diferencia de pdf/reporte.blade.php (fondo claro en todas las páginas),
    la portada de este documento es de fondo oscuro con acentos teal — un
    sistema visual propio del documento que ve el cliente, distinto del resto
    del panel. Igual que reporte.blade.php: todo tabla/bloque de ancho
    porcentual (dompdf no soporta flex/grid/position:absolute para columnas),
    una sola familia DejaVu Sans, table-layout:fixed + word-wrap en tablas
    para que una URL/keyword larga no rompa el ancho de la página.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $titulo }} — {{ $folio }}</title>
    <style>
        @page { margin: 88px 52px 60px; }
        @page :first { margin: 0; }

        body { margin: 0; padding: 0; font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1A2332; line-height: 1.5; }
        table { border-collapse: collapse; }
        td, th { vertical-align: top; }
        .w { width: 100%; }

        /* ── Header/footer fijos de las páginas de contenido ──────────────── */
        .pg-head { position: fixed; top: -88px; left: 0; right: 0; height: 60px; background: #0E1B2A; }
        .pg-head td { padding: 20px 4px; }
        .pg-head .hd-brand { font-size: 11px; font-weight: bold; letter-spacing: 1.6px; color: #FFFFFF; }
        .pg-head .hd-meta { text-align: right; font-size: 10px; color: #8B9CAD; }
        .pg-head .hd-rule { height: 3px; background: #0FA37F; font-size: 1px; line-height: 1px; }
        .pg-foot { position: fixed; bottom: -60px; left: 0; right: 0; height: 40px; border-top: 1px solid #E5E7EB; }
        .pg-foot td { padding-top: 12px; font-size: 9px; color: #8A939E; }
        .pg-foot .ft-r { text-align: right; color: #47515D; }

        /* ── Portada ───────────────────────────────────────────────────── */
        .cv-page { background: #0E1B2A; padding: 0 0 0 10px; }
        .cv-rule { width: 10px; background: #0FA37F; font-size: 1px; line-height: 1px; }
        .cv-body { padding: 56px 64px 0; }
        .cv-logo { width: 180px; height: 58px; }
        .cv-brand { font-size: 24px; font-weight: bold; color: #0FA37F; }
        .cv-brand-sub { font-size: 11px; letter-spacing: 1.4px; color: #FFFFFF; padding-top: 4px; }
        .cv-brand-url { text-align: right; font-size: 10px; color: #7E8FA0; }
        .cv-hr { height: 1px; background: #0FA37F66; font-size: 1px; line-height: 1px; margin-top: 14px; }
        .cv-badge { display: inline; }
        .cv-badge-box { background: #0FA37F; padding: 6px 12px; margin-top: 30px; }
        .cv-badge-txt { font-size: 10px; font-weight: bold; letter-spacing: 1.4px; color: #FFFFFF; }
        .cv-titulo { font-size: 52px; font-weight: bold; line-height: 1.05; padding-top: 18px; }
        .cv-titulo .l1 { color: #FFFFFF; }
        .cv-titulo .l2 { color: #0FA37F; }
        .cv-subtitulo { font-size: 18px; color: #FFFFFF; padding-top: 14px; }
        .cv-vigencia { font-size: 13px; color: #8B9CAD; padding-top: 4px; }
        .cv-precio-box { background: #0FA37F; margin-top: 22px; padding: 16px 20px; }
        .cv-precio { font-size: 32px; font-weight: bold; color: #FFFFFF; }
        .cv-precio-sub { font-size: 12px; color: #E7FBF3; padding-top: 2px; }
        .cv-hero-box { background: #1B2E42; margin-top: 16px; }
        .cv-hero-box td { padding: 16px 14px; border-right: 1px solid #2A3D52; }
        .cv-hero-box td.ultima { border-right: none; }
        .cv-hero-valor { font-size: 22px; font-weight: bold; color: #0FA37F; }
        .cv-hero-label { font-size: 10px; font-weight: bold; color: #FFFFFF; padding-top: 4px; }
        .cv-hero-nota { font-size: 9px; color: #7E8FA0; }
        .cv-footer { padding: 40px 64px 44px; }
        .cv-footer-lbl { font-size: 9px; color: #7E8FA0; }
        .cv-footer-nombre { font-size: 11px; font-weight: bold; color: #FFFFFF; padding-top: 2px; }
        .cv-footer-folio { font-size: 9px; color: #7E8FA0; padding-top: 2px; }

        /* ── Secciones de contenido ────────────────────────────────────── */
        .sec { padding-top: 4px; page-break-before: always; }
        .sec-titulo { font-size: 22px; font-weight: bold; color: #0E1B2A; }
        .sec-intro { font-size: 11.5px; color: #3A4653; line-height: 1.6; padding-top: 8px; }
        .sec-heading { font-size: 12px; font-weight: bold; color: #0E1B2A; padding-top: 20px; padding-bottom: 8px; }

        /* KPIs mini-tarjetas */
        table.kpi-row td { background: #F4F6F8; padding: 10px 12px; border-right: 4px solid #FFFFFF; vertical-align: top; }
        .kpi-valor { font-size: 19px; font-weight: bold; }
        .kpi-label { font-size: 9px; color: #64748B; padding-top: 3px; line-height: 1.3; }

        /* Tablas */
        table.t { width: 100%; table-layout: fixed; margin-top: 4px; }
        table.t thead td { background: #0E1B2A; padding: 7px 8px; font-size: 9px; font-weight: bold; letter-spacing: 0.6px; color: #FFFFFF; word-wrap: break-word; }
        table.t tbody td { padding: 6px 8px; font-size: 9.5px; color: #1A2332; border-bottom: 1px solid #EEF1F5; word-wrap: break-word; }
        table.t tbody tr.destacada td { background: #EAF6F0; font-weight: bold; }
        table.t tbody tr.riesgo td { background: #FCEEEF; }
        table.t td.num { text-align: right; }
        .delta-up { color: #0B7A55; font-weight: bold; }
        .delta-down { color: #B0343C; font-weight: bold; }
        .delta-nd { color: #9AA3AD; }

        /* Callouts */
        table.callout { width: 100%; margin-top: 14px; }
        table.callout td.cal-filete { width: 4px; font-size: 1px; line-height: 1px; }
        table.callout td.cal-cuerpo { padding: 10px 14px; }
        .cal-titulo { font-size: 11px; font-weight: bold; }
        .cal-texto { font-size: 10.5px; line-height: 1.55; padding-top: 4px; }
        .callout-teal td.cal-filete { background: #0FA37F; }
        .callout-teal { background: #EFF9F5; }
        .callout-teal .cal-titulo { color: #0B7A55; }
        .callout-teal .cal-texto { color: #1A2332; }
        .callout-amber td.cal-filete { background: #D98A0B; }
        .callout-amber { background: #FEF7EC; }
        .callout-amber .cal-titulo { color: #97600A; }
        .callout-amber .cal-texto { color: #1A2332; }

        /* Checklist "lo que protege" */
        table.check td { padding: 6px 0; border-bottom: 1px solid #EEF1F5; font-size: 10.5px; }
        table.check td.chk-mark { width: 20px; color: #0B7A55; font-weight: bold; }
        table.check td.chk-titulo { font-weight: bold; color: #1A2332; }

        /* Plan — barra de nombre + resumen */
        .plan-nombre-box { background: #0FA37F; padding: 10px 16px; margin-top: 4px; }
        .plan-nombre { font-size: 13px; font-weight: bold; letter-spacing: 0.6px; color: #FFFFFF; }
        .plan-resumen-box { background: #0E1B2A; padding: 8px 16px; }
        .plan-resumen { font-size: 10.5px; color: #FFFFFF; }

        table.alcance td { width: 50%; padding: 12px 14px 0 0; vertical-align: top; }
        .alcance-titulo { font-size: 11px; font-weight: bold; color: #1A2332; padding-bottom: 6px; }
        .alcance-item { font-size: 10.5px; color: #3A4653; padding: 3px 0; line-height: 1.4; }
        .alcance-item.excluido { color: #9AA3AD; }

        /* Renegociación / condiciones */
        table.pares td { border-bottom: 1px solid #E5E7EB; padding: 8px 8px; }
        table.pares td.pa-k { width: 150px; font-size: 10.5px; font-weight: bold; color: #1A2332; }
        table.pares td.pa-v { font-size: 10.5px; color: #3A4653; line-height: 1.5; }
        table.reneg td { width: 33.33%; padding: 10px 10px 0 0; vertical-align: top; }
        .reneg-letra { font-size: 10px; font-weight: bold; color: #0FA37F; }
        .reneg-nombre { font-size: 10.5px; font-weight: bold; color: #1A2332; padding-top: 2px; }
        .reneg-desc { font-size: 9.5px; color: #64748B; padding-top: 3px; line-height: 1.4; }

        .proj-highlight { color: #0B7A55; font-weight: bold; background: #EAF6F0 !important; }
        .disclaimer { font-size: 9px; color: #8A939E; font-style: italic; padding-top: 10px; line-height: 1.5; }
    </style>
</head>
<body>

@include('pdf.propuesta-continuidad._portada')

<div class="pg-head">
    <table class="w"><tr>
        <td class="hd-brand" style="width:50%;">RANKPRO SOLUTIONS</td>
        <td class="hd-meta" style="width:50%;">Propuesta · Plan Continuidad · {{ $clienteNombre1 }} {{ $clienteNombre2 }}</td>
    </tr></table>
    <div class="hd-rule"></div>
</div>
<div class="pg-foot">
    <table class="w"><tr>
        <td>rankprosolutions.com.mx · Documento confidencial para uso interno del cliente</td>
        <td class="ft-r"><span></span></td>
    </tr></table>
</div>

{{-- Cada sección tiene su interruptor en el editor (ver Visibilidad); la
     portada, el header y el footer no: siempre se imprimen. --}}
@if ($visible('situacion'))
    @include('pdf.propuesta-continuidad._situacion')
@endif
@if ($visible('contexto'))
    @include('pdf.propuesta-continuidad._contexto')
@endif
@if ($visible('plan'))
    @include('pdf.propuesta-continuidad._plan')
@endif
@if ($visible('condiciones'))
    @include('pdf.propuesta-continuidad._condiciones')
@endif

</body>
</html>
