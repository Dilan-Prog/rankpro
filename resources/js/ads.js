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

  // ---------- Grupos de anuncios (con hoja de cálculo de keywords + columnas personalizadas) ----------
  const COMPETENCIA_LABEL = { baja: "Baja", media: "Media", alta: "Alta" };
  const COMPETENCIA_CLASS = { baja: "badge--success", media: "badge--warning", alta: "badge--danger" };

  function grupoRowHtml(g) {
    const count = (g.keywords || []).length;
    const keywordsCell = count
      ? `<span class="badge badge--info">${count} ${count === 1 ? "palabra clave" : "palabras clave"}</span>`
      : '<span style="font-size:var(--text-xs); color:var(--color-muted-foreground);">Sin palabras clave</span>';

    return `<tr data-grupo-id="${g.id}" data-grupo-nombre="${escapeHtml(g.nombre)}" data-grupo-audiencia="${escapeHtml(g.audiencia || "")}" data-grupo-presupuesto="${g.presupuesto ?? ""}" data-grupo-estado="${g.estado}" data-grupo-keywords-json='${escapeHtml(JSON.stringify(g.keywords || []))}' data-grupo-columnas-json='${escapeHtml(JSON.stringify(g.columnas_personalizadas || []))}'>
      <td>${escapeHtml(g.nombre)}</td>
      <td><span style="font-size:var(--text-xs); color:var(--color-muted-foreground);">${escapeHtml(g.audiencia || "—")}</span></td>
      <td class="u-mono">$${Number(g.presupuesto || 0).toLocaleString("es-MX")}</td>
      <td>${keywordsCell}</td>
      <td>${badge(g.estado)}</td>
      <td>${iconButtons("grupo", g.id)}</td>
    </tr>`;
  }

  function initGrupos() {
    const form = document.getElementById("grupoForm");
    const rowsBody = document.querySelector("[data-grupos-rows]");
    if (!form || !rowsBody) return;

    const modalTitle = document.querySelector("[data-grupo-modal-title]");
    const submitLabel = document.querySelector("[data-grupo-submit-label]");
    const keywordsBlock = document.querySelector("[data-keywords-block]");
    const keywordsLockedHint = document.querySelector("[data-keywords-locked-hint]");
    const tableContainer = document.querySelector("[data-keywords-table-container]");

    // Estado en memoria de la hoja de cálculo actualmente abierta — se re-renderiza completa en cada cambio (agregar/borrar columna, editar celda, agregar/borrar fila) para mantener el DOM siempre consistente con los datos guardados.
    const kwState = { grupoId: null, columnas: [], keywords: [] };

    function addSuggestion(nombre) {
      const datalist = document.getElementById("columnasSugeridas");
      if (!datalist || [...datalist.options].some((o) => o.value === nombre)) return;
      const opt = document.createElement("option");
      opt.value = nombre;
      datalist.appendChild(opt);
    }

    function competenciaOptionsHtml(selected) {
      return ["", "baja", "media", "alta"]
        .map((v) => `<option value="${v}" ${selected === v ? "selected" : ""}>${v ? COMPETENCIA_LABEL[v] : "—"}</option>`)
        .join("");
    }

    function keywordDisplayCell(field, kw, columnaId) {
      if (field === "keyword") return escapeHtml(kw.keyword);
      if (field === "volumen_busqueda") return kw.volumen_busqueda != null ? Number(kw.volumen_busqueda).toLocaleString("es-MX") : "—";
      if (field === "competencia") return kw.competencia ? `<span class="badge ${COMPETENCIA_CLASS[kw.competencia]}">${COMPETENCIA_LABEL[kw.competencia]}</span>` : "—";
      if (field === "cpc") return kw.cpc != null ? "$" + kw.cpc : "—";
      if (field === "custom") {
        const val = (kw.datos_personalizados || {})[columnaId];
        return val ? escapeHtml(val) : "—";
      }
      return "—";
    }

    function renderTable() {
      const theadExtra = kwState.columnas
        .map(
          (c) => `<th data-columna-id="${c.id}">
            <span data-columna-nombre-display="${c.id}">${escapeHtml(c.nombre)}</span>
            <button type="button" class="btn--icon" data-delete-columna="${c.id}" title="Eliminar columna"><i class="fa-solid fa-xmark"></i></button>
          </th>`
        )
        .join("");

      const thead = `<tr>
        <th>Palabra clave</th><th>Volumen de búsqueda</th><th>Competencia</th><th>CPC (MXN)</th>
        ${theadExtra}
        <th style="white-space:nowrap;"><button type="button" class="btn--icon" data-add-columna title="Agregar columna"><i class="fa-solid fa-plus"></i></button></th>
      </tr>`;

      const bodyRows = kwState.keywords
        .map((kw) => {
          const customCells = kwState.columnas
            .map((c) => `<td data-editable-cell data-field="custom" data-columna-id="${c.id}">${keywordDisplayCell("custom", kw, c.id)}</td>`)
            .join("");
          return `<tr data-keyword-id="${kw.id}">
            <td data-editable-cell data-field="keyword">${keywordDisplayCell("keyword", kw)}</td>
            <td class="u-mono" data-editable-cell data-field="volumen_busqueda">${keywordDisplayCell("volumen_busqueda", kw)}</td>
            <td data-editable-cell data-field="competencia">${keywordDisplayCell("competencia", kw)}</td>
            <td class="u-mono" data-editable-cell data-field="cpc">${keywordDisplayCell("cpc", kw)}</td>
            ${customCells}
            <td><button type="button" class="btn--icon" title="Eliminar" style="color:var(--text-danger);" data-delete-keyword-row="${kw.id}"><i class="fa-solid fa-trash"></i></button></td>
          </tr>`;
        })
        .join("");

      const footExtra = kwState.columnas.map((c) => `<td><input class="input" type="text" data-kw-custom-input="${c.id}" placeholder="${escapeHtml(c.nombre)}"></td>`).join("");

      const tfoot = `<tr>
        <td><input class="input" type="text" id="kw_keyword" placeholder="Nueva palabra clave"></td>
        <td><input class="input" type="number" min="0" id="kw_volumen" placeholder="0"></td>
        <td><select class="select" id="kw_competencia">${competenciaOptionsHtml("")}</select></td>
        <td><input class="input" type="number" step="0.01" min="0" id="kw_cpc" placeholder="0.00"></td>
        ${footExtra}
        <td><button type="button" class="btn btn--primary" data-add-keyword-row title="Agregar fila"><i class="fa-solid fa-plus"></i></button></td>
      </tr>`;

      tableContainer.innerHTML = `<table class="table"><thead>${thead}</thead><tbody data-keyword-rows>${bodyRows}</tbody><tfoot>${tfoot}</tfoot></table>`;
    }

    function lockKeywords() {
      keywordsBlock.hidden = true;
      keywordsLockedHint.hidden = false;
      kwState.grupoId = null;
      kwState.columnas = [];
      kwState.keywords = [];
      tableContainer.innerHTML = "";
    }

    function unlockKeywords(grupoId, keywords, columnas) {
      keywordsBlock.hidden = false;
      keywordsLockedHint.hidden = true;
      kwState.grupoId = grupoId;
      kwState.columnas = columnas || [];
      kwState.keywords = keywords || [];
      renderTable();
    }

    function resetForm() {
      form.reset();
      delete form.dataset.editingId;
      if (modalTitle) modalTitle.textContent = "Agregar Grupo de Anuncios";
      if (submitLabel) submitLabel.textContent = "Agregar Grupo";
      lockKeywords();
    }

    document.querySelector('[onclick*="grupoModal"]')?.addEventListener("click", resetForm);

    form.addEventListener("submit", (e) => {
      e.preventDefault();
      const editingId = form.dataset.editingId;
      const url = editingId ? form.dataset.updateActionTemplate.replace("__ID__", editingId) : form.dataset.storeAction;
      const payload = Object.fromEntries(new FormData(form).entries());

      request(url, editingId ? "PUT" : "POST", payload)
        .then((grupo) => {
          const rowData = { ...grupo, columnas_personalizadas: grupo.columnas_personalizadas || [] };
          const existing = rowsBody.querySelector(`[data-grupo-id="${grupo.id}"]`);
          if (existing) existing.outerHTML = grupoRowHtml(rowData);
          else rowsBody.insertAdjacentHTML("beforeend", grupoRowHtml(rowData));
          toggleEmptyState("data-grupos-empty", "data-grupos-table", rowsBody);

          if (editingId) {
            window.AgencyOS.closeModal("grupoModal");
            resetForm();
            toast("Grupo actualizado.", "success");
          } else {
            // Al crear, el modal se queda abierto y pasa a modo edición para que se puedan agregar palabras clave de inmediato.
            form.dataset.editingId = grupo.id;
            if (modalTitle) modalTitle.textContent = "Editar Grupo de Anuncios";
            if (submitLabel) submitLabel.textContent = "Guardar Cambios";
            unlockKeywords(grupo.id, grupo.keywords, grupo.columnas_personalizadas);
            toast("Grupo agregado. Ya puedes agregar palabras clave.", "success");
          }
        })
        .catch(() => toast("No se pudo guardar el grupo.", "error"));
    });

    rowsBody.addEventListener("click", (e) => {
      const editBtn = e.target.closest("[data-edit-grupo]");
      const deleteBtn = e.target.closest("[data-delete-grupo]");

      if (editBtn) {
        const row = editBtn.closest("tr");
        form.dataset.editingId = row.dataset.grupoId;
        form.querySelector("#g_nombre").value = row.dataset.grupoNombre || "";
        form.querySelector("#g_audiencia").value = row.dataset.grupoAudiencia || "";
        form.querySelector("#g_presupuesto").value = row.dataset.grupoPresupuesto || "";
        form.querySelector("#g_estado").value = row.dataset.grupoEstado;
        if (modalTitle) modalTitle.textContent = "Editar Grupo de Anuncios";
        if (submitLabel) submitLabel.textContent = "Guardar Cambios";
        unlockKeywords(row.dataset.grupoId, JSON.parse(row.dataset.grupoKeywordsJson || "[]"), JSON.parse(row.dataset.grupoColumnasJson || "[]"));
        window.AgencyOS.openModal("grupoModal");
      }

      if (deleteBtn) {
        if (!window.confirm("¿Eliminar este grupo de anuncios? También se eliminarán sus palabras clave.")) return;
        const id = deleteBtn.dataset.deleteGrupo;
        request(`/admin/ads/grupos/${id}`, "DELETE")
          .then(() => {
            rowsBody.querySelector(`[data-grupo-id="${id}"]`)?.remove();
            toggleEmptyState("data-grupos-empty", "data-grupos-table", rowsBody);
            toast("Grupo eliminado.", "success");
          })
          .catch(() => toast("No se pudo eliminar el grupo.", "error"));
      }
    });

    // ---------- Delegación de eventos sobre la tabla dinámica (se re-crea en cada render) ----------
    tableContainer.addEventListener("click", (e) => {
      // Agregar fila de keyword
      const addRowBtn = e.target.closest("[data-add-keyword-row]");
      if (addRowBtn) {
        const keywordInput = document.getElementById("kw_keyword");
        const keyword = keywordInput.value.trim();
        if (!keyword) {
          keywordInput.focus();
          return;
        }
        const datosPersonalizados = {};
        kwState.columnas.forEach((c) => {
          const input = tableContainer.querySelector(`[data-kw-custom-input="${c.id}"]`);
          if (input && input.value) datosPersonalizados[c.id] = input.value;
        });
        const payload = {
          keyword,
          volumen_busqueda: document.getElementById("kw_volumen").value || null,
          competencia: document.getElementById("kw_competencia").value || null,
          cpc: document.getElementById("kw_cpc").value || null,
          datos_personalizados: datosPersonalizados,
        };
        request(`/admin/ads/grupos/${kwState.grupoId}/keywords`, "POST", payload)
          .then((kw) => {
            kwState.keywords.push(kw);
            renderTable();
            document.getElementById("kw_keyword")?.focus();
          })
          .catch(() => toast("No se pudo agregar la palabra clave.", "error"));
        return;
      }

      // Borrar fila de keyword
      const deleteRowBtn = e.target.closest("[data-delete-keyword-row]");
      if (deleteRowBtn) {
        const id = deleteRowBtn.dataset.deleteKeywordRow;
        request(`/admin/ads/grupos/keywords/${id}`, "DELETE")
          .then(() => {
            kwState.keywords = kwState.keywords.filter((k) => String(k.id) !== String(id));
            renderTable();
          })
          .catch(() => toast("No se pudo eliminar la palabra clave.", "error"));
        return;
      }

      // Agregar columna — el encabezado "+" se convierte en un input con sugerencias (datalist) de nombres ya usados en cualquier otro grupo.
      const addColBtn = e.target.closest("[data-add-columna]");
      if (addColBtn) {
        const th = addColBtn.closest("th");
        th.innerHTML = `<input class="input" type="text" list="columnasSugeridas" placeholder="Nombre de columna" autocomplete="off" style="width:140px;">`;
        const input = th.querySelector("input");
        input.focus();

        const confirmAdd = () => {
          const nombre = input.value.trim();
          if (!nombre) {
            renderTable();
            return;
          }
          request(`/admin/ads/grupos/${kwState.grupoId}/columnas`, "POST", { nombre })
            .then((columna) => {
              kwState.columnas.push(columna);
              addSuggestion(columna.nombre);
              renderTable();
            })
            .catch(() => {
              toast("No se pudo agregar la columna.", "error");
              renderTable();
            });
        };
        input.addEventListener("keydown", (ev) => {
          if (ev.key === "Enter") {
            ev.preventDefault();
            confirmAdd();
          }
          if (ev.key === "Escape") renderTable();
        });
        input.addEventListener("blur", confirmAdd);
        return;
      }

      // Borrar columna
      const delColBtn = e.target.closest("[data-delete-columna]");
      if (delColBtn) {
        if (!window.confirm("¿Eliminar esta columna? Se perderán los valores guardados en ella para todas las palabras clave de este grupo.")) return;
        const columnaId = delColBtn.dataset.deleteColumna;
        request(`/admin/ads/grupos/columnas/${columnaId}`, "DELETE")
          .then(() => {
            kwState.columnas = kwState.columnas.filter((c) => String(c.id) !== String(columnaId));
            renderTable();
          })
          .catch(() => toast("No se pudo eliminar la columna.", "error"));
        return;
      }

      // Renombrar columna (clic en el nombre del encabezado)
      const nombreSpan = e.target.closest("[data-columna-nombre-display]");
      if (nombreSpan) {
        const columnaId = nombreSpan.dataset.columnaNombreDisplay;
        const columna = kwState.columnas.find((c) => String(c.id) === String(columnaId));
        if (!columna) return;
        nombreSpan.innerHTML = `<input class="input" type="text" value="${escapeHtml(columna.nombre)}" autocomplete="off" style="width:110px;">`;
        const input = nombreSpan.querySelector("input");
        input.focus();
        input.select();

        const saveRename = () => {
          const nuevoNombre = input.value.trim();
          if (!nuevoNombre || nuevoNombre === columna.nombre) {
            renderTable();
            return;
          }
          request(`/admin/ads/grupos/columnas/${columnaId}`, "PUT", { nombre: nuevoNombre })
            .then((updated) => {
              columna.nombre = updated.nombre;
              addSuggestion(updated.nombre);
              renderTable();
            })
            .catch(() => {
              toast("No se pudo renombrar la columna.", "error");
              renderTable();
            });
        };
        input.addEventListener("keydown", (ev) => {
          if (ev.key === "Enter") {
            ev.preventDefault();
            input.blur();
          }
          if (ev.key === "Escape") renderTable();
        });
        input.addEventListener("blur", saveRename);
        return;
      }

      // Edición en línea de una celda existente (keyword/volumen/competencia/cpc/columna personalizada)
      const cell = e.target.closest("[data-editable-cell]");
      if (cell && !cell.querySelector("input,select")) {
        const field = cell.dataset.field;
        const row = cell.closest("tr");
        const kw = kwState.keywords.find((k) => String(k.id) === String(row.dataset.keywordId));
        if (!kw) return;

        let inputHtml;
        if (field === "competencia") {
          inputHtml = `<select class="select">${competenciaOptionsHtml(kw.competencia || "")}</select>`;
        } else if (field === "volumen_busqueda") {
          inputHtml = `<input class="input" type="number" min="0" value="${kw.volumen_busqueda ?? ""}">`;
        } else if (field === "cpc") {
          inputHtml = `<input class="input" type="number" step="0.01" min="0" value="${kw.cpc ?? ""}">`;
        } else if (field === "custom") {
          const columnaId = cell.dataset.columnaId;
          const val = (kw.datos_personalizados || {})[columnaId] ?? "";
          inputHtml = `<input class="input" type="text" value="${escapeHtml(val)}">`;
        } else {
          inputHtml = `<input class="input" type="text" value="${escapeHtml(kw.keyword)}">`;
        }

        cell.innerHTML = inputHtml;
        const input = cell.querySelector("input,select");
        input.focus();
        if (input.select) input.select();

        const saveCell = () => {
          const value = input.value;
          const payload = {
            keyword: field === "keyword" ? value : kw.keyword,
            volumen_busqueda: field === "volumen_busqueda" ? value || null : kw.volumen_busqueda,
            competencia: field === "competencia" ? value || null : kw.competencia,
            cpc: field === "cpc" ? value || null : kw.cpc,
            datos_personalizados: kw.datos_personalizados || {},
          };
          if (field === "custom") {
            payload.datos_personalizados = { ...(kw.datos_personalizados || {}), [cell.dataset.columnaId]: value || null };
          }

          request(`/admin/ads/grupos/keywords/${kw.id}`, "PUT", payload)
            .then((fresh) => {
              const idx = kwState.keywords.findIndex((k) => k.id === kw.id);
              kwState.keywords[idx] = fresh;
              renderTable();
            })
            .catch(() => {
              toast("No se pudo guardar el cambio.", "error");
              renderTable();
            });
        };

        input.addEventListener("keydown", (ev) => {
          if (ev.key === "Enter") {
            ev.preventDefault();
            input.blur();
          }
          if (ev.key === "Escape") renderTable();
        });
        input.addEventListener("blur", saveCell);
      }
    });

    // Enter en cualquier campo de la fila de captura agrega la palabra clave, como en una hoja de cálculo.
    tableContainer.addEventListener("keydown", (e) => {
      if (e.key !== "Enter") return;
      if (!["kw_keyword", "kw_volumen", "kw_cpc"].includes(e.target.id) && !e.target.hasAttribute("data-kw-custom-input")) return;
      e.preventDefault();
      tableContainer.querySelector("[data-add-keyword-row]")?.click();
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

  // ==========================================================================
  // Ads index — client-picker cards, platform tabs, KPI/chart aggregates,
  // and AJAX-modal campaign CRUD. Everything below is additive: it only
  // targets elements that exist on index.blade.php (each init* guards on
  // its own required element), so it's inert on show.blade.php where this
  // same file is also loaded for initFasePanel()/initGrupos()/etc above.
  // ==========================================================================

  const PLATAFORMA_META = {
    google_ads: ["Google Ads", "#4285F4"],
    meta_ads: ["Meta Ads", "#1877F2"],
    tiktok_ads: ["TikTok Ads", "#FE2C55"],
  };
  const OBJETIVO_LABELS = { leads: "Leads", ventas: "Ventas", trafico: "Tráfico", branding: "Branding" };
  // Mirrors components/badge.blade.php's own label/class map for these keys
  // exactly (not Labels::faseAds()'s longer strings) so JS-built rows look
  // identical to the <x-badge> the server renders for page-load rows.
  const FASE_ADS_BADGE = {
    briefing: ["Briefing", "badge--neutral"],
    configuracion: ["Configuración", "badge--primary"],
    lanzamiento: ["Lanzamiento", "badge--success"],
    reporte: ["Reporte", "badge--orange"],
    cerrada: ["Cerrada", "badge--success"],
  };
  const ESTADO_CAMPANA_BADGE = {
    activa: ["Activa", "badge--success"],
    pausada: ["Pausada", "badge--warning"],
    finalizada: ["Finalizada", "badge--neutral"],
  };

  let adsActiveCliente = "all";
  let adsActivePlataforma = "todas";
  let adsChartInstance = null;

  function adsBadgeHtml(map, key) {
    const [label, cls] = map[key] || [key, "badge--neutral"];
    return `<span class="badge ${cls}">${label}</span>`;
  }

  function fmtMoney(n) {
    return "$" + Math.round(Number(n) || 0).toLocaleString("es-MX");
  }

  function fmtNum(n) {
    return Number(n || 0).toLocaleString("es-MX");
  }

  /** Mirrors AgencyOS.formatCompact() exactly — see the matching PHP closure in index.blade.php's @php block ($compact). */
  function fmtCompact(n) {
    return window.AgencyOS.formatCompact(Number(n) || 0);
  }

  function adsRoasColor(roas) {
    const r = Number(roas) || 0;
    return r >= 5 ? "var(--text-success)" : r >= 3 ? "var(--text-warning)" : "var(--text-danger)";
  }

  /** Up to 2 uppercase initials from a display name — mirrors App\Support\Labels::initials() exactly. */
  function adsInitials(name) {
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
   * behavior (and the five existing init-panel/initCrudTable callers
   * relying on its plain "request_failed" throw) is untouched.
   */
  function adsRequest(url, method, payload) {
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

  function renderAdsFieldErrors(form, errors) {
    form.querySelectorAll("[data-error-for]").forEach((span) => {
      span.textContent = "";
    });
    Object.keys(errors || {}).forEach((field) => {
      const span = form.querySelector(`[data-error-for="${field}"]`);
      if (span) span.textContent = errors[field][0];
    });
  }

  // ---------- Row rendering — matches index.blade.php's <tr data-campana-row> markup exactly ----------

  function campanaRowCellsHtml(c) {
    const [platLabel, platColor] = PLATAFORMA_META[c.plataforma] || [c.plataforma, "#6b7280"];
    return `
      <td><a href="${c.show_url}" style="font-weight:500; color:var(--color-foreground);">${escapeHtml(c.nombre)}</a></td>
      <td>${escapeHtml(c.cliente)}</td>
      <td><span class="ads-plataforma-pill" style="--pill-color:${platColor}">${platLabel}</span></td>
      <td><span style="font-size:var(--text-xs); color:var(--color-muted-foreground);">${OBJETIVO_LABELS[c.objetivo] || escapeHtml(c.objetivo)}</span></td>
      <td>${adsBadgeHtml(FASE_ADS_BADGE, c.fase_actual)}</td>
      <td>${adsBadgeHtml(ESTADO_CAMPANA_BADGE, c.estado)}</td>
      <td class="u-mono">${fmtMoney(c.inversion)}</td>
      <td class="u-mono">${fmtCompact(c.impresiones)}</td>
      <td class="u-mono">${fmtNum(c.clics)}</td>
      <td class="u-mono">${fmtNum(c.conversiones)}</td>
      <td class="u-mono"><strong style="color:${adsRoasColor(c.roas)}">${c.roas}x</strong></td>
      <td>
        <div style="display:flex; gap:4px;">
          <button type="button" class="btn--icon" title="Editar" data-edit-campana="${c.id}"><i class="fa-solid fa-pen"></i></button>
          <button type="button" class="btn--icon" title="Eliminar" style="color:var(--text-danger);" data-delete-campana="${c.id}"><i class="fa-solid fa-trash"></i></button>
        </div>
      </td>`;
  }

  /** Dataset assigned via properties (not string-embedded JSON), safe regardless of quote characters in the data — mirrors keywords.js's buildKeywordRowElement. */
  function buildCampanaRowElement(c) {
    const tr = document.createElement("tr");
    tr.setAttribute("data-campana-row", "");
    tr.dataset.campanaId = String(c.id);
    tr.dataset.plataforma = c.plataforma;
    tr.dataset.clienteId = String(c.cliente_id);
    tr.dataset.campana = JSON.stringify(c);
    tr.innerHTML = campanaRowCellsHtml(c);
    return tr;
  }

  function allCampanas() {
    return Array.from(document.querySelectorAll("[data-campana-row]")).map((row) => JSON.parse(row.dataset.campana));
  }

  function updateAdsCountSubtitle() {
    const subtitle = document.getElementById("adsCountSubtitle");
    if (!subtitle) return;
    const rows = document.querySelectorAll("[data-campana-row]");
    const clienteCount = new Set(Array.from(rows).map((r) => r.dataset.clienteId)).size;
    subtitle.textContent = `${rows.length} campaña${rows.length === 1 ? "" : "s"} · ${clienteCount} cliente${clienteCount === 1 ? "" : "s"}`;
  }

  // ---------- Account picker ----------

  /** Rebuilds the card row from [data-campana-row]'s embedded JSON — always includes "Todas las cuentas" first, then one card per cliente with >=1 campaign. Card totals are always summed across ALL platforms (never affected by the platform tab). */
  function renderAccountCards() {
    const row = document.getElementById("adsAccountRow");
    if (!row) return;

    const searchValue = (document.getElementById("adsAccountSearch")?.value || "").trim().toLowerCase();
    const campanas = allCampanas();

    const byCliente = new Map();
    campanas.forEach((c) => {
      const key = String(c.cliente_id);
      if (!byCliente.has(key)) byCliente.set(key, { id: key, nombre: c.cliente, contacto: c.cliente_contacto, count: 0, inversion: 0 });
      const entry = byCliente.get(key);
      entry.count += 1;
      entry.inversion += Number(c.inversion) || 0;
    });

    const clientes = Array.from(byCliente.values()).sort((a, b) => a.nombre.localeCompare(b.nombre, "es"));
    const cards = [{ id: "all", nombre: "Todas las cuentas", contacto: null, count: campanas.length, inversion: campanas.reduce((a, c) => a + (Number(c.inversion) || 0), 0) }, ...clientes];

    let visibleClientCards = 0;
    row.innerHTML = cards
      .map((c) => {
        const isActive = String(adsActiveCliente) === c.id;
        const matchesSearch = c.id === "all" || !searchValue || c.nombre.toLowerCase().includes(searchValue);
        if (c.id !== "all" && matchesSearch) visibleClientCards++;
        const metaLine = `${c.contacto ? escapeHtml(c.contacto) + " · " : ""}${c.count} camp. · ${fmtMoney(c.inversion)}`;
        return `<button type="button" class="ads-account-card${isActive ? " is-active" : ""}" data-account-card="${c.id}" ${matchesSearch ? "" : "hidden"}>
          <span class="ads-account-card__avatar">${escapeHtml(adsInitials(c.nombre))}</span>
          <span class="ads-account-card__body">
            <span class="ads-account-card__name">${escapeHtml(c.nombre)}</span>
            <span class="ads-account-card__meta">${metaLine}</span>
          </span>
        </button>`;
      })
      .join("");

    const emptyEl = document.getElementById("adsAccountEmpty");
    const emptyQueryEl = document.getElementById("adsAccountEmptyQuery");
    if (emptyEl) emptyEl.hidden = !searchValue || visibleClientCards > 0;
    if (emptyQueryEl) emptyQueryEl.textContent = searchValue;
  }

  function initAdsAccountPicker() {
    const row = document.getElementById("adsAccountRow");
    if (!row) return;

    renderAccountCards();

    row.addEventListener("click", (e) => {
      const card = e.target.closest("[data-account-card]");
      if (!card) return;
      adsActiveCliente = card.dataset.accountCard;
      renderAccountCards();
      applyAdsFilters();
    });

    document.getElementById("adsAccountSearch")?.addEventListener("input", window.AgencyOS.debounce(renderAccountCards, 150));
  }

  // ---------- Platform tabs ----------

  function initAdsPlatformTabs() {
    const tabsWrap = document.getElementById("adsPlatformTabs");
    if (!tabsWrap) return;

    const presetPlataforma = new URLSearchParams(location.search).get("plataforma");
    if (presetPlataforma && (presetPlataforma === "todas" || PLATAFORMA_META[presetPlataforma])) {
      adsActivePlataforma = presetPlataforma;
    }

    function syncActiveClass() {
      tabsWrap.querySelectorAll("[data-plataforma-tab]").forEach((btn) => {
        btn.classList.toggle("is-active", btn.dataset.plataformaTab === adsActivePlataforma);
      });
    }

    tabsWrap.addEventListener("click", (e) => {
      const btn = e.target.closest("[data-plataforma-tab]");
      if (!btn) return;
      adsActivePlataforma = btn.dataset.plataformaTab;
      syncActiveClass();
      applyAdsFilters();
    });

    syncActiveClass();
  }

  function updatePlatformTabCounts(total, counts) {
    const todasBadge = document.querySelector('[data-plataforma-count="todas"]');
    if (todasBadge) todasBadge.textContent = total;
    Object.keys(counts).forEach((p) => {
      const el = document.querySelector(`[data-plataforma-count="${p}"]`);
      if (el) el.textContent = counts[p];
    });
  }

  // ---------- Filters — AND composition of activeCliente + activePlataforma ----------

  /**
   * Toggles every [data-campana-row]'s display based on both the account
   * picker's selection and the platform tab (both must pass). Tab counts
   * are scoped to activeCliente only (never activePlataforma), matching
   * the account cards' own always-all-platforms totals. global.js's
   * initTablePagination() MutationObserver picks up these style.display
   * writes automatically — no extra coordination needed here.
   */
  function applyAdsFilters() {
    const rows = Array.from(document.querySelectorAll("[data-campana-row]"));
    const clienteCounts = {};
    Object.keys(PLATAFORMA_META).forEach((p) => (clienteCounts[p] = 0));
    let clienteTotal = 0;
    let visibleCount = 0;

    rows.forEach((row) => {
      const matchesCliente = adsActiveCliente === "all" || row.dataset.clienteId === String(adsActiveCliente);
      if (matchesCliente) {
        clienteTotal++;
        if (clienteCounts[row.dataset.plataforma] !== undefined) clienteCounts[row.dataset.plataforma]++;
      }
      const matchesPlataforma = adsActivePlataforma === "todas" || row.dataset.plataforma === adsActivePlataforma;
      const show = matchesCliente && matchesPlataforma;
      row.style.display = show ? "" : "none";
      if (show) visibleCount++;
    });

    updatePlatformTabCounts(clienteTotal, clienteCounts);

    // "No campaigns match the current filters" — a visibility check, not a
    // DOM-existence check like the shared toggleEmptyState() helper, since
    // this table is client-side filterable (rows can exist but be hidden).
    const emptyEl = document.querySelector("[data-campanas-empty]");
    const tableEl = document.querySelector("[data-campanas-table]");
    if (emptyEl) emptyEl.hidden = visibleCount > 0;
    if (tableEl) tableEl.hidden = visibleCount === 0;

    recomputeAdsAggregates();
  }

  // ---------- KPI / chart / platform-split aggregates ----------

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

  /**
   * Recomputes the 6 KPI cards, the chart, and the platform-split bars from
   * every currently-visible [data-campana-row]. The ROAS KPI is the total
   * ratio (totalIngreso / totalInversion), never an average of per-campaign
   * roas values — averaging individual roas would hit the same null-vs-zero
   * pitfall fixed today in Keywords' promedio(), since a campaign's roas is
   * 0.0 both when it genuinely has zero attributed revenue AND when it has
   * no AdsMetrica rows yet, and the backend can't tell those apart in this
   * field. The total-ratio never needs to make that distinction.
   */
  function recomputeAdsAggregates() {
    const campanas = Array.from(document.querySelectorAll("[data-campana-row]"))
      .filter((r) => r.style.display !== "none")
      .map((r) => JSON.parse(r.dataset.campana));

    const totalInversion = campanas.reduce((a, c) => a + (Number(c.inversion) || 0), 0);
    const totalIngreso = campanas.reduce((a, c) => a + (Number(c.ingreso_atribuido) || 0), 0);
    const totalConversiones = campanas.reduce((a, c) => a + (Number(c.conversiones) || 0), 0);
    const totalClics = campanas.reduce((a, c) => a + (Number(c.clics) || 0), 0);
    const totalImpresiones = campanas.reduce((a, c) => a + (Number(c.impresiones) || 0), 0);

    const roas = totalInversion > 0 ? totalIngreso / totalInversion : 0;
    const cpa = totalConversiones > 0 ? totalInversion / totalConversiones : 0;
    const ctr = totalImpresiones > 0 ? (totalClics / totalImpresiones) * 100 : 0;

    setKpi("kpiInversion", window.AgencyOS.formatCurrency(totalInversion));
    setKpi("kpiIngreso", window.AgencyOS.formatCurrency(totalIngreso));
    setKpi("kpiRoas", roas.toFixed(1) + "x");
    setKpi("kpiConversiones", window.AgencyOS.formatNumber(totalConversiones), "CPA " + window.AgencyOS.formatCurrency(cpa));
    setKpi("kpiClics", window.AgencyOS.formatNumber(totalClics), "CTR " + ctr.toFixed(2) + "%");
    setKpi("kpiImpresiones", window.AgencyOS.formatCompact(totalImpresiones));

    updateAdsChart(campanas);
    renderPlatformSplit();
  }

  function initAdsChart() {
    const canvas = document.getElementById("adsChart");
    if (!canvas || typeof Chart === "undefined") return;

    const colors = window.AgencyOS.chartColors();
    adsChartInstance = new Chart(canvas, {
      type: "bar",
      data: {
        labels: [],
        datasets: [
          { label: "Inversión", data: [], backgroundColor: "#14B8A6", borderRadius: 4, maxBarThickness: 28 },
          { label: "Ingreso atribuido", data: [], backgroundColor: "#0F9D6E", borderRadius: 4, maxBarThickness: 28 },
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
          x: { grid: { display: false }, ticks: { color: colors.tick, font: { size: 11 } } },
          y: { grid: { color: colors.grid }, ticks: { color: colors.tick, font: { size: 11 }, callback: (v) => window.AgencyOS.formatCompact(v) } },
        },
      },
    });
  }

  /** Updates the existing Chart.js instance in place (chart.data = ...; chart.update();) instead of destroying/recreating the canvas — exact pattern from seo.js's initMetricasChart(). */
  function updateAdsChart(campanas) {
    if (!adsChartInstance) return;
    adsChartInstance.data.labels = campanas.map((c) => c.nombre);
    adsChartInstance.data.datasets[0].data = campanas.map((c) => Number(c.inversion) || 0);
    adsChartInstance.data.datasets[1].data = campanas.map((c) => Number(c.ingreso_atribuido) || 0);
    adsChartInstance.update();
  }

  /** Scoped to activeCliente only (ignores activePlataforma) — always shows all 3 platforms' split for the selected account, matching the account cards' all-platforms scoping. */
  function renderPlatformSplit() {
    const container = document.getElementById("adsPlatformSplit");
    if (!container) return;

    const campanas = Array.from(document.querySelectorAll("[data-campana-row]"))
      .filter((r) => adsActiveCliente === "all" || r.dataset.clienteId === String(adsActiveCliente))
      .map((r) => JSON.parse(r.dataset.campana));

    const byPlataforma = {};
    Object.keys(PLATAFORMA_META).forEach((p) => (byPlataforma[p] = { count: 0, inversion: 0 }));
    campanas.forEach((c) => {
      if (!byPlataforma[c.plataforma]) return;
      byPlataforma[c.plataforma].count++;
      byPlataforma[c.plataforma].inversion += Number(c.inversion) || 0;
    });

    const active = Object.entries(byPlataforma).filter(([, v]) => v.count > 0);
    if (!active.length) {
      container.innerHTML = '<p style="font-size:var(--text-sm); color:var(--color-muted-foreground); margin:0;">Sin campañas para esta cuenta.</p>';
      return;
    }

    const maxInversion = Math.max(1, ...active.map(([, v]) => v.inversion));
    container.innerHTML = active
      .map(([plataforma, v]) => {
        const [label, color] = PLATAFORMA_META[plataforma];
        const pct = Math.max(2, Math.round((v.inversion / maxInversion) * 100));
        return `<div class="ads-split-row">
          <div class="ads-split-row__label">${label}</div>
          <div class="ads-split-row__bar progress-bar">
            <div class="progress-bar__fill" style="width:${pct}%; background:${color};"></div>
          </div>
          <div class="ads-split-row__meta">${fmtMoney(v.inversion)} · ${v.count} camp.</div>
        </div>`;
      })
      .join("");
  }

  // ---------- Create/edit modal ----------

  function openCampanaFormModal(c) {
    const form = document.getElementById("campanaForm");
    if (!form) return;

    form.reset();
    form.querySelectorAll("[data-error-for]").forEach((span) => {
      span.textContent = "";
    });
    form.dataset.editingId = c?.id ?? "";

    const editOnlyWrap = form.querySelector("[data-edit-only]");
    if (editOnlyWrap) editOnlyWrap.hidden = !c;

    document.getElementById("campanaFormModalTitle").textContent = c ? "Editar Campaña" : "Nueva Campaña";
    document.getElementById("campanaFormSubmit").innerHTML = c
      ? '<i class="fa-solid fa-check"></i> Guardar Cambios'
      : '<i class="fa-solid fa-check"></i> Crear Campaña';

    if (c) {
      const clienteSelect = form.querySelector("#cliente_id");
      clienteSelect.value = String(c.cliente_id);
      clienteSelect.dispatchEvent(new Event("change")); // re-runs initServicioCascade()'s filtering before we set servicio_id below
      form.querySelector("#servicio_id").value = String(c.servicio_id);
      form.querySelector("#cf_nombre").value = c.nombre;
      form.querySelector("#cf_plataforma").value = c.plataforma;
      form.querySelector("#cf_objetivo").value = c.objetivo;
      form.querySelector("#cf_presupuesto").value = c.presupuesto_mensual;
      form.querySelector("#cf_estado").value = c.estado;
      form.querySelector("#cf_fecha_inicio").value = c.fecha_inicio || "";
      form.querySelector("#cf_fecha_fin").value = c.fecha_fin || "";
      form.querySelector("#cf_notas").value = c.notas || "";
    }

    window.AgencyOS.openModal("campanaFormModal");
  }

  /** Inserts/replaces a campaign's <tr> (create -> prepended at top, matching the controller's created_at desc ordering; update -> replaced in place). */
  function upsertCampanaRow(c) {
    const tbody = document.querySelector("[data-campanas-table] tbody");
    if (!tbody) return;

    const existing = tbody.querySelector(`[data-campana-row][data-campana-id="${c.id}"]`);
    const newRow = buildCampanaRowElement(c);
    if (existing) existing.replaceWith(newRow);
    else tbody.insertBefore(newRow, tbody.firstChild);

    updateAdsCountSubtitle();
    renderAccountCards();
    applyAdsFilters();
  }

  function initAdsCampanaForm() {
    const form = document.getElementById("campanaForm");
    if (!form) return;

    document.querySelector("[data-open-campana-modal]")?.addEventListener("click", () => openCampanaFormModal(null));

    document.addEventListener("click", (e) => {
      const editBtn = e.target.closest("[data-edit-campana]");
      if (!editBtn) return;
      const row = editBtn.closest("[data-campana-row]");
      if (row) openCampanaFormModal(JSON.parse(row.dataset.campana));
    });

    form.addEventListener("submit", (e) => {
      e.preventDefault();
      const editingId = form.dataset.editingId;
      const url = editingId ? form.dataset.updateActionTemplate.replace("__ID__", editingId) : form.dataset.storeAction;
      const payload = Object.fromEntries(new FormData(form).entries());

      adsRequest(url, editingId ? "PUT" : "POST", payload)
        .then((c) => {
          upsertCampanaRow(c);
          window.AgencyOS.closeModal("campanaFormModal");
          toast(editingId ? "Campaña actualizada." : "Campaña creada.", "success");
        })
        .catch((err) => {
          if (err.message === "validation_failed") {
            renderAdsFieldErrors(form, err.data.errors || {});
            toast("Revisa los campos marcados.", "error");
          } else {
            toast("No se pudo guardar la campaña.", "error");
          }
        });
    });
  }

  // ---------- Delete — native window.confirm(), matching every other delete flow already in this file (initGrupos/initCrudTable) ----------

  function initAdsCampanaDelete() {
    document.addEventListener("click", (e) => {
      const btn = e.target.closest("[data-delete-campana]");
      if (!btn) return;
      const row = document.querySelector(`[data-campana-row][data-campana-id="${btn.dataset.deleteCampana}"]`);
      if (!row) return;
      const c = JSON.parse(row.dataset.campana);

      if (!window.confirm(`¿Eliminar la campaña "${c.nombre}"? También se eliminarán sus grupos de anuncios (con sus palabras clave), creativos, métricas mensuales y optimizaciones registradas. Esta acción no se puede deshacer.`)) return;

      adsRequest(`/admin/ads/${c.id}`, "DELETE")
        .then(() => {
          row.remove();
          updateAdsCountSubtitle();
          renderAccountCards();
          applyAdsFilters();
          toast("Campaña eliminada.", "success");
        })
        .catch(() => toast("No se pudo eliminar la campaña.", "error"));
    });
  }

  // ---------- ?editar=ID / ?plataforma=X on load — "Volver a editar" from show.blade.php's line 19 ----------

  function initAdsEditFromQuery() {
    const editId = new URLSearchParams(location.search).get("editar");
    if (!editId) return;
    const row = document.querySelector(`[data-campana-row][data-campana-id="${editId}"]`);
    if (!row) return;
    openCampanaFormModal(JSON.parse(row.dataset.campana));
  }

  document.addEventListener("shell:ready", () => {
    initServicioCascade();
    initFasePanel();
    initGrupos();
    initCreativos();
    initMetricas();
    initOptimizaciones();
    initAdsAccountPicker();
    initAdsPlatformTabs();
    initAdsChart();
    initAdsCampanaForm();
    initAdsCampanaDelete();
    initAdsEditFromQuery();
    applyAdsFilters();
  });
})();
