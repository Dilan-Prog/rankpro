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

  document.addEventListener("shell:ready", () => {
    initServicioCascade();
    initFasePanel();
    initGrupos();
    initCreativos();
    initMetricas();
    initOptimizaciones();
  });
})();
