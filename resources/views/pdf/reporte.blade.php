{{--
    Entregable en PDF de un reporte. Recibe las dos claves que devuelve
    App\Services\Reportes\Armador::armar(): $reporte y $secciones.

    Maqueta definitiva del sistema de reportes RankPro (artboards 01–09).
    Reglas del diseño que este documento respeta al pie de la letra:

    · Carta vertical 816×1056 px, márgenes 56px, caja de texto 704px.
    · Header 72px y footer 52px en position:fixed contra
      @page { margin: 96px 56px 76px }. La portada los suprime con
      @page :first { margin: 0 }: dompdf reposiciona los marcos fijos con los
      márgenes de CADA página, así que en la primera se van fuera del papel.
    · Nada de flex, grid, position:absolute para columnas, SVG, webfonts ni
      variables CSS. Todo es tabla, bloque y div de ancho porcentual.
    · Una sola familia en PDF: 'DejaVu Sans'. La jerarquía la cargan tamaño,
      peso, color e interletrado (hoja de estilo, artboard 09).
    · Todo el CSS vive en este único <style>; los parciales sólo usan clases.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $reporte['titulo'] }}</title>
    <style>
        /* ── Página ─────────────────────────────────────────────────────── */
        @page { margin: 96px 56px 76px; }
        @page :first { margin: 0; }

        body { margin: 0; padding: 0; font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1A2332; line-height: 1.5; }
        table { border-collapse: collapse; }
        td, th { vertical-align: top; }
        .w { width: 100%; }
        .avoid { page-break-inside: avoid; }

        /* ── Header y footer fijos ──────────────────────────────────────── */
        .pg-head { position: fixed; top: -96px; left: 0; right: 0; height: 72px; border-bottom: 1px solid #E5E7EB; }
        .pg-foot { position: fixed; bottom: -76px; left: 0; right: 0; height: 52px; border-top: 1px solid #E5E7EB; }
        .pg-head td, .pg-foot td { vertical-align: middle; }
        .pg-head .hd-dot { width: 18px; }
        .pg-head .dot { width: 9px; height: 9px; background: #0F9D6E; font-size: 1px; line-height: 1px; }
        .pg-head .hd-brand { font-size: 10px; font-weight: bold; letter-spacing: 2px; color: #1A2332; }
        .pg-head .hd-meta { text-align: right; font-size: 11px; color: #64748B; }
        .pg-foot td { font-size: 10px; color: #64748B; padding-top: 18px; }
        .pg-foot .ft-c { text-align: center; }
        .pg-foot .ft-r { text-align: right; color: #1A2332; font-weight: bold; }
        /* dompdf 3.x resuelve counter(page) pero no tiene contador 'pages': el
           total lo inyecta RenderizadorPdf con un render previo de conteo. En la
           vista previa, que no pasa por ahí, el pie se queda en «Página N». */
        .pg-num:after { content: "Página " counter(page)@if (! empty($totalPaginas)) " de {{ $totalPaginas }}"@endif; }

        /* ── Portada (artboard 01) ──────────────────────────────────────── */
        .cv-rule-a { height: 10px; background: #0F9D6E; font-size: 1px; line-height: 1px; }
        .cv-rule-b { height: 4px; background: #1A2332; font-size: 1px; line-height: 1px; }
        .cv-band-1 { padding: 84px 72px 0; }
        .cv-band-2 { padding: 232px 72px 0; }
        .cv-band-3 { padding: 176px 72px 0; }
        /* El logo va a 180px de ancho; el original es 493x160, asi que dompdf lo
           escala a 58px de alto manteniendo la proporcion. */
        .cv-logo { width: 180px; height: 58px; }
        /* Respaldo tipográfico si el fichero del logo no está en el servidor. */
        .cv-brand { font-size: 13px; font-weight: bold; letter-spacing: 3px; color: #1A2332; }
        .cv-brand-sub { font-size: 10px; letter-spacing: 2.2px; color: #64748B; padding-top: 6px; }
        .cv-folio { text-align: right; font-size: 10px; letter-spacing: 1.6px; color: #64748B; }
        .cv-kicker { font-size: 12px; font-weight: bold; letter-spacing: 3.4px; color: #0F9D6E; }
        .cv-titulo { font-size: 54px; font-weight: bold; color: #1A2332; letter-spacing: -1.4px; line-height: 1.05; padding-top: 22px; }
        .cv-sub { font-size: 19px; color: #64748B; padding-top: 10px; }
        .cv-hr-verde { height: 3px; width: 96px; background: #0F9D6E; font-size: 1px; line-height: 1px; }
        .cv-hr-gris { height: 3px; background: #E5E7EB; font-size: 1px; line-height: 1px; }
        .cv-meta { padding-top: 34px; }
        .cv-meta td { padding-right: 24px; }
        .cv-meta .cv-lbl { font-size: 9px; font-weight: bold; letter-spacing: 1.8px; color: #64748B; }
        .cv-meta .cv-val { font-size: 17px; color: #1A2332; padding-top: 7px; line-height: 1.35; }
        .cv-meta .cv-val-sec { font-size: 13px; color: #64748B; }
        .cv-meta .cv-lista { font-size: 13px; color: #1A2332; padding-top: 7px; line-height: 1.5; }
        .cv-pie { border-top: 1px solid #E5E7EB; }
        .cv-pie td { padding-top: 14px; font-size: 11px; color: #64748B; line-height: 1.5; }
        .cv-pie .cv-pie-r { text-align: right; color: #1A2332; font-weight: bold; }

        /* ── Sección ────────────────────────────────────────────────────── */
        .sec { page-break-before: always; }
        .sec-rotulo { font-size: 9px; font-weight: bold; letter-spacing: 1.8px; color: #64748B; }
        .sec-titulo { font-size: 21px; font-weight: bold; color: #1A2332; letter-spacing: -0.3px; padding-top: 2px; }
        .sec-titulo-xl { font-size: 25px; font-weight: bold; color: #1A2332; letter-spacing: -0.4px; }
        .sec-intro { font-size: 13px; color: #64748B; line-height: 1.55; padding-top: 8px; }
        .sec-cuerpo { padding-top: 16px; }
        .kicker { font-size: 9px; font-weight: bold; letter-spacing: 1.8px; color: #0F9D6E; padding-bottom: 6px; border-bottom: 1px solid #E5E7EB; margin-top: 22px; }
        .kicker-gris { font-size: 9px; font-weight: bold; letter-spacing: 1.6px; color: #64748B; }
        .nota { font-size: 11px; color: #64748B; line-height: 1.5; padding-top: 10px; }
        .empty { font-size: 10px; color: #94A3B8; padding: 8px 0; }

        /* Aviso de dato desactualizado: único bloque ámbar del sistema. */
        .aviso { border: 1px solid #F59E0B; background: #FFFBEB; margin-top: 16px; }
        .aviso .aviso-filete { width: 6px; background: #F59E0B; font-size: 1px; line-height: 1px; }
        .aviso .aviso-cuerpo { padding: 10px 14px; }
        .aviso .aviso-rotulo { font-size: 9px; font-weight: bold; letter-spacing: 1.4px; color: #B45309; }
        .aviso .aviso-texto { font-size: 11px; color: #78350F; line-height: 1.5; padding-top: 4px; }
        .marca-fecha { font-size: 9px; background: #FEF3C7; color: #B45309; padding: 1px 4px; }

        /* ── Tabla base (artboard 09 · estilo de tabla) ─────────────────── */
        /* `table-layout: fixed` no es cosmético: con el reparto automático,
           dompdf ensancha la columna hasta que quepa su contenido más largo, y
           una URL es una cadena sin espacios que no puede partir. La tabla
           crecía más allá de los 704px de la caja y las últimas columnas se
           salían del papel. Con el reparto fijo mandan los anchos declarados y
           `word-wrap` permite cortar la URL dentro de su celda. */
        table.t { width: 100%; table-layout: fixed; }
        table.t thead td { background: #1A2332; padding: 7px 8px; font-size: 9px; font-weight: bold; letter-spacing: 0.8px; color: #FFFFFF; vertical-align: middle; word-wrap: break-word; }
        table.t tbody td { padding: 4px 8px; font-size: 10px; color: #1A2332; border-bottom: 1px solid #EEF1F5; vertical-align: top; word-wrap: break-word; }
        table.t td.num { font-size: 10px; text-align: right; }
        table.t td.txt2 { font-size: 9px; color: #64748B; }
        .zebra { background: #F9FAFC; }
        .nd { color: #94A3B8; }

        tr.destacada td { background: #ECFBF4; font-weight: bold; }
        tr.destacada td.c0 { border-left: 3px solid #0F9D6E; }

        tr.excluida td { background: #FBFCFD; color: #94A3B8; border-bottom: 1px dashed #E5E7EB; }
        tr.excluida td.c0 { border-left: 3px solid #E5E7EB; }
        .tachado { text-decoration: line-through; }
        .motivo { font-size: 8px; color: #94A3B8; }
        .tag-excluida { font-size: 8px; letter-spacing: 0.8px; color: #94A3B8; }
        .banda-excluidas td { padding: 14px 8px 5px; border-bottom: none; }
        .banda-excluidas .be-l { font-size: 9px; font-weight: bold; letter-spacing: 1.4px; color: #94A3B8; }
        .banda-excluidas .be-r { text-align: right; font-size: 9px; color: #94A3B8; }

        tr.total td { background: #F5F7FA; padding: 7px 8px; font-size: 11px; font-weight: bold; color: #1A2332; border-top: 2px solid #1A2332; border-bottom: none; }
        tr.total-excluido td { background: #FFFFFF; padding: 7px 8px; font-size: 11px; font-weight: bold; color: #94A3B8; border-top: 1px solid #CBD5E1; border-bottom: none; }

        /* ── Badges (artboard 09) ───────────────────────────────────────── */
        .sev { font-size: 8px; font-weight: bold; letter-spacing: 1px; padding: 3px 6px; border: 1px solid #E5E7EB; border-radius: 2px; color: #64748B; background: #F5F7FA; }
        .sev-critico { border-color: #FECACA; color: #EF4444; background: #FEF2F2; }
        .sev-alto { border-color: #FDE68A; color: #B45309; background: #FFFBEB; }
        .sev-medio { border-color: #BFDBFE; color: #1D4ED8; background: #EFF6FF; }
        .sev-informativo { border-color: #E5E7EB; color: #64748B; background: #F5F7FA; }

        .pri { font-size: 9px; font-weight: bold; letter-spacing: 0.6px; color: #FFFFFF; padding: 3px 6px; border-radius: 2px; background: #64748B; }
        .pri-p0 { background: #EF4444; }
        .pri-p1 { background: #F59E0B; }
        .pri-p2 { background: #3B82F6; }
        .pri-p3 { background: #64748B; }

        .opp { font-size: 9px; font-weight: bold; letter-spacing: 0.4px; color: #64748B; }
        .opp-alta { color: #0F9D6E; }
        .opp-media { color: #B45309; }
        .opp-baja { color: #94A3B8; }

        .est { font-size: 8px; font-weight: bold; letter-spacing: 0.6px; padding: 3px 0; border-radius: 2px; text-align: center; color: #94A3B8; background: #F5F7FA; }
        .est-ok { color: #0F9D6E; background: #ECFBF4; }
        .est-revisar { color: #B45309; background: #FFFBEB; }
        .est-critico { color: #EF4444; background: #FEF2F2; }
        .est-nd { color: #94A3B8; background: #F5F7FA; }

        /* ── KPIs (artboard 02) ─────────────────────────────────────────── */
        table.kpi-p td { padding: 0 18px; vertical-align: top; border-left: 1px solid #E5E7EB; }
        table.kpi-p td.k0 { padding-left: 0; border-left: none; }
        .kpi-label { font-size: 10px; font-weight: bold; letter-spacing: 1.2px; color: #64748B; }
        .kpi-valor { font-size: 38px; font-weight: bold; color: #1A2332; line-height: 1.1; padding-top: 2px; }
        .kpi-unidad { font-size: 20px; }
        .kpi-comp { font-size: 12px; font-weight: bold; padding-top: 2px; color: #64748B; }
        .kpi-comp-positiva { color: #0F9D6E; }
        .kpi-comp-negativa { color: #EF4444; }
        .kpi-comp-neutral { color: #64748B; }
        table.kpi-s { background: #F5F7FA; border: 1px solid #E5E7EB; margin-top: 18px; }
        table.kpi-s td { padding: 11px 14px; border-right: 1px solid #E5E7EB; }
        table.kpi-s td.ultima { border-right: none; }
        .kpi-s-valor { font-size: 17px; font-weight: bold; color: #1A2332; }
        .kpi-s-detalle { font-size: 12px; color: #64748B; font-weight: normal; }
        .kpi-s-label { font-size: 10px; color: #64748B; padding-top: 1px; }

        /* Alcance: filete de 2px, sin fondo, para que no compita con los KPIs. */
        table.alcance { margin-top: 16px; border-top: 2px solid #1A2332; }
        table.alcance .al-rotulo { padding: 12px 0 4px; font-size: 9px; font-weight: bold; letter-spacing: 1.6px; color: #1A2332; }
        table.alcance .al-col { padding: 0 22px 12px 0; font-size: 11px; color: #64748B; line-height: 1.5; }

        /* ── Hallazgos (artboard 02) ────────────────────────────────────── */
        table.hallazgo { width: 100%; border-bottom: 1px solid #E5E7EB; }
        table.hallazgo td.h-n { width: 34px; padding: 10px 0; font-size: 15px; font-weight: bold; color: #CBD5E1; }
        table.hallazgo td.h-sev { width: 96px; padding: 11px 12px 10px 0; }
        table.hallazgo td.h-txt { padding: 9px 0 10px; }
        .h-titulo { font-size: 13px; font-weight: bold; color: #1A2332; line-height: 1.35; }
        .h-evidencia { font-size: 11px; color: #64748B; line-height: 1.45; padding-top: 3px; }

        /* ── Serie (artboard 03) ────────────────────────────────────────── */
        .serie-leyenda td { font-size: 11px; color: #64748B; padding-top: 20px; }
        .serie-leyenda .sl-r { text-align: right; font-size: 10px; }
        .serie-leyenda .chip { width: 10px; height: 10px; font-size: 1px; line-height: 1px; }
        table.grafico { width: 100%; margin-top: 10px; border-top: 1px solid #E5E7EB; }
        td.rail { width: 34px; }
        table.rail-t { width: 34px; height: 216px; }
        table.rail-t td { height: 54px; text-align: right; padding-right: 8px; font-size: 9px; color: #94A3B8; vertical-align: top; }
        table.rail-t td.tick { border-top: 1px dotted #E5E7EB; }
        td.lienzo { vertical-align: bottom; border-left: 1px solid #CBD5E1; border-bottom: 2px solid #1A2332; }
        table.barras { width: 100%; height: 216px; }
        table.barras td { vertical-align: bottom; text-align: center; padding: 0; }
        table.par { margin: 0 auto; }
        table.par td { vertical-align: bottom; padding: 0; }
        .bar { font-size: 1px; line-height: 1px; }
        .bar-1 { background: #0F9D6E; }
        .bar-2 { background: #CFE9DE; }
        .bar-3 { background: #94A3B8; }
        table.eje-x td { font-size: 9px; color: #94A3B8; padding-top: 6px; }
        table.eje-x td.ex-fin { text-align: right; }
        .lectura td { font-size: 12px; color: #1A2332; line-height: 1.6; padding: 10px 20px 0 0; }

        /* ── Ficha (artboard 06) ────────────────────────────────────────── */
        .leyenda-semaforo { font-size: 9px; color: #64748B; padding-top: 7px; }
        table.semaforo td.sm-est { padding: 3px 4px; text-align: center; }
        table.ficha { width: 100%; border: 1px solid #E5E7EB; margin-top: 12px; page-break-inside: avoid; }
        table.ficha td.fi-cab { background: #F5F7FA; padding: 7px 10px; border-bottom: 1px solid #E5E7EB; }
        .fi-url { font-size: 11px; font-weight: bold; color: #1A2332; }
        .fi-veredicto { text-align: right; }
        table.ficha td.fi-campo { padding: 5px 10px; border-right: 1px solid #EEF1F5; border-bottom: 1px solid #EEF1F5; vertical-align: top; }
        .fi-k { font-size: 8px; font-weight: bold; letter-spacing: 1px; color: #94A3B8; }
        .fi-v { font-size: 11px; color: #1A2332; padding-top: 3px; line-height: 1.3; }
        .fi-s { font-size: 8px; font-weight: bold; letter-spacing: 0.8px; padding-top: 3px; color: #64748B; }
        .fi-s-ok { color: #0F9D6E; }
        .fi-s-revisar { color: #B45309; }
        .fi-s-critico { color: #EF4444; }
        .fi-s-nd { color: #94A3B8; }

        /* ── Plan (artboard 07) ─────────────────────────────────────────── */
        table.plan tbody td { vertical-align: top; padding: 5px 8px; font-size: 11px; line-height: 1.35; }
        table.plan td.pl-kpi { font-size: 9px; color: #64748B; }
        table.plan td.pl-area { font-size: 9px; color: #64748B; }
        table.score { width: 74px; }
        table.score td { padding: 0; border: none; }
        table.score td.sc-n { width: 30px; font-size: 11px; font-weight: bold; color: #1A2332; }
        table.score td.sc-b { width: 44px; vertical-align: middle; }
        .barra-fondo { height: 6px; background: #E5E7EB; font-size: 1px; line-height: 1px; }
        .barra-llena { height: 6px; background: #0F9D6E; font-size: 1px; line-height: 1px; }
        table.escala { margin-top: 20px; border-top: 2px solid #1A2332; }
        table.escala td.es-izq { width: 234px; padding: 12px 18px 0 0; }
        table.escala td.es-der { padding-top: 12px; font-size: 12px; color: #1A2332; line-height: 1.6; }
        table.escala-lista td { padding: 3px 0; font-size: 10px; color: #64748B; }
        table.escala-lista td.el-b { width: 44px; }
        .como-leerlo { border: 1px solid #E5E7EB; background: #F5F7FA; }
        .como-leerlo td { padding: 8px 10px; font-size: 10px; color: #64748B; line-height: 1.5; }

        /* ── Texto / metodología (artboard 08) ──────────────────────────── */
        table.pares td { border-bottom: 1px solid #E5E7EB; }
        table.pares td.pa-k { width: 170px; padding: 14px 20px 14px 0; font-size: 12px; font-weight: bold; color: #1A2332; line-height: 1.35; }
        table.pares td.pa-v { padding: 14px 0; font-size: 11px; color: #64748B; line-height: 1.55; }
        table.pares tr.primera td { padding-top: 0; }
        table.pares tr.ultima td { border-bottom: none; }
        .bloque { padding-bottom: 12px; page-break-inside: avoid; }
        .bloque-titulo { font-size: 12px; font-weight: bold; color: #1A2332; }
        .bloque-cuerpo { font-size: 12px; color: #1A2332; line-height: 1.6; padding-top: 4px; }
    </style>
</head>
<body>
<div class="pg-head">
    <table class="w" style="height: 71px;">
        <tr>
            <td class="hd-dot"><div class="dot"></div></td>
            <td class="hd-brand">RANKPRO · REPORTE {{ mb_strtoupper($reporte['area_label'] ?: $reporte['area']) }}</td>
            <td class="hd-meta">{{ $reporte['cliente']['empresa'] ?: $reporte['cliente']['nombre'] }} · {{ $reporte['periodo_label'] }}</td>
        </tr>
    </table>
</div>

<div class="pg-foot">
    <table class="w">
        <tr>
            <td>RankPro Solutions · rankprosolutions.com.mx</td>
            <td class="ft-c">{{ $reporte['cliente']['empresa'] ?: $reporte['cliente']['nombre'] }} — {{ $reporte['titulo'] }}</td>
            <td class="ft-r"><span class="pg-num"></span></td>
        </tr>
    </table>
</div>

@include('pdf.reporte._portada')

@forelse ($secciones as $seccion)
    <div class="sec">
        @if (!empty($seccion['rotulo']))
            <div class="sec-rotulo">{{ mb_strtoupper($seccion['rotulo']) }}</div>
        @endif
        <div class="{{ $seccion['tipo'] === 'kpis' ? 'sec-titulo-xl' : 'sec-titulo' }}">{{ $seccion['titulo'] }}</div>

        @if (!empty($seccion['aviso']['texto']))
            <table class="aviso w">
                <tr>
                    <td class="aviso-filete"></td>
                    <td class="aviso-cuerpo">
                        <div class="aviso-rotulo">DATO DESACTUALIZADO · ESTA SECCIÓN</div>
                        <div class="aviso-texto">
                            {{ $seccion['aviso']['texto'] }}
                            @if (!empty($seccion['aviso']['fecha']))
                                <span class="marca-fecha">{{ $seccion['aviso']['fecha'] }}</span>
                            @endif
                        </div>
                    </td>
                </tr>
            </table>
        @endif

        <div class="sec-cuerpo">
            @includeIf('pdf.reporte._'.$seccion['tipo'], ['seccion' => $seccion])
        </div>
    </div>
@empty
    <div class="sec"><div class="empty">Este reporte no tiene secciones visibles.</div></div>
@endforelse
</body>
</html>
