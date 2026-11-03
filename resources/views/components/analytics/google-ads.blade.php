{{--
    Google Ads · etiqueta global (gtag.js) · <head>
    ------------------------------------------------------------------
    Solo se emite si NO hay GTM (App\Support\Medicion::adsDirectoActivo): con GTM
    la etiqueta de Google Ads se despliega desde el contenedor y cargarla aqui
    tambien duplicaria cada visita y cada conversion.

    Va despues del Consent Mode por defecto (mismo parcial que usa GTM), asi
    que no registra nada de publicidad hasta que la persona acepte el banner.

    Conversiones: esta etiqueta sola habilita remarketing y la medicion base.
    Para contar una conversion hace falta la etiqueta de conversion de Google
    Ads (AW-XXXX/ETIQUETA): gtag('event', 'conversion', {send_to: 'AW-XXXX/ETIQUETA'}).
--}}
@if (\App\Support\Medicion::adsDirectoActivo())
    @php($adsId = \App\Support\Medicion::adsId())

    @include('components.analytics.consent-default')

    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $adsId }}"></script>
    <script>
        gtag('js', new Date());
        gtag('config', @json($adsId));
    </script>
@endif
