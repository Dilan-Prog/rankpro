@php
    // Todo viene de la auditoría del ciclo vigente (captura manual); nada se deduce ni se rellena con ejemplos.
    $aud = $campana->faseAuditoria;
    $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
    $indexacionVerificada = (bool) ($aud?->checklist['indexacion_verificada'] ?? false);
    $hayCwv = $aud && ($aud->lcp_mobile !== null || $aud->cls_mobile !== null || $aud->fid_mobile !== null);
@endphp

<div class="seo-kpis">
    <article class="seo-kpi">
        <div class="seo-kpi__top">
            <span class="seo-kpi__label">SEO Score</span>
            <span class="seo-kpi__icono seo-kpi__icono--ambar" aria-hidden="true"><i class="fa-solid fa-gauge-high"></i></span>
        </div>
        @if ($aud?->seo_score !== null)
            <div class="seo-kpi__valor">{{ $aud->seo_score }}<span class="seo-kpi__unidad">/100</span></div>
            <p class="seo-kpi__sub">auditoría del ciclo {{ $campana->ciclo_actual }}</p>
        @else
            <div class="seo-kpi__valor seo-kpi__valor--vacio">—</div>
            <p class="seo-kpi__sub">Sin dato todavía</p>
        @endif
    </article>

    <article class="seo-kpi">
        <div class="seo-kpi__top">
            <span class="seo-kpi__label">Errores técnicos</span>
            <span class="seo-kpi__icono seo-kpi__icono--rojo" aria-hidden="true"><i class="fa-solid fa-triangle-exclamation"></i></span>
        </div>
        @if ($aud?->errores_tecnicos !== null)
            <div class="seo-kpi__valor">{{ number_format($aud->errores_tecnicos) }}</div>
            <p class="seo-kpi__sub">registrados en la auditoría</p>
        @else
            <div class="seo-kpi__valor seo-kpi__valor--vacio">—</div>
            <p class="seo-kpi__sub">Sin dato todavía</p>
        @endif
    </article>

    <article class="seo-kpi">
        <div class="seo-kpi__top">
            <span class="seo-kpi__label">Indexación</span>
            <span class="seo-kpi__icono seo-kpi__icono--verde" aria-hidden="true"><i class="fa-solid fa-globe"></i></span>
        </div>
        @if ($indexacionVerificada)
            <div class="seo-kpi__valor seo-kpi__valor--texto">{{ $aud->indexacion_ok ? 'Correcta' : 'Con problemas' }}</div>
            <p class="seo-kpi__sub">estado en Google, verificado en la auditoría</p>
        @else
            <div class="seo-kpi__valor seo-kpi__valor--vacio">—</div>
            <p class="seo-kpi__sub">Sin verificar todavía</p>
        @endif
    </article>

    <article class="seo-kpi">
        <div class="seo-kpi__top">
            <span class="seo-kpi__label">Core Web Vitals</span>
            <span class="seo-kpi__icono seo-kpi__icono--ambar" aria-hidden="true"><i class="fa-solid fa-bolt"></i></span>
        </div>
        @if ($hayCwv)
            <div class="seo-kpi__valor">{{ $aud->lcp_mobile !== null ? $num($aud->lcp_mobile).'s' : '—' }}<span class="seo-kpi__unidad"> LCP</span></div>
            <p class="seo-kpi__sub">móvil · CLS {{ $aud->cls_mobile !== null ? $num($aud->cls_mobile) : '—' }} · FID {{ $aud->fid_mobile !== null ? $num($aud->fid_mobile) : '—' }}</p>
        @else
            <div class="seo-kpi__valor seo-kpi__valor--vacio">—</div>
            <p class="seo-kpi__sub">Sin dato todavía</p>
        @endif
    </article>
</div>

<div class="seo-duo">
    <section class="seo-panel-card" aria-labelledby="seoTraficoTitulo">
        <header class="seo-panel-card__head">
            <h2 class="seo-panel-card__titulo" id="seoTraficoTitulo">Tráfico orgánico — {{ $campana->cliente->nombre }}</h2>
            @if ($serieTrafico && $serieTrafico['delta'])
                @php $pct = $serieTrafico['delta']['pct']; @endphp
                <span class="seo-delta {{ $pct > 0 ? 'seo-delta--sube' : ($pct < 0 ? 'seo-delta--baja' : '') }}">{{ $pct > 0 ? '+' : '' }}{{ $pct }}% vs {{ $serieTrafico['delta']['contra'] }}</span>
            @endif
        </header>
        @include('admin.seo._grafica-trafico', ['serie' => $serieTrafico, 'cuenta' => $campana->cliente->nombre])
    </section>

    <section class="seo-panel-card" aria-labelledby="seoResumenTitulo">
        <header class="seo-panel-card__head">
            <h2 class="seo-panel-card__titulo" id="seoResumenTitulo">Resumen de la cuenta</h2>
        </header>
        @php
            $r = $resumenCuenta;
            $filas = [
                ['Keywords en seguimiento', $r['keywords_total'] ?: null, 'Sin keywords ligadas'],
                ['Keywords en top 10', $r['keywords_con_posicion'] > 0 ? $r['keywords_top10'] : null, 'Sin mediciones'],
                ['Posiciones registradas', $r['posiciones_registradas'] ?: null, 'Sin registros'],
                ['Backlinks activos', $r['backlinks_total'] ? $r['backlinks_activos'].' / '.$r['backlinks_total'] : null, 'Sin registros'],
                ['Contenidos publicados', $r['contenidos_total'] ? $r['contenidos_publicados'].' / '.$r['contenidos_total'] : null, 'Sin registros'],
                ['Acciones on-page hechas', $r['onpage_total'] ? $r['onpage_completadas'].' / '.$r['onpage_total'] : null, 'Sin registros'],
                ['Último mes capturado', $r['ultima_metrica'], 'Sin registros'],
            ];
        @endphp
        <dl class="seo-filas">
            @foreach ($filas as [$etiqueta, $valor, $vacio])
                <div class="seo-filas__fila">
                    <dt>{{ $etiqueta }}</dt>
                    <dd class="{{ $valor === null ? 'seo-filas__vacio' : 'u-mono' }}">{{ $valor ?? $vacio }}</dd>
                </div>
            @endforeach
        </dl>
    </section>
</div>
