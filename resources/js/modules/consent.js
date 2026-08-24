/**
 * Banner de consentimiento + Consent Mode v2.
 *
 * El estado por defecto ("denied" en todo salvo security_storage) ya lo declara
 * components/analytics/gtm.blade.php ANTES de cargar GTM. Este modulo solo se
 * encarga de la decision de la persona: pintar el banner si no hay decision
 * vigente, y actualizar el consentimiento cuando la haya.
 *
 * Por que importa para SEO/medicion: sin Consent Mode, quien rechaza cookies
 * desaparece de GA4 por completo. Con Consent Mode, Google recibe pings sin
 * cookies y modela esas conversiones, asi que los datos con los que se juzga
 * el plan de contenidos son bastante mas fiables.
 */
const CLAVE = 'rankpro_consent';

const TODO_CONCEDIDO = {
    ad_storage: 'granted',
    ad_user_data: 'granted',
    ad_personalization: 'granted',
    analytics_storage: 'granted',
    functionality_storage: 'granted',
    personalization_storage: 'granted',
    security_storage: 'granted',
};

const SOLO_NECESARIO = {
    ad_storage: 'denied',
    ad_user_data: 'denied',
    ad_personalization: 'denied',
    analytics_storage: 'denied',
    functionality_storage: 'denied',
    personalization_storage: 'denied',
    security_storage: 'granted',
};

const almacenamientoDisponible = () => {
    try {
        window.localStorage.setItem('__rp', '1');
        window.localStorage.removeItem('__rp');
        return true;
    } catch (e) {
        return false;
    }
};

const decisionVigente = (dias) => {
    if (!almacenamientoDisponible()) {
        return null;
    }

    try {
        const guardado = JSON.parse(window.localStorage.getItem(CLAVE));

        if (!guardado || !guardado.ts) {
            return null;
        }

        return Date.now() - guardado.ts < dias * 86400000 ? guardado.estado : null;
    } catch (e) {
        return null;
    }
};

const guardarDecision = (estado) => {
    if (almacenamientoDisponible()) {
        window.localStorage.setItem(CLAVE, JSON.stringify({ estado, ts: Date.now() }));
    }
};

const aplicar = (estado) => {
    // gtag() lo define el bloque inline de gtm.blade.php. Si GTM no esta activo
    // (entorno local, sin GTM_ID) no existe: guardamos la decision igual, para
    // que el banner no reaparezca, pero no hay nada que actualizar.
    if (typeof window.gtag === 'function') {
        window.gtag('consent', 'update', estado);
    }

    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push({
        event: 'consentimiento_actualizado',
        consentimiento: estado.analytics_storage === 'granted' ? 'aceptado' : 'rechazado',
    });
};

document.addEventListener('DOMContentLoaded', () => {
    const banner = document.querySelector('[data-cookie-banner]');

    if (!banner) {
        return;
    }

    const dias = Number(banner.dataset.dias || 180);

    if (decisionVigente(dias)) {
        return; // Ya decidio y sigue vigente: no molestar.
    }

    // Se muestra con clase, no con display inline, para que el CSS controle la
    // animacion y para que el banner no ocupe espacio en el HTML inicial.
    banner.classList.add('is-visible');
    banner.removeAttribute('hidden');

    const cerrar = (estado) => {
        guardarDecision(estado);
        aplicar(estado);
        banner.classList.remove('is-visible');
        banner.setAttribute('hidden', '');
    };

    banner.querySelector('[data-cookie-aceptar]')?.addEventListener('click', () => cerrar(TODO_CONCEDIDO));
    banner.querySelector('[data-cookie-rechazar]')?.addEventListener('click', () => cerrar(SOLO_NECESARIO));
});
