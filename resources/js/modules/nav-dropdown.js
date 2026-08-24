/**
 * Megamenu de Servicios (escritorio) y acordeon de servicios (movil).
 *
 * En escritorio el despliegue base es CSS puro (:hover / :focus-within), asi que
 * el menu funciona aunque el JS falle. Este modulo solo anade lo que el CSS no
 * puede: abrir con clic/teclado, cerrar con Escape y mantener aria-expanded
 * sincronizado. En movil gestiona el acordeon, que si depende de JS.
 */
document.addEventListener('DOMContentLoaded', () => {
    const esEscritorio = () => window.matchMedia('(min-width: 1024px)').matches;

    /* ---------------- Megamenu de escritorio ---------------- */
    const triggers = document.querySelectorAll('.nav-dropdown-trigger');

    const cerrarTodos = () => {
        triggers.forEach((t) => {
            t.classList.remove('is-open');
            t.setAttribute('aria-expanded', 'false');
        });
    };

    triggers.forEach((trigger) => {
        trigger.addEventListener('click', (event) => {
            // En escritorio el disparador abre el panel en vez de navegar. El href
            // a /servicios sigue en el HTML para que Google lo rastree y para que
            // funcione sin JS.
            if (!esEscritorio()) {
                return;
            }

            event.preventDefault();
            const abierto = trigger.classList.contains('is-open');
            cerrarTodos();

            if (!abierto) {
                trigger.classList.add('is-open');
                trigger.setAttribute('aria-expanded', 'true');
            }
        });
    });

    document.addEventListener('click', (event) => {
        // El panel es hermano del disparador, no hijo: hay que mirar el <li>.
        const dentro = Array.from(triggers).some(
            (trigger) => (trigger.closest('li') ?? trigger).contains(event.target)
        );

        if (!dentro) {
            cerrarTodos();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
            return;
        }

        const abierto = document.querySelector('.nav-dropdown-trigger.is-open');

        if (abierto) {
            cerrarTodos();
            abierto.focus();
        }
    });

    /* ---------------- Acordeon del menu movil ---------------- */
    document.querySelectorAll('.mobile-submenu-trigger').forEach((trigger) => {
        const submenu = document.getElementById(trigger.getAttribute('aria-controls'));

        if (!submenu) {
            return;
        }

        trigger.addEventListener('click', () => {
            const abierto = submenu.classList.toggle('is-open');
            trigger.setAttribute('aria-expanded', String(abierto));
            // Cada acordeon (Servicios, Blog...) trae su propia etiqueta en el HTML;
            // los valores por defecto conservan el comportamiento anterior.
            trigger.setAttribute(
                'aria-label',
                abierto
                    ? trigger.dataset.labelOcultar ?? 'Ocultar servicios'
                    : trigger.dataset.labelMostrar ?? 'Mostrar servicios'
            );
        });
    });
});
