/**
 * Roles y Usuarios module — tab switching between Roles and Usuarios panels.
 */
(function () {
  "use strict";

  function init() {
    const tabs = document.querySelectorAll("#rolesTabs .tabs__item");
    if (!tabs.length) return;

    tabs.forEach((tab) => {
      tab.addEventListener("click", () => {
        tabs.forEach((t) => t.classList.remove("is-active"));
        tab.classList.add("is-active");

        document.querySelectorAll("[data-panel-content]").forEach((panel) => {
          panel.hidden = panel.dataset.panelContent !== tab.dataset.panel;
        });
      });
    });
  }

  document.addEventListener("shell:ready", init);
})();
