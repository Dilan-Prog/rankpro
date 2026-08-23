document.addEventListener('DOMContentLoaded', () => {
    const triggers = document.querySelectorAll('.nav-dropdown-trigger');

    triggers.forEach((trigger) => {
        trigger.addEventListener('click', (event) => {
            // En escritorio el disparador abre el submenu; el href a /servicios
            // sigue existiendo para que Google lo rastree y para navegacion sin JS.
            if (window.matchMedia('(min-width: 1024px)').matches) {
                event.preventDefault();
            }

            const isOpen = trigger.classList.contains('is-open');

            triggers.forEach((other) => other.classList.remove('is-open'));

            if (!isOpen) {
                trigger.classList.add('is-open');
            }

            trigger.setAttribute('aria-expanded', String(!isOpen));
        });
    });

    document.addEventListener('click', (event) => {
        // El submenu es hermano del disparador, no hijo: hay que mirar el <li> contenedor.
        const clickedInsideDropdown = Array.from(triggers).some(
            (trigger) => (trigger.closest('li') ?? trigger).contains(event.target)
        );

        if (!clickedInsideDropdown) {
            triggers.forEach((trigger) => {
                trigger.classList.remove('is-open');
                trigger.setAttribute('aria-expanded', 'false');
            });
        }
    });
});
