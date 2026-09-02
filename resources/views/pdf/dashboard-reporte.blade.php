<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte Ejecutivo {{ $periodo }}</title>
    <style>
        @page { margin: 90px 50px 70px 50px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1A2332; line-height: 1.5; }
        header { position: fixed; top: -70px; left: 0; right: 0; height: 60px; }
        footer { position: fixed; bottom: -50px; left: 0; right: 0; height: 30px; text-align: center; font-size: 9px; color: #6B7280; border-top: 1px solid #E5E7EB; padding-top: 8px; }
        .brand { font-size: 16px; font-weight: bold; color: #0F9D6E; }
        .brand-sub { font-size: 9px; color: #6B7280; }
        .doc-title { font-size: 18px; font-weight: bold; margin: 10px 0 4px; }
        .doc-meta { font-size: 10px; color: #6B7280; margin-bottom: 20px; }
        .box { border: 1px solid #E5E7EB; border-radius: 4px; padding: 12px 16px; margin-bottom: 16px; }
        .box h3 { font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #6B7280; margin-bottom: 8px; }
        .section-title { font-size: 13px; font-weight: bold; margin: 0 0 8px; }
        table.services { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        table.services th { background: #F5F7FA; text-align: left; padding: 8px 10px; font-size: 10px; text-transform: uppercase; color: #6B7280; border-bottom: 1px solid #E5E7EB; }
        table.services td { padding: 8px 10px; border-bottom: 1px solid #F0F0F0; font-size: 11px; }
        table.services tfoot td { font-weight: bold; border-top: 2px solid #1A2332; border-bottom: none; }
        .kpi-table td { padding: 6px 10px; border-bottom: 1px solid #F0F0F0; }
        .kpi-table .kpi-label { color: #6B7280; font-size: 10px; text-transform: uppercase; }
        .kpi-table .kpi-value { font-size: 13px; font-weight: bold; }
        .kpi-table .kpi-sub { color: #6B7280; font-size: 10px; }
        .empty { color: #6B7280; font-style: italic; padding: 8px 10px; }
    </style>
</head>
<body>
    <header>
        <div class="brand">RankPro Solutions</div>
        <div class="brand-sub">Agencia de Marketing Digital · rankprosolutions.com.mx</div>
    </header>
    <footer>RankPro Solutions — Reporte Ejecutivo {{ $periodo }} — Página <span></span></footer>

    <div class="doc-title">Reporte Ejecutivo — {{ $periodo }}</div>
    <div class="doc-meta">Fecha de emisión: {{ $fechaEmision }}</div>

    <div class="box">
        <h3>Resumen de KPIs</h3>
        <table class="kpi-table" style="width:100%; border-collapse: collapse;">
            <tbody>
                @foreach ($kpis as $kpi)
                    <tr>
                        <td class="kpi-label" style="width:35%;">{{ $kpi['label'] }}</td>
                        <td class="kpi-value" style="width:25%;">{{ $kpi['value'] }}</td>
                        <td class="kpi-sub">{{ $kpi['sub'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="section-title">Ingresos vs Inversión</div>
    <table class="services">
        <thead>
            <tr><th>Mes</th><th style="text-align:right;">Ingresos</th><th style="text-align:right;">Gastos</th></tr>
        </thead>
        <tbody>
            @foreach ($revenueData as $r)
                <tr>
                    <td>{{ $r['month'] }}</td>
                    <td style="text-align:right;">${{ number_format($r['income'], 2) }} MXN</td>
                    <td style="text-align:right;">${{ number_format($r['expense'], 2) }} MXN</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="section-title">Top Campañas por ROAS</div>
    @if (empty($topRoas))
        <div class="box"><span class="empty">Sin métricas de campañas todavía.</span></div>
    @else
        <table class="services">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Campaña</th>
                    <th>Cliente</th>
                    <th>Plataforma</th>
                    <th style="text-align:right;">Presupuesto</th>
                    <th style="text-align:right;">Gasto</th>
                    <th style="text-align:right;">ROAS</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($topRoas as $i => $c)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ $c['name'] }}</td>
                        <td>{{ $c['client'] }}</td>
                        <td>{{ $c['platform'] }}</td>
                        <td style="text-align:right;">${{ number_format($c['presupuesto_mensual'], 2) }} MXN</td>
                        <td style="text-align:right;">${{ number_format($c['gasto_total'], 2) }} MXN</td>
                        <td style="text-align:right;">{{ $c['roas'] }}x</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="section-title">Contratos por Vencer</div>
    @if (empty($contractsExpiring))
        <div class="box"><span class="empty">Sin contratos por vencer en los próximos días.</span></div>
    @else
        <table class="services">
            <thead>
                <tr><th>Cliente</th><th>Vencimiento</th><th style="text-align:right;">Días</th><th style="text-align:right;">MRR</th></tr>
            </thead>
            <tbody>
                @foreach ($contractsExpiring as $c)
                    <tr>
                        <td>{{ $c['client'] }}</td>
                        <td>{{ $c['end'] }}</td>
                        <td style="text-align:right;">{{ $c['days'] }}</td>
                        <td style="text-align:right;">${{ number_format($c['mrr'], 2) }} MXN</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
