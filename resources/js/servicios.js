/**
 * Servicios module — client-side tab filter over the server-rendered table.
 */
(function () {
  "use strict";

  function init() {
    const tabs = document.querySelectorAll("#servicioTabs .tabs__item");
    const rows = document.querySelectorAll("[data-servicio-row]");
    const noResults = document.getElementById("servicioNoResults");
    if (!tabs.length || !rows.length) return;

    tabs.forEach((tab) => {
      tab.addEventListener("click", () => {
        tabs.forEach((t) => t.classList.remove("is-active"));
        tab.classList.add("is-active");

        const tipo = tab.dataset.tipo;
        let visible = 0;
        rows.forEach((row) => {
          const show = tipo === "all" || row.dataset.tipo === tipo;
          row.style.display = show ? "" : "none";
          if (show) visible++;
        });
        if (noResults) noResults.hidden = visible !== 0;
      });
    });
  }

  document.addEventListener("shell:ready", init);
})();
