<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Propuesta {{ $numero }}</title>
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
        .validez { display: inline-block; background: #FEF3C7; color: #92400E; padding: 4px 10px; border-radius: 12px; font-size: 10px; font-weight: bold; }
        table.services { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        table.services th { background: #F5F7FA; text-align: left; padding: 8px 10px; font-size: 10px; text-transform: uppercase; color: #6B7280; border-bottom: 1px solid #E5E7EB; }
        table.services td { padding: 8px 10px; border-bottom: 1px solid #F0F0F0; font-size: 11px; }
        table.services tfoot td { font-weight: bold; border-top: 2px solid #1A2332; border-bottom: none; }
        .terms { margin-top: 10px; text-align: justify; }
        .terms h3 { font-size: 12px; margin: 14px 0 6px; }
    </style>
</head>
<body>
    <header>
        <div class="brand">RankPro Solutions</div>
        <div class="brand-sub">Agencia de Marketing Digital · rankpro.mx</div>
    </header>
    <footer>RankPro Solutions — Propuesta {{ $numero }} — Página <span></span></footer>

    <div class="doc-title">Propuesta Comercial</div>
    <div class="doc-meta">No. {{ $numero }} &middot; Fecha de emisión: {{ $fechaEmision }}</div>

    <div class="box">
        <h3>Cliente</h3>
        <strong>{{ $cliente->empresa ?: $cliente->nombre }}</strong><br>
        @if ($cliente->contacto_nombre) Contacto: {{ $cliente->contacto_nombre }}<br> @endif
        @if ($cliente->email) Email: {{ $cliente->email }}<br> @endif
        @if ($cliente->telefono) Teléfono: {{ $cliente->telefono }} @endif
    </div>

    <p style="margin-bottom:14px;">
        Vigencia de la propuesta: <span class="validez">{{ $validezDias }} días</span> a partir de la fecha de emisión.
    </p>

    <table class="services">
        <thead>
            <tr><th>Servicio propuesto</th><th>Tipo</th><th style="text-align:right;">Inversión mensual</th></tr>
        </thead>
        <tbody>
            @foreach ($servicios as $servicio)
                <tr>
                    <td>{{ $servicio->nombre }}</td>
                    <td>{{ \App\Support\Labels::servicioTipo($servicio->tipo) }}</td>
                    <td style="text-align:right;">${{ number_format($servicio->precio_mensual, 2) }} MXN</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr><td colspan="2">Inversión mensual total</td><td style="text-align:right;">${{ number_format($totalMensual, 2) }} MXN</td></tr>
        </tfoot>
    </table>

    <div class="terms">
        <h3>Alcance y Condiciones</h3>
        {!! nl2br(e($condiciones)) !!}
    </div>
</body>
</html>
