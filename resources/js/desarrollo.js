/**
 * Desarrollo module — Proceso Administrativo (Munch Galindo) phase panels.
 * Everything here is vanilla fetch()-based AJAX: checklist toggles and
 * field autosave inside the active phase panel, plus create/edit/delete for
 * Tareas, Bugs, QA and Comunicaciones — none of it reloads the page.
 * Approving/retroceding a phase is a normal form POST (full reload is fine
 * there since a whole new phase panel needs to render).
 */
(function () {
  "use strict";

  const { toast } = window.AgencyOS;
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

  const BADGE_MAP = {
    alta: ["Alta", "badge--danger"],
    media: ["Media", "badge--warning"],
    baja: ["Baja", "badge--success"],
    pendiente: ["Pendiente", "badge--warning"],
    en_progreso: ["En Progreso", "badge--info"],
    completada: ["Completada", "badge--success"],
    abierto: ["Abierto", "badge--orange"],
    resuelto: ["Resuelto", "badge--success"],
    aprobado: ["Aprobado", "badge--success"],
    fallido: ["Fallido", "badge--danger"],
  };

  const TIPO_PRUEBA_LABELS = {
    funcional: "Funcional",
    visual: "Visual",
    rendimiento: "Rendimiento",
    seguridad: "Seguridad",
  };

  function escapeHtml(value) {
    return String(value == null ? "" : value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  function badge(status) {
    const [label, cls] = BADGE_MAP[status] || [status, "badge--neutral"];
    return `<span class="badge ${cls}">${label}</span>`;
  }

  function dateOnly(value) {
    return value ? String(value).slice(0, 10) : null;
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

  // ---------- Fase panel: checklist + field autosave + Aprobar button state ----------
  function initFasePanel() {
    const panel = document.querySelector("[data-fase-panel]");
    const form = document.getElementById("faseForm");
    if (!panel || !form) return;

    const actionUrl = form.dataset.faseAction;
    const aprobarBtn = document.querySelector("[data-aprobar-btn]");
    const autosaveNote = document.querySelector("[data-autosave-note]");
    const checklistInputs = Array.from(panel.querySelectorAll("[data-checklist-item]"));

    function showSaved() {
      if (!autosaveNote) return;
      autosaveNote.textContent = "Guardado ✓";
      autosaveNote.classList.add("is-visible");
      clearTimeout(showSaved._t);
      showSaved._t = setTimeout(() => autosaveNote.classList.remove("is-visible"), 1500);
    }

    function recomputeLocalCompleteness() {
      const allChecked = checklistInputs.length > 0 && checklistInputs.every((i) => i.checked);
      if (aprobarBtn) aprobarBtn.disabled = !allChecked;
      return allChecked;
    }

    function collectPayload() {
      const payload = {};

      panel.querySelectorAll("[data-autosave]").forEach((field) => {
        payload[field.name || field.id] = field.value;
      });
      panel.querySelectorAll("[data-autosave-toggle]").forEach((field) => {
        payload[field.name] = field.checked;
      });

      const checklist = {};
      checklistInputs.forEach((input) => {
        checklist[input.dataset.checklistItem] = input.checked;
      });
      payload.checklist = checklist;

      const equipoRows = panel.querySelectorAll("[data-equipo-rows] .equipo-row");
      if (equipoRows.length) {
        payload.equipo_nombre = Array.from(equipoRows).map((row) => row.querySelector("[data-equipo-nombre]").value);
        payload.equipo_rol = Array.from(equipoRows).map((row) => row.querySelector("[data-equipo-rol]").value);
      }

      return payload;
    }

    function save(silent) {
      return request(actionUrl, "POST", collectPayload())
        .then((data) => {
          if (!silent) showSaved();
          if (typeof data.completo === "boolean" && aprobarBtn) aprobarBtn.disabled = !data.completo;
        })
        .catch(() => toast("No se pudo guardar el avance de la fase.", "error"));
    }

    checklistInputs.forEach((input) => {
      input.addEventListener("change", () => {
        recomputeLocalCompleteness();
        save(true);
      });
    });

    let debounceTimer;
    panel.querySelectorAll("[data-autosave]").forEach((field) => {
      const liveEvent = field.tagName === "TEXTAREA" || field.type === "text" || field.type === "number" ? "input" : "change";
      field.addEventListener(liveEvent, () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => save(true), 800);
      });
      field.addEventListener("blur", () => {
        clearTimeout(debounceTimer);
        save(true);
      });
    });

    panel.querySelectorAll("[data-autosave-toggle]").forEach((field) => {
      field.addEventListener("change", () => save(true));
    });

    const progresoRange = panel.querySelector("[data-progreso-range]");
    if (progresoRange) {
      const valueLabel = panel.querySelector("[data-progreso-value]");
      const fill = panel.querySelector("[data-progreso-fill]");
      progresoRange.addEventListener("input", () => {
        if (valueLabel) valueLabel.textContent = progresoRange.value + "%";
        if (fill) fill.style.width = progresoRange.value + "%";
      });
    }

    form.addEventListener("submit", (e) => {
      e.preventDefault();
      save(false);
    });

    const equipoRowsContainer = panel.querySelector("[data-equipo-rows]");
    const equipoAdd = panel.querySelector("[data-equipo-add]");
    if (equipoRowsContainer && equipoAdd) {
      equipoAdd.addEventListener("click", () => {
        const row = document.createElement("div");
        row.className = "equipo-row";
        row.innerHTML =
          '<input class="input" type="text" placeholder="Nombre" data-equipo-nombre>' +
          '<input class="input" type="text" placeholder="Rol" data-equipo-rol>' +
          '<button type="button" class="btn--icon" data-equipo-remove title="Quitar"><i class="fa-solid fa-xmark"></i></button>';
        equipoRowsContainer.appendChild(row);
      });
      equipoRowsContainer.addEventListener("click", (e) => {
        const btn = e.target.closest("[data-equipo-remove]");
        if (btn) btn.closest(".equipo-row").remove();
      });
    }
  }

  // ---------- Generic empty-state/table toggling ----------
  function toggleEmptyState(scope, emptyAttr, tableAttr) {
    const empty = document.querySelector(`[${emptyAttr}]`);
    const table = document.querySelector(`[${tableAttr}]`);
    const hasRows = scope.children.length > 0;
    if (empty) empty.hidden = hasRows;
    if (table) table.hidden = !hasRows;
  }

  // ---------- Tareas ----------
  function initTareas() {
    const form = document.getElementById("tareaForm");
    const rowsBody = document.querySelector("[data-tareas-rows]");
    if (!form || !rowsBody) return;

    const modalTitle = document.querySelector("[data-tarea-modal-title]");
    const submitLabel = document.querySelector("[data-tarea-submit-label]");

    function rowHtml(t) {
      return `<tr data-tarea-id="${t.id}" data-tarea-titulo="${escapeHtml(t.titulo)}" data-tarea-descripcion="${escapeHtml(t.descripcion || "")}" data-tarea-responsable="${escapeHtml(t.responsable || "")}" data-tarea-prioridad="${t.prioridad}" data-tarea-estado="${t.estado}" data-tarea-fecha-limite="${dateOnly(t.fecha_limite) || ""}">
        <td>${escapeHtml(t.titulo)}</td>
        <td><span style="font-size:var(--text-xs); color:var(--color-muted-foreground);">${escapeHtml(t.responsable || "—")}</span></td>
        <td>${badge(t.prioridad)}</td>
        <td>${badge(t.estado)}</td>
        <td class="u-mono" style="font-size:var(--text-xs); color:var(--color-muted-foreground);">${dateOnly(t.fecha_limite) || "—"}</td>
        <td><div style="display:flex; gap:4px;">
          <button type="button" class="btn--icon" title="Editar" data-edit-tarea="${t.id}"><i class="fa-solid fa-pen"></i></button>
          <button type="button" class="btn--icon" title="Eliminar" style="color:var(--text-danger);" data-delete-tarea="${t.id}"><i class="fa-solid fa-trash"></i></button>
        </div></td>
      </tr>`;
    }

    function resetForm() {
      form.reset();
      delete form.dataset.editingId;
      if (modalTitle) modalTitle.textContent = "Agregar Tarea";
      if (submitLabel) submitLabel.textContent = "Agregar";
    }

    document.getElementById("tareaModal")?.addEventListener("click", (e) => {
      if (e.target.closest('[data-modal-close="tareaModal"]') || e.target === e.currentTarget) resetForm();
    });
    document.querySelector('[onclick*="tareaModal"]')?.addEventListener("click", resetForm);

    form.addEventListener("submit", (e) => {
      e.preventDefault();
      const editingId = form.dataset.editingId;
      const url = editingId ? form.dataset.updateActionTemplate.replace("__ID__", editingId) : form.dataset.storeAction;
      const method = editingId ? "PUT" : "POST";
      const payload = Object.fromEntries(new FormData(form).entries());

      request(url, method, payload)
        .then((tarea) => {
          const existing = rowsBody.querySelector(`[data-tarea-id="${tarea.id}"]`);
          if (existing) existing.outerHTML = rowHtml(tarea);
          else rowsBody.insertAdjacentHTML("beforeend", rowHtml(tarea));

          toggleEmptyState(rowsBody, "data-tareas-empty", "data-tareas-table");
          resetForm();
          window.AgencyOS.closeModal("tareaModal");
          toast(editingId ? "Tarea actualizada." : "Tarea agregada.", "success");
        })
        .catch(() => toast("No se pudo guardar la tarea.", "error"));
    });

    rowsBody.addEventListener("click", (e) => {
      const editBtn = e.target.closest("[data-edit-tarea]");
      const deleteBtn = e.target.closest("[data-delete-tarea]");

      if (editBtn) {
        const row = editBtn.closest("tr");
        form.dataset.editingId = row.dataset.tareaId;
        form.querySelector("#t_titulo").value = row.dataset.tareaTitulo || "";
        form.querySelector("#t_descripcion").value = row.dataset.tareaDescripcion || "";
        form.querySelector("#t_responsable").value = row.dataset.tareaResponsable || "";
        form.querySelector("#t_prioridad").value = row.dataset.tareaPrioridad;
        form.querySelector("#t_estado").value = row.dataset.tareaEstado;
        form.querySelector("#t_fecha").value = row.dataset.tareaFechaLimite || "";
        if (modalTitle) modalTitle.textContent = "Editar Tarea";
        if (submitLabel) submitLabel.textContent = "Guardar Cambios";
        window.AgencyOS.openModal("tareaModal");
      }

      if (deleteBtn) {
        if (!window.confirm("¿Eliminar esta tarea?")) return;
        const id = deleteBtn.dataset.deleteTarea;
        request(`/admin/desarrollo/tareas/${id}`, "DELETE")
          .then(() => {
            rowsBody.querySelector(`[data-tarea-id="${id}"]`)?.remove();
            toggleEmptyState(rowsBody, "data-tareas-empty", "data-tareas-table");
            toast("Tarea eliminada.", "success");
          })
          .catch(() => toast("No se pudo eliminar la tarea.", "error"));
      }
    });
  }

  // ---------- Bugs ----------
  function initBugs() {
    const form = document.getElementById("bugForm");
    const rowsBody = document.querySelector("[data-bugs-rows]");
    if (!form || !rowsBody) return;

    const modalTitle = document.querySelector("[data-bug-modal-title]");
    const submitLabel = document.querySelector("[data-bug-submit-label]");

    function rowHtml(b) {
      return `<tr data-bug-id="${b.id}" data-bug-titulo="${escapeHtml(b.titulo)}" data-bug-descripcion="${escapeHtml(b.descripcion || "")}" data-bug-prioridad="${b.prioridad}" data-bug-estado="${b.estado}" data-bug-fecha-resolucion="${dateOnly(b.fecha_resolucion) || ""}">
        <td>${escapeHtml(b.titulo)}</td>
        <td>${badge(b.prioridad)}</td>
        <td>${badge(b.estado)}</td>
        <td class="u-mono" style="font-size:var(--text-xs); color:var(--color-muted-foreground);">${dateOnly(b.fecha_resolucion) || "—"}</td>
        <td><div style="display:flex; gap:4px;">
          <button type="button" class="btn--icon" title="Editar" data-edit-bug="${b.id}"><i class="fa-solid fa-pen"></i></button>
          <button type="button" class="btn--icon" title="Eliminar" style="color:var(--text-danger);" data-delete-bug="${b.id}"><i class="fa-solid fa-trash"></i></button>
        </div></td>
      </tr>`;
    }

    function resetForm() {
      form.reset();
      delete form.dataset.editingId;
      if (modalTitle) modalTitle.textContent = "Reportar Bug";
      if (submitLabel) submitLabel.textContent = "Reportar";
    }

    document.querySelector('[onclick*="bugModal"]')?.addEventListener("click", resetForm);

    form.addEventListener("submit", (e) => {
      e.preventDefault();
      const editingId = form.dataset.editingId;
      const url = editingId ? form.dataset.updateActionTemplate.replace("__ID__", editingId) : form.dataset.storeAction;
      const method = editingId ? "PUT" : "POST";
      const payload = Object.fromEntries(new FormData(form).entries());

      request(url, method, payload)
        .then((bug) => {
          const existing = rowsBody.querySelector(`[data-bug-id="${bug.id}"]`);
          if (existing) existing.outerHTML = rowHtml(bug);
          else rowsBody.insertAdjacentHTML("beforeend", rowHtml(bug));

          toggleEmptyState(rowsBody, "data-bugs-empty", "data-bugs-table");
          resetForm();
          window.AgencyOS.closeModal("bugModal");
          toast(editingId ? "Bug actualizado." : "Bug registrado.", "success");
        })
        .catch(() => toast("No se pudo guardar el bug.", "error"));
    });

    rowsBody.addEventListener("click", (e) => {
      const editBtn = e.target.closest("[data-edit-bug]");
      const deleteBtn = e.target.closest("[data-delete-bug]");

      if (editBtn) {
        const row = editBtn.closest("tr");
        form.dataset.editingId = row.dataset.bugId;
        form.querySelector("#b_titulo").value = row.dataset.bugTitulo || "";
        form.querySelector("#b_descripcion").value = row.dataset.bugDescripcion || "";
        form.querySelector("#b_prioridad").value = row.dataset.bugPrioridad;
        form.querySelector("#b_estado").value = row.dataset.bugEstado;
        form.querySelector("#b_fecha").value = row.dataset.bugFechaResolucion || "";
        if (modalTitle) modalTitle.textContent = "Editar Bug";
        if (submitLabel) submitLabel.textContent = "Guardar Cambios";
        window.AgencyOS.openModal("bugModal");
      }

      if (deleteBtn) {
        if (!window.confirm("¿Eliminar este bug?")) return;
        const id = deleteBtn.dataset.deleteBug;
        request(`/admin/desarrollo/bugs/${id}`, "DELETE")
          .then(() => {
            rowsBody.querySelector(`[data-bug-id="${id}"]`)?.remove();
            toggleEmptyState(rowsBody, "data-bugs-empty", "data-bugs-table");
            toast("Bug eliminado.", "success");
          })
          .catch(() => toast("No se pudo eliminar el bug.", "error"));
      }
    });
  }

  // ---------- QA ----------
  function initQa() {
    const form = document.getElementById("qaForm");
    const rowsBody = document.querySelector("[data-qa-rows]");
    if (!form || !rowsBody) return;

    function rowHtml(q) {
      return `<tr data-qa-id="${q.id}">
        <td>${TIPO_PRUEBA_LABELS[q.tipo_prueba] || q.tipo_prueba}</td>
        <td>${badge(q.resultado)}</td>
        <td><span style="font-size:var(--text-xs); color:var(--color-muted-foreground);">${escapeHtml(q.notas || "—")}</span></td>
        <td><button type="button" class="btn--icon" title="Eliminar" style="color:var(--text-danger);" data-delete-qa="${q.id}"><i class="fa-solid fa-trash"></i></button></td>
      </tr>`;
    }

    form.addEventListener("submit", (e) => {
      e.preventDefault();
      const payload = Object.fromEntries(new FormData(form).entries());

      request(form.dataset.action, "POST", payload)
        .then((qa) => {
          rowsBody.insertAdjacentHTML("beforeend", rowHtml(qa));
          toggleEmptyState(rowsBody, "data-qa-empty", "data-qa-table");
          form.reset();
          window.AgencyOS.closeModal("qaModal");
          toast("Prueba QA agregada.", "success");
        })
        .catch(() => toast("No se pudo guardar la prueba QA.", "error"));
    });

    rowsBody.addEventListener("click", (e) => {
      const deleteBtn = e.target.closest("[data-delete-qa]");
      if (!deleteBtn) return;
      if (!window.confirm("¿Eliminar esta prueba QA?")) return;
      const id = deleteBtn.dataset.deleteQa;
      request(`/admin/desarrollo/qa/${id}`, "DELETE")
        .then(() => {
          rowsBody.querySelector(`[data-qa-id="${id}"]`)?.remove();
          toggleEmptyState(rowsBody, "data-qa-empty", "data-qa-table");
          toast("Prueba QA eliminada.", "success");
        })
        .catch(() => toast("No se pudo eliminar la prueba QA.", "error"));
    });
  }

  // ---------- Comunicaciones ----------
  function initComunicaciones() {
    const form = document.getElementById("comunicacionForm");
    const list = document.querySelector("[data-comunicaciones-list]");
    if (!form || !list) return;

    function itemHtml(c) {
      const aprobaciones = c.aprobaciones
        ? `<p style="margin-top:4px; font-size:var(--text-xs); color:var(--text-success);"><i class="fa-solid fa-circle-check"></i> ${escapeHtml(c.aprobaciones)}</p>`
        : "";
      return `<div class="comunicacion-item" data-comunicacion-id="${c.id}">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; gap: var(--space-3);">
          <div>
            <div class="u-mono" style="font-size:var(--text-xs); color:var(--color-muted-foreground);">${dateOnly(c.fecha)}</div>
            <p style="margin-top:4px; font-size:var(--text-sm);">${escapeHtml(c.resumen)}</p>
            ${aprobaciones}
          </div>
          <button type="button" class="btn--icon" title="Eliminar" style="color:var(--text-danger);" data-delete-comunicacion="${c.id}"><i class="fa-solid fa-trash"></i></button>
        </div>
      </div>`;
    }

    form.addEventListener("submit", (e) => {
      e.preventDefault();
      const payload = Object.fromEntries(new FormData(form).entries());

      request(form.dataset.action, "POST", payload)
        .then((comunicacion) => {
          list.insertAdjacentHTML("afterbegin", itemHtml(comunicacion));
          toggleEmptyState(list, "data-comunicaciones-empty", "data-comunicaciones-list");
          form.reset();
          window.AgencyOS.closeModal("comunicacionModal");
          toast("Comunicación registrada.", "success");
        })
        .catch(() => toast("No se pudo registrar la comunicación.", "error"));
    });

    list.addEventListener("click", (e) => {
      const deleteBtn = e.target.closest("[data-delete-comunicacion]");
      if (!deleteBtn) return;
      if (!window.confirm("¿Eliminar esta comunicación?")) return;
      const id = deleteBtn.dataset.deleteComunicacion;
      request(`/admin/desarrollo/comunicaciones/${id}`, "DELETE")
        .then(() => {
          list.querySelector(`[data-comunicacion-id="${id}"]`)?.remove();
          toggleEmptyState(list, "data-comunicaciones-empty", "data-comunicaciones-list");
          toast("Comunicación eliminada.", "success");
        })
        .catch(() => toast("No se pudo eliminar la comunicación.", "error"));
    });
  }

  document.addEventListener("shell:ready", () => {
    initFasePanel();
    initTareas();
    initBugs();
    initQa();
    initComunicaciones();
  });
})();
