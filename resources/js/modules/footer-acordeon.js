/**
 * Acordeón del pie en teléfono: una sección abierta a la vez. Desde tablet las
 * columnas van siempre abiertas y el botón sale del orden de tabulación.
 */
document.addEventListener('DOMContentLoaded', () => {
    const pie = document.querySelector('.site-footer');

    if (!pie) return;

    const columnas = [...pie.querySelectorAll('.site-footer__col')];
    const telefono = window.matchMedia('(max-width: 767px)');

    const pintar = (abierta) => {
        columnas.forEach((col) => {
            const cerrada = telefono.matches && col !== abierta;
            const boton = col.querySelector('.site-footer__col-btn');

            col.classList.toggle('is-cerrada', cerrada);
            boton.setAttribute('aria-expanded', String(!cerrada));
            boton.tabIndex = telefono.matches ? 0 : -1;
        });
    };

    columnas.forEach((col) => {
        col.querySelector('.site-footer__col-btn').addEventListener('click', () => {
            if (telefono.matches) pintar(col.classList.contains('is-cerrada') ? col : null);
        });
    });

    pie.classList.add('con-acordeon');
    telefono.addEventListener('change', () => pintar(null));
    pintar(null);
});
