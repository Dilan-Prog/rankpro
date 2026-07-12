document.addEventListener('DOMContentLoaded', () => {
    const navbar = document.getElementById('navbar');

    if (!navbar) return;

    const applyShadow = () => {
        navbar.classList.toggle('is-scrolled', window.scrollY > 8);
    };

    applyShadow();
    window.addEventListener('scroll', applyShadow, { passive: true });
});
