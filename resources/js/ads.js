/**
 * Ads module — Proceso Administrativo phase panels, recurring-service model
 * like SEO: Briefing/Configuración approve once, Lanzamiento/Reporte cycle
 * indefinitely (Nuevo Ciclo / Cerrar / Pausar buttons on the Reporte panel
 * are normal form POSTs). Checklist toggles, field autosave, and the four
 * child tables (grupos, creativos, métricas, optimizaciones) all use
 * vanilla fetch() — no page reloads for those.
 */
(function () {
  "use strict";

  const { toast } = window.AgencyOS;
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

  const BADGE_MAP = {
    activo: ["Activo", "badge--success"],
    pausado: ["Pausado", "badge--warning"],
  };

  const TIPO_OPTIMIZACION = {
    puja: "Puja",
    audiencia: "Audiencia",
    creativo: "Creativo",
    presupuesto: "Presupuesto",
    keyword: "Keyword",
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

  function toggleEmptyState(emptyAttr, tableAttr, rowsContainer) {
    const empty = document.querySelector(`[${emptyAttr}]`);
    const table = document.querySelector(`[${tableAttr}]`);
    const hasRows = rowsContainer.children.length > 0;
    if (empty) empty.hidden = hasRows;
    if (table) table.hidden = !hasRows;
  }

  /** Filters the create/edit form's "Servicio" select to the chosen client's services. */
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
  }

  /**
   * Shared factory for the four child tables — each has a modal form with
   * create/edit modes (data-store-action / data-update-action-template with
   * __ID__), delegated edit/delete row buttons, and a rowHtml renderer.
   */
  function initCrudTable(cfg) {
    const form = document.getElementById(cfg.formId);
    const rowsBody = document.querySelector(`[data-${cfg.slug}-rows]`);
    if (!form || !rowsBody) return;

    const modalTitle = document.querySelector(`[data-${cfg.entity}-modal-title]`);
    const submitLabel = document.querySelector(`[data-${cfg.entity}-submit-label]`);

    function resetForm() {
      form.reset();
      delete form.dataset.editingId;
      if (modalTitle) modalTitle.textContent = cfg.addTitle;
      if (submitLabel) submitLabel.textContent = cfg.addLabel;
    }

    document.querySelector(`[onclick*="${cfg.modalId}"]`)?.addEventListener("click", resetForm);

    form.addEventListener("submit", (e) => {
      e.preventDefault();
      const editingId = form.dataset.editingId;
      const url = editingId ? form.dataset.updateActionTemplate.replace("__ID__", editingId) : form.dataset.storeAction;
      const payload = Object.fromEntries(new FormData(form).entries());

      request(url, editingId ? "PUT" : "POST", payload)
        .then((item) => {
          const existing = rowsBody.querySelector(`[data-${cfg.entity}-id="${item.id}"]`);
          if (existing) existing.outerHTML = cfg.rowHtml(item);
          else rowsBody.insertAdjacentHTML("beforeend", cfg.rowHtml(item));

          toggleEmptyState(`data-${cfg.slug}-empty`, `data-${cfg.slug}-table`, rowsBody);
          resetForm();
          window.AgencyOS.closeModal(cfg.modalId);
          toast(editingId ? cfg.updatedMsg : cfg.createdMsg, "success");
        })
        .catch(() => toast(cfg.errorMsg, "error"));
    });

    rowsBody.addEventListener("click", (e) => {
      const editBtn = e.target.closest(`[data-edit-${cfg.entity}]`);
      const deleteBtn = e.target.closest(`[data-delete-${cfg.entity}]`);

      if (editBtn) {
        const row = editBtn.closest("tr");
        form.dataset.editingId = row.dataset[cfg.entity + "Id"];
        cfg.fillForm(form, row.dataset);
        if (modalTitle) modalTitle.textContent = cfg.editTitle;
        if (submitLabel) submitLabel.textContent = "Guardar Cambios";
        window.AgencyOS.openModal(cfg.modalId);
      }

      if (deleteBtn) {
        if (!window.confirm(cfg.confirmDelete)) return;
        const id = deleteBtn.dataset["delete" + cfg.entity.charAt(0).toUpperCase() + cfg.entity.slice(1)];
        request(cfg.deleteUrl(id), "DELETE")
          .then(() => {
            rowsBody.querySelector(`[data-${cfg.entity}-id="${id}"]`)?.remove();
            toggleEmptyState(`data-${cfg.slug}-empty`, `data-${cfg.slug}-table`, rowsBody);
            toast(cfg.deletedMsg, "success");
          })
          .catch(() => toast(cfg.errorMsg, "error"));
      }
    });
  }

  function iconButtons(entity, id) {
    return `<div style="display:flex; gap:4px;">
      <button type="button" class="btn--icon" title="Editar" data-edit-${entity}="${id}"><i class="fa-solid fa-pen"></i></button>
      <button type="button" class="btn--icon" title="Eliminar" style="color:var(--text-danger);" data-delete-${entity}="${id}"><i class="fa-solid fa-trash"></i></button>
    </div>`;
  }

  // ---------- Grupos de anuncios ----------
  function initGrupos() {
    initCrudTable({
      formId: "grupoForm",
      slug: "grupos",
      entity: "grupo",
      modalId: "grupoModal",
      addTitle: "Agregar Grupo de Anuncios",
      editTitle: "Editar Grupo de Anuncios",
      addLabel: "Agregar",
      confirmDelete: "¿Eliminar este grupo de anuncios?",
      createdMsg: "Grupo agregado.",
      updatedMsg: "Grupo actualizado.",
      deletedMsg: "Grupo eliminado.",
      errorMsg: "No se pudo guardar el grupo.",
      deleteUrl: (id) => `/admin/ads/grupos/${id}`,
      rowHtml: (g) => {
        const keywords = Array.isArray(g.keywords) ? g.keywords : [];
        const chips = keywords.length
          ? keywords.map((kw) => `<span style="padding:2px 6px; border-radius:4px; background:var(--color-secondary); border:1px solid var(--color-border); font-size:var(--text-xs);">${escapeHtml(kw)}</span>`).join("")
          : '<span style="font-size:var(--text-xs); color:var(--color-muted-foreground);">—</span>';
        return `<tr data-grupo-id="${g.id}" data-grupo-nombre="${escapeHtml(g.nombre)}" data-grupo-audiencia="${escapeHtml(g.audiencia || "")}" data-grupo-presupuesto="${g.presupuesto ?? ""}" data-grupo-keywords="${escapeHtml(keywords.join(", "))}" data-grupo-estado="${g.estado}">
          <td>${escapeHtml(g.nombre)}</td>
          <td><span style="font-size:var(--text-xs); color:var(--color-muted-foreground);">${escapeHtml(g.audiencia || "—")}</span></td>
          <td class="u-mono">$${Number(g.presupuesto || 0).toLocaleString("es-MX")}</td>
          <td><div style="display:flex; flex-wrap:wrap; gap:4px;">${chips}</div></td>
          <td>${badge(g.estado)}</td>
          <td>${iconButtons("grupo", g.id)}</td>
        </tr>`;
      },
      fillForm: (form, d) => {
        form.querySelector("#g_nombre").value = d.grupoNombre || "";
        form.querySelector("#g_audiencia").value = d.grupoAudiencia || "";
        form.querySelector("#g_presupuesto").value = d.grupoPresupuesto || "";
        form.querySelector("#g_keywords").value = d.grupoKeywords || "";
        form.querySelector("#g_estado").value = d.grupoEstado;
      },
    });
  }

  // ---------- Creativos ----------
  function initCreativos() {
    initCrudTable({
      formId: "creativoForm",
      slug: "creativos",
      entity: "creativo",
      modalId: "creativoModal",
      addTitle: "Agregar Creativo",
      editTitle: "Editar Creativo",
      addLabel: "Agregar",
      confirmDelete: "¿Eliminar este creativo?",
      createdMsg: "Creativo agregado.",
      updatedMsg: "Creativo actualizado.",
      deletedMsg: "Creativo eliminado.",
      errorMsg: "No se pudo guardar el creativo.",
      deleteUrl: (id) => `/admin/ads/creativos/${id}`,
      rowHtml: (cr) => {
        const copyCorto = (cr.copy || "").length > 60 ? cr.copy.slice(0, 57) + "..." : cr.copy || "—";
        return `<tr data-creativo-id="${cr.id}" data-creativo-titulo="${escapeHtml(cr.titulo)}" data-creativo-copy="${escapeHtml(cr.copy || "")}" data-creativo-tipo="${cr.tipo}" data-creativo-url-creativo="${escapeHtml(cr.url_creativo || "")}" data-creativo-ab-testing="${cr.ab_testing ? "1" : ""}" data-creativo-estado="${cr.estado}">
          <td>${escapeHtml(cr.titulo)}</td>
          <td><span style="font-size:var(--text-xs); color:var(--color-muted-foreground);">${escapeHtml(copyCorto)}</span></td>
          <td style="text-transform:capitalize;">${cr.tipo}</td>
          <td><span class="u-mono" style="color:var(--color-primary); font-size:var(--text-xs)">${escapeHtml(cr.url_creativo || "—")}</span></td>
          <td>${cr.ab_testing ? "Sí" : "No"}</td>
          <td>${badge(cr.estado)}</td>
          <td>${iconButtons("creativo", cr.id)}</td>
        </tr>`;
      },
      fillForm: (form, d) => {
        form.querySelector("#cr_titulo").value = d.creativoTitulo || "";
        form.querySelector("#cr_copy").value = d.creativoCopy || "";
        form.querySelector("#cr_tipo").value = d.creativoTipo;
        form.querySelector("#cr_url").value = d.creativoUrlCreativo || "";
        form.querySelector("#cr_ab").checked = d.creativoAbTesting === "1";
        form.querySelector("#cr_estado").value = d.creativoEstado;
      },
    });
  }

  // ---------- Métricas mensuales ----------
  function initMetricas() {
    initCrudTable({
      formId: "metricaForm",
      slug: "metricas",
      entity: "metrica",
      modalId: "metricaModal",
      addTitle: "Agregar Métricas del Mes",
      editTitle: "Editar Métricas del Mes",
      addLabel: "Agregar",
      confirmDelete: "¿Eliminar las métricas de este mes?",
      createdMsg: "Métricas agregadas.",
      updatedMsg: "Métricas actualizadas.",
      deletedMsg: "Métricas eliminadas.",
      errorMsg: "No se pudieron guardar las métricas (revisa que el mes/año no esté duplicado).",
      deleteUrl: (id) => `/admin/ads/metricas/${id}`,
      rowHtml: (m) => {
        const roas = m.roas != null ? Number(m.roas) : null;
        const roasColor = roas == null ? "inherit" : roas >= 5 ? "var(--text-success)" : roas >= 3 ? "var(--text-warning)" : "inherit";
        return `<tr data-metrica-id="${m.id}" data-metrica-mes="${m.mes}" data-metrica-anio="${m.anio}" data-metrica-inversion-real="${m.inversion_real ?? ""}" data-metrica-impresiones="${m.impresiones ?? ""}" data-metrica-clics="${m.clics ?? ""}" data-metrica-ctr="${m.ctr ?? ""}" data-metrica-cpc="${m.cpc ?? ""}" data-metrica-conversiones="${m.conversiones ?? ""}" data-metrica-cpl="${m.cpl ?? ""}" data-metrica-cpa="${m.cpa ?? ""}" data-metrica-roas="${m.roas ?? ""}" data-metrica-valor-conversion="${m.valor_conversion ?? ""}">
          <td class="u-mono">${String(m.mes).padStart(2, "0")}/${m.anio}</td>
          <td class="u-mono">$${Number(m.inversion_real || 0).toLocaleString("es-MX")}</td>
          <td class="u-mono">${Number(m.impresiones || 0).toLocaleString("es-MX")}</td>
          <td class="u-mono">${Number(m.clics || 0).toLocaleString("es-MX")}</td>
          <td class="u-mono">${m.ctr != null ? m.ctr + "%" : "—"}</td>
          <td class="u-mono">${m.cpc != null ? "$" + m.cpc : "—"}</td>
          <td class="u-mono">${m.conversiones ?? 0}</td>
          <td class="u-mono">${m.cpl != null ? "$" + m.cpl : "—"}</td>
          <td class="u-mono">${m.cpa != null ? "$" + m.cpa : "—"}</td>
          <td class="u-mono"><strong style="color:${roasColor}">${m.roas != null ? m.roas + "x" : "—"}</strong></td>
          <td>${iconButtons("metrica", m.id)}</td>
        </tr>`;
      },
      fillForm: (form, d) => {
        form.querySelector("#m_mes").value = d.metricaMes;
        form.querySelector("#m_anio").value = d.metricaAnio;
        form.querySelector("#m_inversion").value = d.metricaInversionReal || "";
        form.querySelector("#m_impresiones").value = d.metricaImpresiones || "";
        form.querySelector("#m_clics").value = d.metricaClics || "";
        form.querySelector("#m_ctr").value = d.metricaCtr || "";
        form.querySelector("#m_cpc").value = d.metricaCpc || "";
        form.querySelector("#m_conversiones").value = d.metricaConversiones || "";
        form.querySelector("#m_cpl").value = d.metricaCpl || "";
        form.querySelector("#m_cpa").value = d.metricaCpa || "";
        form.querySelector("#m_roas").value = d.metricaRoas || "";
        form.querySelector("#m_valor").value = d.metricaValorConversion || "";
      },
    });
  }

  // ---------- Optimizaciones ----------
  function initOptimizaciones() {
    initCrudTable({
      formId: "optimizacionForm",
      slug: "optimizaciones",
      entity: "optimizacion",
      modalId: "optimizacionModal",
      addTitle: "Registrar Optimización",
      editTitle: "Editar Optimización",
      addLabel: "Registrar",
      confirmDelete: "¿Eliminar esta optimización?",
      createdMsg: "Optimización registrada.",
      updatedMsg: "Optimización actualizada.",
      deletedMsg: "Optimización eliminada.",
      errorMsg: "No se pudo guardar la optimización.",
      deleteUrl: (id) => `/admin/ads/optimizaciones/${id}`,
      rowHtml: (o) => {
        const fecha = o.fecha ? String(o.fecha).slice(0, 10) : "—";
        return `<tr data-optimizacion-id="${o.id}" data-optimizacion-fecha="${fecha}" data-optimizacion-tipo="${o.tipo}" data-optimizacion-descripcion="${escapeHtml(o.descripcion)}" data-optimizacion-resultado="${escapeHtml(o.resultado || "")}">
          <td class="u-mono" style="font-size:var(--text-xs); color:var(--color-muted-foreground);">${fecha}</td>
          <td>${TIPO_OPTIMIZACION[o.tipo] || o.tipo}</td>
          <td><span style="font-size:var(--text-sm);">${escapeHtml(o.descripcion)}</span></td>
          <td><span style="font-size:var(--text-xs); color:var(--color-muted-foreground);">${escapeHtml(o.resultado || "—")}</span></td>
          <td>${iconButtons("optimizacion", o.id)}</td>
        </tr>`;
      },
      fillForm: (form, d) => {
        form.querySelector("#o_fecha").value = d.optimizacionFecha || "";
        form.querySelector("#o_tipo").value = d.optimizacionTipo;
        form.querySelector("#o_descripcion").value = d.optimizacionDescripcion || "";
        form.querySelector("#o_resultado").value = d.optimizacionResultado || "";
      },
    });
  }

  document.addEventListener("shell:ready", () => {
    initServicioCascade();
    initFasePanel();
    initGrupos();
    initCreativos();
    initMetricas();
    initOptimizaciones();
  });
})();
