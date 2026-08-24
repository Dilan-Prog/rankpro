{{--
    Banner de consentimiento de cookies.

    Se renderiza siempre en el HTML pero nace con [hidden]; el modulo
    resources/js/modules/consent.js decide si mostrarlo segun haya o no una
    decision vigente en localStorage. Asi no hay parpadeo ni salto de layout
    (el banner esta en position: fixed, fuera del flujo).

    Solo se emite si la medicion esta activa: sin GTM no hay nada que consentir
    y pedir permiso para nada seria ruido.
--}}
@php
    $gtmActivo = config('analytics.gtm_id')
        && in_array(config('app.env'), config('analytics.entornos'), true);
@endphp

@if ($gtmActivo)
    <div class="cookie-banner"
         data-cookie-banner
         data-dias="{{ config('analytics.consent_dias') }}"
         role="dialog"
         aria-modal="false"
         aria-labelledby="cookie-banner-titulo"
         aria-describedby="cookie-banner-texto"
         hidden>
        <div class="cookie-banner__contenido">
            <div class="cookie-banner__texto">
                <p class="cookie-banner__titulo" id="cookie-banner-titulo">Cookies y medición</p>
                <p id="cookie-banner-texto">
                    Usamos cookies propias y de terceros para entender cómo se usa el sitio y
                    mejorar nuestros servicios. Puedes rechazarlas sin perder ninguna
                    funcionalidad. Más detalle en la
                    <a href="{{ route('legal.cookies') }}">política de cookies</a>
                    y en el <a href="{{ route('legal.privacidad') }}">aviso de privacidad</a>.
                </p>
            </div>

            <div class="cookie-banner__acciones">
                <button type="button" class="cookie-banner__btn cookie-banner__btn--ghost" data-cookie-rechazar>
                    Solo lo necesario
                </button>
                <button type="button" class="cookie-banner__btn cookie-banner__btn--primary" data-cookie-aceptar>
                    Aceptar todas
                </button>
            </div>
        </div>
    </div>
@endif
