/**
 * Archivos module — the client selector reloads the page with ?cliente=ID
 * (files are per-client, loaded server-side, same pattern as the SEO module).
 */
(function () {
  "use strict";

  function init() {
    const select = document.getElementById("archivoClientSelect");
    if (!select) return;
    select.addEventListener("change", () => {
      const url = new URL(window.location.href);
      url.searchParams.set("cliente", select.value);
      window.location.href = url.toString();
    });
  }

  document.addEventListener("shell:ready", init);
})();
