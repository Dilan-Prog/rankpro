/**
 * Contrato/Propuesta generator forms — shows only the selected client's
 * servicios checkbox group (all groups are pre-rendered, hidden by default).
 */
(function () {
  "use strict";

  function init() {
    const select = document.getElementById("documentoClienteSelect");
    const hint = document.getElementById("documentoServiciosHint");
    if (!select) return;

    function update() {
      const clienteId = select.value;
      let anyVisible = false;

      document.querySelectorAll(".documento-servicios-group").forEach((group) => {
        const show = group.dataset.clienteGroup === clienteId;
        group.hidden = !show;
        if (show) anyVisible = true;
        if (!show) {
          group.querySelectorAll("input[type=checkbox]").forEach((cb) => (cb.checked = false));
        }
      });

      if (hint) hint.hidden = anyVisible;
    }

    select.addEventListener("change", update);
    update();
  }

  document.addEventListener("shell:ready", init);
})();
