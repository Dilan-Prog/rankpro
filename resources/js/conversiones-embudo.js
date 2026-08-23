/**
 * Tablero de embudo (kanban) de Conversiones por cliente — arrastrar y
 * soltar una tarjeta entre columnas reclasifica la conversión. Usa la API
 * nativa de HTML5 Drag and Drop (sin librería nueva) y reutiliza el mismo
 * endpoint POST .../conversiones/{conversion}/etapa que ya usa el select de
 * la vista de tabla, pidiendo JSON en vez de una redirección de página.
 */
(function () {
  "use strict";

  const { toast } = window.AgencyOS;
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

  function updateCount(zone) {
    const column = zone.closest("[data-etapa-id]");
    const badge = column?.querySelector("[data-kanban-count]");
    if (badge) badge.textContent = zone.children.length;
  }

  document.addEventListener("DOMContentLoaded", () => {
    const board = document.querySelector("[data-kanban]");
    if (!board) return;

    const asignarBase = board.dataset.asignarBase;
    let draggedCard = null;

    board.addEventListener("dragstart", (e) => {
      const card = e.target.closest("[data-kanban-card]");
      if (!card) return;
      draggedCard = card;
      e.dataTransfer.setData("text/plain", card.dataset.conversionId);
      e.dataTransfer.effectAllowed = "move";
      setTimeout(() => card.classList.add("is-dragging"), 0);
    });

    board.addEventListener("dragend", (e) => {
      const card = e.target.closest("[data-kanban-card]");
      if (card) card.classList.remove("is-dragging");
      draggedCard = null;
    });

    board.querySelectorAll("[data-kanban-dropzone]").forEach((zone) => {
      zone.addEventListener("dragover", (e) => {
        e.preventDefault();
        e.dataTransfer.dropEffect = "move";
        zone.classList.add("is-drag-over");
      });

      zone.addEventListener("dragleave", () => {
        zone.classList.remove("is-drag-over");
      });

      zone.addEventListener("drop", (e) => {
        e.preventDefault();
        zone.classList.remove("is-drag-over");
        if (!draggedCard) return;

        const conversionId = draggedCard.dataset.conversionId;
        const etapaId = zone.closest("[data-etapa-id]").dataset.etapaId;
        const sourceZone = draggedCard.parentElement;
        if (sourceZone === zone) return;

        fetch(`${asignarBase}/${conversionId}/etapa`, {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
            "X-CSRF-TOKEN": csrfToken,
          },
          body: JSON.stringify({ ads_embudo_etapa_id: etapaId || null }),
        })
          .then((res) => {
            if (!res.ok) throw new Error("request_failed");
            return res.json();
          })
          .then(() => {
            zone.appendChild(draggedCard);
            updateCount(zone);
            updateCount(sourceZone);
          })
          .catch(() => toast("No se pudo mover la tarjeta a esa etapa.", "error"));
      });
    });
  });
})();
