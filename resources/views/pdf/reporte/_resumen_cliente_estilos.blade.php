{{--
    Estilos del resumen para el cliente (clases rc-*). Un solo parcial que comparten el
    PDF completo, el PDF de 1 página y la vista pública, para que las tres se vean igual.
    Solo tablas y bloques: dompdf no maneja flex ni grid, y los navegadores sí leen tablas.
--}}
        .rc-cols { width: 100%; }
        .rc-cols td { width: 50%; padding: 0 14px 0 0; }
        .rc-cols td.rc-der { padding: 0 0 0 14px; }
        .rc-bloque-titulo { font-size: 10px; font-weight: bold; letter-spacing: 1.4px; padding-bottom: 6px; border-bottom: 2px solid #1A2332; }
        .rc-logros .rc-bloque-titulo { color: #0F9D6E; border-bottom-color: #0F9D6E; }
        .rc-retos .rc-bloque-titulo { color: #B45309; border-bottom-color: #F59E0B; }
        .rc-item { font-size: 11px; line-height: 1.45; padding: 7px 0; border-bottom: 1px solid #E5E7EB; color: #1A2332; }
        .rc-vacio { font-size: 10px; color: #94A3B8; padding: 8px 0; }
        .rc-pasos { margin-top: 16px; }
        .rc-pasos .rc-bloque-titulo { color: #1A2332; }
        .rc-num { font-weight: bold; color: #0F9D6E; }
        .rc-graf-titulo { font-size: 10px; font-weight: bold; letter-spacing: 1.4px; color: #1A2332; margin-top: 18px; padding-bottom: 6px; border-bottom: 2px solid #1A2332; }
        .rc-leyenda { font-size: 9px; color: #64748B; padding: 6px 0 2px; }
        .rc-chip { display: inline-block; width: 8px; height: 8px; margin-right: 3px; }
        .rc-chip-1, .rc-bar-1 { background: #0F9D6E; }
        .rc-chip-2, .rc-bar-2 { background: #94A3B8; }
        table.rc-barras { width: 100%; height: 118px; border-bottom: 2px solid #1A2332; border-left: 1px solid #CBD5E1; }
        table.rc-barras td { vertical-align: bottom; text-align: center; padding: 0; }
        table.rc-par { margin: 0 auto; }
        table.rc-par td { vertical-align: bottom; padding: 0 1px 0 0; }
        .rc-bar { width: 14px; font-size: 1px; line-height: 1px; }
        table.rc-valores { width: 100%; margin-top: 4px; }
        table.rc-valores td { text-align: center; font-size: 8px; color: #64748B; padding: 1px 0; }
        table.rc-valores td.rc-x { font-weight: bold; color: #1A2332; }
        .rc-aviso { border: 1px solid #F59E0B; background: #FFFBEB; padding: 6px 10px; margin-top: 10px; font-size: 9px; color: #78350F; }
        .rc-aviso b { letter-spacing: 1.2px; color: #B45309; }
