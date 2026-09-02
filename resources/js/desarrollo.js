/**
 * Desarrollo module — Proceso Administrativo (Munch Galindo) phase panels,
 * plus the two Kanban boards added on top of the existing list/table views:
 *
 *   (A) Proyectos-by-phase board on index.blade.php (data-proyecto-kanban).
 *       Drag is constrained to the current phase's immediate neighbor
 *       columns only — forward re-uses fase.aprobar (gated on the phase's
 *       checklist, same as the "Aprobar" button), backward re-uses
 *       fase.retroceder (no gate). A drop on a non-adjacent column is
 *       rejected before any request is sent.
 *   (B) Tareas/Bugs boards inside show.blade.php (data-tareas-kanban /
 *       data-bugs-kanban). Free drag between any column — no business
 *       gate — PUTs the new estado via the existing tareas.update /
 *       bugs.update routes, same endpoints the edit modal already uses.
 *
 * Both Kanban boards reuse the exact dragstart/dragover/dragleave/drop
 * wiring and the "only move the DOM node once the request succeeds"
 * pattern from resources/js/conversiones-embudo.js — a rejected/failed
 * drop simply never relocates the card, which is visually equivalent to a
 * snap-back with none of the animation complexity.
 *
 * Everything else here is vanilla fetch()-based AJAX: checklist toggles
 * and field autosave inside the active phase panel, plus create/edit/delete
 * for Tareas, Bugs, QA and Comunicaciones — none of it reloads the page.
 * Approving/retroceding a phase from the show-page buttons is still a
 * normal form POST (full reload is fine there since a whole new phase
 * panel needs to render) — the Kanban board is the only caller that asks
 * those same two routes for a JSON response instead.
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
    // Proyecto estado (used by the index Kanban cards after a fase move)
    activo: ["Activo", "badge--success"],
    pausado: ["Pausado", "badge--warning"],
    cancelado: ["Cancelado", "badge--danger"],
    cerrado: ["Cerrado", "badge--success"],
    // Proyecto fase_actual (index Proyectos/Entregas cards — mirrors badge.blade.php's map)
    planeacion: ["Planeación", "badge--info"],
    organizacion: ["Organización", "badge--primary"],
    direccion: ["Dirección", "badge--warning"],
    control: ["Control", "badge--orange"],
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

  // ---------- View toggles (list/kanban, table/kanban) ----------
  // Generic: any button with [data-view-toggle-btn][data-view-toggle-group]
  // activates the panel sharing the same group whose [data-view-panel]
  // matches the button's data-view-toggle-btn value. Preference remembered
  // per group in localStorage so a reload keeps the chosen view.
  function initViewToggles() {
    const groups = new Set(
      Array.from(document.querySelectorAll("[data-view-toggle-group]")).map((el) => el.dataset.viewToggleGroup)
    );

    groups.forEach((group) => {
      const buttons = Array.from(document.querySelectorAll(`[data-view-toggle-btn][data-view-toggle-group="${group}"]`));
      const panels = Array.from(document.querySelectorAll(`[data-view-panel][data-view-toggle-group="${group}"]`));
      if (!buttons.length || !panels.length) return;

      const storageKey = `desarrollo:view:${group}`;
      let stored = null;
      try {
        stored = localStorage.getItem(storageKey);
      } catch (e) {
        stored = null;
      }
      const initial = stored && buttons.some((b) => b.dataset.viewToggleBtn === stored) ? stored : buttons[0].dataset.viewToggleBtn;

      function activate(view) {
        buttons.forEach((b) => b.classList.toggle("is-active", b.dataset.viewToggleBtn === view));
        panels.forEach((p) => {
          p.hidden = p.dataset.viewPanel !== view;
        });
        try {
          localStorage.setItem(storageKey, view);
        } catch (e) {
          /* ignore (private browsing, storage disabled, etc.) */
        }
      }

      buttons.forEach((b) => b.addEventListener("click", () => activate(b.dataset.viewToggleBtn)));
      activate(initial);
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

  // ---------- Proyectos Kanban (Variant A — index.blade.php) ----------
  // Adjacent-phase-only drag gate: a card may only be dropped one column to
  // the right (aprobar — gated on the phase checklist server-side, exactly
  // like clicking "Aprobar" on the show page) or one column to the left
  // (retroceder — no gate). Any other target column is rejected client-side
  // before a request is ever sent. Adjacency is computed from each
  // column's data-orden (FaseProyecto::orden(), 1..5 including Cerrado).
  function initProyectosKanban() {
    const board = document.querySelector("[data-proyecto-kanban]");
    if (!board) return;

    const aprobarBase = board.dataset.aprobarBase;
    const retrocederBase = board.dataset.retrocederBase;
    let draggedCard = null;

    function updateCount(zone) {
      const column = zone.closest("[data-fase]");
      const countEl = column?.querySelector("[data-kanban-count]");
      if (countEl) countEl.textContent = zone.children.length;
    }

    function updateCard(card, data) {
      const estadoSlot = card.querySelector("[data-kanban-card-estado]");
      if (estadoSlot && data.estado) estadoSlot.innerHTML = badge(data.estado);

      if (typeof data.porcentaje_avance === "number") {
        const fill = card.querySelector("[data-kanban-card-progress-fill]");
        if (fill) fill.style.width = data.porcentaje_avance + "%";
        const porcentajeEl = card.querySelector("[data-kanban-card-porcentaje]");
        if (porcentajeEl) porcentajeEl.textContent = data.porcentaje_avance + "%";
      }
    }

    board.addEventListener("dragstart", (e) => {
      const card = e.target.closest("[data-kanban-card]");
      if (!card) return;
      draggedCard = card;
      e.dataTransfer.setData("text/plain", card.dataset.proyectoId);
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

        // Captured as a local const: `draggedCard` is reset to null by the
        // "dragend" listener above, which fires before this drop's async
        // fetch resolves — using the outer variable inside .then() would
        // read null by then and crash appendChild(null), masking a
        // successful server update as a false "no se pudo mover" error.
        const card = draggedCard;
        const sourceZone = card.parentElement;
        const sourceColumn = sourceZone.closest("[data-fase]");
        const targetColumn = zone.closest("[data-fase]");
        if (!sourceColumn || !targetColumn || sourceColumn === targetColumn) return;

        const diff = Number(targetColumn.dataset.orden) - Number(sourceColumn.dataset.orden);
        if (diff !== 1 && diff !== -1) {
          toast("Solo puedes mover un proyecto a la fase actual, anterior o siguiente.", "error");
          return;
        }

        const proyectoId = card.dataset.proyectoId;
        const endpoint = diff === 1
          ? `${aprobarBase}/${proyectoId}/fase/aprobar`
          : `${retrocederBase}/${proyectoId}/fase/retroceder`;

        fetch(endpoint, { method: "POST", headers: jsonHeaders(), body: JSON.stringify({}) })
          .then(async (res) => {
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(data.message || "No se pudo mover el proyecto a esa fase.");
            return data;
          })
          .then((data) => {
            zone.appendChild(card);
            card.dataset.fase = data.fase_actual;
            updateCard(card, data);
            updateCount(zone);
            updateCount(sourceZone);
            toast(data.message || "Proyecto movido.", "success");
          })
          .catch((err) => toast(err.message || "No se pudo mover el proyecto a esa fase.", "error"));
      });
    });
  }

  // ---------- Tareas ----------
  function initTareas() {
    const form = document.getElementById("tareaForm");
    const rowsBody = document.querySelector("[data-tareas-rows]");
    if (!form || !rowsBody) return;

    const modalTitle = document.querySelector("[data-tarea-modal-title]");
    const submitLabel = document.querySelector("[data-tarea-submit-label]");
    const kanbanBoard = document.querySelector("[data-tareas-kanban]");

    function rowHtml(t) {
      return `<tr data-tarea-id="${t.id}" data-tarea-titulo="${escapeHtml(t.titulo)}" data-tarea-descripcion="${escapeHtml(t.descripcion || "")}" data-tarea-responsable="${escapeHtml(t.responsable || "")}" data-tarea-prioridad="${t.prioridad}" data-tarea-estado="${t.estado}" data-tarea-fecha-limite="${dateOnly(t.fecha_limite) || ""}">
        <td>${escapeHtml(t.titulo)}</td>
        <td><span style="font-size:var(--text-xs); color:var(--color-muted-foreground);">${escapeHtml(t.responsable || "—")}</span></td>
        <td>${badge(t.prioridad)}</td>
        <td data-tarea-estado-cell>${badge(t.estado)}</td>
        <td class="u-mono" style="font-size:var(--text-xs); color:var(--color-muted-foreground);">${dateOnly(t.fecha_limite) || "—"}</td>
        <td><div style="display:flex; gap:4px;">
          <button type="button" class="btn--icon" title="Editar" data-edit-tarea="${t.id}"><i class="fa-solid fa-pen"></i></button>
          <button type="button" class="btn--icon" title="Eliminar" style="color:var(--text-danger);" data-delete-tarea="${t.id}"><i class="fa-solid fa-trash"></i></button>
        </div></td>
      </tr>`;
    }

    function cardHtml(t) {
      return `<div class="kanban__card" draggable="true" data-kanban-card data-tarea-id="${t.id}" data-tarea-titulo="${escapeHtml(t.titulo)}" data-tarea-descripcion="${escapeHtml(t.descripcion || "")}" data-tarea-responsable="${escapeHtml(t.responsable || "")}" data-tarea-prioridad="${t.prioridad}" data-tarea-estado="${t.estado}" data-tarea-fecha-limite="${dateOnly(t.fecha_limite) || ""}">
        <div class="kanban__card-top">
          <span class="kanban__card-title" title="${escapeHtml(t.titulo)}">${escapeHtml(t.titulo)}</span>
          ${badge(t.prioridad)}
        </div>
        <div class="kanban__card-meta">
          <span>${escapeHtml(t.responsable || "Sin responsable")}</span>
          <span class="u-mono">${dateOnly(t.fecha_limite) || "—"}</span>
        </div>
        <div style="display:flex; justify-content:flex-end; gap:4px; margin-top:var(--space-2);">
          <button type="button" class="btn--icon" title="Editar" data-edit-tarea="${t.id}"><i class="fa-solid fa-pen"></i></button>
          <button type="button" class="btn--icon" title="Eliminar" style="color:var(--text-danger);" data-delete-tarea="${t.id}"><i class="fa-solid fa-trash"></i></button>
        </div>
      </div>`;
    }

    function updateKanbanCount(zone) {
      if (!zone) return;
      const column = zone.closest("[data-estado]");
      const countEl = column?.querySelector("[data-kanban-count]");
      if (countEl) countEl.textContent = zone.children.length;
    }

    function upsertKanbanCard(t) {
      if (!kanbanBoard) return;
      const existing = kanbanBoard.querySelector(`[data-tarea-id="${t.id}"]`);
      const oldZone = existing?.parentElement || null;
      existing?.remove();

      const zone = kanbanBoard.querySelector(`[data-estado="${t.estado}"] [data-kanban-dropzone]`);
      if (zone) {
        zone.insertAdjacentHTML("beforeend", cardHtml(t));
        updateKanbanCount(zone);
      }
      if (oldZone && oldZone !== zone) updateKanbanCount(oldZone);
    }

    function removeKanbanCard(id) {
      if (!kanbanBoard) return;
      const existing = kanbanBoard.querySelector(`[data-tarea-id="${id}"]`);
      const zone = existing?.parentElement || null;
      existing?.remove();
      if (zone) updateKanbanCount(zone);
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
          upsertKanbanCard(tarea);

          toggleEmptyState(rowsBody, "data-tareas-empty", "data-tareas-table");
          resetForm();
          window.AgencyOS.closeModal("tareaModal");
          toast(editingId ? "Tarea actualizada." : "Tarea agregada.", "success");
        })
        .catch(() => toast("No se pudo guardar la tarea.", "error"));
    });

    function handleListClick(e) {
      const editBtn = e.target.closest("[data-edit-tarea]");
      const deleteBtn = e.target.closest("[data-delete-tarea]");

      if (editBtn) {
        const source = editBtn.closest("[data-tarea-id]");
        form.dataset.editingId = source.dataset.tareaId;
        form.querySelector("#t_titulo").value = source.dataset.tareaTitulo || "";
        form.querySelector("#t_descripcion").value = source.dataset.tareaDescripcion || "";
        form.querySelector("#t_responsable").value = source.dataset.tareaResponsable || "";
        form.querySelector("#t_prioridad").value = source.dataset.tareaPrioridad;
        form.querySelector("#t_estado").value = source.dataset.tareaEstado;
        form.querySelector("#t_fecha").value = source.dataset.tareaFechaLimite || "";
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
            removeKanbanCard(id);
            toggleEmptyState(rowsBody, "data-tareas-empty", "data-tareas-table");
            toast("Tarea eliminada.", "success");
          })
          .catch(() => toast("No se pudo eliminar la tarea.", "error"));
      }
    }

    rowsBody.addEventListener("click", handleListClick);
    kanbanBoard?.addEventListener("click", handleListClick);
  }

  // ---------- Tareas Kanban drag (Variant B, no business gate) ----------
  function initTareasKanbanDrag() {
    const board = document.querySelector("[data-tareas-kanban]");
    if (!board) return;

    const updateBase = board.dataset.updateBase;
    let draggedCard = null;

    function updateCount(zone) {
      const column = zone.closest("[data-estado]");
      const countEl = column?.querySelector("[data-kanban-count]");
      if (countEl) countEl.textContent = zone.children.length;
    }

    function syncTableRow(t) {
      const row = document.querySelector(`[data-tareas-rows] [data-tarea-id="${t.id}"]`);
      if (!row) return;
      row.dataset.tareaEstado = t.estado;
      const cell = row.querySelector("[data-tarea-estado-cell]");
      if (cell) cell.innerHTML = badge(t.estado);
    }

    board.addEventListener("dragstart", (e) => {
      const card = e.target.closest("[data-kanban-card]");
      if (!card) return;
      draggedCard = card;
      e.dataTransfer.setData("text/plain", card.dataset.tareaId);
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

      zone.addEventListener("dragleave", () => zone.classList.remove("is-drag-over"));

      zone.addEventListener("drop", (e) => {
        e.preventDefault();
        zone.classList.remove("is-drag-over");
        if (!draggedCard) return;

        // Captured as a local const: "dragend" (above) nulls out the outer
        // `draggedCard` before this drop's async request resolves — reading
        // it inside .then() would crash appendChild(null) after a
        // successful server update and show a false error toast instead.
        const card = draggedCard;
        const sourceZone = card.parentElement;
        if (sourceZone === zone) return;

        const nuevoEstado = zone.closest("[data-estado]").dataset.estado;
        const id = card.dataset.tareaId;
        const payload = {
          titulo: card.dataset.tareaTitulo,
          descripcion: card.dataset.tareaDescripcion || null,
          responsable: card.dataset.tareaResponsable || null,
          prioridad: card.dataset.tareaPrioridad,
          estado: nuevoEstado,
          fecha_limite: card.dataset.tareaFechaLimite || null,
        };

        request(`${updateBase}/${id}`, "PUT", payload)
          .then((tarea) => {
            zone.appendChild(card);
            card.dataset.tareaEstado = tarea.estado;
            updateCount(zone);
            updateCount(sourceZone);
            syncTableRow(tarea);
          })
          .catch(() => toast("No se pudo mover la tarea.", "error"));
      });
    });
  }

  // ---------- Bugs ----------
  function initBugs() {
    const form = document.getElementById("bugForm");
    const rowsBody = document.querySelector("[data-bugs-rows]");
    if (!form || !rowsBody) return;

    const modalTitle = document.querySelector("[data-bug-modal-title]");
    const submitLabel = document.querySelector("[data-bug-submit-label]");
    const kanbanBoard = document.querySelector("[data-bugs-kanban]");

    function rowHtml(b) {
      return `<tr data-bug-id="${b.id}" data-bug-titulo="${escapeHtml(b.titulo)}" data-bug-descripcion="${escapeHtml(b.descripcion || "")}" data-bug-prioridad="${b.prioridad}" data-bug-estado="${b.estado}" data-bug-fecha-resolucion="${dateOnly(b.fecha_resolucion) || ""}">
        <td>${escapeHtml(b.titulo)}</td>
        <td>${badge(b.prioridad)}</td>
        <td data-bug-estado-cell>${badge(b.estado)}</td>
        <td class="u-mono" style="font-size:var(--text-xs); color:var(--color-muted-foreground);">${dateOnly(b.fecha_resolucion) || "—"}</td>
        <td><div style="display:flex; gap:4px;">
          <button type="button" class="btn--icon" title="Editar" data-edit-bug="${b.id}"><i class="fa-solid fa-pen"></i></button>
          <button type="button" class="btn--icon" title="Eliminar" style="color:var(--text-danger);" data-delete-bug="${b.id}"><i class="fa-solid fa-trash"></i></button>
        </div></td>
      </tr>`;
    }

    function cardHtml(b) {
      return `<div class="kanban__card" draggable="true" data-kanban-card data-bug-id="${b.id}" data-bug-titulo="${escapeHtml(b.titulo)}" data-bug-descripcion="${escapeHtml(b.descripcion || "")}" data-bug-prioridad="${b.prioridad}" data-bug-estado="${b.estado}" data-bug-fecha-resolucion="${dateOnly(b.fecha_resolucion) || ""}">
        <div class="kanban__card-top">
          <span class="kanban__card-title" title="${escapeHtml(b.titulo)}">${escapeHtml(b.titulo)}</span>
          ${badge(b.prioridad)}
        </div>
        <div class="kanban__card-meta">
          <span class="u-mono">${dateOnly(b.fecha_resolucion) || "Sin fecha de resolución"}</span>
        </div>
        <div style="display:flex; justify-content:flex-end; gap:4px; margin-top:var(--space-2);">
          <button type="button" class="btn--icon" title="Editar" data-edit-bug="${b.id}"><i class="fa-solid fa-pen"></i></button>
          <button type="button" class="btn--icon" title="Eliminar" style="color:var(--text-danger);" data-delete-bug="${b.id}"><i class="fa-solid fa-trash"></i></button>
        </div>
      </div>`;
    }

    function updateKanbanCount(zone) {
      if (!zone) return;
      const column = zone.closest("[data-estado]");
      const countEl = column?.querySelector("[data-kanban-count]");
      if (countEl) countEl.textContent = zone.children.length;
    }

    function upsertKanbanCard(b) {
      if (!kanbanBoard) return;
      const existing = kanbanBoard.querySelector(`[data-bug-id="${b.id}"]`);
      const oldZone = existing?.parentElement || null;
      existing?.remove();

      const zone = kanbanBoard.querySelector(`[data-estado="${b.estado}"] [data-kanban-dropzone]`);
      if (zone) {
        zone.insertAdjacentHTML("beforeend", cardHtml(b));
        updateKanbanCount(zone);
      }
      if (oldZone && oldZone !== zone) updateKanbanCount(oldZone);
    }

    function removeKanbanCard(id) {
      if (!kanbanBoard) return;
      const existing = kanbanBoard.querySelector(`[data-bug-id="${id}"]`);
      const zone = existing?.parentElement || null;
      existing?.remove();
      if (zone) updateKanbanCount(zone);
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
          upsertKanbanCard(bug);

          toggleEmptyState(rowsBody, "data-bugs-empty", "data-bugs-table");
          resetForm();
          window.AgencyOS.closeModal("bugModal");
          toast(editingId ? "Bug actualizado." : "Bug registrado.", "success");
        })
        .catch(() => toast("No se pudo guardar el bug.", "error"));
    });

    function handleListClick(e) {
      const editBtn = e.target.closest("[data-edit-bug]");
      const deleteBtn = e.target.closest("[data-delete-bug]");

      if (editBtn) {
        const source = editBtn.closest("[data-bug-id]");
        form.dataset.editingId = source.dataset.bugId;
        form.querySelector("#b_titulo").value = source.dataset.bugTitulo || "";
        form.querySelector("#b_descripcion").value = source.dataset.bugDescripcion || "";
        form.querySelector("#b_prioridad").value = source.dataset.bugPrioridad;
        form.querySelector("#b_estado").value = source.dataset.bugEstado;
        form.querySelector("#b_fecha").value = source.dataset.bugFechaResolucion || "";
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
            removeKanbanCard(id);
            toggleEmptyState(rowsBody, "data-bugs-empty", "data-bugs-table");
            toast("Bug eliminado.", "success");
          })
          .catch(() => toast("No se pudo eliminar el bug.", "error"));
      }
    }

    rowsBody.addEventListener("click", handleListClick);
    kanbanBoard?.addEventListener("click", handleListClick);
  }

  // ---------- Bugs Kanban drag (Variant B, no business gate) ----------
  function initBugsKanbanDrag() {
    const board = document.querySelector("[data-bugs-kanban]");
    if (!board) return;

    const updateBase = board.dataset.updateBase;
    let draggedCard = null;

    function updateCount(zone) {
      const column = zone.closest("[data-estado]");
      const countEl = column?.querySelector("[data-kanban-count]");
      if (countEl) countEl.textContent = zone.children.length;
    }

    function syncTableRow(b) {
      const row = document.querySelector(`[data-bugs-rows] [data-bug-id="${b.id}"]`);
      if (!row) return;
      row.dataset.bugEstado = b.estado;
      const cell = row.querySelector("[data-bug-estado-cell]");
      if (cell) cell.innerHTML = badge(b.estado);
    }

    board.addEventListener("dragstart", (e) => {
      const card = e.target.closest("[data-kanban-card]");
      if (!card) return;
      draggedCard = card;
      e.dataTransfer.setData("text/plain", card.dataset.bugId);
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

      zone.addEventListener("dragleave", () => zone.classList.remove("is-drag-over"));

      zone.addEventListener("drop", (e) => {
        e.preventDefault();
        zone.classList.remove("is-drag-over");
        if (!draggedCard) return;

        // Captured as a local const: "dragend" (above) nulls out the outer
        // `draggedCard` before this drop's async request resolves — reading
        // it inside .then() would crash appendChild(null) after a
        // successful server update and show a false error toast instead.
        const card = draggedCard;
        const sourceZone = card.parentElement;
        if (sourceZone === zone) return;

        const nuevoEstado = zone.closest("[data-estado]").dataset.estado;
        const id = card.dataset.bugId;
        const payload = {
          titulo: card.dataset.bugTitulo,
          descripcion: card.dataset.bugDescripcion || null,
          prioridad: card.dataset.bugPrioridad,
          estado: nuevoEstado,
          fecha_resolucion: card.dataset.bugFechaResolucion || null,
        };

        request(`${updateBase}/${id}`, "PUT", payload)
          .then((bug) => {
            zone.appendChild(card);
            card.dataset.bugEstado = bug.estado;
            updateCount(zone);
            updateCount(sourceZone);
            syncTableRow(bug);
          })
          .catch(() => toast("No se pudo mover el bug.", "error"));
      });
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

  // ==========================================================================
  // Desarrollo index — sub-tabs, KPI/chart aggregates, filters, Entregas tab,
  // and AJAX-modal CRUD for proyectos plus the new global cross-project Bugs
  // tab. Everything below is additive: each init* guards on its own required
  // element, so it's inert on show.blade.php where this same file is also
  // loaded for the 9 functions above.
  // ==========================================================================

  const FASE_LABELS = {
    planeacion: "Planeación",
    organizacion: "Organización",
    direccion: "Dirección",
    control: "Control",
    cerrado: "Cerrado",
  };
  const FASE_ORDEN = { planeacion: 1, organizacion: 2, direccion: 3, control: 4, cerrado: 5 };
  const TIPO_PROYECTO_LABELS = { web_nueva: "Web Nueva", rediseno: "Rediseño", software: "Software", landing: "Landing Page" };

  let desarrolloChartInstance = null;

  /**
   * Like request(), but rejects with err.message "validation_failed"
   * (err.data.errors) on 422 and carries err.status/err.data on any
   * failure — needed for the proyecto/global-bug form modals' field-level
   * error rendering. Kept separate from the shared request() above (used by
   * initFasePanel/initTareas/initBugs/initQa/initComunicaciones) so that
   * function's plain "request_failed" throw stays untouched for its
   * existing callers — exact precedent: ads.js's adsRequest().
   */
  function desarrolloRequest(url, method, payload) {
    return fetch(url, { method, headers: jsonHeaders(), body: JSON.stringify(payload || {}) }).then((res) => {
      if (!res.ok) {
        return res
          .json()
          .catch(() => ({}))
          .then((data) => {
            const err = new Error(res.status === 422 ? "validation_failed" : "request_failed");
            err.status = res.status;
            err.data = data;
            throw err;
          });
      }
      return res.json();
    });
  }

  function renderDesarrolloFieldErrors(form, errors) {
    form.querySelectorAll("[data-error-for]").forEach((span) => {
      span.textContent = "";
    });
    Object.keys(errors || {}).forEach((field) => {
      const span = form.querySelector(`[data-error-for="${field}"]`);
      if (span) span.textContent = errors[field][0];
    });
  }

  function setKpi(id, value, sub) {
    const el = document.getElementById(id);
    if (!el) return;
    const valueEl = el.querySelector(".kpi__value");
    if (valueEl) valueEl.textContent = value;
    if (sub !== undefined) {
      const subEl = el.querySelector(".kpi__sub");
      if (subEl) subEl.textContent = sub;
    }
  }

  // ---------- Sub-tabs (Proyectos / Bugs / Entregas) — exact pattern from seo.js's initSeoTabs() ----------
  function initDesarrolloSubTabs() {
    const tabs = document.querySelectorAll("#desarrolloTabs .tabs__item");
    if (!tabs.length) return;

    tabs.forEach((tab) => {
      tab.addEventListener("click", () => {
        tabs.forEach((t) => t.classList.remove("is-active"));
        tab.classList.add("is-active");

        document.querySelectorAll("[data-panel-content]").forEach((panel) => {
          panel.hidden = panel.dataset.panelContent !== tab.dataset.panel;
        });

        if (tab.dataset.panel === "entregas") renderEntregas();
      });
    });
  }

  // ---------- Fase mini-visual (4 segments: Planeación/Organización/Dirección/Control) — mirrors index.blade.php's inline @foreach exactly ----------
  function faseMiniHtml(p) {
    return Object.keys(FASE_ORDEN)
      .filter((key) => key !== "cerrado")
      .map((key) => {
        const orden = FASE_ORDEN[key];
        const state = orden < p.fase_orden || p.fase_actual === "cerrado" ? "done" : orden === p.fase_orden ? "current" : "locked";
        return `<span class="proyecto-card__fase-seg proyecto-card__fase-seg--${state}" title="${FASE_LABELS[key]}"></span>`;
      })
      .join("");
  }

  // ---------- "Entrega en N días" / "Entrega vencida" — mirrors index.blade.php's $entregaInfo() closure ----------
  function entregaInfo(p) {
    if (!p.fecha_entrega_estimada || p.fase_actual === "cerrado") return null;
    const fecha = new Date(p.fecha_entrega_estimada + "T00:00:00");
    const hoy = new Date();
    hoy.setHours(0, 0, 0, 0);
    return Math.ceil((fecha.getTime() - hoy.getTime()) / 86400000);
  }

  function entregaBadgeHtml(p, vencidaLabel, hoyLabel, futureLabel) {
    const dias = entregaInfo(p);
    if (dias === null) return "";
    if (dias < 0) return `<span class="badge badge--danger">${vencidaLabel} (${Math.abs(dias)}d)</span>`;
    if (dias === 0) return `<span class="badge badge--warning">${hoyLabel}</span>`;
    return `<span style="font-size:var(--text-xs); color:var(--color-muted-foreground);">${futureLabel} ${dias} día${dias === 1 ? "" : "s"}</span>`;
  }

  // ---------- Proyecto row rendering — matches index.blade.php's <div data-proyecto-row> markup exactly ----------
  function proyectoRowInnerHtml(p) {
    const entregaBadge = entregaBadgeHtml(p, "Entrega vencida", "Entrega hoy", "Entrega en");
    const repoLink = p.url_repositorio
      ? `<a href="${p.url_repositorio}" target="_blank" rel="noopener" style="color:var(--color-primary);"><i class="fa-solid fa-code-branch"></i> Repositorio</a>`
      : "";
    const stagingLink = p.url_staging
      ? `<a href="${p.url_staging}" target="_blank" rel="noopener" style="color:var(--color-primary);"><i class="fa-solid fa-flask"></i> Staging</a>`
      : "";
    const responsableLine = p.responsable ? `<span>·</span><span>Responsable: ${escapeHtml(p.responsable)}</span>` : "";

    return `
      <div class="proyecto-card__head">
        <div>
          <div style="display:flex; align-items:center; gap:10px; margin-bottom:6px; flex-wrap:wrap;">
            <a href="${p.show_url}" style="font-weight:600; color:var(--color-foreground); font-size:var(--text-lg);">${escapeHtml(p.nombre)}</a>
            ${badge(p.fase_actual)}
            ${badge(p.estado)}
            ${entregaBadge}
          </div>
          <div style="display:flex; flex-wrap:wrap; gap: 6px; align-items:center; font-size:var(--text-xs); color:var(--color-muted-foreground);">
            <span>${escapeHtml(p.cliente)}</span><span>·</span><span>${TIPO_PROYECTO_LABELS[p.tipo] || p.tipo}</span>
            ${responsableLine}
          </div>
        </div>
        <div style="text-align:right; display:flex; align-items:flex-start; gap: var(--space-3);">
          <div>
            <div class="u-mono" style="font-size:var(--text-2xl); font-weight:700;">${p.porcentaje_avance}%</div>
            <div style="font-size:var(--text-xs); color:var(--color-muted-foreground);">completado</div>
          </div>
          <div style="display:flex; gap:4px;">
            <button type="button" class="btn--icon" title="Editar" data-edit-proyecto="${p.id}"><i class="fa-solid fa-pen"></i></button>
            <button type="button" class="btn--icon" title="Eliminar" style="color:var(--text-danger);" data-delete-proyecto="${p.id}"><i class="fa-solid fa-trash"></i></button>
          </div>
        </div>
      </div>

      <div class="proyecto-card__fase-mini" style="margin: var(--space-4) 0;" title="Fase actual: ${FASE_LABELS[p.fase_actual] || p.fase_actual}">
        ${faseMiniHtml(p)}
      </div>

      <div class="proyecto-card__stats">
        <div>
          <div class="proyecto-card__stat-label">Presupuesto</div>
          <div class="proyecto-card__stat-value u-mono">${window.AgencyOS.formatCurrency(p.presupuesto)}</div>
        </div>
        <div>
          <div class="proyecto-card__stat-label">Cobrado</div>
          <div class="proyecto-card__stat-value u-mono" style="color:var(--text-success)">${window.AgencyOS.formatCurrency(p.pagos_recibidos)}</div>
        </div>
        <div>
          <div class="proyecto-card__stat-label">Pendiente</div>
          <div class="proyecto-card__stat-value u-mono" style="color:var(--text-warning)">${window.AgencyOS.formatCurrency(p.pendiente)}</div>
        </div>
        <div>
          <div class="proyecto-card__stat-label">Bugs abiertos</div>
          <div class="proyecto-card__stat-value u-mono" style="${p.bugs_abiertos_count > 0 ? "color:var(--text-danger)" : ""}">${p.bugs_abiertos_count}</div>
        </div>
      </div>

      <div class="proyecto-card__footer">
        <div style="display:flex; gap:12px; flex-wrap:wrap; color:var(--color-muted-foreground);">
          <span>Inicio: ${dateOnly(p.fecha_inicio) || "—"}</span>
          <span>Entrega estimada: ${dateOnly(p.fecha_entrega_estimada) || "—"}</span>
          ${repoLink}
          ${stagingLink}
        </div>
        <a href="${p.show_url}" style="color:var(--color-primary); font-weight:500;">Ver detalle →</a>
      </div>`;
  }

  /** Dataset assigned via properties (not string-embedded JSON), safe regardless of quote characters in the data — mirrors ads.js's buildCampanaRowElement. */
  function buildProyectoRowElement(p) {
    const div = document.createElement("div");
    div.className = "card card--padded";
    div.setAttribute("data-proyecto-row", "");
    div.dataset.proyectoId = String(p.id);
    div.dataset.clienteId = String(p.cliente_id);
    div.dataset.estado = p.estado;
    div.dataset.proyecto = JSON.stringify(p);
    div.innerHTML = proyectoRowInnerHtml(p);
    return div;
  }

  function allProyectos() {
    return Array.from(document.querySelectorAll("[data-proyecto-row]")).map((row) => JSON.parse(row.dataset.proyecto));
  }

  function visibleProyectoRows() {
    return Array.from(document.querySelectorAll("[data-proyecto-row]")).filter((r) => r.style.display !== "none");
  }

  function updateDesarrolloSubtitle() {
    const subtitle = document.querySelector(".page-header__subtitle");
    if (!subtitle) return;
    const enProceso = allProyectos().filter((p) => p.fase_actual !== "cerrado").length;
    subtitle.textContent = `${enProceso} proyectos en proceso`;
  }

  // ---------- Filters (Proyectos tab) — plain dropdown-filter pattern, exact precedent: keywords.js's applyListaFilters() ----------
  function applyDesarrolloFilters() {
    const cliente = document.getElementById("desarrolloClienteFilter")?.value || "all";
    const estado = document.getElementById("desarrolloEstadoFilter")?.value || "all";

    let visibleCount = 0;
    document.querySelectorAll("[data-proyecto-row]").forEach((row) => {
      const matchesCliente = cliente === "all" || row.dataset.clienteId === String(cliente);
      const matchesEstado = estado === "all" || row.dataset.estado === estado;
      const show = matchesCliente && matchesEstado;
      row.style.display = show ? "" : "none";
      if (show) visibleCount++;
    });

    // Only the empty-state message is toggled here — the list container's
    // own [hidden] is owned by initViewToggles() (list vs. kanban), and
    // overwriting it here would fight that when the kanban view is active.
    const emptyEl = document.querySelector("[data-proyectos-empty]");
    if (emptyEl) emptyEl.hidden = visibleCount > 0;

    recomputeDesarrolloKpis();

    const entregasPanel = document.querySelector('[data-panel-content="entregas"]');
    if (entregasPanel && !entregasPanel.hidden) renderEntregas();
  }

  function initDesarrolloFilters() {
    const cliente = document.getElementById("desarrolloClienteFilter");
    const estado = document.getElementById("desarrolloEstadoFilter");
    if (!cliente && !estado) return;

    cliente?.addEventListener("change", applyDesarrolloFilters);
    estado?.addEventListener("change", applyDesarrolloFilters);
  }

  // ---------- KPI / chart aggregates (Proyectos tab) ----------
  function recomputeDesarrolloKpis() {
    const proyectos = visibleProyectoRows().map((r) => JSON.parse(r.dataset.proyecto));

    const totalPresupuesto = proyectos.reduce((a, p) => a + (Number(p.presupuesto) || 0), 0);
    const totalCobrado = proyectos.reduce((a, p) => a + (Number(p.pagos_recibidos) || 0), 0);
    const totalPendiente = proyectos.reduce((a, p) => a + (Number(p.pendiente) || 0), 0);
    const enCurso = proyectos.filter((p) => p.fase_actual !== "cerrado").length;
    // porcentaje_avance is always a real 0-100 number (never null) per
    // Proyecto::toRow() — a plain average is safe here, unlike averaging a
    // per-project ratio that can be genuinely absent (see ads.js's ROAS
    // comment for the null-vs-zero pitfall this avoids).
    const avance = proyectos.length ? proyectos.reduce((a, p) => a + (Number(p.porcentaje_avance) || 0), 0) / proyectos.length : 0;
    const pct = totalPresupuesto > 0 ? (totalCobrado / totalPresupuesto) * 100 : 0;

    setKpi("kpiProyectos", window.AgencyOS.formatNumber(proyectos.length), `${enCurso} en curso`);
    setKpi("kpiPresupuesto", window.AgencyOS.formatCurrency(totalPresupuesto));
    setKpi("kpiCobrado", window.AgencyOS.formatCurrency(totalCobrado), `${pct.toFixed(1)}% del total`);
    setKpi("kpiPorCobrar", window.AgencyOS.formatCurrency(totalPendiente));
    setKpi("kpiAvance", avance.toFixed(1) + "%");

    updateDesarrolloChart(proyectos);
  }

  function initDesarrolloChart() {
    const canvas = document.getElementById("desarrolloChart");
    if (!canvas || typeof Chart === "undefined") return;

    const colors = window.AgencyOS.chartColors();
    desarrolloChartInstance = new Chart(canvas, {
      type: "bar",
      data: {
        labels: [],
        datasets: [
          { label: "Cobrado", data: [], backgroundColor: "#0F9D6E", borderRadius: 4, maxBarThickness: 28, stack: "s" },
          { label: "Pendiente", data: [], backgroundColor: "#F59E0B", borderRadius: 4, maxBarThickness: 28, stack: "s" },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: true, position: "bottom", labels: { color: colors.tick, boxWidth: 10, font: { size: 11 } } },
          tooltip: {
            backgroundColor: colors.tooltipBg,
            borderColor: colors.tooltipBorder,
            borderWidth: 1,
            titleColor: colors.tooltipText,
            bodyColor: colors.tooltipText,
            padding: 10,
            callbacks: { label: (ctx) => `${ctx.dataset.label}: ${window.AgencyOS.formatCurrency(ctx.parsed.y)}` },
          },
        },
        scales: {
          x: { stacked: true, grid: { display: false }, ticks: { color: colors.tick, font: { size: 11 } } },
          y: {
            stacked: true,
            grid: { color: colors.grid },
            ticks: { color: colors.tick, font: { size: 11 }, callback: (v) => window.AgencyOS.formatCompact(v) },
          },
        },
      },
    });
  }

  /** Updates the existing Chart.js instance in place (chart.data = ...; chart.update();) instead of destroying/recreating the canvas — exact pattern from ads.js's updateAdsChart(). */
  function updateDesarrolloChart(proyectos) {
    if (!desarrolloChartInstance) return;
    desarrolloChartInstance.data.labels = proyectos.map((p) => p.nombre);
    desarrolloChartInstance.data.datasets[0].data = proyectos.map((p) => Number(p.pagos_recibidos) || 0);
    desarrolloChartInstance.data.datasets[1].data = proyectos.map((p) => Number(p.pendiente) || 0);
    desarrolloChartInstance.update();
  }

  // ---------- Entregas tab — rebuilt entirely from [data-proyecto-row]'s embedded JSON, no extra fetch ----------
  function renderEntregas() {
    const list = document.querySelector("[data-entregas-list]");
    if (!list) return;

    const proyectos = visibleProyectoRows()
      .map((r) => JSON.parse(r.dataset.proyecto))
      .sort((a, b) => (a.fecha_entrega_estimada || "9999-99-99").localeCompare(b.fecha_entrega_estimada || "9999-99-99"));

    list.innerHTML = proyectos
      .map((p) => {
        const badgeHtml = entregaBadgeHtml(p, "Vencida", "Hoy", "En");
        return `<div class="entrega-card">
          <div>
            <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px; flex-wrap:wrap;">
              <a href="${p.show_url}" style="font-weight:600; color:var(--color-foreground);">${escapeHtml(p.nombre)}</a>
              ${badge(p.fase_actual)}
            </div>
            <div style="font-size:var(--text-xs); color:var(--color-muted-foreground);">${escapeHtml(p.cliente)}</div>
          </div>
          <div style="text-align:right;">
            <div class="u-mono" style="font-size:var(--text-sm);">${dateOnly(p.fecha_entrega_estimada) || "Sin fecha"}</div>
            ${badgeHtml}
          </div>
        </div>`;
      })
      .join("");

    const emptyEl = document.querySelector("[data-entregas-empty]");
    if (emptyEl) emptyEl.hidden = proyectos.length > 0;
  }

  // ---------- Create/edit modal (Proyecto) ----------
  function openProyectoFormModal(p) {
    const form = document.getElementById("proyectoForm");
    if (!form) return;

    form.reset();
    form.querySelectorAll("[data-error-for]").forEach((span) => {
      span.textContent = "";
    });
    form.dataset.editingId = p?.id ?? "";

    const editOnlyWrap = form.querySelector("[data-edit-only]");
    if (editOnlyWrap) editOnlyWrap.hidden = !p;

    document.getElementById("proyectoFormModalTitle").textContent = p ? "Editar Proyecto" : "Nuevo Proyecto";
    document.getElementById("proyectoFormSubmit").innerHTML = p
      ? '<i class="fa-solid fa-check"></i> Guardar Cambios'
      : '<i class="fa-solid fa-check"></i> Crear Proyecto';

    if (p) {
      form.querySelector("#pf_cliente_id").value = String(p.cliente_id);
      form.querySelector("#pf_tipo").value = p.tipo;
      form.querySelector("#pf_nombre").value = p.nombre;
      form.querySelector("#pf_descripcion").value = p.descripcion || "";
      form.querySelector("#pf_presupuesto").value = p.presupuesto;
      form.querySelector("#pf_anticipo").value = p.anticipo;
      form.querySelector("#pf_forma_pago").value = p.forma_pago || "";
      form.querySelector("#pf_fecha_inicio").value = p.fecha_inicio || "";
      form.querySelector("#pf_fecha_entrega_estimada").value = p.fecha_entrega_estimada || "";
      form.querySelector("#pf_responsable").value = p.responsable || "";
      form.querySelector("#pf_pagos_recibidos").value = p.pagos_recibidos;
      form.querySelector("#pf_fecha_entrega_real").value = p.fecha_entrega_real || "";
      form.querySelector("#pf_estado").value = p.estado;
    }

    window.AgencyOS.openModal("proyectoFormModal");
  }

  /** Inserts/replaces a proyecto's card (create -> prepended at top, matching the controller's created_at desc ordering; update -> replaced in place). */
  function upsertProyectoRow(p) {
    const list = document.querySelector("[data-proyectos-list]");
    if (!list) return;

    const existing = list.querySelector(`[data-proyecto-row][data-proyecto-id="${p.id}"]`);
    const newRow = buildProyectoRowElement(p);
    if (existing) existing.replaceWith(newRow);
    else list.insertBefore(newRow, list.firstChild);

    updateDesarrolloSubtitle();
    applyDesarrolloFilters();
  }

  function initProyectoForm() {
    const form = document.getElementById("proyectoForm");
    if (!form) return;

    document.querySelector("[data-open-proyecto-modal]")?.addEventListener("click", () => openProyectoFormModal(null));

    document.addEventListener("click", (e) => {
      const editBtn = e.target.closest("[data-edit-proyecto]");
      if (!editBtn) return;
      const row = editBtn.closest("[data-proyecto-row]");
      if (row) openProyectoFormModal(JSON.parse(row.dataset.proyecto));
    });

    form.addEventListener("submit", (e) => {
      e.preventDefault();
      const editingId = form.dataset.editingId;
      const url = editingId ? form.dataset.updateActionTemplate.replace("__ID__", editingId) : form.dataset.storeAction;
      const payload = Object.fromEntries(new FormData(form).entries());

      desarrolloRequest(url, editingId ? "PUT" : "POST", payload)
        .then((p) => {
          upsertProyectoRow(p);
          window.AgencyOS.closeModal("proyectoFormModal");
          toast(editingId ? "Proyecto actualizado." : "Proyecto creado.", "success");
        })
        .catch((err) => {
          if (err.message === "validation_failed") {
            renderDesarrolloFieldErrors(form, err.data.errors || {});
            toast("Revisa los campos marcados.", "error");
          } else {
            toast("No se pudo guardar el proyecto.", "error");
          }
        });
    });
  }

  // ---------- Delete (Proyecto) — native window.confirm(), matching every other delete flow already in this file ----------
  function initProyectoDelete() {
    document.addEventListener("click", (e) => {
      const btn = e.target.closest("[data-delete-proyecto]");
      if (!btn) return;
      const row = document.querySelector(`[data-proyecto-row][data-proyecto-id="${btn.dataset.deleteProyecto}"]`);
      if (!row) return;
      const p = JSON.parse(row.dataset.proyecto);

      if (
        !window.confirm(
          `¿Eliminar el proyecto "${p.nombre}"? También se eliminarán sus tareas, bugs, pruebas QA y comunicaciones. Esta acción no se puede deshacer.`
        )
      )
        return;

      desarrolloRequest(`/admin/desarrollo/${p.id}`, "DELETE")
        .then(() => {
          row.remove();
          updateDesarrolloSubtitle();
          applyDesarrolloFilters();
          toast("Proyecto eliminado.", "success");
        })
        .catch(() => toast("No se pudo eliminar el proyecto.", "error"));
    });
  }

  // ---------- ?editar=ID on load — "Editar" from show.blade.php's line 19, which now points back here since the dedicated edit page no longer exists ----------
  function initDesarrolloEditFromQuery() {
    const editId = new URLSearchParams(location.search).get("editar");
    if (!editId) return;
    const row = document.querySelector(`[data-proyecto-row][data-proyecto-id="${editId}"]`);
    if (!row) return;
    openProyectoFormModal(JSON.parse(row.dataset.proyecto));
  }

  // ---------- Global Bugs tab — parallel, independent implementation from initBugs() above (that one is wired to show.blade.php's #bugForm/#bugModal specific ids); mirrors its structure/patterns closely for consistency ----------
  function recomputeBugsKpis() {
    const bugs = Array.from(document.querySelectorAll("[data-bug-row]"))
      .filter((r) => r.style.display !== "none")
      .map((r) => JSON.parse(r.dataset.bug));

    setKpi("kpiBugsAbiertos", window.AgencyOS.formatNumber(bugs.filter((b) => b.estado === "abierto").length));
    setKpi("kpiBugsEnProgreso", window.AgencyOS.formatNumber(bugs.filter((b) => b.estado === "en_progreso").length));
    setKpi("kpiBugsResueltos", window.AgencyOS.formatNumber(bugs.filter((b) => b.estado === "resuelto").length));
    setKpi("kpiBugsAlta", window.AgencyOS.formatNumber(bugs.filter((b) => b.prioridad === "alta").length));
  }

  function applyBugsFilters() {
    const proyecto = document.getElementById("bugsProyectoFilter")?.value || "all";
    const prioridad = document.getElementById("bugsPrioridadFilter")?.value || "all";
    const estado = document.getElementById("bugsEstadoFilter")?.value || "all";

    let visibleCount = 0;
    document.querySelectorAll("[data-bug-row]").forEach((row) => {
      const matchesProyecto = proyecto === "all" || row.dataset.proyectoId === String(proyecto);
      const matchesPrioridad = prioridad === "all" || row.dataset.prioridad === prioridad;
      const matchesEstado = estado === "all" || row.dataset.estado === estado;
      const show = matchesProyecto && matchesPrioridad && matchesEstado;
      row.style.display = show ? "" : "none";
      if (show) visibleCount++;
    });

    const emptyEl = document.querySelector("[data-desarrollo-bugs-empty]");
    const tableEl = document.querySelector("[data-desarrollo-bugs-table]");
    if (emptyEl) emptyEl.hidden = visibleCount > 0;
    if (tableEl) tableEl.hidden = visibleCount === 0;

    recomputeBugsKpis();
  }

  function bugRowCellsHtml(b) {
    const dias = b.dias_abierto == null ? "—" : b.dias_abierto;
    return `
      <td><a href="/admin/desarrollo/${b.proyecto_id}" style="color:var(--color-foreground); font-weight:500;">${escapeHtml(b.proyecto_nombre)}</a></td>
      <td>${escapeHtml(b.titulo)}</td>
      <td>${badge(b.prioridad)}</td>
      <td>${badge(b.estado)}</td>
      <td class="u-mono" style="font-size:var(--text-xs); color:var(--color-muted-foreground);">${dateOnly(b.created_at) || "—"}</td>
      <td class="u-mono" style="font-size:var(--text-xs); color:var(--color-muted-foreground);">${dateOnly(b.fecha_resolucion) || "—"}</td>
      <td class="u-mono">${dias}</td>
      <td>
        <div style="display:flex; gap:4px;">
          <button type="button" class="btn--icon" title="Editar" data-edit-bug-global="${b.id}"><i class="fa-solid fa-pen"></i></button>
          <button type="button" class="btn--icon" title="Eliminar" style="color:var(--text-danger);" data-delete-bug-global="${b.id}"><i class="fa-solid fa-trash"></i></button>
        </div>
      </td>`;
  }

  /** Dataset assigned via properties, safe regardless of quote characters in the data — mirrors buildProyectoRowElement/ads.js's buildCampanaRowElement. */
  function buildBugRowElement(b) {
    const tr = document.createElement("tr");
    tr.setAttribute("data-bug-row", "");
    tr.dataset.bugId = String(b.id);
    tr.dataset.proyectoId = String(b.proyecto_id);
    tr.dataset.prioridad = b.prioridad;
    tr.dataset.estado = b.estado;
    tr.dataset.bug = JSON.stringify(b);
    tr.innerHTML = bugRowCellsHtml(b);
    return tr;
  }

  function openGlobalBugFormModal(b) {
    const form = document.getElementById("globalBugForm");
    if (!form) return;

    form.reset();
    form.querySelectorAll("[data-error-for]").forEach((span) => {
      span.textContent = "";
    });
    form.dataset.editingId = b?.id ?? "";

    document.getElementById("globalBugFormModalTitle").textContent = b ? "Editar Bug" : "Reportar Bug";
    document.getElementById("globalBugFormSubmit").innerHTML = b
      ? '<i class="fa-solid fa-check"></i> Guardar Cambios'
      : '<i class="fa-solid fa-check"></i> Reportar Bug';

    if (b) {
      form.querySelector("#gb_proyecto_id").value = String(b.proyecto_id);
      form.querySelector("#gb_titulo").value = b.titulo;
      form.querySelector("#gb_descripcion").value = b.descripcion || "";
      form.querySelector("#gb_prioridad").value = b.prioridad;
      form.querySelector("#gb_estado").value = b.estado;
      form.querySelector("#gb_fecha_resolucion").value = b.fecha_resolucion || "";
    }
    // proyecto_id only exists to build the create URL (POST
    // /admin/desarrollo/{proyecto}/bugs) — bugs.update doesn't accept/use a
    // proyecto_id at all, so changing it once a bug exists would silently
    // do nothing server-side. Locked in edit mode to avoid that trap.
    form.querySelector("#gb_proyecto_id").disabled = !!b;

    window.AgencyOS.openModal("globalBugFormModal");
  }

  function upsertBugRow(b) {
    const tbody = document.querySelector("[data-desarrollo-bugs-table] tbody");
    if (!tbody) return;

    const existing = tbody.querySelector(`[data-bug-row][data-bug-id="${b.id}"]`);
    const newRow = buildBugRowElement(b);
    if (existing) existing.replaceWith(newRow);
    else tbody.insertBefore(newRow, tbody.firstChild);

    applyBugsFilters();
  }

  /** Keeps a Proyectos-tab card's "Bugs abiertos" stat (and its embedded data-proyecto JSON) in sync after a bug is created/updated/deleted from the global Bugs tab — that mutation only touches the bugs table by default, so without this the card's count goes stale until a reload. */
  function syncProyectoBugsCount(proyectoId) {
    const row = document.querySelector(`[data-proyecto-row][data-proyecto-id="${proyectoId}"]`);
    if (!row) return;

    const p = JSON.parse(row.dataset.proyecto);
    p.bugs_abiertos_count = Array.from(document.querySelectorAll(`[data-bug-row][data-proyecto-id="${proyectoId}"]`)).filter(
      (r) => r.dataset.estado === "abierto" || r.dataset.estado === "en_progreso"
    ).length;
    upsertProyectoRow(p);
  }

  function initDesarrolloBugsTab() {
    document.getElementById("bugsProyectoFilter")?.addEventListener("change", applyBugsFilters);
    document.getElementById("bugsPrioridadFilter")?.addEventListener("change", applyBugsFilters);
    document.getElementById("bugsEstadoFilter")?.addEventListener("change", applyBugsFilters);

    const form = document.getElementById("globalBugForm");
    if (!form) return;

    document.querySelector("[data-open-bug-modal]")?.addEventListener("click", () => openGlobalBugFormModal(null));

    document.addEventListener("click", (e) => {
      const editBtn = e.target.closest("[data-edit-bug-global]");
      if (editBtn) {
        const row = editBtn.closest("[data-bug-row]");
        if (row) openGlobalBugFormModal(JSON.parse(row.dataset.bug));
        return;
      }

      const deleteBtn = e.target.closest("[data-delete-bug-global]");
      if (deleteBtn) {
        const row = document.querySelector(`[data-bug-row][data-bug-id="${deleteBtn.dataset.deleteBugGlobal}"]`);
        if (!row) return;
        const b = JSON.parse(row.dataset.bug);
        if (!window.confirm(`¿Eliminar el bug "${b.titulo}"?`)) return;

        desarrolloRequest(`/admin/desarrollo/bugs/${b.id}`, "DELETE")
          .then(() => {
            row.remove();
            applyBugsFilters();
            syncProyectoBugsCount(b.proyecto_id);
            toast("Bug eliminado.", "success");
          })
          .catch(() => toast("No se pudo eliminar el bug.", "error"));
      }
    });

    form.addEventListener("submit", (e) => {
      e.preventDefault();
      const editingId = form.dataset.editingId;
      const proyectoId = form.querySelector("#gb_proyecto_id").value;
      const url = editingId ? `/admin/desarrollo/bugs/${editingId}` : `/admin/desarrollo/${proyectoId}/bugs`;
      const payload = Object.fromEntries(new FormData(form).entries());
      delete payload.proyecto_id; // only used to build the create URL above — not part of BugController::validated()

      desarrolloRequest(url, editingId ? "PUT" : "POST", payload)
        .then((b) => {
          upsertBugRow(b);
          syncProyectoBugsCount(b.proyecto_id);
          window.AgencyOS.closeModal("globalBugFormModal");
          toast(editingId ? "Bug actualizado." : "Bug registrado.", "success");
        })
        .catch((err) => {
          if (err.message === "validation_failed") {
            renderDesarrolloFieldErrors(form, err.data.errors || {});
            toast("Revisa los campos marcados.", "error");
          } else {
            toast("No se pudo guardar el bug.", "error");
          }
        });
    });
  }

  document.addEventListener("shell:ready", () => {
    initViewToggles();
    initProyectosKanban();
    initFasePanel();
    initTareas();
    initTareasKanbanDrag();
    initBugs();
    initBugsKanbanDrag();
    initQa();
    initComunicaciones();
    initDesarrolloSubTabs();
    initDesarrolloFilters();
    initDesarrolloChart();
    initProyectoForm();
    initProyectoDelete();
    initDesarrolloEditFromQuery();
    initDesarrolloBugsTab();
    applyDesarrolloFilters();
    applyBugsFilters();
  });
})();
