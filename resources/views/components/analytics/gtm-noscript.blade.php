{{--
    Google Tag Manager · <noscript>
    ------------------------------------------------------------------
    Justo despues de la etiqueta <body> de apertura. Solo aporta datos de
    usuarios sin JavaScript; se mantiene porque GTM lo espera y no cuesta nada.

    Nota SEO: el iframe lleva aria-hidden y tabindex="-1" para que no aparezca
    en el orden de tabulacion ni lo anuncien los lectores de pantalla.
--}}
@php
    $gtmId = config('analytics.gtm_id');
    $gtmActivo = $gtmId && in_array(config('app.env'), config('analytics.entornos'), true);
@endphp

@if ($gtmActivo)
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ $gtmId }}"
        height="0" width="0" style="display:none;visibility:hidden"
        title="Google Tag Manager" aria-hidden="true" tabindex="-1"></iframe></noscript>
@endif
