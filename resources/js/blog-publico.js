/**
 * Blog publico: realce de la seccion activa en la tabla de contenidos.
 *
 * Es progresivo por diseno: los enlaces de la TOC son anclas reales y funcionan
 * sin JavaScript. Esto solo anade el estado visual .is-activo.
 *
 * Se usa IntersectionObserver y no un listener de scroll para no provocar
 * reflows en cada pixel (coste directo en INP).
 */
document.addEventListener('DOMContentLoaded', () => {
    const toc = document.querySelector('.toc');
    const contenido = document.querySelector('.prosa-articulo');

    if (!toc || !contenido || !('IntersectionObserver' in window)) {
        return;
    }

    const enlaces = new Map();

    toc.querySelectorAll('.toc__enlace').forEach((enlace) => {
        const id = decodeURIComponent((enlace.getAttribute('href') || '').slice(1));
        if (id) {
            enlaces.set(id, enlace);
        }
    });

    if (enlaces.size === 0) {
        return;
    }

    const titulos = Array.from(contenido.querySelectorAll('h2[id], h3[id]'))
        .filter((titulo) => enlaces.has(titulo.id));

    if (titulos.length === 0) {
        return;
    }

    let activo = null;

    const marcar = (id) => {
        if (activo === id) {
            return;
        }
        if (activo && enlaces.has(activo)) {
            enlaces.get(activo).classList.remove('is-activo');
        }
        activo = id;
        if (enlaces.has(id)) {
            enlaces.get(id).classList.add('is-activo');
        }
    };

    const observador = new IntersectionObserver(
        (entradas) => {
            const visibles = entradas
                .filter((entrada) => entrada.isIntersecting)
                .sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top);

            if (visibles.length > 0) {
                marcar(visibles[0].target.id);
            }
        },
        {
            // Franja de deteccion bajo el navbar fijo: el titulo se considera
            // activo cuando entra en el tercio superior de la ventana.
            rootMargin: '-96px 0px -70% 0px',
            threshold: 0,
        }
    );

    titulos.forEach((titulo) => observador.observe(titulo));
});
