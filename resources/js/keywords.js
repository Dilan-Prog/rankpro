/**
 * Keywords module — Listas de Keywords (client + canal + responsable
 * grouping) with a nested keyword sub-table per list, plus a legacy "Sin
 * lista asignada" table for keywords that predate this grouping. Mirrors
 * servicios.js's data-*-row JSON-on-row + fetch()-driven CRUD pattern for
 * the lista-level records, and seo.js's initContenido() child-record CRUD
 * pattern for keywords nested inside a lista.
 *
 * Server responses only ever return the single record that was mutated
 * (a keyword or a lista), never a full lista with fresh aggregates — so
 * every keyword mutation recomputes its lista's displayed aggregates
 * (keywords_count, volumen_total, kd/cpc/posicion promedio, fuentes)
 * client-side from the sub-table's own rows via recomputeListaAggregates().
 * This mirrors the exact sum/count formulas in KeywordLista::toRow() (nulls
 * treated as 0 in the sum, divided by the full row count) so the numbers
 * never drift from what a fresh page load would show.
 */
(function () {
  "use strict";

  const { toast } = window.AgencyOS;
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

  const TIPO_LABELS = { principal: "Principal", secundaria: "Secundaria", long_tail: "Long Tail", lsi: "LSI" };
  const INTENCION_LABELS = { informacional: "Informacional", transaccional: "Transaccional", navegacional: "Navegacional" };
  const HERRAMIENTA_LABELS = { semrush: "Semrush", ahrefs: "Ahrefs", google_kp: "Google Keyword Planner", otro: "Otro" };
  const ESTADO_BADGE = {
    en_uso: ["En Uso", "badge--primary"],
    seguimiento: ["Seguimiento", "badge--info"],
    descartada: ["Descartada", "badge--neutral"],
  };

  // Holds the lista object currently shown in the detail modal, so its
  // "Editar/Importar/Añadir palabra clave" buttons can hand off without a
  // second lookup — mirrors servicios.js's currentDetailServicio.
  let currentDetailLista = null;

  // ---------- Base helpers ----------

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

  /** JSON fetch helper — rejects with err.message "validation_failed" (err.data.errors) on 422, "request_failed" otherwise. */
  function request(url, method, payload) {
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

  function fmtMoney(n) {
    return "$" + Number(n || 0).toLocaleString("es-MX", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function fmtNum(n) {
    return Number(n || 0).toLocaleString("es-MX");
  }

  function badgeHtml(estado) {
    const [label, cls] = ESTADO_BADGE[estado] || [estado, "badge--neutral"];
    return `<span class="badge ${cls}">${label}</span>`;
  }

  function clearFieldErrors(form) {
    form.querySelectorAll("[data-error-for]").forEach((span) => {
      span.textContent = "";
    });
  }

  function renderFieldErrors(form, errors) {
    clearFieldErrors(form);
    Object.keys(errors || {}).forEach((field) => {
      const span = form.querySelector(`[data-error-for="${field}"]`);
      if (span) span.textContent = errors[field][0];
    });
  }

  function fillForm(form, record) {
    if (!record) {
      form.reset();
      return;
    }
    Array.from(form.elements).forEach((el) => {
      if (!el.name || !(el.name in record)) return;
      el.value = record[el.name] ?? "";
    });
  }

  // ---------- Row content builders ----------

  function keywordPosicionHtml(k) {
    const actual = k.posicion_actual;
    const anterior = k.posicion_anterior;
    const delta = actual != null && anterior != null ? anterior - actual : null;
    const color =
      delta == null ? "var(--color-muted-foreground)" : delta > 0 ? "var(--text-success)" : delta < 0 ? "var(--text-danger)" : "var(--color-muted-foreground)";
    const posLabel = actual ? `#${actual}` : "—";
    const deltaLabel = delta != null ? ` <span style="font-size:var(--text-xs); color:${color};">(${delta > 0 ? "+" : ""}${delta})</span>` : "";
    return posLabel + deltaLabel;
  }

  /** Matches admin.keywords._keyword-row.blade.php's <td> markup exactly. */
  function keywordRowCellsHtml(k) {
    return `
      <td><div style="font-weight:500">${escapeHtml(k.keyword)}</div></td>
      <td><span style="text-transform:capitalize; font-size:var(--text-xs); color:var(--color-muted-foreground)">${TIPO_LABELS[k.tipo] || escapeHtml(k.tipo)}</span></td>
      <td class="u-mono">${fmtNum(k.volumen_busqueda)}</td>
      <td class="u-mono">${k.dificultad ?? "—"}</td>
      <td class="u-mono" style="color:var(--text-success)">${k.cpc_estimado > 0 ? fmtMoney(k.cpc_estimado) : "—"}</td>
      <td><span style="font-size:var(--text-xs); color:var(--color-muted-foreground)">${INTENCION_LABELS[k.intencion] || "—"}</span></td>
      <td><span class="u-mono" style="font-size:var(--text-xs); color:var(--color-primary);">${escapeHtml(k.url_asignada || "—")}</span></td>
      <td class="u-mono">${keywordPosicionHtml(k)}</td>
      <td><span style="font-size:var(--text-xs); color:var(--color-muted-foreground)">${HERRAMIENTA_LABELS[k.herramienta_origen] || "—"}</span></td>
      <td>
        <div style="display:flex; gap:4px;">
          <button type="button" class="btn--icon" title="Editar" data-edit-keyword="${k.id}"><i class="fa-solid fa-pen"></i></button>
          <button type="button" class="btn--icon" title="Eliminar" style="color:var(--text-danger);" data-delete-keyword="${k.id}"><i class="fa-solid fa-trash"></i></button>
        </div>
      </td>`;
  }

  /** Builds a full keyword <tr> element — dataset assigned via properties (not string-embedded JSON), safe regardless of quote characters in the data. */
  function buildKeywordRowElement(k) {
    const tr = document.createElement("tr");
    tr.setAttribute("data-keyword-row", "");
    tr.dataset.keywordId = String(k.id);
    tr.dataset.listaId = k.lista_id != null ? String(k.lista_id) : "";
    tr.dataset.clienteId = String(k.cliente_id);
    tr.dataset.volumen = String(k.volumen_busqueda ?? 0);
    tr.dataset.dificultad = k.dificultad != null ? String(k.dificultad) : "";
    tr.dataset.cpc = k.cpc_estimado != null ? String(k.cpc_estimado) : "";
    tr.dataset.posicion = k.posicion_actual != null ? String(k.posicion_actual) : "";
    tr.dataset.herramienta = k.herramienta_origen || "";
    tr.dataset.keyword = JSON.stringify(k);
    tr.innerHTML = keywordRowCellsHtml(k);
    return tr;
  }

  /** Matches admin.keywords._lista-row.blade.php's lista <td> markup exactly. */
  function listaRowCellsHtml(l) {
    const fuentes = (l.fuentes || []).map((f) => escapeHtml(f)).join(", ") || "—";
    return `
      <td><input type="checkbox" data-lista-checkbox value="${l.id}" aria-label="Seleccionar ${escapeHtml(l.nombre)}" style="width:15px;height:15px;accent-color:var(--color-primary);"></td>
      <td>
        <div style="display:flex; align-items:center; gap:8px;">
          <button type="button" class="btn--icon" data-toggle-lista title="Expandir / colapsar">
            <i class="fa-solid fa-chevron-right lista-toggle__chevron"></i>
          </button>
          <span style="font-weight:500">${escapeHtml(l.nombre)}</span>
        </div>
      </td>
      <td>${escapeHtml(l.cliente)}</td>
      <td>${badgeHtml(l.estado)}</td>
      <td class="u-mono">${l.keywords_count}</td>
      <td class="u-mono">${fmtNum(l.volumen_total)}</td>
      <td class="u-mono">${l.kd_promedio ?? "—"}</td>
      <td class="u-mono">${l.cpc_promedio != null ? fmtMoney(l.cpc_promedio) : "—"}</td>
      <td class="u-mono">${l.posicion_promedio != null ? "#" + l.posicion_promedio : "—"}</td>
      <td><span style="font-size:var(--text-xs); color:var(--color-muted-foreground)">${fuentes}</span></td>
      <td>
        <div style="display:flex; gap:4px;">
          <button type="button" class="btn--icon" title="Editar lista" data-edit-lista="${l.id}"><i class="fa-solid fa-pen"></i></button>
          <button type="button" class="btn--icon" title="Eliminar lista" style="color:var(--text-danger);" data-delete-lista="${l.id}"><i class="fa-solid fa-trash"></i></button>
        </div>
      </td>`;
  }

  function buildListaRowElement(l) {
    const tr = document.createElement("tr");
    tr.className = "is-clickable";
    tr.setAttribute("data-lista-row", "");
    tr.dataset.listaId = String(l.id);
    tr.dataset.search = (l.nombre + " " + (l.keywords || []).map((k) => k.keyword).join(" ")).toLowerCase();
    tr.dataset.estado = l.estado;
    tr.dataset.cliente = String(l.cliente_id);
    tr.dataset.lista = JSON.stringify(l);
    tr.innerHTML = listaRowCellsHtml(l);
    return tr;
  }

  /** Sub-row chrome (header buttons + empty-state + table shell) — matches _lista-row.blade.php's subrow markup; keyword <tr>s are appended as real elements afterward (buildKeywordRowElement), not string-interpolated. */
  function listaSubrowShellHtml(l, hasKeywords) {
    return `
      <div style="padding: var(--space-4) var(--space-4) var(--space-4) var(--space-8);">
        <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:var(--space-2); margin-bottom:var(--space-3);">
          <div class="record-modal__section-label" style="margin:0;">Keywords de esta lista</div>
          <div style="display:flex; gap:var(--space-2);">
            <button type="button" class="btn btn--secondary btn--sm" data-open-import-modal="${l.id}">
              <i class="fa-solid fa-file-import"></i> Importar keywords
            </button>
            <button type="button" class="btn btn--primary btn--sm" data-add-keyword-to-lista="${l.id}" data-add-keyword-cliente="${l.cliente_id}">
              <i class="fa-solid fa-plus"></i> Añadir palabra clave a esta lista
            </button>
          </div>
        </div>
        <div class="empty-state" data-lista-keywords-empty="${l.id}" style="padding: var(--space-6);" ${hasKeywords ? "hidden" : ""}>
          <p class="empty-state__text" style="margin-bottom:0;">Esta lista aún no tiene keywords.</p>
        </div>
        <div class="table-wrap" data-lista-keywords-table="${l.id}" ${hasKeywords ? "" : "hidden"}>
          <table class="table">
            <thead>
              <tr>
                <th>Palabra clave</th><th>Tipo</th><th>Volumen</th><th>KD</th><th>CPC Est.</th>
                <th>Intención</th><th>URL</th><th>Posición</th><th>Fuente</th><th></th>
              </tr>
            </thead>
            <tbody data-lista-keywords-rows="${l.id}"></tbody>
          </table>
        </div>
      </div>`;
  }

  /** class="table__empty" opts this row out of global.js's pagination row-counting — see the matching comment in _lista-row.blade.php. */
  function buildListaSubrowElement(l, wasOpen) {
    const tr = document.createElement("tr");
    tr.className = "table__empty";
    tr.setAttribute("data-lista-subrow", String(l.id));
    tr.hidden = !wasOpen;

    const td = document.createElement("td");
    td.colSpan = 11;
    td.style.padding = "0";
    td.style.background = "var(--color-secondary)";
    const keywords = l.keywords || [];
    td.innerHTML = listaSubrowShellHtml(l, keywords.length > 0);
    tr.appendChild(td);

    const tbody = td.querySelector(`[data-lista-keywords-rows="${l.id}"]`);
    keywords.forEach((k) => tbody.appendChild(buildKeywordRowElement(k)));

    return tr;
  }

  // ---------- Shared lookups ----------

  function listasTbody() {
    return document.querySelector("[data-listas-table] tbody");
  }

  function keywordTargetTbody(listaId) {
    if (!listaId) return document.querySelector("[data-sinlista-rows]");
    return document.querySelector(`[data-lista-keywords-rows="${listaId}"]`);
  }

  function updateKeywordsCountSubtitle() {
    const subtitle = document.getElementById("keywordsCountSubtitle");
    if (!subtitle) return;
    const listaCount = document.querySelectorAll("[data-lista-row]").length;
    const keywordCount = document.querySelectorAll("[data-keyword-row]").length;
    subtitle.textContent = `${keywordCount} keywords en ${listaCount} listas`;
  }

  function toggleSinListaEmptyState() {
    const empty = document.querySelector("[data-sinlista-empty]");
    const wrap = document.querySelector("[data-sinlista-table]");
    const rows = document.querySelector("[data-sinlista-rows]");
    if (!empty || !wrap || !rows) return;
    const hasRows = rows.children.length > 0;
    empty.hidden = hasRows;
    wrap.hidden = !hasRows;
  }

  /** Re-inserts the "no listas yet" placeholder row if the last lista was just deleted (the @forelse/@empty row only exists in the DOM when the page first loaded with zero listas). */
  function ensureListasEmptyState() {
    const tbody = listasTbody();
    if (!tbody) return;
    if (tbody.querySelector("[data-lista-row]")) return;
    if (tbody.querySelector(".table__empty:not([data-lista-subrow])")) return;
    const tr = document.createElement("tr");
    tr.className = "table__empty";
    const td = document.createElement("td");
    td.colSpan = 11;
    td.textContent = 'Aún no hay listas de keywords. Crea la primera con "Nueva Lista".';
    tr.appendChild(td);
    tbody.appendChild(tr);
  }

  function upsertSelectOption(select, id, label, clienteId) {
    if (!select) return;
    let opt = Array.from(select.options).find((o) => o.value === String(id));
    if (!opt) {
      opt = document.createElement("option");
      opt.value = String(id);
      select.appendChild(opt);
    }
    opt.textContent = label;
    if (clienteId != null) opt.dataset.cliente = String(clienteId);
  }

  /** Keeps the keywordFormModal's and keywordImportModal's lista_id <select>s (server-rendered once at page load) in sync with AJAX-created/renamed listas. */
  function syncListaOptionsEverywhere(l) {
    const label = `${l.nombre} (${l.cliente})`;
    upsertSelectOption(document.getElementById("ki_lista_id"), l.id, label);
    upsertSelectOption(document.getElementById("kw_lista_id"), l.id, label, l.cliente_id);
  }

  function removeListaOption(id) {
    document.getElementById("ki_lista_id")?.querySelector(`option[value="${id}"]`)?.remove();
    document.getElementById("kw_lista_id")?.querySelector(`option[value="${id}"]`)?.remove();
  }

  /**
   * Recomputes a lista row's displayed aggregates from its sub-table's own
   * rows (server never returns a fresh lista after a keyword mutation).
   * Sums treat a missing dataset value as 0 and always divide by the full
   * row count — matching KeywordLista::toRow()'s Collection::sum() behavior
   * exactly (nulls count as 0, not excluded from the average).
   */
  function recomputeListaAggregates(listaId) {
    const listaRow = document.querySelector(`[data-lista-row][data-lista-id="${listaId}"]`);
    if (!listaRow) return;

    const rows = Array.from(document.querySelectorAll(`[data-lista-keywords-rows="${listaId}"] [data-keyword-row]`));
    const count = rows.length;
    const sumAttr = (attr) => rows.reduce((acc, r) => acc + (parseFloat(r.dataset[attr]) || 0), 0);
    // Averages only over rows that actually have a value for the field — mirrors
    // KeywordLista::promedio() server-side, so a keyword missing e.g. CPC doesn't
    // silently count as $0 and drag the average down.
    const promedio = (attr, decimals) => {
      const valores = rows.map((r) => r.dataset[attr]).filter((v) => v !== undefined && v !== "").map(parseFloat);
      if (!valores.length) return null;
      const factor = Math.pow(10, decimals);
      return Math.round((valores.reduce((a, v) => a + v, 0) / valores.length) * factor) / factor;
    };

    const volumenTotal = sumAttr("volumen");
    const kdProm = promedio("dificultad", 0);
    const cpcProm = promedio("cpc", 2);
    const posProm = promedio("posicion", 1);
    const fuentes = Array.from(new Set(rows.map((r) => r.dataset.herramienta).filter(Boolean)));

    const l = JSON.parse(listaRow.dataset.lista);
    l.keywords_count = count;
    l.volumen_total = volumenTotal;
    l.kd_promedio = kdProm;
    l.cpc_promedio = cpcProm;
    l.posicion_promedio = posProm;
    l.fuentes = fuentes;
    l.keywords = rows.map((r) => JSON.parse(r.dataset.keyword));
    listaRow.dataset.lista = JSON.stringify(l);
    listaRow.dataset.search = (l.nombre + " " + l.keywords.map((k) => k.keyword).join(" ")).toLowerCase();

    const cells = listaRow.querySelectorAll(":scope > td");
    cells[4].textContent = count;
    cells[5].textContent = fmtNum(volumenTotal);
    cells[6].textContent = kdProm ?? "—";
    cells[7].textContent = cpcProm != null ? fmtMoney(cpcProm) : "—";
    cells[8].textContent = posProm != null ? "#" + posProm : "—";
    const fuentesSpan = cells[9].querySelector("span");
    if (fuentesSpan) fuentesSpan.textContent = fuentes.join(", ") || "—";

    const emptyDiv = document.querySelector(`[data-lista-keywords-empty="${listaId}"]`);
    const tableWrap = document.querySelector(`[data-lista-keywords-table="${listaId}"]`);
    if (emptyDiv) emptyDiv.hidden = count > 0;
    if (tableWrap) tableWrap.hidden = count === 0;

    // Keep the detail modal's data (and its visible fields, if it's still
    // open) in sync — but never re-open it if the user already closed it
    // (e.g. the "Añadir palabra clave" flow closes it before the keyword
    // form modal opens).
    if (currentDetailLista && String(currentDetailLista.id) === String(listaId)) {
      currentDetailLista = l;
      const modalEl = document.getElementById("listaDetailModal");
      if (modalEl && !modalEl.hidden) renderListaDetailFields(l);
    }
  }

  /** Inserts/replaces a lista's row + sub-row pair (create -> inserted at top; update -> replaced in place, preserving its current expand state). */
  function upsertListaRow(l) {
    const tbody = listasTbody();
    if (!tbody) return;

    const existingRow = tbody.querySelector(`[data-lista-row][data-lista-id="${l.id}"]`);
    const existingSubrow = tbody.querySelector(`[data-lista-subrow="${l.id}"]`);
    const wasOpen = existingSubrow ? !existingSubrow.hidden : false;

    const newRow = buildListaRowElement(l);
    const newSubrow = buildListaSubrowElement(l, wasOpen);

    if (existingRow) {
      existingRow.replaceWith(newRow);
      if (existingSubrow) existingSubrow.replaceWith(newSubrow);
      else newRow.insertAdjacentElement("afterend", newSubrow);
    } else {
      tbody.querySelector(".table__empty:not([data-lista-subrow])")?.remove();
      tbody.insertBefore(newSubrow, tbody.firstChild);
      tbody.insertBefore(newRow, newSubrow);
    }

    syncListaOptionsEverywhere(l);
    updateKeywordsCountSubtitle();
    applyListaFilters();
  }

  // ---------- Filters ----------

  function applyListaFilters() {
    const search = (document.getElementById("listasSearch")?.value || "").trim().toLowerCase();
    const cliente = document.getElementById("listasClienteFilter")?.value || "all";
    const estado = document.getElementById("listasEstadoFilter")?.value || "all";

    document.querySelectorAll("[data-lista-row]").forEach((row) => {
      const matchesSearch = !search || (row.dataset.search || "").includes(search);
      const matchesCliente = cliente === "all" || row.dataset.cliente === cliente;
      const matchesEstado = estado === "all" || row.dataset.estado === estado;
      const show = matchesSearch && matchesCliente && matchesEstado;
      row.style.display = show ? "" : "none";

      if (!show) {
        const subrow = document.querySelector(`[data-lista-subrow="${row.dataset.listaId}"]`);
        if (subrow && !subrow.hidden) {
          subrow.hidden = true;
          row.querySelector(".lista-toggle__chevron")?.classList.remove("is-open");
        }
      }
    });
  }

  function initFilters() {
    const search = document.getElementById("listasSearch");
    const cliente = document.getElementById("listasClienteFilter");
    const estado = document.getElementById("listasEstadoFilter");
    if (!search && !cliente && !estado) return;

    const debouncedApply = window.AgencyOS.debounce(applyListaFilters, 200);
    search?.addEventListener("input", debouncedApply);
    cliente?.addEventListener("change", applyListaFilters);
    estado?.addEventListener("change", applyListaFilters);
  }

  // ---------- Expand / collapse ----------

  function initExpandCollapse() {
    document.addEventListener("click", (e) => {
      const toggle = e.target.closest("[data-toggle-lista]");
      if (!toggle) return;
      const row = toggle.closest("[data-lista-row]");
      const subrow = row && document.querySelector(`[data-lista-subrow="${row.dataset.listaId}"]`);
      if (!subrow) return;
      subrow.hidden = !subrow.hidden;
      toggle.querySelector(".lista-toggle__chevron")?.classList.toggle("is-open", !subrow.hidden);
    });
  }

  // ---------- Lista detail modal ----------

  /** Pure DOM update of the detail modal's fields — does not touch currentDetailLista or open/close the modal, so it's safe to call for a background refresh while the modal may or may not be visible. */
  function renderListaDetailFields(l) {
    document.getElementById("listaDetailTitle").textContent = l.nombre;
    document.getElementById("listaDetailBadge").innerHTML = badgeHtml(l.estado);
    document.getElementById("listaDetailCount").textContent = l.keywords_count;
    document.getElementById("listaDetailVolumen").textContent = fmtNum(l.volumen_total);
    document.getElementById("listaDetailKd").textContent = l.kd_promedio ?? "—";
    document.getElementById("listaDetailCpc").textContent = l.cpc_promedio != null ? fmtMoney(l.cpc_promedio) : "—";
    document.getElementById("listaDetailCliente").textContent = l.cliente || "—";
    document.getElementById("listaDetailCanal").textContent = l.canal || "—";
    document.getElementById("listaDetailResponsable").textContent = l.responsable_nombre || "— Sin asignar —";
    document.getElementById("listaDetailPosicion").textContent = l.posicion_promedio != null ? "#" + l.posicion_promedio : "—";
    document.getElementById("listaDetailFuentes").textContent = (l.fuentes || []).join(", ") || "—";
    document.getElementById("listaDetailUpdated").textContent = l.updated_at || "—";

    const irCliente = document.getElementById("listaDetailIrCliente");
    if (irCliente) irCliente.href = irCliente.dataset.hrefTemplate.replace("__ID__", l.cliente_id);
  }

  function openListaDetailModal(l) {
    currentDetailLista = l;
    renderListaDetailFields(l);
    window.AgencyOS.openModal("listaDetailModal");
  }

  function initDetailModal() {
    document.addEventListener("click", (e) => {
      const row = e.target.closest("[data-lista-row]");
      if (!row) return;
      if (e.target.closest("[data-toggle-lista], a, button, form, input")) return; // let toggle/action controls behave normally
      openListaDetailModal(JSON.parse(row.dataset.lista));
    });

    document.getElementById("listaDetailEditar")?.addEventListener("click", () => {
      window.AgencyOS.closeModal("listaDetailModal");
      openListaFormModal(currentDetailLista);
    });

    document.getElementById("listaDetailImportar")?.addEventListener("click", () => {
      window.AgencyOS.closeModal("listaDetailModal");
      openImportModal(currentDetailLista?.id);
    });

    document.getElementById("listaDetailAddKeyword")?.addEventListener("click", () => {
      window.AgencyOS.closeModal("listaDetailModal");
      openKeywordFormModal(null, currentDetailLista?.id, currentDetailLista?.cliente_id);
    });
  }

  // ---------- Lista create/edit form modal ----------

  function openListaFormModal(l) {
    const form = document.getElementById("listaForm");
    if (!form) return;

    clearFieldErrors(form);
    fillForm(form, l);
    form.dataset.editingId = l?.id ?? "";

    document.getElementById("listaFormModalTitle").textContent = l ? "Editar Lista de Keywords" : "Nueva Lista de Keywords";
    document.getElementById("listaFormSubmit").innerHTML = l
      ? '<i class="fa-solid fa-check"></i> Guardar Cambios'
      : '<i class="fa-solid fa-check"></i> Crear Lista';

    window.AgencyOS.openModal("listaFormModal");
  }

  function initOpenListaModalButtons() {
    document.querySelectorAll("[data-open-lista-modal]").forEach((btn) => {
      btn.addEventListener("click", () => openListaFormModal(null));
    });
  }

  function initEditListaButtons() {
    document.addEventListener("click", (e) => {
      const btn = e.target.closest("[data-edit-lista]");
      if (!btn) return;
      const row = btn.closest("[data-lista-row]");
      if (!row) return;
      openListaFormModal(JSON.parse(row.dataset.lista));
    });
  }

  function initListaForm() {
    const form = document.getElementById("listaForm");
    if (!form) return;

    form.addEventListener("submit", (e) => {
      e.preventDefault();
      const editingId = form.dataset.editingId;
      const url = editingId ? form.dataset.updateActionTemplate.replace("__ID__", editingId) : form.dataset.storeAction;
      const method = editingId ? "PUT" : "POST";
      const payload = Object.fromEntries(new FormData(form).entries());

      request(url, method, payload)
        .then((l) => {
          upsertListaRow(l);
          window.AgencyOS.closeModal("listaFormModal");
          toast(editingId ? "Lista actualizada." : "Lista creada.", "success");
        })
        .catch((err) => {
          if (err.message === "validation_failed") {
            renderFieldErrors(form, err.data.errors || {});
            toast("Revisa los campos marcados.", "error");
          } else {
            toast("No se pudo guardar la lista.", "error");
          }
        });
    });
  }

  // ---------- Lista delete ----------

  function initListaDelete() {
    const confirmBtn = document.getElementById("listaDeleteConfirm");
    if (!confirmBtn) return;

    document.addEventListener("click", (e) => {
      const btn = e.target.closest("[data-delete-lista]");
      if (!btn) return;
      const row = btn.closest("[data-lista-row]");
      if (!row) return;
      const l = JSON.parse(row.dataset.lista);

      document.getElementById("listaDeleteName").textContent = l.nombre;
      document.getElementById("listaDeleteCount").textContent = l.keywords_count;
      confirmBtn.dataset.listaId = l.id;

      window.AgencyOS.openModal("listaDeleteModal");
    });

    confirmBtn.addEventListener("click", () => {
      const id = confirmBtn.dataset.listaId;
      if (!id) return;
      const url = confirmBtn.dataset.destroyActionTemplate.replace("__ID__", id);

      request(url, "DELETE")
        .then(() => {
          // The list itself is deleted, but its keywords are only detached
          // (lista_id -> null) server-side — move their existing rows into
          // the "Sin lista asignada" table instead of discarding them.
          const subrow = document.querySelector(`[data-lista-subrow="${id}"]`);
          const orphanRows = subrow ? Array.from(subrow.querySelectorAll("[data-keyword-row]")) : [];
          const sinListaBody = document.querySelector("[data-sinlista-rows]");
          orphanRows.forEach((row) => {
            row.dataset.listaId = "";
            const k = JSON.parse(row.dataset.keyword);
            k.lista_id = null;
            k.lista_nombre = null;
            row.dataset.keyword = JSON.stringify(k);
            sinListaBody?.appendChild(row);
          });

          document.querySelector(`[data-lista-row][data-lista-id="${id}"]`)?.remove();
          subrow?.remove();
          removeListaOption(id);
          toggleSinListaEmptyState();
          ensureListasEmptyState();
          updateKeywordsCountSubtitle();

          window.AgencyOS.closeModal("listaDeleteModal");
          toast("Lista eliminada. Sus keywords quedaron sin lista asignada.", "success");
        })
        .catch(() => toast("No se pudo eliminar la lista.", "error"));
    });
  }

  // ---------- Bulk selection ----------

  function updateBulkToolbar() {
    const toolbar = document.getElementById("listasBulkToolbar");
    const countEl = document.getElementById("listasBulkCount");
    if (!toolbar || !countEl) return;
    const checked = document.querySelectorAll("[data-lista-checkbox]:checked");
    toolbar.hidden = checked.length === 0;
    countEl.textContent = `${checked.length} lista${checked.length === 1 ? "" : "s"} seleccionada${checked.length === 1 ? "" : "s"}`;
  }

  function initBulkSelection() {
    const toolbar = document.getElementById("listasBulkToolbar");
    const checkAll = document.getElementById("listasCheckAll");
    const cancelBtn = document.getElementById("listasBulkCancelar");
    const descartarBtn = document.getElementById("listasBulkDescartar");

    document.addEventListener("change", (e) => {
      if (e.target.matches("[data-lista-checkbox]")) updateBulkToolbar();
    });

    checkAll?.addEventListener("change", () => {
      document.querySelectorAll("[data-lista-checkbox]").forEach((cb) => {
        const row = cb.closest("[data-lista-row]");
        if (row && row.style.display === "none") return; // don't select filtered-out rows
        cb.checked = checkAll.checked;
      });
      updateBulkToolbar();
    });

    cancelBtn?.addEventListener("click", () => {
      document.querySelectorAll("[data-lista-checkbox]").forEach((cb) => {
        cb.checked = false;
      });
      if (checkAll) checkAll.checked = false;
      updateBulkToolbar();
    });

    descartarBtn?.addEventListener("click", () => {
      const ids = Array.from(document.querySelectorAll("[data-lista-checkbox]:checked")).map((cb) => cb.value);
      if (!ids.length || !toolbar) return;
      if (!window.confirm(`¿Descartar ${ids.length} lista(s) seleccionada(s)? Su estado pasará a "Descartada".`)) return;

      request(toolbar.dataset.bulkDescartarAction, "POST", { ids })
        .then((data) => {
          (data.listas || []).forEach((l) => {
            const row = document.querySelector(`[data-lista-row][data-lista-id="${l.id}"]`);
            if (!row) return;
            row.dataset.estado = l.estado;
            row.dataset.lista = JSON.stringify(l);
            const badgeCell = row.querySelectorAll(":scope > td")[3];
            if (badgeCell) badgeCell.innerHTML = badgeHtml(l.estado);
          });

          document.querySelectorAll("[data-lista-checkbox]").forEach((cb) => {
            cb.checked = false;
          });
          if (checkAll) checkAll.checked = false;
          updateBulkToolbar();
          toast(`${ids.length} lista(s) descartada(s).`, "success");
        })
        .catch(() => toast("No se pudo descartar las listas seleccionadas.", "error"));
    });
  }

  // ---------- Keyword create/edit form modal ----------

  /** Filters #kw_lista_id's options to the chosen client, resetting the selection if it no longer belongs to that client — mirrors seo.js's initServicioCascade. */
  function applyListaCascadeToKeywordForm(clienteId) {
    const listaSelect = document.getElementById("kw_lista_id");
    if (!listaSelect) return;
    const options = Array.from(listaSelect.options).filter((o) => o.dataset.cliente);
    options.forEach((opt) => {
      opt.hidden = opt.dataset.cliente !== String(clienteId);
    });
    if (listaSelect.value && listaSelect.selectedOptions[0]?.hidden) listaSelect.value = "";
  }

  function initKeywordFormCascade() {
    document.getElementById("kw_cliente_id")?.addEventListener("change", (e) => applyListaCascadeToKeywordForm(e.target.value));
  }

  function openKeywordFormModal(k, presetListaId, presetClienteId) {
    const form = document.getElementById("keywordForm");
    if (!form) return;

    clearFieldErrors(form);
    fillForm(form, k);
    form.dataset.editingId = k?.id ?? "";
    form.dataset.editingListaId = k?.lista_id != null ? String(k.lista_id) : "";

    if (!k && presetClienteId) form.querySelector("#kw_cliente_id").value = String(presetClienteId);
    applyListaCascadeToKeywordForm(form.querySelector("#kw_cliente_id").value);
    if (!k && presetListaId) form.querySelector("#kw_lista_id").value = String(presetListaId);

    document.getElementById("keywordFormModalTitle").textContent = k ? "Editar Keyword" : "Añadir Keyword";
    document.getElementById("keywordFormSubmit").innerHTML = k
      ? '<i class="fa-solid fa-check"></i> Guardar Cambios'
      : '<i class="fa-solid fa-check"></i> Guardar Keyword';

    window.AgencyOS.openModal("keywordFormModal");
  }

  function initOpenKeywordFormButtons() {
    document.addEventListener("click", (e) => {
      const editBtn = e.target.closest("[data-edit-keyword]");
      if (editBtn) {
        const row = editBtn.closest("[data-keyword-row]");
        if (row) openKeywordFormModal(JSON.parse(row.dataset.keyword));
        return;
      }
      const addBtn = e.target.closest("[data-add-keyword-to-lista]");
      if (addBtn) {
        openKeywordFormModal(null, addBtn.dataset.addKeywordToLista, addBtn.dataset.addKeywordCliente);
      }
    });
  }

  /** Places a saved keyword's row in the correct tbody (its lista's sub-table, or "Sin lista asignada") and recomputes aggregates for whichever lista(s) were affected. */
  function placeKeywordRow(k, previousListaId) {
    const newListaId = k.lista_id != null ? String(k.lista_id) : "";
    previousListaId = previousListaId || "";

    document.querySelector(`[data-keyword-row][data-keyword-id="${k.id}"]`)?.remove();

    const tbody = keywordTargetTbody(newListaId);
    tbody?.appendChild(buildKeywordRowElement(k));

    toggleSinListaEmptyState();
    updateKeywordsCountSubtitle();

    if (newListaId) recomputeListaAggregates(newListaId);
    if (previousListaId && previousListaId !== newListaId) recomputeListaAggregates(previousListaId);
  }

  function initKeywordForm() {
    const form = document.getElementById("keywordForm");
    if (!form) return;

    form.addEventListener("submit", (e) => {
      e.preventDefault();
      const editingId = form.dataset.editingId;
      const previousListaId = form.dataset.editingListaId || "";
      const url = editingId ? form.dataset.updateActionTemplate.replace("__ID__", editingId) : form.dataset.storeAction;
      const method = editingId ? "PUT" : "POST";
      const payload = Object.fromEntries(new FormData(form).entries());

      request(url, method, payload)
        .then((k) => {
          placeKeywordRow(k, previousListaId);
          window.AgencyOS.closeModal("keywordFormModal");
          toast(editingId ? "Keyword actualizada." : "Keyword agregada.", "success");
        })
        .catch((err) => {
          if (err.message === "validation_failed") {
            renderFieldErrors(form, err.data.errors || {});
            toast("Revisa los campos marcados.", "error");
          } else {
            toast("No se pudo guardar la keyword.", "error");
          }
        });
    });
  }

  // ---------- Keyword delete ----------

  function initKeywordDelete() {
    const confirmBtn = document.getElementById("keywordDeleteConfirm");
    if (!confirmBtn) return;

    document.addEventListener("click", (e) => {
      const btn = e.target.closest("[data-delete-keyword]");
      if (!btn) return;
      const row = btn.closest("[data-keyword-row]");
      if (!row) return;
      const k = JSON.parse(row.dataset.keyword);

      document.getElementById("keywordDeleteName").textContent = k.keyword;
      confirmBtn.dataset.keywordId = k.id;
      confirmBtn.dataset.listaId = row.dataset.listaId || "";

      window.AgencyOS.openModal("keywordDeleteModal");
    });

    confirmBtn.addEventListener("click", () => {
      const id = confirmBtn.dataset.keywordId;
      if (!id) return;
      const listaId = confirmBtn.dataset.listaId || "";
      const url = confirmBtn.dataset.destroyActionTemplate.replace("__ID__", id);

      request(url, "DELETE")
        .then(() => {
          document.querySelector(`[data-keyword-row][data-keyword-id="${id}"]`)?.remove();
          toggleSinListaEmptyState();
          updateKeywordsCountSubtitle();
          if (listaId) recomputeListaAggregates(listaId);
          window.AgencyOS.closeModal("keywordDeleteModal");
          toast("Keyword eliminada.", "success");
        })
        .catch(() => toast("No se pudo eliminar la keyword.", "error"));
    });
  }

  // ---------- Import ----------

  function importResultsHtml(data) {
    // Two distinct 422 shapes: a standard Laravel validate() failure on the
    // texto/archivo fields themselves (has `errors`, handled via
    // renderFieldErrors instead — see the submit handler), vs. this
    // module's own "no rows to import" / "every row malformed" responses
    // (never have `creadas`, may carry just a `message`).
    if (data.creadas === undefined) {
      return `<div class="alert-banner" style="--alert-color:#EF4444; margin-top:var(--space-4);"><i class="fa-solid fa-triangle-exclamation"></i><p>${escapeHtml(data.message || "No se encontraron filas para importar.")}</p></div>`;
    }
    const creadasHtml = `<p style="font-size:var(--text-sm); margin-top:var(--space-4);"><strong style="color:var(--text-success)">${data.creadas}</strong> keyword(s) creada(s).</p>`;
    if (!data.errores || !data.errores.length) return creadasHtml;
    const erroresHtml = data.errores
      .map(
        (err) =>
          `<li style="margin-bottom:4px;"><strong>Fila ${err.fila}:</strong> ${escapeHtml(err.texto)} — <span style="color:var(--text-danger)">${(err.errores || []).map(escapeHtml).join("; ")}</span></li>`
      )
      .join("");
    return `${creadasHtml}<div style="font-size:var(--text-xs); color:var(--color-muted-foreground); margin-bottom:4px;">${data.errores.length} fila(s) con errores:</div><ul style="padding-left:18px; font-size:var(--text-xs);">${erroresHtml}</ul>`;
  }

  function applyImportedLista(l) {
    upsertListaRow(l);
    const subrow = document.querySelector(`[data-lista-subrow="${l.id}"]`);
    if (subrow) subrow.hidden = false;
    document.querySelector(`[data-lista-row][data-lista-id="${l.id}"] .lista-toggle__chevron`)?.classList.add("is-open");
  }

  function openImportModal(listaId) {
    const select = document.getElementById("ki_lista_id");
    const form = document.getElementById("keywordImportForm");
    if (!select || !form) return;

    document.getElementById("keywordImportResults").innerHTML = "";
    form.reset();

    if (listaId) {
      select.disabled = true;
      select.value = String(listaId);
    } else {
      select.disabled = false;
      select.value = "";
    }

    window.AgencyOS.openModal("keywordImportModal");
  }

  function initOpenImportModalButtons() {
    document.addEventListener("click", (e) => {
      const btn = e.target.closest("[data-open-import-modal]");
      if (!btn) return;
      openImportModal(btn.dataset.openImportModal || null);
    });
  }

  function initImportForm() {
    const form = document.getElementById("keywordImportForm");
    const listaSelect = document.getElementById("ki_lista_id");
    const resultsPanel = document.getElementById("keywordImportResults");
    if (!form || !listaSelect || !resultsPanel) return;

    form.addEventListener("submit", (e) => {
      e.preventDefault();
      const listaId = listaSelect.value;
      if (!listaId) {
        toast("Selecciona una lista destino.", "error");
        return;
      }

      const url = form.dataset.importActionTemplate.replace("__ID__", listaId);
      const fileInput = document.getElementById("ki_archivo");
      const textoInput = document.getElementById("ki_texto");
      const hasFile = fileInput.files && fileInput.files.length > 0;
      const submitBtn = document.getElementById("keywordImportSubmit");
      submitBtn.disabled = true;
      clearFieldErrors(form);

      const fetchPromise = hasFile
        ? (() => {
            const fd = new FormData();
            fd.append("archivo", fileInput.files[0]);
            if (textoInput.value.trim()) fd.append("texto", textoInput.value);
            return fetch(url, { method: "POST", headers: { Accept: "application/json", "X-CSRF-TOKEN": csrfToken }, body: fd });
          })()
        : fetch(url, { method: "POST", headers: jsonHeaders(), body: JSON.stringify({ texto: textoInput.value }) });

      fetchPromise
        .then((res) => res.json().then((data) => ({ status: res.status, data })))
        .then(({ status, data }) => {
          submitBtn.disabled = false;

          // Standard Laravel validate() failure on texto/archivo themselves
          // (e.g. wrong file mime) — field errors, no creadas/errores/lista.
          if (status === 422 && data.errors) {
            renderFieldErrors(form, data.errors);
            toast(data.message || "Revisa los campos marcados.", "error");
            return;
          }

          resultsPanel.innerHTML = importResultsHtml(data);
          if (data.lista) applyImportedLista(data.lista);

          if (status === 422) {
            toast(data.message || "No se pudo importar ninguna fila — revisa los errores.", "error");
            return;
          }

          form.querySelector("#ki_texto").value = "";
          form.querySelector("#ki_archivo").value = "";
          toast(`${data.creadas} keyword(s) importada(s).`, "success");
        })
        .catch(() => {
          submitBtn.disabled = false;
          toast("No se pudo procesar la importación.", "error");
        });
    });
  }

  document.addEventListener("shell:ready", () => {
    initFilters();
    initExpandCollapse();
    initDetailModal();
    initOpenListaModalButtons();
    initEditListaButtons();
    initListaForm();
    initListaDelete();
    initBulkSelection();
    initKeywordFormCascade();
    initOpenKeywordFormButtons();
    initKeywordForm();
    initKeywordDelete();
    initOpenImportModalButtons();
    initImportForm();
  });
})();
