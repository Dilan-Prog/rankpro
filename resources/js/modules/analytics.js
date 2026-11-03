/**
 * Eventos de conversion del sitio publico.
 *
 * Empuja al dataLayer los eventos que en GTM se convierten en key events de GA4
 * y en conversiones de Google Ads. Se hace aqui y no con disparadores de clic de
 * GTM a proposito: los selectores viven junto al marcado que los produce, asi que
 * si alguien renombra una clase, el cambio se ve en el diff en lugar de romperse
 * en silencio dentro de la interfaz de GTM.
 *
 * Eventos que emite:
 *   click_whatsapp          · boton flotante, footer y cualquier enlace wa.me
 *   click_telefono          · enlaces tel:
 *   click_email             · enlaces mailto:
 *   envio_formulario_contacto · envio del formulario de /contacto
 *   scroll_90               · lectura completa (util para medir los articulos)
 *   agendar_completado      · reserva creada en /agendar (lo emite agendar.js)
 *
 * Dos modos segun window.rankproMedicion (lo imprime el servidor solo si hay
 * medicion configurada en el panel):
 *   gtm / sin variable -> dataLayer.push (comportamiento original)
 *   gtag               -> gtag('event', ...) y, si hay etiqueta, la conversion
 *
 * NOTA: no se envia ningun dato personal al dataLayer. De los enlaces mailto y
 * tel solo se registra su ubicacion en la pagina, nunca la direccion ni el
 * numero, para no filtrar datos de contacto a la analitica.
 */
const push = (evento, datos = {}) => {
    const medicion = window.rankproMedicion;

    // Sin la variable no hay medicion configurada en el panel: el comportamiento es
    // el de siempre (dataLayer, que GTM recoge si el contenedor viene del .env).
    if (!medicion || medicion.modo !== 'gtag') {
        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push({ event: evento, ...datos });

        return;
    }

    // Modo gtag (sin Tag Manager): gtag.js ya cargo y definio window.gtag. Si aun
    // no existe (bloqueador de anuncios, carga lenta) se descarta en silencio en
    // vez de romper la pagina.
    if (typeof window.gtag !== 'function') {
        return;
    }

    window.gtag('event', evento, datos);

    // Conversion de Google Ads: solo si el panel tiene la etiqueta de ese evento.
    const etiqueta = medicion.conversiones && medicion.conversiones[evento];

    if (etiqueta) {
        window.gtag('event', 'conversion', { send_to: etiqueta });
    }
};

// Publica la misma logica para otros scripts (p. ej. agendar.js emite
// 'agendar_completado'). Nunca debe recibir datos personales.
window.rankproEvento = push;

/** Ubicacion legible del enlace, para poder comparar que CTA funciona mejor. */
const ubicacion = (elemento) => {
    if (elemento.closest('.whatsapp-float')) return 'boton_flotante';
    if (elemento.closest('footer')) return 'footer';
    if (elemento.closest('header, .navbar, .topbar')) return 'cabecera';
    if (elemento.closest('form')) return 'formulario';
    if (elemento.closest('.hero')) return 'hero';

    return 'contenido';
};

document.addEventListener('DOMContentLoaded', () => {
    /* ---------------- Clics en canales de contacto ---------------- */
    document.addEventListener('click', (event) => {
        const enlace = event.target.closest('a[href]');

        if (!enlace) {
            return;
        }

        const href = enlace.getAttribute('href') || '';

        if (href.includes('wa.me') || href.includes('api.whatsapp.com')) {
            push('click_whatsapp', { ubicacion: ubicacion(enlace) });
        } else if (href.startsWith('tel:')) {
            push('click_telefono', { ubicacion: ubicacion(enlace) });
        } else if (href.startsWith('mailto:')) {
            push('click_email', { ubicacion: ubicacion(enlace) });
        }
    });

    /* ---------------- Envio del formulario de contacto ---------------- */
    document.querySelectorAll('form[data-form-contacto]').forEach((formulario) => {
        formulario.addEventListener('submit', () => {
            // En submit, no en la respuesta del servidor: la pagina navega y se
            // perderia el evento. Si el envio falla por validacion, GA4 contara
            // un intento de mas; es preferible a perder conversiones reales.
            push('envio_formulario_contacto', {
                formulario: formulario.dataset.formContacto || 'contacto',
            });
        });
    });

    /* ---------------- Lectura completa ---------------- */
    let scrollEnviado = false;

    window.addEventListener(
        'scroll',
        () => {
            if (scrollEnviado) {
                return;
            }

            const alto = document.documentElement.scrollHeight - window.innerHeight;

            if (alto > 0 && (window.scrollY / alto) >= 0.9) {
                scrollEnviado = true;
                push('scroll_90');
            }
        },
        { passive: true }
    );
});
