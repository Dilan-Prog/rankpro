{{--
    Dificultad (KD) como barrita + número. Mismos umbrales que kdHtml() en
    keywords.js (<40 bajo, 40-69 medio, >=70 alto): si uno cambia, cambia el otro.
    $valor puede ser null: se pinta un guion, nunca "null".
--}}
@php
    $kdValor = ($valor ?? null) === null || $valor === '' ? null : max(0, min(100, (int) round($valor)));
    $kdNivel = $kdValor === null ? null : ($kdValor >= 70 ? 'alto' : ($kdValor >= 40 ? 'medio' : 'bajo'));
@endphp
@if ($kdValor === null)
    <span class="kwx-nulo">—</span>
@else
    <span class="kwx-kd" title="Dificultad {{ $kdValor }} de 100"><span class="kwx-kd__barra" aria-hidden="true"><span class="kwx-kd__relleno kwx-kd__relleno--{{ $kdNivel }}" style="width:{{ $kdValor }}%"></span></span><span class="kwx-kd__num u-mono">{{ $kdValor }}</span></span>
@endif
