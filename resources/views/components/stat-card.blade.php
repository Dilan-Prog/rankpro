{{--
    KPI / stat tile. Usage:
    <x-stat-card label="MRR Activo" value="$337K" sub="MXN · 6 clientes activos"
                 :trend="8.2" icon="fa-arrow-trend-up" color="emerald" />
    $color maps to a .kpi__icon--{color} modifier defined in global.css
    (emerald, primary, amber, teal, red, blue).
--}}
@props(['label', 'value', 'sub' => null, 'trend' => null, 'icon', 'color' => 'primary'])
<div {{ $attributes->merge(['class' => 'kpi']) }}>
    <div class="kpi__top">
        <span class="kpi__label">{{ $label }}</span>
        <span class="kpi__icon kpi__icon--{{ $color }}"><i class="fa-solid {{ $icon }}"></i></span>
    </div>
    <div class="kpi__value">{{ $value }}</div>
    @if ($sub)
        <div class="kpi__sub">{{ $sub }}</div>
    @endif
    @if (!is_null($trend))
        <div class="kpi__trend {{ $trend >= 0 ? 'kpi__trend--up' : 'kpi__trend--down' }}">
            <i class="fa-solid {{ $trend >= 0 ? 'fa-arrow-up-right' : 'fa-arrow-down-right' }}"></i>
            {{ abs($trend) }}% vs mes anterior
        </div>
    @endif
</div>
