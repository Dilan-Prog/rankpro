/**
 * Keywords module — client-side multi-filter (search + tipo + estado + cliente)
 * over the server-rendered table.
 */
(function () {
  "use strict";

  function init() {
    const search = document.getElementById("kwSearch");
    const tipoFilter = document.getElementById("kwTipoFilter");
    const estadoFilter = document.getElementById("kwEstadoFilter");
    const clienteFilter = document.getElementById("kwClienteFilter");
    const rows = document.querySelectorAll("[data-kw-row]");
    const noResults = document.getElementById("kwNoResults");
    if (!rows.length) return;

    function applyFilters() {
      const term = (search?.value || "").trim().toLowerCase();
      const tipo = tipoFilter?.value || "all";
      const estado = estadoFilter?.value || "all";
      const cliente = clienteFilter?.value || "all";
      let visible = 0;

      rows.forEach((row) => {
        const show =
          (!term || row.dataset.search.includes(term)) &&
          (tipo === "all" || row.dataset.tipo === tipo) &&
          (estado === "all" || row.dataset.estado === estado) &&
          (cliente === "all" || row.dataset.cliente === cliente);
        row.style.display = show ? "" : "none";
        if (show) visible++;
      });

      if (noResults) noResults.hidden = visible !== 0;
    }

    [search].forEach((el) => el && el.addEventListener("input", window.AgencyOS.debounce(applyFilters, 150)));
    [tipoFilter, estadoFilter, clienteFilter].forEach((el) => el && el.addEventListener("change", applyFilters));
  }

  document.addEventListener("shell:ready", init);
})();
