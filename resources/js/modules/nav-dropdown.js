document.addEventListener('DOMContentLoaded', () => {
    const triggers = document.querySelectorAll('.nav-dropdown-trigger');

    triggers.forEach((trigger) => {
        trigger.addEventListener('click', () => {
            const isOpen = trigger.classList.contains('is-open');

            triggers.forEach((other) => other.classList.remove('is-open'));

            if (!isOpen) {
                trigger.classList.add('is-open');
            }
        });
    });

    document.addEventListener('click', (event) => {
        const clickedInsideDropdown = Array.from(triggers).some((trigger) => trigger.contains(event.target));

        if (!clickedInsideDropdown) {
            triggers.forEach((trigger) => trigger.classList.remove('is-open'));
        }
    });
});
