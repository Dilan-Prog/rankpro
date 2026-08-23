/**
 * Módulo global de Conversiones — edición en línea de la tabla. Fecha,
 * Cliente, Tipo e Identificador se quedan bloqueados a propósito (vienen del
 * script de tracking); solo Valor y las columnas personalizadas son
 * editables ahí mismo, sin recargar la página. Agregar/renombrar/eliminar
 * una columna sí recarga la página al terminar — cambia la estructura de la
 * tabla completa (cabecera + todas las filas), así que es más simple y
 * seguro que sincronizar el DOM a mano, y conserva los filtros activos
 * porque recarga la misma URL.
 */
(function () {
  "use strict";

  const { toast } = window.AgencyOS;
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

  function escapeHtml(value) {
    return String(value == null ? "" : value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  function jsonHeaders() {
    return {
      "Content-Type": "application/json",
      Accept: "application/json",
      "X-CSRF-TOKEN": csrfToken,
    };
  }

  function request(url, method, payload) {
    return fetch(url, { method, headers: jsonHeaders(), body: JSON.stringify(payload || {}) }).then((res) => {
      if (!res.ok) throw new Error("request_failed");
      return res.json();
    });
  }

  document.addEventListener("DOMContentLoaded", () => {
    const container = document.querySelector("[data-conversiones-table]");
    if (!container) return;

    const routes = {
      columnasStore: container.dataset.columnasStore,
      columnasBase: container.dataset.columnasBase,
      actualizarBase: container.dataset.actualizarBase,
    };

    container.addEventListener("click", (e) => {
      // Agregar columna — recarga la página para mantener la cabecera y todas las filas consistentes.
      const addColBtn = e.target.closest("[data-add-columna]");
      if (addColBtn) {
        const th = addColBtn.closest("th");
        th.innerHTML = `<input class="input" type="text" placeholder="Nombre de columna" autocomplete="off" style="width:140px;">`;
        const input = th.querySelector("input");
        input.focus();

        let addCancelled = false;
        const resetAddButton = () => {
          th.innerHTML = `<button type="button" class="btn--icon" data-add-columna title="Agregar columna"><i class="fa-solid fa-plus"></i></button>`;
        };
        const confirmAdd = () => {
          if (addCancelled) return;
          const nombre = input.value.trim();
          if (!nombre) {
            resetAddButton();
            return;
          }
          request(routes.columnasStore, "POST", { nombre })
            .then(() => window.location.reload())
            .catch(() => toast("No se pudo agregar la columna.", "error"));
        };
        input.addEventListener("keydown", (ev) => {
          if (ev.key === "Enter") {
            ev.preventDefault();
            input.blur();
          }
          if (ev.key === "Escape") {
            addCancelled = true;
            resetAddButton();
          }
        });
        input.addEventListener("blur", confirmAdd);
        return;
      }

      // Borrar columna
      const delColBtn = e.target.closest("[data-delete-columna]");
      if (delColBtn) {
        if (!window.confirm("¿Eliminar esta columna? Se perderán los valores guardados en ella para todas las conversiones.")) return;
        const columnaId = delColBtn.dataset.deleteColumna;
        request(`${routes.columnasBase}/${columnaId}`, "DELETE")
          .then(() => window.location.reload())
          .catch(() => toast("No se pudo eliminar la columna.", "error"));
        return;
      }

      // Renombrar columna (clic en el nombre del encabezado)
      const nombreSpan = e.target.closest("[data-columna-nombre-display]");
      if (nombreSpan) {
        const columnaId = nombreSpan.dataset.columnaNombreDisplay;
        const nombreActual = nombreSpan.textContent.trim();
        nombreSpan.innerHTML = `<input class="input" type="text" value="${escapeHtml(nombreActual)}" autocomplete="off" style="width:110px;">`;
        const input = nombreSpan.querySelector("input");
        input.focus();
        input.select();

        let renameCancelled = false;
        const saveRename = () => {
          if (renameCancelled) return;
          const nuevoNombre = input.value.trim();
          if (!nuevoNombre || nuevoNombre === nombreActual) {
            nombreSpan.textContent = nombreActual;
            return;
          }
          request(`${routes.columnasBase}/${columnaId}`, "PUT", { nombre: nuevoNombre })
            .then(() => window.location.reload())
            .catch(() => {
              toast("No se pudo renombrar la columna.", "error");
              nombreSpan.textContent = nombreActual;
            });
        };
        input.addEventListener("keydown", (ev) => {
          if (ev.key === "Enter") {
            ev.preventDefault();
            input.blur();
          }
          if (ev.key === "Escape") {
            renameCancelled = true;
            nombreSpan.textContent = nombreActual;
          }
        });
        input.addEventListener("blur", saveRename);
        return;
      }

      // Edición en línea de Valor o de una columna personalizada — sin recargar la página.
      const cell = e.target.closest("[data-editable-cell]");
      if (cell && !cell.querySelector("input")) {
        const field = cell.dataset.field;
        const row = cell.closest("tr");
        const conversionId = row.dataset.conversionId;
        const currentText = cell.textContent.trim();
        const currentValue = currentText === "—" ? "" : currentText;

        const inputType = field === "valor" ? "number" : "text";
        const inputStep = field === "valor" ? ' step="0.01" min="0"' : "";
        cell.innerHTML = `<input type="${inputType}"${inputStep} value="${escapeHtml(currentValue)}">`;
        const input = cell.querySelector("input");
        input.focus();
        input.select();

        let cancelled = false;

        const restoreDisplay = (displayValue) => {
          cell.textContent = displayValue && displayValue !== "" ? displayValue : "—";
        };

        const saveCell = () => {
          if (cancelled) return;
          const value = input.value.trim();
          const payload = field === "valor" ? { valor: value || null } : { datos_personalizados: { [cell.dataset.columnaId]: value || null } };

          request(`${routes.actualizarBase}/${conversionId}`, "PUT", payload)
            .then((fresh) => {
              if (field === "valor") {
                restoreDisplay(fresh.valor !== null ? Number(fresh.valor).toFixed(2) : "");
              } else {
                restoreDisplay((fresh.datos_personalizados || {})[cell.dataset.columnaId]);
              }
            })
            .catch(() => {
              toast("No se pudo guardar el cambio.", "error");
              restoreDisplay(currentValue);
            });
        };

        input.addEventListener("keydown", (ev) => {
          if (ev.key === "Enter") {
            ev.preventDefault();
            input.blur();
          }
          if (ev.key === "Escape") {
            cancelled = true;
            restoreDisplay(currentValue);
          }
        });
        input.addEventListener("blur", saveCell);
      }
    });
  });
})();
