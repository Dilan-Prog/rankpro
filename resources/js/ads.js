/**
 * Ads module — platform switching on the index page is server-rendered
 * (plain links reload with ?plataforma=...). On the create/edit forms,
 * the "Servicio" select is filtered client-side to the chosen client's
 * services (all options are pre-rendered with data-cliente, we just hide
 * the ones that don't match).
 */
(function () {
  "use strict";

  function initServicioCascade() {
    const clienteSelect = document.getElementById("cliente_id");
    const servicioSelect = document.getElementById("servicio_id");
    if (!clienteSelect || !servicioSelect) return;

    const options = Array.from(servicioSelect.options).filter((o) => o.dataset.cliente);

    function update() {
      const clienteId = clienteSelect.value;
      let firstMatch = null;

      options.forEach((option) => {
        const matches = option.dataset.cliente === clienteId;
        option.hidden = !matches;
        if (matches && !firstMatch) firstMatch = option;
      });

      if (servicioSelect.value && !options.find((o) => o.value === servicioSelect.value && !o.hidden)) {
        servicioSelect.value = "";
      }
    }

    clienteSelect.addEventListener("change", update);
    update();
  }

  document.addEventListener("shell:ready", initServicioCascade);
})();
