// Tablero por cliente (sección de Reportes): comportamiento propio de la pantalla.
// Las barras y la línea se dibujan en el servidor (Blade + SVG); aquí solo se
// recarga al cambiar de cliente, para no obligar a pulsar un botón.
document.addEventListener("DOMContentLoaded", () => {
  const selector = document.querySelector("[data-tablero-selector]");
  if (!selector) return;

  selector.addEventListener("change", () => {
    if (selector.value) selector.form.submit();
  });
});
