{{--
    gtag.js directo (GA4 y/o Google Ads) · <head>
    ------------------------------------------------------------------
    Solo se emite en modo 'gtag' de App\Support\Medicion: es decir, SIN contenedor
    GTM. Con GTM, GA4 y Google Ads se despliegan desde el contenedor y cargarlos
    aqui tambien duplicaria cada visita y cada conversion.

    Un solo loader (gtag/js) aunque haya GA4 y Ads: el primer ID abre el script y
    gtag('config') se llama una vez por cada ID valido. Cargar el script dos veces
    mandaria cada evento por duplicado.

    Va despues del Consent Mode por defecto (mismo parcial que usa GTM, y solo uno
    de los dos se incluye por pagina), asi que no registra nada de publicidad ni
    analitica hasta que la persona acepte el banner.

    Conversiones: la etiqueta AW-XXXX/ETIQUETA de cada evento llega al JS publico por
    window.rankproMedicion (components/analytics/config).
--}}
@if (\App\Support\Medicion::gtagActivo())
    @php
        $ga4Id = \App\Support\Medicion::ga4Id();
        $adsId = \App\Support\Medicion::adsId();
        $idsGtag = array_values(array_filter([$ga4Id, $adsId]));
    @endphp

    @include('components.analytics.consent-default')

    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $idsGtag[0] }}"></script>
    <script>
        gtag('js', new Date());
@foreach ($idsGtag as $idGtag)
        gtag('config', @json($idGtag));
@endforeach
    </script>
@endif
