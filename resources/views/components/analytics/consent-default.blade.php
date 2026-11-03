{{--
    Consent Mode v2 · estado por defecto
    ------------------------------------------------------------------
    Define window.dataLayer y gtag() (los usa tambien el banner de cookies,
    resources/js/modules/consent.js) y declara el consentimiento por defecto o
    el que la persona ya haya guardado. Tiene que ir ANTES de cargar cualquier
    etiqueta de Google (GTM o gtag.js), o las primeras peticiones salen sin
    senal de consentimiento y Google las descarta.

    Lo incluyen components/analytics/gtm y components/analytics/gtag.
--}}
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
