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
 *
 * El histórico de posiciones (sección "Histórico de posiciones" más abajo) es
 * la excepción a ese patrón: su unidad es la RONDA (una lista + una fecha, con
 * todas sus keywords), la matriz se pide bajo demanda al servidor en vez de
 * derivarse de las filas, y el POST sí devuelve la lista entera ya recalculada.
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
    return `<span class="badge ${cls}">${escapeHtml(label)}</span>`;
  }

  const NULO_HTML = '<span class="kwx-nulo">—</span>';

  /** Mismos umbrales que admin.keywords._kd.blade.php (<40 bajo, 40-69 medio, >=70 alto). */
  function kdNivel(v) {
    return v >= 70 ? "alto" : v >= 40 ? "medio" : "bajo";
  }

  /** KD como barrita + número; un valor ausente es un guion, nunca "null" ni "NaN". */
  function kdHtml(valor) {
    if (valor == null || valor === "" || isNaN(Number(valor))) return NULO_HTML;
    const v = Math.max(0, Math.min(100, Math.round(Number(valor))));
    return `<span class="kwx-kd" title="Dificultad ${v} de 100"><span class="kwx-kd__barra" aria-hidden="true"><span class="kwx-kd__relleno kwx-kd__relleno--${kdNivel(v)}" style="width:${v}%"></span></span><span class="kwx-kd__num u-mono">${v}</span></span>`;
  }

  function fuentesTexto(fuentes) {
    return (fuentes || []).map((f) => HERRAMIENTA_LABELS[f] || f).join(", ") || "—";
  }

  /** "SEO + Google Ads · Valeria Mora · act. 2024-12-12": canal, responsable y fecha, saltando lo que falte. */
  function listaSubtitulo(l) {
    return [l.canal, l.responsable_nombre, l.updated_at ? "act. " + l.updated_at : null].filter(Boolean).join(" · ");
  }

  /** textContent de un nodo por id; vacío o null se muestra como guion para no pintar "null". */
  function setText(id, texto) {
    const el = document.getElementById(id);
    if (el) el.textContent = texto == null || texto === "" ? "—" : String(texto);
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

  /** "#3 (+2)": la posición en negrita y el cambio frente a la medición anterior en gris; sin posición, un guion. */
  function keywordPosicionHtml(k) {
    const actual = k.posicion_actual;
    const anterior = k.posicion_anterior;
    if (!actual) return NULO_HTML;
    const delta = anterior != null ? anterior - actual : null;
    const deltaLabel =
      delta != null ? ` <span class="kwx-pos__delta" title="Cambio frente a la medición anterior">(${delta > 0 ? "+" : ""}${delta})</span>` : "";
    return `<span class="kwx-pos"><strong>#${actual}</strong>${deltaLabel}</span>`;
  }

  /** Matches admin.keywords._keyword-row.blade.php's <td> markup exactly. */
  function keywordRowCellsHtml(k) {
    return `
      <td><button type="button" class="kwx-kw-btn" data-open-keyword-detail title="Ver ficha de la keyword">${escapeHtml(k.keyword)}</button></td>
      <td><span class="kwx-tenue">${escapeHtml(TIPO_LABELS[k.tipo] || k.tipo)}</span></td>
      <td class="u-mono">${fmtNum(k.volumen_busqueda)}</td>
      <td>${kdHtml(k.dificultad)}</td>
      <td class="u-mono kwx-verde">${k.cpc_estimado > 0 ? fmtMoney(k.cpc_estimado) : "—"}</td>
      <td><span class="kwx-tenue">${INTENCION_LABELS[k.intencion] || "—"}</span></td>
      <td>${k.url_asignada ? `<span class="u-mono kwx-url" title="${escapeHtml(k.url_asignada)}">${escapeHtml(k.url_asignada)}</span>` : NULO_HTML}</td>
      <td class="u-mono">${keywordPosicionHtml(k)}</td>
      <td><span class="kwx-tenue">${HERRAMIENTA_LABELS[k.herramienta_origen] || "—"}</span></td>
      <td>
        <div class="kwx-acciones">
          <button type="button" class="kwx-icono" title="Editar" aria-label="Editar ${escapeHtml(k.keyword)}" data-edit-keyword="${k.id}"><i class="fa-solid fa-pen"></i></button>
          <button type="button" class="kwx-icono kwx-icono--peligro" title="Eliminar" aria-label="Eliminar ${escapeHtml(k.keyword)}" data-delete-keyword="${k.id}"><i class="fa-solid fa-trash"></i></button>
        </div>
      </td>`;
  }

  /** Builds a full keyword <tr> element — dataset assigned via properties (not string-embedded JSON), safe regardless of quote characters in the data. */
  function buildKeywordRowElement(k) {
    const tr = document.createElement("tr");
    tr.className = "is-clickable";
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

  /** Matches admin.keywords._lista-row.blade.php's lista <td> markup exactly (12 columnas). */
  function listaRowCellsHtml(l, n) {
    const sub = listaSubtitulo(l);
    const nombre = escapeHtml(l.nombre);
    return `
      <td><input type="checkbox" class="kwx-check" data-lista-checkbox value="${l.id}" aria-label="Seleccionar ${nombre}"></td>
      <td class="u-mono kwx-num" data-col="num">${n ?? ""}</td>
      <td data-col="nombre">
        <div class="kwx-nombre">
          <button type="button" class="kwx-chevron" data-toggle-lista aria-expanded="false" aria-controls="listaSub${l.id}"
            aria-label="Mostrar u ocultar las keywords de ${nombre}" title="Expandir / colapsar">
            <i class="fa-solid fa-chevron-right lista-toggle__chevron" aria-hidden="true"></i>
          </button>
          <div class="kwx-nombre__texto">
            <span class="kwx-nombre__titulo">${nombre}</span>
            ${sub ? `<span class="kwx-nombre__sub">${escapeHtml(sub)}</span>` : ""}
          </div>
        </div>
      </td>
      <td data-col="cliente">${escapeHtml(l.cliente)}</td>
      <td data-col="estado">${badgeHtml(l.estado)}</td>
      <td class="u-mono" data-col="count">${l.keywords_count}</td>
      <td class="u-mono" data-col="volumen">${fmtNum(l.volumen_total)}</td>
      <td data-col="kd">${kdHtml(l.kd_promedio)}</td>
      <td class="u-mono kwx-verde" data-col="cpc">${l.cpc_promedio != null ? fmtMoney(l.cpc_promedio) : "—"}</td>
      <td class="u-mono" data-col="pos">${l.posicion_promedio != null ? "#" + l.posicion_promedio : "—"}</td>
      <td data-col="fuente"><span class="kwx-tenue">${escapeHtml(fuentesTexto(l.fuentes))}</span></td>
      <td>
        <div class="kwx-acciones">
          <button type="button" class="kwx-icono" title="Ver detalle de la lista" aria-label="Ver detalle de ${nombre}" data-open-lista-detail><i class="fa-solid fa-arrow-up-right-from-square"></i></button>
          <button type="button" class="kwx-icono" title="Historial de posiciones" aria-label="Abrir el historial de ${nombre}" data-lista-historial="${l.id}"><i class="fa-solid fa-clock-rotate-left"></i></button>
          <div class="kwx-menu" data-kwx-menu>
            <button type="button" class="kwx-icono" title="Más acciones" aria-label="Más acciones de ${nombre}" aria-haspopup="menu" aria-expanded="false" data-kwx-menu-toggle>
              <i class="fa-solid fa-ellipsis-vertical"></i>
            </button>
            <div class="kwx-menu__lista" role="menu" hidden>
              <button type="button" role="menuitem" data-open-lista-detail><i class="fa-solid fa-circle-info"></i> Ver detalle</button>
              <button type="button" role="menuitem" data-edit-lista="${l.id}"><i class="fa-solid fa-pen"></i> Editar lista</button>
              <button type="button" role="menuitem" data-open-import-modal="${l.id}"><i class="fa-solid fa-file-import"></i> Importar keywords</button>
              <button type="button" role="menuitem" data-open-medicion-modal="${l.id}"><i class="fa-solid fa-crosshairs"></i> Nueva medición</button>
              <button type="button" role="menuitem" class="kwx-menu__peligro" data-delete-lista="${l.id}"><i class="fa-solid fa-trash"></i> Eliminar lista</button>
            </div>
          </div>
        </div>
      </td>`;
  }

  function buildListaRowElement(l, n) {
    const tr = document.createElement("tr");
    tr.className = "is-clickable";
    tr.setAttribute("data-lista-row", "");
    tr.dataset.listaId = String(l.id);
    tr.dataset.search = (l.nombre + " " + (l.keywords || []).map((k) => k.keyword).join(" ")).toLowerCase();
    tr.dataset.estado = l.estado;
    tr.dataset.cliente = String(l.cliente_id);
    tr.dataset.lista = JSON.stringify(l);
    tr.innerHTML = listaRowCellsHtml(l, n);
    return tr;
  }

  /** Sub-row chrome (header buttons + empty-state + table shell) — matches _lista-row.blade.php's subrow markup; keyword <tr>s are appended as real elements afterward (buildKeywordRowElement), not string-interpolated. */
  function listaSubrowShellHtml(l, hasKeywords) {
    return `
      <div class="kwx-sub__interior">
        <div class="kwx-sub__barra">
          <div class="kwx-etiqueta">Keywords de esta lista</div>
          <div class="kwx-sub__botones">
            <button type="button" class="kwx-btn kwx-btn--sm" data-toggle-historial="${l.id}" aria-expanded="false" aria-controls="listaHistorial${l.id}">
              <i class="fa-solid fa-clock-rotate-left"></i> Historial
            </button>
            <button type="button" class="kwx-btn kwx-btn--sm" data-open-import-modal="${l.id}">
              <i class="fa-solid fa-file-import"></i> Importar keywords
            </button>
            <button type="button" class="kwx-btn kwx-btn--sm" data-open-medicion-modal="${l.id}">
              <i class="fa-solid fa-crosshairs"></i> Nueva medición
            </button>
          </div>
        </div>
        <div class="kw-hist" id="listaHistorial${l.id}" data-historial-panel="${l.id}" hidden></div>
        <div class="kwx-vacio" data-lista-keywords-empty="${l.id}" ${hasKeywords ? "hidden" : ""}>
          <p>Esta lista aún no tiene keywords.</p>
        </div>
        <div class="kwx-subtabla" data-lista-keywords-table="${l.id}" ${hasKeywords ? "" : "hidden"}>
          <div class="kwx-subtabla__scroll">
            <table class="table">
              <thead>
                <tr>
                  <th>Palabra clave</th><th>Tipo</th><th>Volumen</th><th>KD</th><th>CPC est.</th>
                  <th>Intención</th><th>URL</th><th>Posición</th><th>Fuente</th><th><span class="u-visually-hidden">Acciones</span></th>
                </tr>
              </thead>
              <tbody data-lista-keywords-rows="${l.id}"></tbody>
            </table>
          </div>
        </div>
        <div class="kwx-sub__pie">
          <button type="button" class="kwx-enlace" data-add-keyword-to-lista="${l.id}" data-add-keyword-cliente="${l.cliente_id}">
            <i class="fa-solid fa-plus"></i> Añadir palabra clave a esta lista
          </button>
        </div>
      </div>`;
  }

  /** class="table__empty" opts this row out of global.js's pagination row-counting — see the matching comment in _lista-row.blade.php. */
  function buildListaSubrowElement(l, wasOpen) {
    const tr = document.createElement("tr");
    tr.className = "table__empty";
    tr.id = `listaSub${l.id}`;
    tr.setAttribute("data-lista-subrow", String(l.id));
    tr.hidden = !wasOpen;

    const td = document.createElement("td");
    td.colSpan = 12;
    td.className = "kwx-sub";
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
    subtitle.textContent = `${listaCount} ${listaCount === 1 ? "lista" : "listas"} de palabras clave · ${keywordCount} ${keywordCount === 1 ? "keyword" : "keywords"} en total`;
    renumerarListas();
  }

  /** El "#" sigue el orden del DOM (no el de lo visible): así no se reinicia al filtrar ni al cambiar de página. */
  function renumerarListas() {
    document.querySelectorAll("[data-lista-row]").forEach((row, i) => {
      const celda = row.querySelector('[data-col="num"]');
      if (celda) celda.textContent = String(i + 1);
    });
  }

  /** Abre o cierra el sub-row de una lista y mantiene sincronizados el chevron y su aria-expanded. */
  function setListaAbierta(listaId, abierta) {
    const subrow = document.querySelector(`[data-lista-subrow="${listaId}"]`);
    const row = document.querySelector(`[data-lista-row][data-lista-id="${listaId}"]`);
    if (!subrow) return;
    subrow.hidden = !abierta;
    const toggle = row?.querySelector("[data-toggle-lista]");
    toggle?.setAttribute("aria-expanded", abierta ? "true" : "false");
    toggle?.querySelector(".lista-toggle__chevron")?.classList.toggle("is-open", abierta);
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
    td.colSpan = 12;
    td.textContent = 'Aún no hay listas de keywords. Crea la primera con "Nueva lista".';
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

    const celda = (col) => listaRow.querySelector(`:scope > [data-col="${col}"]`);
    celda("count").textContent = count;
    celda("volumen").textContent = fmtNum(volumenTotal);
    celda("kd").innerHTML = kdHtml(kdProm);
    celda("cpc").textContent = cpcProm != null ? fmtMoney(cpcProm) : "—";
    celda("pos").textContent = posProm != null ? "#" + posProm : "—";
    const fuentesSpan = celda("fuente").querySelector("span");
    if (fuentesSpan) fuentesSpan.textContent = fuentesTexto(fuentes);

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

    const newRow = buildListaRowElement(l, existingRow?.querySelector('[data-col="num"]')?.textContent);
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

    // La fila nueva nace cerrada: si la lista estaba abierta se vuelve a marcar (chevron y aria-expanded).
    if (wasOpen) setListaAbierta(l.id, true);

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
        if (subrow && !subrow.hidden) setListaAbierta(row.dataset.listaId, false);
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
      setListaAbierta(row.dataset.listaId, subrow.hidden);
    });
  }

  // ---------- Lista detail modal ----------

  /** Pure DOM update of the detail modal's fields — does not touch currentDetailLista or open/close the modal, so it's safe to call for a background refresh while the modal may or may not be visible. */
  function renderListaDetailFields(l) {
    document.getElementById("listaDetailTitle").textContent = l.nombre;
    document.getElementById("listaDetailBadge").innerHTML = badgeHtml(l.estado);
    document.getElementById("listaDetailCount").textContent = l.keywords_count;
    document.getElementById("listaDetailVolumen").textContent = fmtNum(l.volumen_total);
    document.getElementById("listaDetailKd").innerHTML = kdHtml(l.kd_promedio);
    document.getElementById("listaDetailCpc").textContent = l.cpc_promedio != null ? fmtMoney(l.cpc_promedio) : "—";
    document.getElementById("listaDetailCliente").textContent = l.cliente || "—";
    document.getElementById("listaDetailCanal").textContent = l.canal || "—";
    document.getElementById("listaDetailResponsable").textContent = l.responsable_nombre || "— Sin asignar —";
    document.getElementById("listaDetailPosicion").textContent = l.posicion_promedio != null ? "#" + l.posicion_promedio : "—";
    document.getElementById("listaDetailFuentes").textContent = fuentesTexto(l.fuentes);
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
      // Los controles propios de la fila (chevron, ⋮, íconos) se comportan normal; sólo "ver detalle" abre la ficha desde un botón.
      if (e.target.closest("[data-open-lista-detail]")) {
        openListaDetailModal(JSON.parse(row.dataset.lista));
        return;
      }
      if (e.target.closest("[data-toggle-lista], a, button, form, input")) return;
      openListaDetailModal(JSON.parse(row.dataset.lista));
    });

    // Ícono "historial" de la fila: abre la lista y su matriz de posiciones de una vez.
    document.addEventListener("click", (e) => {
      const btn = e.target.closest("[data-lista-historial]");
      if (!btn) return;
      const id = btn.dataset.listaHistorial;
      setListaAbierta(id, true);
      setHistorialOpen(id, true);
      document.querySelector(`[data-lista-subrow="${id}"]`)?.scrollIntoView({ block: "nearest", behavior: "smooth" });
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

    document.getElementById("listaFormModalTitle").textContent = l ? "Editar lista de keywords" : "Nueva lista de keywords";
    setText("listaFormModalSub", l ? l.nombre : "Agrupa las keywords de un cliente por canal.");
    document.getElementById("listaFormSubmit").textContent = l ? "Guardar cambios" : "Crear lista";

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
            const badgeCell = row.querySelector(':scope > [data-col="estado"]');
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
    document.getElementById("kw_cliente_id")?.addEventListener("change", (e) => {
      applyListaCascadeToKeywordForm(e.target.value);
      sincronizarClienteDeLista();
    });
    document.getElementById("kw_lista_id")?.addEventListener("change", sincronizarClienteDeLista);
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

    document.getElementById("keywordFormModalTitle").textContent = k ? "Editar palabra clave" : "Añadir palabra clave";
    document.getElementById("keywordFormSubmit").textContent = k ? "Guardar cambios" : "Guardar keyword";
    document.querySelector("[data-kw-mas]")?.removeAttribute("open");
    sincronizarClienteDeLista();

    window.AgencyOS.openModal("keywordFormModal");
  }

  /**
   * Con lista, el cliente es el de la lista (se copia al campo oculto); sin lista el
   * campo "Cliente" aparece, porque una keyword suelta tiene que saber de quién es.
   * El subtítulo del modal acompaña a la lista elegida.
   */
  function sincronizarClienteDeLista() {
    const form = document.getElementById("keywordForm");
    if (!form) return;
    const listaSelect = form.querySelector("#kw_lista_id");
    const clienteSelect = form.querySelector("#kw_cliente_id");
    const campoCliente = form.querySelector("[data-kw-cliente-field]");
    const opt = listaSelect.selectedOptions[0];
    const conLista = !!listaSelect.value && !!opt;

    if (conLista && opt.dataset.cliente) clienteSelect.value = opt.dataset.cliente;
    if (campoCliente) campoCliente.hidden = conLista;

    const sub = document.getElementById("keywordFormModalSub");
    if (sub) {
      if (conLista) sub.textContent = listaFromRow(listaSelect.value)?.nombre || opt.textContent.trim();
      else if (clienteSelect.value) sub.textContent = `Sin lista · ${clienteSelect.selectedOptions[0].textContent.trim()}`;
      else sub.textContent = "Sin lista asignada";
    }
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
            // Un error en estado/fecha/notas no se vería con "Más datos" cerrado.
            const mas = form.querySelector("[data-kw-mas]");
            if (mas && Array.from(mas.querySelectorAll("[data-error-for]")).some((sp) => sp.textContent)) mas.open = true;
            // El campo Cliente sólo aparece sin lista; si el error es suyo, se vuelve a mostrar.
            if (err.data.errors && err.data.errors.cliente_id) form.querySelector("[data-kw-cliente-field]")?.removeAttribute("hidden");
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
      return `<div class="kwx-aviso kwx-aviso--error"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i><p>${escapeHtml(data.message || "No se encontraron filas para importar.")}</p></div>`;
    }
    const creadasHtml = `<p class="kwx-import__ok"><strong class="kwx-verde">${data.creadas}</strong> keyword(s) creada(s).</p>`;
    if (!data.errores || !data.errores.length) return creadasHtml;
    const erroresHtml = data.errores
      .map(
        (err) =>
          `<li><strong>Fila ${err.fila}:</strong> ${escapeHtml(err.texto)} — <span class="kwx-rojo">${(err.errores || []).map(escapeHtml).join("; ")}</span></li>`
      )
      .join("");
    return `${creadasHtml}<div class="kwx-import__cuenta">${data.errores.length} fila(s) con errores:</div><ul class="kwx-import__errores">${erroresHtml}</ul>`;
  }

  function applyImportedLista(l) {
    upsertListaRow(l);
    setListaAbierta(l.id, true);
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

  // ---------- Histórico de posiciones ----------
  //
  // La unidad de captura es la RONDA (una lista + una fecha), no la keyword
  // suelta: el equipo mide toda la lista de una sentada una vez al mes y anota,
  // keyword a keyword, qué se hizo para moverla. Esa nota es el motivo de la
  // pantalla, no un campo secundario.
  //
  // La matriz se pide bajo demanda (initHistorial) y se cachea por lista en
  // historialCache; openHistorialIds recuerda qué paneles estaban abiertos para
  // poder repintarlos después de que upsertListaRow() reconstruya el sub-row.

  const historialCache = new Map();
  const openHistorialIds = new Set();

  function medicionTemplates() {
    const table = document.querySelector("[data-listas-table]");
    return {
      index: table?.dataset.medicionesIndexTemplate || "",
      store: table?.dataset.medicionesStoreTemplate || "",
      update: table?.dataset.medicionUpdateTemplate || "",
    };
  }

  /** GET helper — request() always serializes a body, which a GET can't carry. */
  function getJson(url) {
    return fetch(url, { method: "GET", headers: { Accept: "application/json", "X-CSRF-TOKEN": csrfToken } }).then((res) => {
      if (!res.ok) {
        const err = new Error("request_failed");
        err.status = res.status;
        throw err;
      }
      return res.json();
    });
  }

  /** dd/mm/yyyy a partir de un "YYYY-MM-DD" plano — sin new Date(), que desplazaría la fecha por zona horaria. */
  function fmtFecha(iso) {
    const parts = String(iso || "").split("-");
    if (parts.length !== 3) return String(iso || "");
    return `${parts[2]}/${parts[1]}/${parts[0]}`;
  }

  function todayIso() {
    const d = new Date();
    const pad = (n) => String(n).padStart(2, "0");
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
  }

  function listaFromRow(listaId) {
    const row = document.querySelector(`[data-lista-row][data-lista-id="${listaId}"]`);
    return row ? JSON.parse(row.dataset.lista) : null;
  }

  /**
   * Variación respecto de la ronda anterior con el mismo criterio que el resto
   * del módulo: mejorar es SUBIR en el ranking, o sea un número más bajo, así
   * que delta = anterior - actual y un delta positivo se pinta en verde.
   */
  function deltaHtml(delta) {
    if (delta == null || delta === 0) return "";
    const color = delta > 0 ? "var(--text-success)" : "var(--text-danger)";
    const icon = delta > 0 ? "fa-arrow-up" : "fa-arrow-down";
    return ` <span class="kw-hist__delta" style="color:${color};"><i class="fa-solid ${icon}"></i>${Math.abs(delta)}</span>`;
  }

  // <<pura>>
  // ---------- Funciones puras: pegado, comparativo y gráfica ----------
  //
  // Sin DOM ni estado a propósito: así se pueden probar con Node copiando este
  // bloque (no hay framework de tests de JS en el proyecto).

  /** Minúsculas, sin acentos y con espacios colapsados: la llave para emparejar lo pegado con la lista. */
  function normalizarKeyword(valor) {
    return String(valor == null ? "" : valor)
      .normalize("NFD")
      .replace(/[̀-ͯ]/g, "")
      .toLowerCase()
      .replace(/\s+/g, " ")
      .trim();
  }

  /** Separador por mayoría en las primeras líneas: tab (Excel) gana siempre; si no, ; o , (CSV según la región). */
  function detectarSeparador(texto) {
    const s = String(texto || "");
    if (s.indexOf("\t") !== -1) return "\t";
    const lineas = s.split(/\r?\n/).filter((l) => l.trim() !== "").slice(0, 10);
    let puntoComa = 0;
    let coma = 0;
    lineas.forEach((l) => {
      const sinComillas = l.replace(/"[^"]*"/g, "");
      puntoComa += (sinComillas.match(/;/g) || []).length;
      coma += (sinComillas.match(/,/g) || []).length;
    });
    if (puntoComa > 0 && puntoComa >= coma) return ";";
    return ",";
  }

  /** Parser CSV/TSV con comillas (y comillas dobles escapadas); cada fila conserva su número de línea de origen para reportar errores. */
  function parsearTabla(texto, sep) {
    const filas = [];
    const s = String(texto || "").replace(/^﻿/, "");
    let celdas = [];
    let celda = "";
    let enComillas = false;
    let linea = 1;
    let lineaInicio = 1;

    const cerrarFila = () => {
      celdas.push(celda);
      celda = "";
      if (celdas.some((c) => c.trim() !== "")) filas.push({ linea: lineaInicio, celdas });
      celdas = [];
    };

    for (let i = 0; i < s.length; i++) {
      const c = s[i];
      if (enComillas) {
        if (c === '"') {
          if (s[i + 1] === '"') {
            celda += '"';
            i++;
          } else {
            enComillas = false;
          }
        } else {
          if (c === "\n") linea++;
          celda += c;
        }
        continue;
      }
      if (c === '"' && celda.trim() === "") {
        enComillas = true;
        celda = "";
      } else if (c === sep) {
        celdas.push(celda);
        celda = "";
      } else if (c === "\r") {
        continue;
      } else if (c === "\n") {
        cerrarFila();
        linea++;
        lineaInicio = linea;
      } else {
        celda += c;
      }
    }
    cerrarFila();
    return { filas, comillaSinCerrar: enComillas };
  }

  const MARCAS_SIN_POSICION = ["n/d", "nd", "n/a", "na", "-", "--", "—", "–", "norankea", "norankeada", "sindato", "s/d"];

  /** "n/d", "-", "" y "no rankea" son posición null (sin dato); lo que no se entiende se reporta, nunca se adivina. */
  function parsearPosicion(crudo) {
    const t = String(crudo == null ? "" : crudo)
      .replace(/[\s ]/g, "")
      .toLowerCase()
      .replace(/^#/, "");
    if (t === "" || MARCAS_SIN_POSICION.indexOf(t) !== -1) return { ok: true, valor: null };
    if (/^\d+([.,]\d+)?$/.test(t)) {
      // Search Console da promedios con decimales (12.4): el banco guarda enteros.
      const n = Math.round(parseFloat(t.replace(",", ".")));
      if (n < 1 || n > 1000) return { ok: false, motivo: `posición fuera de 1-1000: "${String(crudo).trim()}"` };
      return { ok: true, valor: n };
    }
    return { ok: false, motivo: `posición no reconocida: "${String(crudo).trim()}"` };
  }

  const ENCABEZADOS = {
    keyword: ["keyword", "keywords", "palabra clave", "palabras clave", "consulta", "consultas", "consultas principales", "query", "queries", "top queries", "termino"],
    posicion: ["posicion", "posicion media", "posicion promedio", "position", "average position", "avg position", "pos", "rank", "ranking"],
    url: ["url", "pagina", "pagina principal", "page", "landing", "link"],
  };

  /** Reconoce una fila de encabezado por nombres conocidos (Excel propio o Search Console) y devuelve el índice de cada columna. */
  function detectarEncabezado(celdas) {
    const norm = celdas.map(normalizarKeyword);
    const idx = { keyword: -1, posicion: -1, url: -1 };
    let reconocido = false;
    Object.keys(ENCABEZADOS).forEach((clave) => {
      const i = norm.findIndex((c) => ENCABEZADOS[clave].indexOf(c) !== -1);
      if (i !== -1) {
        idx[clave] = i;
        reconocido = true;
      }
    });
    return reconocido ? idx : null;
  }

  /**
   * Lee el texto pegado y lo empareja con las keywords de la lista por nombre
   * normalizado. Nada se descarta en silencio: lo que no coincide, lo repetido
   * y lo malformado vuelven listado fila por fila.
   */
  function procesarPegado(texto, keywords) {
    const res = { coincidencias: [], sinCoincidencia: [], errores: [], repetidas: [], noMencionadas: [], encabezado: false, error: null };
    if (!String(texto || "").trim()) {
      res.error = "No hay nada que procesar: pega primero las filas.";
      return res;
    }

    const { filas, comillaSinCerrar } = parsearTabla(texto, detectarSeparador(texto));
    if (comillaSinCerrar) res.errores.push({ linea: 0, motivo: "hay unas comillas sin cerrar; las filas finales pueden haberse leído mal" });
    if (!filas.length) {
      res.error = "No hay nada que procesar: pega primero las filas.";
      return res;
    }

    let cols = { keyword: 0, posicion: 1, url: 2 };
    let datos = filas;
    const encabezado = detectarEncabezado(filas[0].celdas);
    if (encabezado) {
      res.encabezado = true;
      datos = filas.slice(1);
      if (encabezado.keyword === -1 || encabezado.posicion === -1) {
        res.error = "El encabezado no trae columna de keyword y de posición.";
        return res;
      }
      cols = encabezado;
    }

    const porNorm = new Map();
    (keywords || []).forEach((k) => {
      const n = normalizarKeyword(k.keyword);
      if (!porNorm.has(n)) porNorm.set(n, []);
      porNorm.get(n).push(k);
    });

    const aplicadas = new Map();
    datos.forEach((fila) => {
      const kw = String(fila.celdas[cols.keyword] == null ? "" : fila.celdas[cols.keyword]).trim();
      if (!kw) {
        res.errores.push({ linea: fila.linea, motivo: "keyword vacía" });
        return;
      }
      if (fila.celdas.length <= cols.posicion) {
        res.errores.push({ linea: fila.linea, keyword: kw, motivo: "falta la columna de posición" });
        return;
      }
      const pos = parsearPosicion(fila.celdas[cols.posicion]);
      if (!pos.ok) {
        res.errores.push({ linea: fila.linea, keyword: kw, motivo: pos.motivo });
        return;
      }
      const norm = normalizarKeyword(kw);
      if (!porNorm.has(norm)) {
        res.sinCoincidencia.push({ linea: fila.linea, keyword: kw, posicion: pos.valor });
        return;
      }
      if (aplicadas.has(norm)) res.repetidas.push({ linea: aplicadas.get(norm).linea, keyword: kw });
      const url = cols.url !== -1 && fila.celdas[cols.url] != null ? String(fila.celdas[cols.url]).trim() : "";
      aplicadas.set(norm, { linea: fila.linea, posicion: pos.valor, url: url || null });
    });

    aplicadas.forEach((v, norm) => {
      porNorm.get(norm).forEach((k) => {
        res.coincidencias.push({ keyword_id: k.id, keyword: k.keyword, posicion: v.posicion, url: v.url, linea: v.linea });
      });
    });
    porNorm.forEach((lista, norm) => {
      if (!aplicadas.has(norm)) lista.forEach((k) => res.noMencionadas.push(k.keyword));
    });
    return res;
  }

  /** Última ronda estrictamente anterior a `fecha` (los ISO YYYY-MM-DD se comparan bien como texto). */
  function rondaAnterior(fechas, fecha) {
    const previas = (fechas || []).filter((f) => f < fecha).sort();
    return previas.length ? previas[previas.length - 1] : null;
  }

  /**
   * Qué copiar de la ronda anterior a las filas del modal. `filas` trae lo que
   * hay hoy en cada caja; una con posición ya tecleada o pegada se conserva y
   * solo se cuenta, porque la precarga no debe pisar trabajo del usuario.
   */
  function planCopiaRonda(data, fecha, filas) {
    const origen = rondaAnterior((data && data.fechas) || [], fecha);
    if (!origen) return { origen: null, aplicar: [], copiadas: 0, conservadas: 0 };
    const porId = new Map();
    ((data && data.keywords) || []).forEach((row) => {
      const m = (row.mediciones || {})[origen];
      if (m) porId.set(String(row.keyword_id), m);
    });
    const res = { origen, aplicar: [], copiadas: 0, conservadas: 0 };
    (filas || []).forEach((f) => {
      const m = porId.get(String(f.keyword_id));
      if (!m) return;
      if (String(f.posicion == null ? "" : f.posicion).trim() !== "") {
        res.conservadas++;
        return;
      }
      res.aplicar.push({ keyword_id: f.keyword_id, posicion: m.posicion != null ? String(m.posicion) : "", url: m.url || "" });
      res.copiadas++;
    });
    return res;
  }

  function diasEntre(isoA, isoB) {
    const parse = (s) => {
      const p = String(s).split("-").map(Number);
      return Date.UTC(p[0], p[1] - 1, p[2]);
    };
    return Math.round((parse(isoB) - parse(isoA)) / 86400000);
  }

  /**
   * Ronda cuya fecha cae más cerca de `objetivo` días antes de `ultima`. Sin
   * tolerancia, una ronda de hace 5 días pasaría por "hace un mes"; con ±15 los
   * rangos de 1 y 2 meses (15-45 y 45-75) no se solapan, y fuera de ellos es
   * "sin dato" en vez de un número engañoso.
   */
  function rondaCercana(fechas, ultima, objetivo, excluir) {
    let mejor = null;
    (fechas || []).forEach((f) => {
      if (f >= ultima || f === excluir) return;
      const dias = diasEntre(f, ultima);
      const dist = Math.abs(dias - objetivo);
      if (dist <= 15 && (!mejor || dist < mejor.dist)) mejor = { fecha: f, dias, dist };
    });
    return mejor ? { fecha: mejor.fecha, dias: mejor.dias } : null;
  }

  function posicionDe(row, fecha) {
    const m = (row.mediciones || {})[fecha];
    return m && m.posicion != null ? m.posicion : null;
  }

  /** Promedio y conteos de una ronda; una keyword sin posición cuenta como "sin dato", nunca como 0. */
  function resumenRonda(data, fecha) {
    const keywords = data.keywords || [];
    const vals = [];
    keywords.forEach((row) => {
      const p = posicionDe(row, fecha);
      if (p != null) vals.push(p);
    });
    const suma = vals.reduce((a, b) => a + b, 0);
    return {
      fecha,
      total: keywords.length,
      medidas: vals.length,
      sinDato: keywords.length - vals.length,
      promedio: vals.length ? Math.round((suma / vals.length) * 10) / 10 : null,
      top3: vals.filter((p) => p <= 3).length,
      top10: vals.filter((p) => p <= 10).length,
      top100: vals.filter((p) => p <= 100).length,
    };
  }

  /** Ahora (última ronda) contra ~1 y ~2 meses antes. mejoraron = ahora tiene número más bajo que en el corte. */
  function comparativo(data) {
    const fechas = ((data && data.fechas) || []).slice().sort();
    if (!fechas.length) return null;
    const ultima = fechas[fechas.length - 1];
    const ahora = resumenRonda(data, ultima);

    const contra = (ronda) => {
      if (!ronda) return null;
      const r = resumenRonda(data, ronda.fecha);
      const out = { ...r, dias: ronda.dias, mejoraron: 0, empeoraron: 0, igual: 0, sinDatoCmp: 0, variacion: null };
      (data.keywords || []).forEach((row) => {
        const a = posicionDe(row, ultima);
        const b = posicionDe(row, ronda.fecha);
        if (a == null || b == null) out.sinDatoCmp++;
        else if (a < b) out.mejoraron++;
        else if (a > b) out.empeoraron++;
        else out.igual++;
      });
      // Positivo = la lista subió (promedio más bajo), igual que el delta de las celdas.
      if (ahora.promedio != null && r.promedio != null) out.variacion = Math.round((r.promedio - ahora.promedio) * 10) / 10;
      return out;
    };

    const r1 = rondaCercana(fechas, ultima, 30);
    const r2 = rondaCercana(fechas, ultima, 60, r1 ? r1.fecha : null);
    return { ahora, mes1: contra(r1), mes2: contra(r2) };
  }

  /** Serie por ronda: el promedio de la lista (keywordId null) o la posición de una keyword; valor null = sin dato. */
  function serieEvolucion(data, keywordId) {
    const fechas = ((data && data.fechas) || []).slice().sort();
    return fechas.map((fecha) => {
      if (keywordId == null || keywordId === "") return { fecha, valor: resumenRonda(data, fecha).promedio };
      const row = (data.keywords || []).find((r) => String(r.keyword_id) === String(keywordId));
      return { fecha, valor: row ? posicionDe(row, fecha) : null };
    });
  }

  /** SVG inline con el eje invertido (posición 1 arriba). Devuelve "" si no hay ningún valor que graficar. */
  function svgEvolucion(serie, titulo) {
    const valores = serie.filter((p) => p.valor != null).map((p) => p.valor);
    if (!valores.length) return "";

    const W = 640;
    const H = 240;
    const m = { t: 16, r: 20, b: 34, l: 48 };
    const iw = W - m.l - m.r;
    const ih = H - m.t - m.b;

    let min = Math.min(...valores);
    let max = Math.max(...valores);
    if (min === max) {
      min = Math.max(1, min - 2);
      max = max + 2;
    } else {
      const pad = (max - min) * 0.1;
      min = Math.max(1, min - pad);
      max = max + pad;
    }
    const x = (i) => (serie.length === 1 ? m.l + iw / 2 : m.l + (iw * i) / (serie.length - 1));
    const y = (v) => m.t + (ih * (v - min)) / (max - min);

    const ticks = [];
    for (let i = 0; i <= 3; i++) {
      const v = Math.round(min + ((max - min) * i) / 3);
      if (ticks.indexOf(v) === -1) ticks.push(v);
    }
    const grid = ticks
      .map(
        (v) =>
          `<line x1="${m.l}" x2="${W - m.r}" y1="${y(v).toFixed(1)}" y2="${y(v).toFixed(1)}" stroke="currentColor" stroke-opacity="0.15"/>` +
          `<text x="${m.l - 8}" y="${(y(v) + 4).toFixed(1)}" text-anchor="end" font-size="11" fill="currentColor">#${v}</text>`
      )
      .join("");

    // Un hueco (valor null) corta la línea: unir los puntos de al lado inventaría una tendencia.
    let path = "";
    let pendiente = false;
    serie.forEach((p, i) => {
      if (p.valor == null) {
        pendiente = false;
        return;
      }
      path += `${pendiente ? "L" : "M"}${x(i).toFixed(1)} ${y(p.valor).toFixed(1)} `;
      pendiente = true;
    });

    const salto = Math.ceil(serie.length / 8);
    // La primera y la última etiqueta se alinean hacia adentro: centradas chocan con las etiquetas "#N" del eje (izquierda) o se cortan en el borde (derecha).
    const etiquetas = serie
      .map((p, i) => {
        // Con muchas rondas la penúltima etiqueta con salto quedaría pegada a la última y se encimarían.
        if (!((i % salto === 0 && serie.length - 1 - i >= salto) || i === serie.length - 1)) return "";
        const ancla = serie.length === 1 ? "middle" : i === 0 ? "start" : i === serie.length - 1 ? "end" : "middle";
        const xe = i === 0 && serie.length > 1 ? m.l : i === serie.length - 1 && serie.length > 1 ? W - m.r : x(i);
        return `<text x="${xe.toFixed(1)}" y="${H - 10}" text-anchor="${ancla}" font-size="11" fill="currentColor">${escapeHtml(fmtFecha(p.fecha))}</text>`;
      })
      .join("");

    const puntos = serie
      .map((p, i) =>
        p.valor == null
          ? ""
          : `<circle cx="${x(i).toFixed(1)}" cy="${y(p.valor).toFixed(1)}" r="4" fill="currentColor"><title>${escapeHtml(fmtFecha(p.fecha))}: #${p.valor}</title></circle>`
      )
      .join("");

    const sinDato = serie.length - valores.length;
    const descripcion = serie.map((p) => `${fmtFecha(p.fecha)}: ${p.valor != null ? "#" + p.valor : "sin dato"}`).join("; ");
    const etiquetaAria = `${titulo}. Eje invertido: la posición 1 está arriba. ${descripcion}.${sinDato ? ` ${sinDato} ronda(s) sin dato.` : ""}`;

    return (
      `<svg class="kw-evo__svg" viewBox="0 0 ${W} ${H}" role="img" aria-label="${escapeHtml(etiquetaAria)}" data-evolucion-svg>` +
      `<title>${escapeHtml(titulo)}</title>` +
      grid +
      `<path d="${path.trim()}" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>` +
      puntos +
      etiquetas +
      `</svg>`
    );
  }
  // <</pura>>

  function comparativoHtml(comp) {
    if (!comp) return "";
    const SIN = '<span class="kw-hist__nd">sin dato</span>';
    const cortes = [
      { clave: "ahora", titulo: "Ahora", r: comp.ahora },
      { clave: "mes1", titulo: "~1 mes antes", r: comp.mes1 },
      { clave: "mes2", titulo: "~2 meses antes", r: comp.mes2 },
    ];

    const fila = (metrica, etiqueta, celda) =>
      `<tr data-comp-metrica="${metrica}"><th scope="row">${etiqueta}</th>${cortes
        .map((c) => `<td data-comp-corte="${c.clave}">${c.r ? celda(c.r, c.clave) : SIN}</td>`)
        .join("")}</tr>`;
    const deTotal = (n, r) => `${n} de ${r.total}`;

    const cabecera = cortes.map((c) => `<th scope="col" data-comp-corte="${c.clave}">${c.titulo}</th>`).join("");

    return `
      <section class="kw-comp" data-comparativo aria-label="Comparativo de la lista">
        <h4 class="kw-comp__titulo">Comparativo</h4>
        <div class="kw-hist__scroll">
          <table class="table kw-comp__table">
            <thead><tr><th scope="col"></th>${cabecera}</tr></thead>
            <tbody>
              ${fila("fecha", "Ronda", (r) => `${fmtFecha(r.fecha)}${r.dias ? ` <span class="u-mono">(hace ${r.dias} días)</span>` : ""}`)}
              ${fila("promedio", "Posición promedio", (r) => (r.promedio != null ? `<span class="u-mono">#${r.promedio}</span>` : SIN))}
              ${fila("variacion", "Variación del promedio hasta ahora", (r, c) =>
                c === "ahora" ? "—" : r.variacion == null ? SIN : r.variacion === 0 ? "igual" : deltaHtml(r.variacion)
              )}
              ${fila("top3", "Keywords en top 3", (r) => deTotal(r.top3, r))}
              ${fila("top10", "Keywords en top 10", (r) => deTotal(r.top10, r))}
              ${fila("top100", "Keywords en top 100", (r) => deTotal(r.top100, r))}
              ${fila("mejoraron", "Mejoraron hasta ahora", (r, c) => (c === "ahora" ? "—" : r.mejoraron))}
              ${fila("empeoraron", "Empeoraron hasta ahora", (r, c) => (c === "ahora" ? "—" : r.empeoraron))}
              ${fila("igual", "Igual hasta ahora", (r, c) => (c === "ahora" ? "—" : r.igual))}
              ${fila("sin-dato", "Sin dato para comparar", (r, c) => (c === "ahora" ? "—" : r.sinDatoCmp))}
            </tbody>
          </table>
        </div>
      </section>`;
  }

  function graficaHtml(data, keywordId) {
    const nombre =
      keywordId == null || keywordId === ""
        ? "Posición promedio de la lista"
        : `Posición de "${((data.keywords || []).find((r) => String(r.keyword_id) === String(keywordId)) || {}).keyword || ""}"`;
    const svg = svgEvolucion(serieEvolucion(data, keywordId), nombre);
    return svg || '<p class="kw-hist__loading" data-evolucion-vacia>Aún no hay posiciones registradas para graficar esta selección.</p>';
  }

  function evolucionHtml(listaId, data) {
    const opciones = (data.keywords || [])
      .map((row) => `<option value="${row.keyword_id}">${escapeHtml(row.keyword)}</option>`)
      .join("");
    return `
      <section class="kw-evo" data-historial-evolucion>
        <div class="kw-evo__head">
          <h4 class="kw-evo__titulo">Evolución</h4>
          <label class="kw-evo__selector">Ver:
            <select class="select" data-historial-serie="${listaId}" aria-label="Qué evolución graficar">
              <option value="">Promedio de la lista</option>
              ${opciones}
            </select>
          </label>
        </div>
        <div class="kw-evo__grafica" data-historial-grafica>${graficaHtml(data, null)}</div>
      </section>`;
  }

  function initEvolucion() {
    document.addEventListener("change", (e) => {
      const select = e.target.closest ? e.target.closest("[data-historial-serie]") : null;
      if (!select) return;
      const data = historialCache.get(String(select.dataset.historialSerie));
      const destino = select.closest("[data-historial-evolucion]")?.querySelector("[data-historial-grafica]");
      if (data && destino) destino.innerHTML = graficaHtml(data, select.value);
    });
  }

  function historialCellHtml(medicion, delta) {
    // Tres estados bien distintos: sin medición (esa keyword no se midió esa
    // ronda), medida sin posición (posicion === null: "no rankea / sin dato"),
    // y medida con posición.
    if (!medicion) {
      return `<td class="kw-hist__cell kw-hist__cell--vacia" data-historial-cell data-medicion-estado="sin-medicion" title="No se midió en esta ronda"></td>`;
    }

    const tieneNota = !!(medicion.nota && String(medicion.nota).trim());
    const posLabel = medicion.posicion != null ? `#${medicion.posicion}` : '<span class="kw-hist__nd">n/d</span>';
    const notaIndicador = tieneNota
      ? '<i class="fa-solid fa-note-sticky kw-hist__nota-dot" aria-hidden="true"></i><span class="u-visually-hidden">Con nota</span>'
      : "";
    const titulo = tieneNota ? String(medicion.nota) : "Sin nota — pulsa para escribir qué se hizo";

    return `
      <td class="kw-hist__cell ${medicion.posicion == null ? "kw-hist__cell--nd" : ""} ${tieneNota ? "kw-hist__cell--con-nota" : ""}"
          data-historial-cell data-medicion-estado="${medicion.posicion == null ? "sin-posicion" : "medida"}" data-medicion-id="${medicion.id}">
        <button type="button" class="kw-hist__btn" data-open-medicion-nota="${medicion.id}" title="${escapeHtml(titulo)}">
          <span class="kw-hist__pos u-mono">${posLabel}${deltaHtml(delta)}</span>
          ${notaIndicador}
        </button>
      </td>`;
  }

  function historialHtml(listaId, data) {
    const fechas = data.fechas || [];
    const keywords = data.keywords || [];

    if (!fechas.length) {
      return `
        <div class="kw-hist__empty">
          <p class="kw-hist__empty-text">Esta lista aún no tiene mediciones registradas.</p>
          <button type="button" class="btn btn--primary btn--sm" data-open-medicion-modal="${listaId}">
            <i class="fa-solid fa-crosshairs"></i> Registrar la primera medición
          </button>
        </div>`;
    }

    const headHtml = fechas.map((f) => `<th class="kw-hist__col-fecha" data-historial-fecha="${escapeHtml(f)}">${fmtFecha(f)}</th>`).join("");

    const bodyHtml = keywords
      .map((row) => {
        const mediciones = row.mediciones || {};
        let previa = null;
        const celdas = fechas
          .map((f) => {
            const m = mediciones[f] || null;
            let delta = null;
            if (m && m.posicion != null) {
              if (previa != null) delta = previa - m.posicion;
              previa = m.posicion;
            }
            return historialCellHtml(m, delta);
          })
          .join("");

        return `
          <tr data-historial-row data-keyword-id="${row.keyword_id}">
            <th scope="row" class="kw-hist__keyword">
              <span class="kw-hist__keyword-text">${escapeHtml(row.keyword)}</span>
              ${row.url_asignada ? `<span class="kw-hist__keyword-url u-mono">${escapeHtml(row.url_asignada)}</span>` : ""}
            </th>
            ${celdas}
          </tr>`;
      })
      .join("");

    // Fila de cierre: la posición media de la lista por ronda, que es la cifra
    // que enseña el avance de un vistazo.
    const promedios = data.promedios || {};
    let previaProm = null;
    const promHtml = fechas
      .map((f) => {
        const valor = promedios[f];
        let delta = null;
        if (valor != null) {
          if (previaProm != null) delta = Math.round((previaProm - valor) * 10) / 10;
          previaProm = valor;
        }
        const label = valor != null ? `#${valor}` : '<span class="kw-hist__nd">n/d</span>';
        return `<td class="kw-hist__cell kw-hist__cell--promedio" data-historial-promedio="${escapeHtml(f)}"><span class="kw-hist__pos u-mono">${label}${deltaHtml(delta)}</span></td>`;
      })
      .join("");

    return `
      ${comparativoHtml(comparativo(data))}
      ${evolucionHtml(listaId, data)}
      <div class="kw-hist__scroll" data-historial-scroll>
        <table class="table kw-hist__table" data-historial-table="${listaId}">
          <thead>
            <tr><th class="kw-hist__keyword kw-hist__keyword--head">Palabra clave</th>${headHtml}</tr>
          </thead>
          <tbody>${bodyHtml}</tbody>
          <tfoot>
            <tr data-historial-promedios>
              <th scope="row" class="kw-hist__keyword kw-hist__keyword--prom">Posición media de la lista</th>
              ${promHtml}
            </tr>
          </tfoot>
        </table>
      </div>
      <p class="kw-hist__leyenda">
        <span class="kw-hist__leyenda-item"><i class="fa-solid fa-note-sticky kw-hist__nota-dot"></i> tiene nota de qué se hizo — pulsa la celda para leerla o editarla</span>
        <span class="kw-hist__leyenda-item"><span class="kw-hist__leyenda-vacia"></span> no se midió esa ronda</span>
        <span class="kw-hist__leyenda-item"><span class="kw-hist__nd">n/d</span> medida, pero no rankea</span>
      </p>`;
  }

  function renderHistorial(listaId) {
    const panel = document.querySelector(`[data-historial-panel="${listaId}"]`);
    const data = historialCache.get(String(listaId));
    if (!panel || !data) return;
    panel.innerHTML = historialHtml(listaId, data);
  }

  function loadHistorial(listaId, force) {
    const key = String(listaId);
    const panel = document.querySelector(`[data-historial-panel="${key}"]`);
    if (!panel) return Promise.resolve();

    if (!force && historialCache.has(key)) {
      renderHistorial(key);
      return Promise.resolve();
    }

    panel.innerHTML = '<p class="kw-hist__loading"><i class="fa-solid fa-spinner fa-spin"></i> Cargando histórico…</p>';

    return getJson(medicionTemplates().index.replace("__ID__", key))
      .then((data) => {
        historialCache.set(key, data);
        renderHistorial(key);
      })
      .catch(() => {
        panel.innerHTML = '<p class="kw-hist__loading">No se pudo cargar el histórico.</p>';
        toast("No se pudo cargar el histórico de posiciones.", "error");
      });
  }

  function setHistorialOpen(listaId, open) {
    const key = String(listaId);
    const panel = document.querySelector(`[data-historial-panel="${key}"]`);
    const btn = document.querySelector(`[data-toggle-historial="${key}"]`);
    if (!panel) return;
    panel.hidden = !open;
    btn?.setAttribute("aria-expanded", open ? "true" : "false");
    if (open) {
      openHistorialIds.add(key);
      loadHistorial(key, false);
    } else {
      openHistorialIds.delete(key);
    }
  }

  function initHistorial() {
    document.addEventListener("click", (e) => {
      const btn = e.target.closest("[data-toggle-historial]");
      if (!btn) return;
      const id = btn.dataset.toggleHistorial;
      const panel = document.querySelector(`[data-historial-panel="${id}"]`);
      setHistorialOpen(id, panel ? panel.hidden : true);
    });
  }

  // ---------- Nueva medición (la ronda) ----------

  function medicionRowElement(k) {
    const tr = document.createElement("tr");
    tr.setAttribute("data-medicion-row", "");
    tr.dataset.keywordId = String(k.id);
    tr.innerHTML = `
      <td class="kw-medicion__kw-cell">
        <div class="kw-medicion__kw"></div>
        <div class="kw-medicion__url u-mono"></div>
      </td>
      <td>
        <input class="input kw-medicion__pos-input" type="number" min="1" max="1000" inputmode="numeric" placeholder="—"
               data-medicion-posicion aria-label="Posición">
      </td>
      <td>
        <textarea class="textarea kw-medicion__nota-input" rows="2" data-medicion-nota aria-label="Qué se hizo"
                  placeholder="Ej. Reescritura del title y del H1, tres enlaces internos desde el blog."></textarea>
        <input type="hidden" data-medicion-url>
      </td>`;
    tr.querySelector(".kw-medicion__kw").textContent = k.keyword;
    tr.querySelector(".kw-medicion__url").textContent = k.url_asignada || "— sin URL asignada —";
    tr.querySelector("[data-medicion-url]").value = k.url_asignada || "";
    return tr;
  }

  function renderMedicionRows(l) {
    const tbody = document.querySelector("[data-medicion-rows]");
    const table = document.querySelector("[data-medicion-table]");
    const empty = document.querySelector("[data-medicion-empty]");
    const submit = document.getElementById("medicionFormSubmit");
    if (!tbody) return;

    tbody.innerHTML = "";
    const keywords = (l && l.keywords) || [];
    keywords.forEach((k) => tbody.appendChild(medicionRowElement(k)));

    if (table) table.hidden = keywords.length === 0;
    if (empty) empty.hidden = keywords.length > 0;
    if (submit) submit.disabled = keywords.length === 0;
  }

  /**
   * Precarga los valores de la ronda de esa fecha si ya existe: reenviar la
   * misma fecha corrige la ronda en vez de duplicarla, así que corregir tiene
   * que ser tan natural como crear.
   */
  function prefillMedicionRonda(listaId, fecha) {
    const estado = document.querySelector("[data-medicion-estado]");
    const rows = Array.from(document.querySelectorAll("[data-medicion-row]"));
    const aplicar = (data) => {
      const existentes = new Map();
      (data.keywords || []).forEach((row) => {
        const m = (row.mediciones || {})[fecha];
        if (m) existentes.set(String(row.keyword_id), m);
      });

      rows.forEach((tr) => {
        const m = existentes.get(tr.dataset.keywordId);
        const pos = tr.querySelector("[data-medicion-posicion]");
        const nota = tr.querySelector("[data-medicion-nota]");
        const url = tr.querySelector("[data-medicion-url]");
        pos.value = m && m.posicion != null ? String(m.posicion) : "";
        nota.value = m && m.nota ? m.nota : "";
        if (m && m.url) url.value = m.url;
      });

      if (estado) {
        estado.textContent = existentes.size
          ? `Ya hay una ronda registrada el ${fmtFecha(fecha)} (${existentes.size} keyword(s)). Al guardar la corriges, no se duplica.`
          : "Ronda nueva: aún no hay mediciones con esta fecha.";
        estado.dataset.medicionEstadoTipo = existentes.size ? "correccion" : "nueva";
      }
    };

    const key = String(listaId);
    if (historialCache.has(key)) {
      aplicar(historialCache.get(key));
      return Promise.resolve();
    }
    return getJson(medicionTemplates().index.replace("__ID__", key))
      .then((data) => {
        historialCache.set(key, data);
        aplicar(data);
      })
      .catch(() => {
        if (estado) estado.textContent = "";
      });
  }

  function openMedicionModal(listaId) {
    const form = document.getElementById("medicionForm");
    const fechaInput = document.querySelector("[data-medicion-fecha]");
    const l = listaFromRow(listaId);
    if (!form || !fechaInput || !l) return;

    clearFieldErrors(form);
    form.dataset.listaId = String(listaId);
    document.getElementById("medicionFormTitle").textContent = `Nueva medición — ${l.nombre}`;
    fechaInput.value = todayIso();
    renderMedicionRows(l);
    prefillMedicionRonda(listaId, fechaInput.value);

    // Lo pegado y el resumen de la ronda anterior no deben sobrevivir a otra lista.
    const pegar = form.querySelector("[data-medicion-pegar-texto]");
    if (pegar) pegar.value = "";
    const panelPegar = form.querySelector("[data-medicion-pegar-panel]");
    if (panelPegar) panelPegar.hidden = true;
    setResumenCaptura("");

    window.AgencyOS.openModal("medicionFormModal");
  }

  function initOpenMedicionModalButtons() {
    document.addEventListener("click", (e) => {
      const btn = e.target.closest("[data-open-medicion-modal]");
      if (!btn) return;
      openMedicionModal(btn.dataset.openMedicionModal);
    });
  }

  function initMedicionFechaChange() {
    const fechaInput = document.querySelector("[data-medicion-fecha]");
    const form = document.getElementById("medicionForm");
    if (!fechaInput || !form) return;
    fechaInput.addEventListener("change", () => {
      if (!fechaInput.value || !form.dataset.listaId) return;
      prefillMedicionRonda(form.dataset.listaId, fechaInput.value);
    });
  }

  // ---------- Captura rápida de la ronda ----------

  /** Keywords de la lista abierta en el modal, leídas de sus filas (las mismas que se guardan). */
  function keywordsDelModal() {
    return Array.from(document.querySelectorAll("[data-medicion-row]")).map((tr) => ({
      id: Number(tr.dataset.keywordId),
      keyword: tr.querySelector(".kw-medicion__kw").textContent,
    }));
  }

  function filaDelModal(keywordId) {
    return document.querySelector(`[data-medicion-row][data-keyword-id="${keywordId}"]`);
  }

  function setResumenCaptura(html) {
    const el = document.querySelector("[data-medicion-captura-resumen]");
    if (el) el.innerHTML = html;
  }

  function listaHtml(titulo, items, atributo) {
    if (!items.length) return "";
    return `<div ${atributo}><strong>${titulo} (${items.length})</strong><ul>${items.map((i) => `<li>${i}</li>`).join("")}</ul></div>`;
  }

  function resumenPegadoHtml(res) {
    if (res.error) return `<p data-pegar-error>${escapeHtml(res.error)}</p>`;
    const coinciden = `${res.coincidencias.length} coincidencia(s), ${res.sinCoincidencia.length} sin coincidencia`;
    const conLinea = (linea, texto) => escapeHtml((linea ? `Línea ${linea}: ` : "") + texto);
    return (
      `<p data-pegar-conteo>${coinciden}${res.errores.length ? `, ${res.errores.length} fila(s) con error` : ""}.</p>` +
      listaHtml(
        "Sin coincidencia en esta lista",
        res.sinCoincidencia.map((f) => conLinea(f.linea, `"${f.keyword}"`)),
        "data-pegar-sin-coincidencia"
      ) +
      listaHtml(
        "Filas con error (no se aplicaron)",
        res.errores.map((f) => conLinea(f.linea, (f.keyword ? `"${f.keyword}" — ` : "") + f.motivo)),
        "data-pegar-errores"
      ) +
      listaHtml(
        "Repetidas en lo pegado (se usó la última)",
        res.repetidas.map((f) => conLinea(f.linea, `"${f.keyword}"`)),
        "data-pegar-repetidas"
      ) +
      listaHtml(
        "Keywords de la lista que no aparecen en lo pegado (quedan como están)",
        res.noMencionadas.map((k) => escapeHtml(k)),
        "data-pegar-no-mencionadas"
      )
    );
  }

  function aplicarPegado() {
    const area = document.querySelector("[data-medicion-pegar-texto]");
    if (!area) return;
    const res = procesarPegado(area.value, keywordsDelModal());
    res.coincidencias.forEach((c) => {
      const tr = filaDelModal(c.keyword_id);
      if (!tr) return;
      tr.querySelector("[data-medicion-posicion]").value = c.posicion != null ? String(c.posicion) : "";
      // Sin URL en lo pegado se conserva la asignada: borrarla sería perder dato por omisión.
      if (c.url) tr.querySelector("[data-medicion-url]").value = c.url;
    });
    setResumenCaptura(resumenPegadoHtml(res));
  }

  /** Precarga posición y URL (no la nota) con la última ronda anterior a la fecha elegida, sin pisar cajas ya llenas. */
  function copiarRondaAnterior(listaId, fecha) {
    const key = String(listaId);
    const form = document.getElementById("medicionForm");
    const fechaInput = document.querySelector("[data-medicion-fecha]");
    // La respuesta puede llegar con el modal cerrado o ya apuntando a otra lista/fecha; aplicarla ahí sería escribir en la ronda equivocada.
    const vigente = () =>
      !!form &&
      !!fechaInput &&
      !document.getElementById("medicionFormModal")?.hidden &&
      form.dataset.listaId === key &&
      fechaInput.value === fecha;

    const aplicar = (data) => {
      if (!vigente()) return;
      const filas = Array.from(document.querySelectorAll("[data-medicion-row]")).map((tr) => ({
        keyword_id: tr.dataset.keywordId,
        posicion: tr.querySelector("[data-medicion-posicion]").value,
      }));
      const plan = planCopiaRonda(data, fecha, filas);
      if (!plan.origen) {
        setResumenCaptura(`<p data-copiar-resultado>No hay una ronda anterior al ${escapeHtml(fmtFecha(fecha))}.</p>`);
        return;
      }
      plan.aplicar.forEach((c) => {
        const tr = filaDelModal(c.keyword_id);
        if (!tr) return;
        tr.querySelector("[data-medicion-posicion]").value = c.posicion;
        if (c.url) tr.querySelector("[data-medicion-url]").value = c.url;
      });
      const aviso = plan.conservadas
        ? ` ${plan.conservadas} renglón(es) ya tenían datos y se conservaron.`
        : "";
      setResumenCaptura(
        `<p data-copiar-resultado>Se copiaron ${plan.copiadas} keyword(s) de la ronda del ${escapeHtml(fmtFecha(plan.origen))}. La nota no se copia.${aviso}</p>`
      );
    };

    if (historialCache.has(key)) {
      aplicar(historialCache.get(key));
      return Promise.resolve();
    }
    return getJson(medicionTemplates().index.replace("__ID__", key))
      .then((data) => {
        historialCache.set(key, data);
        aplicar(data);
      })
      .catch(() => {
        if (vigente()) toast("No se pudo cargar la ronda anterior.", "error");
      });
  }

  function initCapturaRapida() {
    const form = document.getElementById("medicionForm");
    if (!form) return;

    form.querySelector("[data-medicion-pegar-toggle]")?.addEventListener("click", () => {
      const panel = form.querySelector("[data-medicion-pegar-panel]");
      if (!panel) return;
      panel.hidden = !panel.hidden;
      if (!panel.hidden) panel.querySelector("[data-medicion-pegar-texto]")?.focus();
    });
    form.querySelector("[data-medicion-pegar-aplicar]")?.addEventListener("click", aplicarPegado);
    form.querySelector("[data-medicion-copiar-anterior]")?.addEventListener("click", () => {
      const fecha = form.querySelector("[data-medicion-fecha]").value;
      if (!form.dataset.listaId || !fecha) return;
      copiarRondaAnterior(form.dataset.listaId, fecha);
    });

    // Enter en una posición baja al siguiente renglón en vez de enviar el formulario: la ronda se llena sin mouse.
    form.addEventListener("keydown", (e) => {
      if (e.key !== "Enter" || e.isComposing || !e.target.matches || !e.target.matches("[data-medicion-posicion]")) return;
      e.preventDefault();
      const siguiente = e.target.closest("[data-medicion-row]")?.nextElementSibling;
      const destino = siguiente
        ? siguiente.querySelector("[data-medicion-posicion]")
        : document.getElementById("medicionFormSubmit");
      if (destino) {
        destino.focus();
        if (destino.select) destino.select();
      }
    });
  }

  function collectMediciones() {
    return Array.from(document.querySelectorAll("[data-medicion-row]")).map((tr) => {
      const pos = tr.querySelector("[data-medicion-posicion]").value.trim();
      const nota = tr.querySelector("[data-medicion-nota]").value.trim();
      const url = tr.querySelector("[data-medicion-url]").value.trim();
      return {
        keyword_id: Number(tr.dataset.keywordId),
        posicion: pos === "" ? null : Number(pos),
        url: url === "" ? null : url,
        nota: nota === "" ? null : nota,
      };
    });
  }

  /** Vuelve a pintar la fila de la lista con el toRow() recién devuelto y repinta el histórico si estaba abierto (upsertListaRow reconstruye el sub-row entero). */
  function afterRondaGuardada(listaId, lista) {
    const key = String(listaId);
    const estabaAbierto = openHistorialIds.has(key);
    historialCache.delete(key);

    if (lista) {
      upsertListaRow(lista);
      setListaAbierta(lista.id, true);
    }

    if (estabaAbierto) setHistorialOpen(key, true);
  }

  function initMedicionForm() {
    const form = document.getElementById("medicionForm");
    if (!form) return;

    form.addEventListener("submit", (e) => {
      e.preventDefault();
      const listaId = form.dataset.listaId;
      const fecha = document.querySelector("[data-medicion-fecha]").value;
      const mediciones = collectMediciones();
      if (!listaId || !mediciones.length) return;

      const submit = document.getElementById("medicionFormSubmit");
      submit.disabled = true;
      clearFieldErrors(form);

      request(medicionTemplates().store.replace("__ID__", listaId), "POST", { fecha, mediciones })
        .then((data) => {
          submit.disabled = false;
          afterRondaGuardada(listaId, data.lista);
          window.AgencyOS.closeModal("medicionFormModal");
          toast(`Medición del ${fmtFecha(data.fecha)} guardada.`, "success");
        })
        .catch((err) => {
          submit.disabled = false;
          if (err.message === "validation_failed") {
            const errores = err.data.errors || {};
            renderFieldErrors(form, errores);
            // El mensaje crudo de Laravel no dice qué keyword falló: se marca el campo y se antepone el nombre.
            const porFila = marcarErroresRonda(form, errores);
            toast(porFila.length ? porFila.slice(0, 3).join(" · ") : err.data.message || "Revisa los campos marcados.", "error");
          } else {
            toast("No se pudo guardar la medición.", "error");
          }
        });
    });
  }

  /** Marca (aria-invalid) y enfoca el primer campo con error de la ronda; devuelve "keyword: mensaje" por cada error de fila. */
  function marcarErroresRonda(form, errores) {
    const filas = Array.from(form.querySelectorAll("[data-medicion-row]"));
    form.querySelectorAll("[data-medicion-row] [aria-invalid]").forEach((el) => el.removeAttribute("aria-invalid"));
    const atributos = { posicion: "[data-medicion-posicion]", url: "[data-medicion-url]", nota: "[data-medicion-nota]" };
    const mensajes = [];
    let primero = null;
    Object.keys(errores).forEach((clave) => {
      const m = clave.match(/^mediciones\.(\d+)\.(\w+)$/);
      const tr = m ? filas[Number(m[1])] : null;
      if (!tr) return;
      const el = atributos[m[2]] ? tr.querySelector(atributos[m[2]]) : null;
      if (el) {
        el.setAttribute("aria-invalid", "true");
        if (!primero && el.type !== "hidden") primero = el;
      }
      mensajes.push(`${tr.querySelector(".kw-medicion__kw").textContent}: ${errores[clave][0]}`);
    });
    if (primero) primero.focus();
    return mensajes;
  }

  // ---------- Corrección de una medición suelta (la nota) ----------

  /** Busca una medición dentro del histórico cacheado por su id, devolviendo también su keyword y fecha para la cabecera del modal. */
  function findMedicionEnCache(medicionId) {
    let hallada = null;
    historialCache.forEach((data, listaId) => {
      if (hallada) return;
      (data.keywords || []).forEach((row) => {
        if (hallada) return;
        Object.keys(row.mediciones || {}).forEach((fecha) => {
          const m = row.mediciones[fecha];
          if (!hallada && m && String(m.id) === String(medicionId)) {
            hallada = { medicion: m, keyword: row.keyword, fecha, listaId };
          }
        });
      });
    });
    return hallada;
  }

  function openMedicionNotaModal(medicionId) {
    const form = document.getElementById("medicionNotaForm");
    const found = findMedicionEnCache(medicionId);
    if (!form || !found) return;

    clearFieldErrors(form);
    form.dataset.medicionId = String(medicionId);
    form.dataset.listaId = String(found.listaId);

    document.getElementById("medicionNotaTitle").textContent = found.keyword;
    document.getElementById("medicionNotaSubtitle").textContent = `Medición del ${fmtFecha(found.fecha)}`;
    document.getElementById("kmn_posicion").value = found.medicion.posicion != null ? String(found.medicion.posicion) : "";
    document.getElementById("kmn_url").value = found.medicion.url || "";
    document.getElementById("kmn_nota").value = found.medicion.nota || "";
    document.getElementById("medicionNotaAutor").textContent = found.medicion.registrado_por ? `Registrada por ${found.medicion.registrado_por}` : "";

    window.AgencyOS.openModal("medicionNotaModal");
  }

  function initMedicionNota() {
    document.addEventListener("click", (e) => {
      const btn = e.target.closest("[data-open-medicion-nota]");
      if (!btn) return;
      openMedicionNotaModal(btn.dataset.openMedicionNota);
    });

    const form = document.getElementById("medicionNotaForm");
    if (!form) return;

    form.addEventListener("submit", (e) => {
      e.preventDefault();
      const medicionId = form.dataset.medicionId;
      const listaId = form.dataset.listaId;
      if (!medicionId) return;

      const pos = document.getElementById("kmn_posicion").value.trim();
      const url = document.getElementById("kmn_url").value.trim();
      const nota = document.getElementById("kmn_nota").value.trim();

      const submit = document.getElementById("medicionNotaSubmit");
      submit.disabled = true;
      clearFieldErrors(form);

      request(medicionTemplates().update.replace("__ID__", medicionId), "PUT", {
        posicion: pos === "" ? null : Number(pos),
        url: url === "" ? null : url,
        nota: nota === "" ? null : nota,
      })
        .then((data) => {
          submit.disabled = false;
          window.AgencyOS.closeModal("medicionNotaModal");
          toast("Medición actualizada.", "success");
          // La fila del banco tambien cambia: corregir una medicion puede mover
          // la posicion de la keyword y la media de la lista.
          if (data && data.lista) upsertListaRow(data.lista);
          // Recarga en vez de parchear la caché: el promedio de la ronda lo
          // calcula el servidor y cambiaría con la posición corregida.
          if (listaId) loadHistorial(listaId, true);
        })
        .catch((err) => {
          submit.disabled = false;
          if (err.message === "validation_failed") {
            renderFieldErrors(form, err.data.errors || {});
            toast("Revisa los campos marcados.", "error");
          } else {
            toast("No se pudo guardar la medición.", "error");
          }
        });
    });
  }

  // ---------- Ficha de la keyword (sólo lectura) ----------

  // Keyword que muestra la ficha, para que "Editar keyword" entregue el registro sin otra búsqueda.
  let currentDetailKeyword = null;
  // Sólo la última petición de bitácora puede pintar: abrir otra ficha antes de que llegue la anterior no debe mezclarlas.
  let bitacoraToken = 0;

  function serpUrl(keyword) {
    return "https://www.google.com.mx/search?q=" + encodeURIComponent(keyword);
  }

  /** Posición como "#3", o null si no hay (0 y vacío cuentan como sin posición, igual que en la tabla). */
  function posicionLabel(n) {
    return n ? "#" + n : null;
  }

  function cambioHtml(actual, anterior) {
    if (!actual || !anterior) return NULO_HTML;
    const delta = anterior - actual;
    if (delta === 0) return "Sin cambio";
    const n = Math.abs(delta);
    const texto = `${delta > 0 ? "+" : "−"}${n} ${n === 1 ? "posición" : "posiciones"}`;
    return `<span class="${delta > 0 ? "kwx-verde" : "kwx-rojo"}">${texto}</span>`;
  }

  function bitacoraHtml(mediciones) {
    if (!mediciones.length) {
      return '<li class="kwx-bitacora__vacio">Aún no hay notas de lo que se hizo con esta keyword. Aparecen al guardar una medición con nota.</li>';
    }
    return mediciones
      .map(
        (m) =>
          `<li><span class="kwx-bitacora__fecha u-mono">${escapeHtml(m.fecha || "—")}</span>` +
          `<span class="kwx-bitacora__texto">${escapeHtml(m.nota)}</span>` +
          `<span class="kwx-bitacora__autor">${escapeHtml(m.registrado_por || "")}</span></li>`
      )
      .join("");
  }

  function cargarBitacora(k, token) {
    const lista = document.getElementById("kwDetailBitacora");
    if (!lista) return;
    lista.innerHTML = '<li class="kwx-bitacora__vacio"><i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> Cargando bitácora…</li>';
    const plantilla = document.querySelector("[data-listas-table]")?.dataset.keywordMedicionesTemplate || "";
    getJson(plantilla.replace("__ID__", k.id))
      .then((data) => {
        if (token !== bitacoraToken) return;
        lista.innerHTML = bitacoraHtml((data && data.mediciones) || []);
      })
      .catch(() => {
        if (token !== bitacoraToken) return;
        lista.innerHTML = '<li class="kwx-bitacora__vacio">No se pudo cargar la bitácora.</li>';
      });
  }

  function abrirKeywordDetalle(k) {
    currentDetailKeyword = k;
    const token = ++bitacoraToken;

    const cliente = k.cliente && k.cliente !== "—" ? k.cliente : null;
    const fuente = HERRAMIENTA_LABELS[k.herramienta_origen] ? `fuente ${HERRAMIENTA_LABELS[k.herramienta_origen]}` : null;
    setText("kwDetailTitle", k.keyword);
    setText("kwDetailSub", [cliente, fuente].filter(Boolean).join(" · ") || "Sin cliente ni fuente de origen");
    document.getElementById("kwDetailBadge").innerHTML = badgeHtml(k.estado);
    setText("kwDetailTipo", TIPO_LABELS[k.tipo] || k.tipo);

    setText("kwDetailPosicion", posicionLabel(k.posicion_actual));
    setText("kwDetailVolumen", k.volumen_busqueda != null ? fmtNum(k.volumen_busqueda) : null);
    const kd = k.dificultad != null && !isNaN(Number(k.dificultad)) ? Math.max(0, Math.min(100, Math.round(Number(k.dificultad)))) : null;
    const kdEl = document.getElementById("kwDetailKd");
    kdEl.textContent = kd != null ? String(kd) : "—";
    kdEl.className = "kwx-metrica__val u-mono" + (kd != null ? ` kwx-kd-texto--${kdNivel(kd)}` : "");
    setText("kwDetailCpc", k.cpc_estimado > 0 ? fmtMoney(k.cpc_estimado) : null);

    setText("kwDetailIntencion", INTENCION_LABELS[k.intencion]);
    setText("kwDetailUrl", k.url_asignada);
    setText("kwDetailAnterior", posicionLabel(k.posicion_anterior));
    document.getElementById("kwDetailCambio").innerHTML = cambioHtml(k.posicion_actual, k.posicion_anterior);
    setText("kwDetailLista", k.lista_nombre || "Sin lista");
    setText("kwDetailIncorporada", k.fecha_incorporacion);
    const notas = document.getElementById("kwDetailNotas");
    notas.hidden = !k.notas;
    notas.textContent = k.notas ? `Notas: ${k.notas}` : "";

    const barra = document.getElementById("kwDetailKdBarra");
    barra.style.width = kd != null ? kd + "%" : "0%";
    setText("kwDetailKdPct", kd != null ? kd + "%" : null);

    document.getElementById("kwDetailSerp").href = serpUrl(k.keyword);
    cargarBitacora(k, token);
    window.AgencyOS.openModal("keywordDetailModal");
  }

  function initKeywordDetalle() {
    document.addEventListener("click", (e) => {
      const row = e.target.closest("[data-keyword-row]");
      if (!row) return;
      // La fila entera abre la ficha, salvo sus botones de editar/eliminar (el nombre sí es un botón que la abre).
      if (e.target.closest("[data-edit-keyword], [data-delete-keyword], a, input, select, textarea")) return;
      abrirKeywordDetalle(JSON.parse(row.dataset.keyword));
    });

    document.getElementById("kwDetailEditar")?.addEventListener("click", () => {
      const k = currentDetailKeyword;
      if (!k) return;
      window.AgencyOS.closeModal("keywordDetailModal");
      openKeywordFormModal(k);
    });
  }

  // ---------- Menú ⋮ de cada lista ----------

  function initMenusFila() {
    let abierto = null;

    const cerrar = (devolverFoco) => {
      if (!abierto) return;
      const { toggle, panel } = abierto;
      panel.hidden = true;
      toggle.setAttribute("aria-expanded", "false");
      if (devolverFoco) toggle.focus();
      abierto = null;
    };

    // position:fixed porque la tabla recorta (overflow) cualquier menú absoluto en las últimas filas.
    const abrir = (toggle) => {
      const panel = toggle.parentElement.querySelector(".kwx-menu__lista");
      if (!panel) return;
      cerrar(false);
      panel.hidden = false;
      toggle.setAttribute("aria-expanded", "true");
      const r = toggle.getBoundingClientRect();
      const ancho = panel.offsetWidth;
      const alto = panel.offsetHeight;
      const cabeAbajo = r.bottom + 4 + alto <= window.innerHeight - 8;
      panel.style.top = Math.max(8, cabeAbajo ? r.bottom + 4 : r.top - alto - 4) + "px";
      panel.style.left = Math.max(8, Math.min(r.right - ancho, window.innerWidth - ancho - 8)) + "px";
      abierto = { toggle, panel };
      panel.querySelector('[role="menuitem"]')?.focus();
    };

    document.addEventListener("click", (e) => {
      const toggle = e.target.closest("[data-kwx-menu-toggle]");
      if (toggle) {
        const yaAbierto = abierto && abierto.toggle === toggle;
        if (yaAbierto) cerrar(true);
        else abrir(toggle);
        return;
      }
      cerrar(false);
    });

    document.addEventListener("keydown", (e) => {
      if (!abierto) return;
      const items = Array.from(abierto.panel.querySelectorAll('[role="menuitem"]'));
      const i = items.indexOf(document.activeElement);
      if (e.key === "Escape") {
        e.stopPropagation();
        cerrar(true);
      } else if (e.key === "ArrowDown") {
        e.preventDefault();
        items[(i + 1) % items.length]?.focus();
      } else if (e.key === "ArrowUp") {
        e.preventDefault();
        items[(i - 1 + items.length) % items.length]?.focus();
      } else if (e.key === "Tab") {
        cerrar(false);
      }
    }, true);

    window.addEventListener("resize", () => cerrar(false));
    window.addEventListener("scroll", () => cerrar(false), true);
  }

  // ---------- Accesibilidad de los modales ----------
  //
  // global.js abre y cierra los .modal-overlay pero no nombra el diálogo, no mueve el
  // foco ni lo devuelve; aquí se hace sólo para los modales de este módulo (no se toca x-modal).

  const FOCALIZABLES = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

  function visibles(raiz) {
    return Array.from(raiz.querySelectorAll(FOCALIZABLES)).filter((el) => el.getClientRects().length > 0);
  }

  function initModalesAccesibles() {
    document.querySelectorAll(".kwx .modal-overlay").forEach((overlay) => {
      const dialogo = overlay.querySelector(".modal");
      if (!dialogo) return;
      const titulo = overlay.querySelector(".modal__header h2");
      if (titulo) {
        if (!titulo.id) titulo.id = `${overlay.id}Titulo`;
        dialogo.setAttribute("aria-labelledby", titulo.id);
      }
      dialogo.setAttribute("tabindex", "-1");

      let opener = null;
      new MutationObserver(() => {
        if (!overlay.hidden) {
          if (!opener) opener = document.activeElement;
          // Con formulario se enfoca el primer campo; sin él (fichas y confirmaciones) el propio diálogo.
          const campo = dialogo.querySelector("form") ? visibles(dialogo.querySelector(".modal__body")).find((el) => el.matches("input, select, textarea")) : null;
          (campo || dialogo).focus({ preventScroll: true });
        } else if (opener) {
          if (document.contains(opener) && opener.getClientRects().length > 0) opener.focus({ preventScroll: true });
          opener = null;
        }
      }).observe(overlay, { attributes: true, attributeFilter: ["hidden"] });

      // El Tab no debe escapar del diálogo hacia la página que queda detrás.
      overlay.addEventListener("keydown", (e) => {
        if (e.key !== "Tab" || overlay.hidden) return;
        const focalizables = visibles(dialogo);
        if (!focalizables.length) return;
        const primero = focalizables[0];
        const ultimo = focalizables[focalizables.length - 1];
        if (e.shiftKey && (document.activeElement === primero || document.activeElement === dialogo)) {
          e.preventDefault();
          ultimo.focus();
        } else if (!e.shiftKey && document.activeElement === ultimo) {
          e.preventDefault();
          primero.focus();
        }
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
    initHistorial();
    initOpenMedicionModalButtons();
    initMedicionFechaChange();
    initCapturaRapida();
    initEvolucion();
    initMedicionForm();
    initMedicionNota();
    initKeywordDetalle();
    initMenusFila();
    initModalesAccesibles();
  });
})();
