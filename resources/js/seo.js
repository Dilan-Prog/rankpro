/**
 * SEO module — Proceso Administrativo (Munch Galindo) phase panels, adapted
 * for a recurring service: Auditoría/Estrategia are approved once, then
 * Ejecución/Reporte cycle indefinitely (see the "Siguiente Ciclo" button
 * on the Reporte panel, a normal form POST like aprobar/retroceder).
 * Checklist toggles, field autosave, and posiciones/backlinks CRUD all use
 * vanilla fetch() — no page reloads for those.
 */
(function () {
  "use strict";

  const { toast } = window.AgencyOS;
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

  const BADGE_MAP = {
    activo: ["Activo", "badge--success"],
    caido: ["Caído", "badge--danger"],
    borrador: ["Borrador", "badge--neutral"],
    publicado: ["Publicado", "badge--success"],
    actualizar: ["Actualizar", "badge--warning"],
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

  /** Filters the create form's "Servicio" select to the chosen client's SEO services (all options pre-rendered with data-cliente, hidden client-side). */
  function initServicioCascade() {
    const clienteSelect = document.getElementById("cliente_id");
    const servicioSelect = document.getElementById("servicio_id");
    if (!clienteSelect || !servicioSelect) return;

    const options = Array.from(servicioSelect.options).filter((o) => o.dataset.cliente);

    function update() {
      const clienteId = clienteSelect.value;
      options.forEach((option) => {
        option.hidden = option.dataset.cliente !== clienteId;
      });
      if (servicioSelect.value && !options.find((o) => o.value === servicioSelect.value && !o.hidden)) {
        servicioSelect.value = "";
      }
    }

    clienteSelect.addEventListener("change", update);
    update();
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
        if (field.multiple) {
          payload[field.name.replace("[]", "")] = Array.from(field.selectedOptions).map((o) => o.value);
        } else {
          payload[field.name || field.id] = field.value;
        }
      });
      panel.querySelectorAll("[data-autosave-toggle]").forEach((field) => {
        payload[field.name] = field.checked;
      });

      const checklist = {};
      checklistInputs.forEach((input) => {
        checklist[input.dataset.checklistItem] = input.checked;
      });
      payload.checklist = checklist;

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

    form.addEventListener("submit", (e) => {
      e.preventDefault();
      save(false);
    });
  }

  // ---------- Generic empty-state/table toggling ----------
  function toggleEmptyState(emptyAttr, tableAttr, rowsContainer) {
    const empty = document.querySelector(`[${emptyAttr}]`);
    const table = document.querySelector(`[${tableAttr}]`);
    const hasRows = rowsContainer.children.length > 0;
    if (empty) empty.hidden = hasRows;
    if (table) table.hidden = !hasRows;
  }

  // ---------- Posiciones ----------
  function initPosiciones() {
    const form = document.getElementById("posicionForm");
    const rowsBody = document.querySelector("[data-posiciones-rows]");
    if (!form || !rowsBody) return;

    function rowHtml(p) {
      const actual = p.posicion_actual;
      const color = actual == null ? "inherit" : actual <= 3 ? "var(--text-success)" : actual <= 10 ? "var(--text-warning)" : "inherit";
      const variacionColor = p.variacion > 0 ? "var(--text-success)" : p.variacion < 0 ? "var(--text-danger)" : "inherit";
      return `<tr data-posicion-id="${p.id}">
        <td>${escapeHtml(p.keyword)}</td>
        <td><span class="u-mono" style="color:var(--color-primary); font-size:var(--text-xs)">${escapeHtml(p.url_pagina || "—")}</span></td>
        <td class="u-mono"><strong style="color:${color}">#${actual ?? "—"}</strong></td>
        <td class="u-mono" style="color:var(--color-muted-foreground)">#${p.posicion_anterior ?? "—"}</td>
        <td class="u-mono" style="color:${variacionColor}">${p.variacion > 0 ? "+" : ""}${p.variacion}</td>
        <td class="u-mono">${Number(p.volumen_busqueda || 0).toLocaleString("es-MX")}</td>
        <td class="u-mono">${p.dificultad_keyword ?? "—"}</td>
        <td><span style="font-size:var(--text-xs); color:var(--color-muted-foreground); text-transform:capitalize;">${p.dispositivo}</span></td>
        <td><button type="button" class="btn--icon" title="Eliminar" style="color:var(--text-danger);" data-delete-posicion="${p.id}"><i class="fa-solid fa-trash"></i></button></td>
      </tr>`;
    }

    form.addEventListener("submit", (e) => {
      e.preventDefault();
      const payload = Object.fromEntries(new FormData(form).entries());

      request(form.dataset.action, "POST", payload)
        .then((posicion) => {
          rowsBody.insertAdjacentHTML("beforeend", rowHtml(posicion));
          toggleEmptyState("data-posiciones-empty", "data-posiciones-table", rowsBody);
          form.reset();
          window.AgencyOS.closeModal("posicionModal");
          toast("Posición agregada.", "success");
        })
        .catch(() => toast("No se pudo guardar la posición.", "error"));
    });

    rowsBody.addEventListener("click", (e) => {
      const deleteBtn = e.target.closest("[data-delete-posicion]");
      if (!deleteBtn) return;
      if (!window.confirm("¿Eliminar esta posición?")) return;
      const id = deleteBtn.dataset.deletePosicion;
      request(`/admin/seo/posiciones/${id}`, "DELETE")
        .then(() => {
          rowsBody.querySelector(`[data-posicion-id="${id}"]`)?.remove();
          toggleEmptyState("data-posiciones-empty", "data-posiciones-table", rowsBody);
          toast("Posición eliminada.", "success");
        })
        .catch(() => toast("No se pudo eliminar la posición.", "error"));
    });
  }

  // ---------- Backlinks ----------
  function initBacklinks() {
    const form = document.getElementById("backlinkForm");
    const rowsBody = document.querySelector("[data-backlinks-rows]");
    if (!form || !rowsBody) return;

    function rowHtml(b) {
      return `<tr data-backlink-id="${b.id}">
        <td><span class="u-mono" style="color:var(--color-primary); font-size:var(--text-xs)">${escapeHtml(b.url_destino)}</span></td>
        <td>${escapeHtml(b.url_origen)}</td>
        <td class="u-mono"><strong style="color:var(--text-success)">${b.da_dr ?? "—"}</strong></td>
        <td style="text-transform:capitalize;">${escapeHtml(b.tipo)}</td>
        <td>${badge(b.estado)}</td>
        <td class="u-mono" style="font-size:var(--text-xs); color:var(--color-muted-foreground)">${b.fecha_conseguido ? String(b.fecha_conseguido).slice(0, 10) : "—"}</td>
        <td><button type="button" class="btn--icon" title="Eliminar" style="color:var(--text-danger);" data-delete-backlink="${b.id}"><i class="fa-solid fa-trash"></i></button></td>
      </tr>`;
    }

    form.addEventListener("submit", (e) => {
      e.preventDefault();
      const payload = Object.fromEntries(new FormData(form).entries());

      request(form.dataset.action, "POST", payload)
        .then((backlink) => {
          rowsBody.insertAdjacentHTML("beforeend", rowHtml(backlink));
          toggleEmptyState("data-backlinks-empty", "data-backlinks-table", rowsBody);
          form.reset();
          window.AgencyOS.closeModal("backlinkModal");
          toast("Backlink agregado.", "success");
        })
        .catch(() => toast("No se pudo guardar el backlink.", "error"));
    });

    rowsBody.addEventListener("click", (e) => {
      const deleteBtn = e.target.closest("[data-delete-backlink]");
      if (!deleteBtn) return;
      if (!window.confirm("¿Eliminar este backlink?")) return;
      const id = deleteBtn.dataset.deleteBacklink;
      request(`/admin/seo/backlinks/${id}`, "DELETE")
        .then(() => {
          rowsBody.querySelector(`[data-backlink-id="${id}"]`)?.remove();
          toggleEmptyState("data-backlinks-empty", "data-backlinks-table", rowsBody);
          toast("Backlink eliminado.", "success");
        })
        .catch(() => toast("No se pudo eliminar el backlink.", "error"));
    });
  }

  // ---------- Contenido ----------
  function initContenido() {
    const form = document.getElementById("contenidoForm");
    const rowsBody = document.querySelector("[data-contenido-rows]");
    if (!form || !rowsBody) return;

    const modalTitle = document.querySelector("[data-contenido-modal-title]");
    const submitLabel = document.querySelector("[data-contenido-submit-label]");

    function rowHtml(ct) {
      return `<tr data-contenido-id="${ct.id}" data-contenido-titulo="${escapeHtml(ct.titulo)}" data-contenido-keyword-objetivo="${escapeHtml(ct.keyword_objetivo || "")}" data-contenido-url="${escapeHtml(ct.url || "")}" data-contenido-trafico-generado="${ct.trafico_generado ?? ""}" data-contenido-estado="${ct.estado}">
        <td>${escapeHtml(ct.titulo)}</td>
        <td><span style="font-size:var(--text-xs); color:var(--color-muted-foreground);">${escapeHtml(ct.keyword_objetivo || "—")}</span></td>
        <td><span class="u-mono" style="color:var(--color-primary); font-size:var(--text-xs)">${escapeHtml(ct.url || "—")}</span></td>
        <td class="u-mono">${Number(ct.trafico_generado || 0).toLocaleString("es-MX")}</td>
        <td>${badge(ct.estado)}</td>
        <td><div style="display:flex; gap:4px;">
          <button type="button" class="btn--icon" title="Editar" data-edit-contenido="${ct.id}"><i class="fa-solid fa-pen"></i></button>
          <button type="button" class="btn--icon" title="Eliminar" style="color:var(--text-danger);" data-delete-contenido="${ct.id}"><i class="fa-solid fa-trash"></i></button>
        </div></td>
      </tr>`;
    }

    function resetForm() {
      form.reset();
      delete form.dataset.editingId;
      if (modalTitle) modalTitle.textContent = "Agregar Contenido";
      if (submitLabel) submitLabel.textContent = "Agregar";
    }

    document.querySelector('[onclick*="contenidoModal"]')?.addEventListener("click", resetForm);

    form.addEventListener("submit", (e) => {
      e.preventDefault();
      const editingId = form.dataset.editingId;
      const url = editingId ? form.dataset.updateActionTemplate.replace("__ID__", editingId) : form.dataset.storeAction;
      const method = editingId ? "PUT" : "POST";
      const payload = Object.fromEntries(new FormData(form).entries());

      request(url, method, payload)
        .then((contenido) => {
          const existing = rowsBody.querySelector(`[data-contenido-id="${contenido.id}"]`);
          if (existing) existing.outerHTML = rowHtml(contenido);
          else rowsBody.insertAdjacentHTML("beforeend", rowHtml(contenido));

          toggleEmptyState("data-contenido-empty", "data-contenido-table", rowsBody);
          resetForm();
          window.AgencyOS.closeModal("contenidoModal");
          toast(editingId ? "Contenido actualizado." : "Contenido agregado.", "success");
        })
        .catch(() => toast("No se pudo guardar el contenido.", "error"));
    });

    rowsBody.addEventListener("click", (e) => {
      const editBtn = e.target.closest("[data-edit-contenido]");
      const deleteBtn = e.target.closest("[data-delete-contenido]");

      if (editBtn) {
        const row = editBtn.closest("tr");
        form.dataset.editingId = row.dataset.contenidoId;
        form.querySelector("#ct_titulo").value = row.dataset.contenidoTitulo || "";
        form.querySelector("#ct_keyword").value = row.dataset.contenidoKeywordObjetivo || "";
        form.querySelector("#ct_url").value = row.dataset.contenidoUrl || "";
        form.querySelector("#ct_trafico").value = row.dataset.contenidoTraficoGenerado || "";
        form.querySelector("#ct_estado").value = row.dataset.contenidoEstado;
        if (modalTitle) modalTitle.textContent = "Editar Contenido";
        if (submitLabel) submitLabel.textContent = "Guardar Cambios";
        window.AgencyOS.openModal("contenidoModal");
      }

      if (deleteBtn) {
        if (!window.confirm("¿Eliminar este contenido?")) return;
        const id = deleteBtn.dataset.deleteContenido;
        request(`/admin/seo/contenido/${id}`, "DELETE")
          .then(() => {
            rowsBody.querySelector(`[data-contenido-id="${id}"]`)?.remove();
            toggleEmptyState("data-contenido-empty", "data-contenido-table", rowsBody);
            toast("Contenido eliminado.", "success");
          })
          .catch(() => toast("No se pudo eliminar el contenido.", "error"));
      }
    });
  }

  document.addEventListener("shell:ready", () => {
    initServicioCascade();
    initFasePanel();
    initPosiciones();
    initBacklinks();
    initContenido();
  });
})();
