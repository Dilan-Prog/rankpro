{{--
    Google Tag Manager · <head>
    ------------------------------------------------------------------
    Va lo mas arriba posible del <head>, pero SIEMPRE despues del bloque de
    Consent Mode: gtag('consent','default',...) tiene que ejecutarse antes de
    que GTM cargue cualquier etiqueta, o las primeras peticiones salen sin
    senal de consentimiento y Google las descarta.

    Solo se emite en modo 'gtm' de App\Support\Medicion (ID de GTM valido y
    medicion activa); si no, no se emite absolutamente nada.
--}}
@if (\App\Support\Medicion::gtmActivo())
    @php($gtmId = \App\Support\Medicion::gtmId())

    {{-- 1. Consent Mode v2: estado por defecto ANTES de cargar GTM --}}
    @include('components.analytics.consent-default')

    {{-- 2. Contenedor de GTM --}}
    <script>
        (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
        new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
        j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
        'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
        })(window,document,'script','dataLayer','{{ $gtmId }}');
    </script>
@endif
