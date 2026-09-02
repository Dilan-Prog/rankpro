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
    en_progreso: ["En Progreso", "badge--info"],
    completada: ["Completada", "badge--success"],
    pausada: ["Pausada", "badge--warning"],
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

      // Técnico checklist checkboxes live in the separate "Técnico" tab, outside
      // this panel's DOM subtree, but submit with this form via form="faseForm".
      const tecnicoChecklist = {};
      document.querySelectorAll("[data-tecnico-checklist-item]").forEach((input) => {
        tecnicoChecklist[input.dataset.tecnicoChecklistItem] = input.checked;
      });
      payload.tecnico_checklist = tecnicoChecklist;

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

    // Técnico checklist lives outside the panel (separate tab) but shares this
    // form via form="faseForm" — autosave on change same as the phase checklist,
    // without touching recomputeLocalCompleteness (that's phase-approval only).
    document.querySelectorAll("[data-tecnico-checklist-item]").forEach((input) => {
      input.addEventListener("change", () => save(true));
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

  // ---------- On-Page acciones ----------
  function initOnPage() {
    const form = document.getElementById("onpageForm");
    const listContainer = document.querySelector("[data-onpage-list]");
    if (!form || !listContainer) return;

    const modalTitle = document.querySelector("[data-onpage-modal-title]");
    const submitLabel = document.querySelector("[data-onpage-submit-label]");

    function itemHtml(accion) {
      const fecha = accion.fecha ? String(accion.fecha).slice(0, 10) : null;
      return `<div class="card card--padded" data-onpage-id="${accion.id}" data-onpage-url-pagina="${escapeHtml(accion.url_pagina)}" data-onpage-accion="${escapeHtml(accion.accion)}" data-onpage-fecha="${fecha || ""}" data-onpage-responsable-id="${accion.responsable_id ?? ""}" data-onpage-estado="${accion.estado}">
        <div style="display:flex; align-items:flex-start; justify-content:space-between; gap: var(--space-3);">
          <div style="min-width:0;">
            <span class="u-mono" style="color:var(--color-primary); font-size:var(--text-xs); word-break:break-all;">${escapeHtml(accion.url_pagina)}</span>
            <p style="margin-top: var(--space-2); font-size:var(--text-sm);">${escapeHtml(accion.accion)}</p>
            <div style="margin-top: var(--space-2); font-size:var(--text-xs); color:var(--color-muted-foreground); display:flex; gap: var(--space-3); flex-wrap:wrap;">
              <span><i class="fa-solid fa-calendar"></i> ${fecha || "—"}</span>
              <span><i class="fa-solid fa-user"></i> ${escapeHtml(accion.responsable?.name || "Sin asignar")}</span>
            </div>
          </div>
          <div style="display:flex; align-items:flex-start; gap: var(--space-3); flex-shrink:0;">
            ${badge(accion.estado)}
            <div style="display:flex; gap:4px;">
              <button type="button" class="btn--icon" title="Editar" data-edit-onpage="${accion.id}"><i class="fa-solid fa-pen"></i></button>
              <button type="button" class="btn--icon" title="Eliminar" style="color:var(--text-danger);" data-delete-onpage="${accion.id}"><i class="fa-solid fa-trash"></i></button>
            </div>
          </div>
        </div>
      </div>`;
    }

    function resetForm() {
      form.reset();
      delete form.dataset.editingId;
      if (modalTitle) modalTitle.textContent = "Agregar Acción On-Page";
      if (submitLabel) submitLabel.textContent = "Agregar";
    }

    document.querySelector('[onclick*="onpageModal"]')?.addEventListener("click", resetForm);

    form.addEventListener("submit", (e) => {
      e.preventDefault();
      const editingId = form.dataset.editingId;
      const url = editingId ? form.dataset.updateActionTemplate.replace("__ID__", editingId) : form.dataset.storeAction;
      const method = editingId ? "PUT" : "POST";
      const payload = Object.fromEntries(new FormData(form).entries());

      request(url, method, payload)
        .then((accion) => {
          const existing = listContainer.querySelector(`[data-onpage-id="${accion.id}"]`);
          if (existing) existing.outerHTML = itemHtml(accion);
          else listContainer.insertAdjacentHTML("afterbegin", itemHtml(accion));

          toggleEmptyState("data-onpage-empty", "data-onpage-list", listContainer);
          resetForm();
          window.AgencyOS.closeModal("onpageModal");
          toast(editingId ? "Acción actualizada." : "Acción agregada.", "success");
        })
        .catch(() => toast("No se pudo guardar la acción On-Page.", "error"));
    });

    listContainer.addEventListener("click", (e) => {
      const editBtn = e.target.closest("[data-edit-onpage]");
      const deleteBtn = e.target.closest("[data-delete-onpage]");

      if (editBtn) {
        const item = editBtn.closest("[data-onpage-id]");
        form.dataset.editingId = item.dataset.onpageId;
        form.querySelector("#op_url").value = item.dataset.onpageUrlPagina || "";
        form.querySelector("#op_accion").value = item.dataset.onpageAccion || "";
        form.querySelector("#op_fecha").value = item.dataset.onpageFecha || "";
        form.querySelector("#op_responsable").value = item.dataset.onpageResponsableId || "";
        form.querySelector("#op_estado").value = item.dataset.onpageEstado;
        if (modalTitle) modalTitle.textContent = "Editar Acción On-Page";
        if (submitLabel) submitLabel.textContent = "Guardar Cambios";
        window.AgencyOS.openModal("onpageModal");
      }

      if (deleteBtn) {
        if (!window.confirm("¿Eliminar esta acción On-Page?")) return;
        const id = deleteBtn.dataset.deleteOnpage;
        request(`/admin/seo/onpage/${id}`, "DELETE")
          .then(() => {
            listContainer.querySelector(`[data-onpage-id="${id}"]`)?.remove();
            toggleEmptyState("data-onpage-empty", "data-onpage-list", listContainer);
            toast("Acción eliminada.", "success");
          })
          .catch(() => toast("No se pudo eliminar la acción On-Page.", "error"));
      }
    });
  }

  // ---------- Tabs de la campaña (Resumen / Posiciones / Link Building / On-Page / Técnico / Contenido / Reporte) ----------
  function initSeoTabs() {
    const tabs = document.querySelectorAll("#seoTabs .tabs__item");
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

  // ---------- Métricas mensuales (tendencia) ----------
  function initMetricasChart() {
    const canvas = document.getElementById("metricasChart");
    if (!canvas || typeof Chart === "undefined") return;

    const data = JSON.parse(canvas.dataset.metricas || "[]");
    const colors = window.AgencyOS.chartColors();

    new Chart(canvas, {
      type: "line",
      data: {
        labels: data.map((d) => d.label),
        datasets: [
          { label: "Tráfico orgánico", data: data.map((d) => d.trafico), borderColor: "#0F9D6E", backgroundColor: "rgba(15, 157, 110, 0.12)", fill: true, tension: 0.35, borderWidth: 2, pointRadius: 2, yAxisID: "y" },
          { label: "Keywords Top 3", data: data.map((d) => d.top3), borderColor: "#F59E0B", borderWidth: 2, tension: 0.35, pointRadius: 2, yAxisID: "y1" },
          { label: "Keywords Top 10", data: data.map((d) => d.top10), borderColor: "#3B82F6", borderWidth: 2, tension: 0.35, pointRadius: 2, yAxisID: "y1" },
          { label: "Backlinks", data: data.map((d) => d.backlinks), borderColor: "#EF4444", borderWidth: 2, tension: 0.35, pointRadius: 2, yAxisID: "y1" },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: "index", intersect: false },
        plugins: {
          legend: { display: true, position: "bottom", labels: { color: colors.tick, boxWidth: 10, font: { size: 11 } } },
          tooltip: { backgroundColor: colors.tooltipBg, borderColor: colors.tooltipBorder, borderWidth: 1, titleColor: colors.tooltipText, bodyColor: colors.tooltipText, padding: 10 },
        },
        scales: {
          x: { grid: { display: false }, ticks: { color: colors.tick, font: { size: 11 } } },
          y: { position: "left", grid: { color: colors.grid }, ticks: { color: colors.tick, font: { size: 11 } }, title: { display: true, text: "Tráfico", color: colors.tick } },
          y1: { position: "right", grid: { display: false }, ticks: { color: colors.tick, font: { size: 11 } } },
        },
      },
    });
  }

  function initMetricasMensuales() {
    const form = document.getElementById("metricaForm");
    const rowsBody = document.querySelector("[data-metricas-rows]");
    if (!form || !rowsBody) return;

    const modalTitle = document.querySelector("[data-metrica-modal-title]");
    const submitLabel = document.querySelector("[data-metrica-submit-label]");

    function rowHtml(m) {
      const mes = String(m.mes).padStart(2, "0");
      const pendColor = m.errores_pendientes > 0 ? "var(--text-danger)" : "var(--color-muted-foreground)";
      return `<tr data-metrica-id="${m.id}" data-metrica-mes="${m.mes}" data-metrica-anio="${m.anio}" data-metrica-trafico-organico="${m.trafico_organico}" data-metrica-keywords-top3="${m.keywords_top3}" data-metrica-keywords-top10="${m.keywords_top10}" data-metrica-keywords-top100="${m.keywords_top100}" data-metrica-backlinks-total="${m.backlinks_total}" data-metrica-errores-resueltos="${m.errores_resueltos}" data-metrica-errores-pendientes="${m.errores_pendientes}" data-metrica-notas="${escapeHtml(m.notas || "")}">
        <td class="u-mono">${mes}/${m.anio}</td>
        <td class="u-mono">#${m.ciclo}</td>
        <td class="u-mono">${Number(m.trafico_organico || 0).toLocaleString("es-MX")}</td>
        <td class="u-mono">${m.keywords_top3} / ${m.keywords_top10} / ${m.keywords_top100}</td>
        <td class="u-mono">${Number(m.backlinks_total || 0).toLocaleString("es-MX")}</td>
        <td class="u-mono"><span style="color:var(--text-success)">${m.errores_resueltos}</span> / <span style="color:${pendColor}">${m.errores_pendientes}</span></td>
        <td><div style="display:flex; gap:4px;">
          <button type="button" class="btn--icon" title="Editar" data-edit-metrica="${m.id}"><i class="fa-solid fa-pen"></i></button>
          <button type="button" class="btn--icon" title="Eliminar" style="color:var(--text-danger);" data-delete-metrica="${m.id}"><i class="fa-solid fa-trash"></i></button>
        </div></td>
      </tr>`;
    }

    function resetForm() {
      form.reset();
      delete form.dataset.editingId;
      if (modalTitle) modalTitle.textContent = "Agregar Mes";
      if (submitLabel) submitLabel.textContent = "Agregar";
    }

    document.querySelector('[onclick*="metricaModal"]')?.addEventListener("click", resetForm);

    form.addEventListener("submit", (e) => {
      e.preventDefault();
      const editingId = form.dataset.editingId;
      const url = editingId ? form.dataset.updateActionTemplate.replace("__ID__", editingId) : form.dataset.storeAction;
      const method = editingId ? "PUT" : "POST";
      const payload = Object.fromEntries(new FormData(form).entries());

      request(url, method, payload)
        .then(() => {
          toast(editingId ? "Métrica actualizada. Recargando gráfica…" : "Mes agregado. Recargando gráfica…", "success");
          window.location.reload();
        })
        .catch(() => toast("No se pudo guardar la métrica — revisa que no exista ya un registro para ese mes.", "error"));
    });

    rowsBody.addEventListener("click", (e) => {
      const editBtn = e.target.closest("[data-edit-metrica]");
      const deleteBtn = e.target.closest("[data-delete-metrica]");

      if (editBtn) {
        const row = editBtn.closest("tr");
        form.dataset.editingId = row.dataset.metricaId;
        form.querySelector("#mm_mes").value = row.dataset.metricaMes;
        form.querySelector("#mm_anio").value = row.dataset.metricaAnio;
        form.querySelector("#mm_trafico").value = row.dataset.metricaTraficoOrganico;
        form.querySelector("#mm_top3").value = row.dataset.metricaKeywordsTop3;
        form.querySelector("#mm_top10").value = row.dataset.metricaKeywordsTop10;
        form.querySelector("#mm_top100").value = row.dataset.metricaKeywordsTop100;
        form.querySelector("#mm_backlinks").value = row.dataset.metricaBacklinksTotal;
        form.querySelector("#mm_resueltos").value = row.dataset.metricaErroresResueltos;
        form.querySelector("#mm_pendientes").value = row.dataset.metricaErroresPendientes;
        form.querySelector("#mm_notas").value = row.dataset.metricaNotas || "";
        if (modalTitle) modalTitle.textContent = "Editar Mes";
        if (submitLabel) submitLabel.textContent = "Guardar Cambios";
        window.AgencyOS.openModal("metricaModal");
      }

      if (deleteBtn) {
        if (!window.confirm("¿Eliminar este mes? Se perderá de la gráfica de tendencia.")) return;
        const id = deleteBtn.dataset.deleteMetrica;
        request(`/admin/seo/metricas-mensuales/${id}`, "DELETE")
          .then(() => window.location.reload())
          .catch(() => toast("No se pudo eliminar la métrica.", "error"));
      }
    });
  }

  // ==========================================================================
  // SEO index — client-picker cards (search filter, AJAX create/edit modal,
  // AJAX delete) and KPI tiles. Everything below is additive: each init*
  // guards on its own required element, so it's inert on show.blade.php
  // where this same file is also loaded for initFasePanel()/initPosiciones()/
  // etc above. Reuses the existing initServicioCascade() (called in
  // shell:ready below) for the modal's cliente_id -> servicio_id cascade —
  // the modal's selects share the same #cliente_id/#servicio_id ids that
  // function already looks for.
  // ==========================================================================

  // Mirrors components/badge.blade.php's own label/class map for these keys
  // exactly, so JS-built cards look identical to the <x-badge> the server
  // renders for page-load cards.
  const ESTADO_CAMPANA_BADGE = {
    activa: ["Activa", "badge--success"],
    pausada: ["Pausada", "badge--warning"],
    finalizada: ["Finalizada", "badge--neutral"],
  };

  // Mirrors App\Support\Labels::faseSeo() exactly.
  const SEO_FASE_LABELS = {
    auditoria: "Auditoría",
    estrategia: "Estrategia",
    ejecucion: "Ejecución",
    reporte: "Reporte y Análisis",
    cerrada: "Cerrada",
  };

  function seoBadgeHtml(map, key) {
    const [label, cls] = map[key] || [key, "badge--neutral"];
    return `<span class="badge ${cls}">${label}</span>`;
  }

  /** Up to 2 uppercase initials from a display name — mirrors App\Support\Labels::initials() exactly. */
  function seoClienteInitials(name) {
    const source = (name || "").trim() || "Usuario";
    return source
      .split(" ")
      .filter(Boolean)
      .map((part) => part.charAt(0).toUpperCase())
      .slice(0, 2)
      .join("");
  }

  /**
   * Like request(), but rejects with err.message "validation_failed"
   * (err.data.errors) on 422 and carries err.status/err.data on any
   * failure — needed for the campaign form's field-level error rendering.
   * Kept separate from the shared request() above so that function's
   * behavior (and every existing init-panel caller relying on its plain
   * "request_failed" throw) is untouched. Mirrors ads.js's adsRequest().
   */
  function seoRequest(url, method, payload) {
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

  function renderSeoFieldErrors(form, errors) {
    form.querySelectorAll("[data-error-for]").forEach((span) => {
      span.textContent = "";
    });
    Object.keys(errors || {}).forEach((field) => {
      const span = form.querySelector(`[data-error-for="${field}"]`);
      if (span) span.textContent = errors[field][0];
    });
  }

  // ---------- Card rendering — matches index.blade.php's <div data-seo-cliente-card> markup exactly, both states ----------

  function buildSeoClienteCardElement(row) {
    const card = document.createElement("div");
    card.className = "seo-cliente-card";
    card.setAttribute("data-seo-cliente-card", "");
    card.dataset.clienteId = String(row.cliente_id);
    card.dataset.search = `${row.cliente || ""} ${row.contacto || ""}`.toLowerCase();
    card.dataset.cliente = JSON.stringify(row);

    const initials = escapeHtml(seoClienteInitials(row.cliente));
    const contactoHtml = row.contacto ? `<span class="seo-cliente-card__contacto">${escapeHtml(row.contacto)}</span>` : "";

    if (row.campana_id) {
      const faseLabel = SEO_FASE_LABELS[row.fase_actual] || row.fase_actual || "—";
      const scoreLabel = row.seo_score != null ? row.seo_score : "—";
      const traficoLabel = row.trafico_actual != null ? Number(row.trafico_actual).toLocaleString("es-MX") : "—";
      card.innerHTML = `
        <a href="${row.show_url}" class="seo-cliente-card__link">
          <div class="seo-cliente-card__header">
            <span class="seo-cliente-card__avatar">${initials}</span>
            <span class="seo-cliente-card__id">
              <span class="seo-cliente-card__name">${escapeHtml(row.cliente)}</span>
              ${contactoHtml}
            </span>
            ${seoBadgeHtml(ESTADO_CAMPANA_BADGE, row.estado)}
          </div>
          <div class="seo-cliente-card__body">
            <div class="seo-cliente-card__stat">
              <span class="seo-cliente-card__stat-label">Fase</span>
              <span class="seo-cliente-card__stat-value">${escapeHtml(faseLabel)}</span>
            </div>
            <div class="seo-cliente-card__stat">
              <span class="seo-cliente-card__stat-label">SEO Score</span>
              <span class="seo-cliente-card__stat-value u-mono">${scoreLabel}/100</span>
            </div>
            <div class="seo-cliente-card__stat">
              <span class="seo-cliente-card__stat-label">Tráfico orgánico</span>
              <span class="seo-cliente-card__stat-value u-mono">${traficoLabel}</span>
            </div>
            <div class="seo-cliente-card__stat">
              <span class="seo-cliente-card__stat-label">MRR</span>
              <span class="seo-cliente-card__stat-value u-mono">$${Math.round(Number(row.mrr) || 0).toLocaleString("es-MX")}</span>
            </div>
          </div>
        </a>
        <div class="seo-cliente-card__footer">
          <button type="button" class="btn--icon" title="Editar" data-edit-seo-cliente><i class="fa-solid fa-pen"></i></button>
          <button type="button" class="btn--icon" title="Eliminar" style="color:var(--text-danger);" data-delete-seo-cliente><i class="fa-solid fa-trash"></i></button>
        </div>`;
    } else {
      card.innerHTML = `
        <div class="seo-cliente-card__header">
          <span class="seo-cliente-card__avatar">${initials}</span>
          <span class="seo-cliente-card__id">
            <span class="seo-cliente-card__name">${escapeHtml(row.cliente)}</span>
            ${contactoHtml}
          </span>
          <span class="seo-cliente-card__sin-campana">Sin campaña</span>
        </div>
        <div class="seo-cliente-card__body seo-cliente-card__body--empty">
          <p class="seo-cliente-card__empty-text">Este cliente aún no tiene una campaña SEO.</p>
          <button type="button" class="btn btn--secondary btn--sm" data-open-seo-modal data-preselect-cliente="${row.cliente_id}">
            <i class="fa-solid fa-plus"></i> Crear campaña
          </button>
        </div>`;
    }

    return card;
  }

  /** Replaces one client card in place (create never reaches here — it navigates away via show_url) and re-applies the current search filter. */
  function upsertSeoClienteCard(row) {
    const grid = document.querySelector("[data-seo-cliente-grid]");
    if (!grid) return;

    const existing = grid.querySelector(`[data-seo-cliente-card][data-cliente-id="${row.cliente_id}"]`);
    const newCard = buildSeoClienteCardElement(row);
    if (existing) existing.replaceWith(newCard);
    else grid.appendChild(newCard);

    applySeoClienteFilter();
  }

  function findSeoClienteRow(clienteId) {
    const card = document.querySelector(`[data-seo-cliente-card][data-cliente-id="${clienteId}"]`);
    return card ? JSON.parse(card.dataset.cliente) : null;
  }

  // ---------- Search filter ----------

  function applySeoClienteFilter() {
    const query = (document.getElementById("seoClienteSearch")?.value || "").trim().toLowerCase();
    const cards = Array.from(document.querySelectorAll("[data-seo-cliente-card]"));
    let visible = 0;

    cards.forEach((card) => {
      const matches = !query || (card.dataset.search || "").includes(query);
      card.classList.toggle("hidden", !matches);
      if (matches) visible++;
    });

    const emptyEl = document.querySelector("[data-seo-cliente-empty]");
    if (emptyEl) emptyEl.hidden = visible > 0;
  }

  function initSeoClienteFilters() {
    const search = document.getElementById("seoClienteSearch");
    if (!search) return;
    search.addEventListener("input", window.AgencyOS.debounce(applySeoClienteFilter, 200));
    applySeoClienteFilter();
  }

  // ---------- Create/edit modal ----------

  /**
   * clienteRow: a specific client pre-selected for creation (from an
   * empty-state card's "Crear campaña" button), or null for "any client"
   * (header button). campanaRow: the full client-picker row for a client
   * that already has a campaign, to edit it — check campanaRow.campana_id
   * to tell create/edit mode apart, since both params share the same
   * cliente-row shape.
   */
  function openSeoCampanaFormModal(clienteRow, campanaRow) {
    const form = document.getElementById("seoCampanaForm");
    if (!form) return;

    form.reset();
    form.querySelectorAll("[data-error-for]").forEach((span) => {
      span.textContent = "";
    });

    const isEditing = !!(campanaRow && campanaRow.campana_id);
    form.dataset.editingId = isEditing ? campanaRow.campana_id : "";

    const editOnlyWrap = form.querySelector("[data-edit-only]");
    if (editOnlyWrap) editOnlyWrap.hidden = !isEditing;

    document.getElementById("seoCampanaFormModalTitle").textContent = isEditing ? "Editar Campaña SEO" : "Nueva Campaña SEO";
    document.getElementById("seoCampanaFormSubmit").innerHTML = isEditing
      ? '<i class="fa-solid fa-check"></i> Guardar Cambios'
      : '<i class="fa-solid fa-check"></i> Crear Campaña';

    const clienteSelect = form.querySelector("#cliente_id");

    if (isEditing) {
      clienteSelect.value = String(campanaRow.cliente_id);
      clienteSelect.dispatchEvent(new Event("change")); // re-runs initServicioCascade()'s filtering before we set servicio_id below
      form.querySelector("#servicio_id").value = String(campanaRow.servicio_id);
      form.querySelector("#sf_nombre").value = campanaRow.campana_nombre || "";
      form.querySelector("#sf_url_sitio").value = campanaRow.url_sitio || "";
      form.querySelector("#sf_fecha_inicio").value = campanaRow.fecha_inicio || "";
      form.querySelector("#sf_estado").value = campanaRow.estado || "activa";
      form.querySelector("#sf_notas").value = campanaRow.notas || "";
    } else if (clienteRow) {
      clienteSelect.value = String(clienteRow.cliente_id);
      clienteSelect.dispatchEvent(new Event("change"));
    }

    window.AgencyOS.openModal("seoCampanaFormModal");
  }

  function initSeoCampanaForm() {
    const form = document.getElementById("seoCampanaForm");
    if (!form) return;

    document.addEventListener("click", (e) => {
      const openBtn = e.target.closest("[data-open-seo-modal]");
      if (openBtn) {
        const preselectId = openBtn.dataset.preselectCliente;
        openSeoCampanaFormModal(preselectId ? findSeoClienteRow(preselectId) : null, null);
        return;
      }

      const editBtn = e.target.closest("[data-edit-seo-cliente]");
      if (editBtn) {
        const card = editBtn.closest("[data-seo-cliente-card]");
        if (card) openSeoCampanaFormModal(null, JSON.parse(card.dataset.cliente));
      }
    });

    form.addEventListener("submit", (e) => {
      e.preventDefault();
      const editingId = form.dataset.editingId;
      const url = editingId ? form.dataset.updateActionTemplate.replace("__ID__", editingId) : form.dataset.storeAction;
      const payload = Object.fromEntries(new FormData(form).entries());

      seoRequest(url, editingId ? "PUT" : "POST", payload)
        .then((data) => {
          if (editingId) {
            upsertSeoClienteCard(data);
            window.AgencyOS.closeModal("seoCampanaFormModal");
            toast("Campaña actualizada.", "success");
          } else {
            // Brand-new campaign — its real work happens on the detail page's phase panel, so go straight there.
            window.location.href = data.show_url;
          }
        })
        .catch((err) => {
          if (err.message === "validation_failed") {
            renderSeoFieldErrors(form, err.data.errors || {});
            toast("Revisa los campos marcados.", "error");
          } else {
            toast("No se pudo guardar la campaña.", "error");
          }
        });
    });
  }

  // ---------- Delete — native window.confirm(), matching every other delete flow already in this file ----------

  function initSeoCampanaDelete() {
    document.addEventListener("click", (e) => {
      const btn = e.target.closest("[data-delete-seo-cliente]");
      if (!btn) return;
      const card = btn.closest("[data-seo-cliente-card]");
      if (!card) return;
      const row = JSON.parse(card.dataset.cliente);
      if (!row.campana_id) return;

      if (!window.confirm(`¿Eliminar la campaña SEO de ${row.cliente}? Se eliminarán también sus posiciones, backlinks, contenido, acciones on-page y reportes registrados. Esta acción no se puede deshacer.`)) return;

      seoRequest(`/admin/seo/${row.campana_id}`, "DELETE")
        .then((data) => {
          upsertSeoClienteCard(data);
          toast("Campaña eliminada.", "success");
        })
        .catch(() => toast("No se pudo eliminar la campaña.", "error"));
    });
  }

  // ---------- ?editar=ID on load — "Editar" from show.blade.php's line 19, which now points back here since the dedicated edit page no longer exists ----------

  function initSeoIndexEditFromQuery() {
    const editId = new URLSearchParams(location.search).get("editar");
    if (!editId) return;

    const card = Array.from(document.querySelectorAll("[data-seo-cliente-card]")).find((c) => {
      const row = JSON.parse(c.dataset.cliente);
      return String(row.campana_id) === String(editId);
    });
    if (!card) return;

    openSeoCampanaFormModal(null, JSON.parse(card.dataset.cliente));
  }

  document.addEventListener("shell:ready", () => {
    initServicioCascade();
    initSeoTabs();
    initFasePanel();
    initPosiciones();
    initBacklinks();
    initContenido();
    initOnPage();
    initMetricasChart();
    initMetricasMensuales();
    initSeoClienteFilters();
    initSeoCampanaForm();
    initSeoCampanaDelete();
    initSeoIndexEditFromQuery();
  });
})();
