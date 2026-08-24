{{--
    Google Tag Manager · <head>
    ------------------------------------------------------------------
    Va lo mas arriba posible del <head>, pero SIEMPRE despues del bloque de
    Consent Mode: gtag('consent','default',...) tiene que ejecutarse antes de
    que GTM cargue cualquier etiqueta, o las primeras peticiones salen sin
    senal de consentimiento y Google las descarta.

    Si no hay GTM_ID, o el entorno no esta en config('analytics.entornos'),
    no se emite absolutamente nada.
--}}
@php
    $gtmId = config('analytics.gtm_id');
    $gtmActivo = $gtmId && in_array(config('app.env'), config('analytics.entornos'), true);
@endphp

@if ($gtmActivo)
    {{-- 1. Consent Mode v2: estado por defecto ANTES de cargar GTM --}}
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}

        // Decision previa guardada en localStorage, si la hay.
        var rpConsent = null;
        try {
            var guardado = window.localStorage.getItem('rankpro_consent');
            if (guardado) {
                var parsed = JSON.parse(guardado);
                // Caduca a los {{ config('analytics.consent_dias') }} dias.
                if (parsed && parsed.ts && (Date.now() - parsed.ts) < {{ config('analytics.consent_dias') * 86400000 }}) {
                    rpConsent = parsed.estado;
                }
            }
        } catch (e) { /* localStorage bloqueado: se trata como sin decision */ }

        gtag('consent', 'default', rpConsent || @json(config('analytics.consent_por_defecto')));

        // Google necesita saber que el sitio implementa Consent Mode aunque
        // la persona aun no haya decidido.
        gtag('set', 'ads_data_redaction', true);
        gtag('set', 'url_passthrough', true);
    </script>

    {{-- 2. Contenedor de GTM --}}
    <script>
        (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
        new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
        j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
        'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
        })(window,document,'script','dataLayer','{{ $gtmId }}');
    </script>
@endif
