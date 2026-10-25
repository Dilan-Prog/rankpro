/**
 * Módulo Métricas (admin): autoenvío del selector cliente/mes, modal de
 * crear/editar definición, confirmación al eliminar y aviso si se sale con
 * valores sin guardar. Los datos llegan ya renderizados; nada pide al servidor.
 */
(function () {
  "use strict";

  const root = document.querySelector("[data-metricas]");
  if (!root) return;

  // Cambiar cliente o mes recarga la pantalla sin pulsar "Ver".
  root.querySelectorAll("[data-metricas-cliente], [data-metricas-mes]").forEach((el) => {
    el.addEventListener("change", () => el.form && el.form.submit());
  });

  const modalForm = document.querySelector("[data-metrica-form]");
  if (modalForm) {
    const titulo = document.querySelector("[data-metrica-modal-titulo]");
    const metodo = modalForm.querySelector("[data-metrica-method]");

    function abrir(modo, datos) {
      modalForm.reset();
      if (modo === "editar") {
        modalForm.action = datos.url;
        metodo.value = "PUT";
        metodo.disabled = false;
        titulo.textContent = "Editar métrica";
        modalForm.elements.nombre.value = datos.nombre || "";
        modalForm.elements.unidad.value = datos.unidad || "";
        modalForm.elements.meta.value = datos.meta || "";
        modalForm.elements.sentido.value = datos.sentido || "mayor";
        modalForm.elements.umbral_amarillo_pct.value = datos.umbral || 80;
        modalForm.elements.orden.value = datos.orden || "";
      } else {
        modalForm.action = modalForm.dataset.urlCrear;
        metodo.disabled = true;
        titulo.textContent = "Nueva métrica";
      }
      window.AgencyOS.openModal("metricaModal");
    }

    root.querySelector("[data-metrica-nueva]")?.addEventListener("click", () => abrir("crear"));
    root.querySelectorAll("[data-metrica-editar]").forEach((btn) => {
      btn.addEventListener("click", () => abrir("editar", btn.dataset));
    });
  }

  root.querySelectorAll("form[data-confirmar]").forEach((form) => {
    form.addEventListener("submit", (e) => {
      if (!window.confirm(form.dataset.confirmar)) e.preventDefault();
    });
  });

  // Aviso de cambios sin guardar: evita perder una captura larga por un clic en el menú.
  const formMes = root.querySelector("[data-metricas-form]");
  if (formMes) {
    let sucio = false;
    formMes.addEventListener("input", () => { sucio = true; });
    formMes.addEventListener("submit", () => { sucio = false; });
    window.addEventListener("beforeunload", (e) => {
      if (sucio) { e.preventDefault(); e.returnValue = ""; }
    });
  }
})();
