/**
 * Archivos module — client switching stays a real `?cliente=ID` full-page
 * navigation (the client-picker cards are plain <a href> links, no JS
 * needed for that part). Everything below is additive AJAX behavior for
 * the file list itself: search/categoría/orden filters, a list/grid view
 * toggle, a read-only detail modal, delete, and a real multipart file
 * upload (the only non-JSON request in this file — see archivoUploadRequest()).
 */
(function () {
  "use strict";

  const { toast } = window.AgencyOS;
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

  // Extension -> accent color. Mirrors index.blade.php's own $typeColors
  // array exactly, so JS-built rows/cards look identical to page-load ones.
  const TYPE_COLORS = {
    pdf: "#EF4444",
    zip: "#F59E0B",
    rar: "#F59E0B",
    xlsx: "#10B981",
    xls: "#10B981",
    csv: "#10B981",
    png: "#0F9D6E",
    jpg: "#0F9D6E",
    jpeg: "#0F9D6E",
    gif: "#0F9D6E",
    svg: "#0F9D6E",
    fig: "#0F9D6E",
  };

  function archivoColor(ext) {
    return TYPE_COLORS[ext] || "#64748B";
  }

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

  /** Plain JSON request (delete only — store() is multipart, see archivoUploadRequest below). */
  function request(url, method) {
    return fetch(url, { method, headers: jsonHeaders() }).then((res) => {
      if (!res.ok) throw new Error("request_failed");
      return res.json();
    });
  }

  /**
   * Real file upload — multipart/form-data, NOT JSON. The browser sets the
   * "multipart/form-data; boundary=..." Content-Type itself only when we
   * don't set one manually, so this deliberately never sets Content-Type
   * (unlike jsonHeaders() above, which every other AJAX call in this file
   * uses). Kept as its own function rather than reusing request() so that
   * one is never accidentally called with a FormData body.
   */
  function archivoUploadRequest(url, formData) {
    return fetch(url, {
      method: "POST",
      headers: { "X-CSRF-TOKEN": csrfToken, Accept: "application/json" },
      body: formData,
    }).then((res) => {
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

  function renderArchivoFieldErrors(form, errors) {
    form.querySelectorAll("[data-error-for]").forEach((span) => {
      span.textContent = "";
    });
    Object.keys(errors || {}).forEach((field) => {
      const span = form.querySelector(`[data-error-for="${field}"]`);
      if (span) span.textContent = errors[field][0];
    });
  }

  // ---------- Client-picker search filter (mirrors seo.js's initSeoClienteFilters()/applySeoClienteFilter() exactly) ----------

  function applyArchivoClienteFilter() {
    const query = (document.getElementById("archivoClienteSearch")?.value || "").trim().toLowerCase();
    const cards = Array.from(document.querySelectorAll("[data-archivo-cliente-card]"));
    let visible = 0;

    cards.forEach((card) => {
      const matches = !query || (card.dataset.search || "").includes(query);
      card.classList.toggle("hidden", !matches);
      if (matches) visible++;
    });

    const emptyEl = document.querySelector("[data-archivo-cliente-empty]");
    if (emptyEl) emptyEl.hidden = visible > 0;
  }

  function initArchivoClienteFilters() {
    const search = document.getElementById("archivoClienteSearch");
    if (!search) return;
    search.addEventListener("input", window.AgencyOS.debounce(applyArchivoClienteFilter, 200));
    applyArchivoClienteFilter();
  }

  // ---------- List/grid view toggle ----------

  function activeArchivoView() {
    const listPanel = document.querySelector('[data-archivo-view-panel="list"]');
    return listPanel && !listPanel.hidden ? "list" : "grid";
  }

  function initArchivoViewToggle() {
    const buttons = Array.from(document.querySelectorAll("[data-archivo-view]"));
    if (!buttons.length) return;

    buttons.forEach((btn) => {
      btn.addEventListener("click", () => {
        const view = btn.dataset.archivoView;
        buttons.forEach((b) => b.classList.toggle("is-active", b === btn));
        document.querySelectorAll("[data-archivo-view-panel]").forEach((panel) => {
          panel.hidden = panel.dataset.archivoViewPanel !== view;
        });
        applyArchivoFilters();
      });
    });
  }

  // ---------- Search / categoría / orden filters — applied to whichever view is currently active ----------

  function archivoSortComparator(sort) {
    if (sort === "name") return (a, b) => (a.dataset.nombre || "").localeCompare(b.dataset.nombre || "", "es");
    if (sort === "size") return (a, b) => Number(b.dataset.tamano || 0) - Number(a.dataset.tamano || 0);
    return (a, b) => (b.dataset.fecha || "").localeCompare(a.dataset.fecha || ""); // date desc — "Y-m-d" strings compare lexicographically fine
  }

  function applyArchivoFilters() {
    const searchInput = document.getElementById("archivoSearch");
    if (!searchInput) return; // filter bar doesn't exist — the "no files at all" empty-state is showing instead

    const query = searchInput.value.trim().toLowerCase();
    const categoria = document.getElementById("archivoCategoriaFilter")?.value || "";
    const sort = document.getElementById("archivoSort")?.value || "date";
    const compare = archivoSortComparator(sort);
    const view = activeArchivoView();
    const panel = document.querySelector(`[data-archivo-view-panel="${view}"]`);
    if (!panel) return;

    const matchesFilters = (row) => (!query || (row.dataset.search || "").includes(query)) && (!categoria || row.dataset.tipo === categoria);

    let visibleCount = 0;

    if (view === "list") {
      const tbody = panel.querySelector("tbody");
      const rows = Array.from(panel.querySelectorAll("[data-archivo-row]"));
      rows.forEach((row) => {
        const show = matchesFilters(row);
        row.hidden = !show;
        if (show) visibleCount++;
      });
      if (tbody) rows.filter((r) => !r.hidden).sort(compare).forEach((r) => tbody.appendChild(r));
    } else {
      const sections = Array.from(panel.querySelectorAll("[data-archivo-category-section]"));
      sections.forEach((section) => {
        const grid = section.querySelector(".archivos-grid");
        const rows = Array.from(section.querySelectorAll("[data-archivo-row]"));
        let sectionVisible = 0;
        rows.forEach((row) => {
          const show = matchesFilters(row);
          row.hidden = !show;
          if (show) sectionVisible++;
        });
        if (grid) rows.filter((r) => !r.hidden).sort(compare).forEach((r) => grid.appendChild(r));
        // A category section with zero visible files (after filtering) must
        // itself disappear too — otherwise you'd see e.g. "Reportes (3)" as
        // a heading with no cards under it.
        section.hidden = sectionVisible === 0;
        visibleCount += sectionVisible;
      });
    }

    const emptyEl = document.querySelector("[data-archivo-filter-empty]");
    if (emptyEl) emptyEl.hidden = visibleCount > 0;

    const listTable = document.querySelector("[data-archivo-list-table]");
    if (listTable) listTable.hidden = view === "list" && visibleCount === 0;
  }

  // ---------- Detail modal ----------

  function openArchivoDetailModal(rowEl) {
    const a = JSON.parse(rowEl.dataset.archivo);

    const title = document.getElementById("archivoDetailModalTitle");
    if (title) title.textContent = a.nombre;
    document.getElementById("archivoDetailCategoria").textContent = a.tipo_label;
    document.getElementById("archivoDetailTipo").textContent = a.extension || "—";
    document.getElementById("archivoDetailPeso").textContent = a.tamano_label;
    document.getElementById("archivoDetailSubidoPor").textContent = a.subido_por || "—";
    document.getElementById("archivoDetailFecha").textContent = a.fecha;

    const downloadLink = document.getElementById("archivoDetailDownload");
    if (downloadLink) downloadLink.href = a.download_url;

    // Reuses the exact same delegated [data-delete-archivo] handler
    // (initArchivoDelete() below) as the row/card trash icons — setting
    // this attribute is all that's needed to wire the modal's own button.
    const deleteBtn = document.getElementById("archivoDetailDelete");
    if (deleteBtn) deleteBtn.dataset.deleteArchivo = String(a.id);

    window.AgencyOS.openModal("archivoDetailModal");
  }

  function initArchivoRowClicks() {
    document.addEventListener("click", (e) => {
      if (e.target.closest("[data-delete-archivo]")) return;
      if (e.target.closest("a[href]")) return; // the download <a> — never intercept its native navigation
      const row = e.target.closest("[data-archivo-row]");
      if (!row) return;
      openArchivoDetailModal(row);
    });
  }

  // ---------- Delete — native window.confirm(), matching every other delete flow in this codebase ----------

  function updateArchivoCategoryHeadingCount(section) {
    const heading = section.querySelector("[data-archivo-category-heading]");
    if (!heading) return;
    const count = section.querySelectorAll("[data-archivo-row]").length;
    heading.textContent = `${archivoCategoriaLabel(section.dataset.tipo)} (${count})`;
  }

  function archivoCategoriaLabel(tipo) {
    const opt = document.querySelector(`#archivoCategoriaFilter option[value="${tipo}"]`);
    return opt ? opt.textContent : tipo;
  }

  /**
   * Recomputes the 4 KPI tiles from the list view's [data-archivo-row]
   * elements (always exactly one row per file regardless of which view is
   * active, so it's the single source of truth for these counts).
   */
  function patchArchivoKpis() {
    const rows = Array.from(document.querySelectorAll('[data-archivo-view-panel="list"] [data-archivo-row]'));
    const setValue = (id, value) => {
      const el = document.getElementById(id);
      const valueEl = el?.querySelector(".kpi__value");
      if (valueEl) valueEl.textContent = value;
    };

    const pesoMb = rows.reduce((sum, r) => sum + (Number(r.dataset.tamano) || 0), 0) / 1048576;
    const contratos = rows.filter((r) => r.dataset.tipo === "contrato").length;
    const fechas = rows.map((r) => r.dataset.fecha).filter(Boolean).sort();
    const ultimo = fechas.length ? fechas[fechas.length - 1] : "—";

    setValue("archivoKpiArchivos", String(rows.length));
    setValue("archivoKpiPeso", pesoMb.toFixed(1) + " MB");
    setValue("archivoKpiContratos", String(contratos));
    setValue("archivoKpiUltimo", ultimo);
  }

  function initArchivoDelete() {
    document.addEventListener("click", (e) => {
      const btn = e.target.closest("[data-delete-archivo]");
      if (!btn) return;
      e.stopPropagation();

      const id = btn.dataset.deleteArchivo;
      const existingRow = document.querySelector(`[data-archivo-row][data-archivo-id="${id}"]`);
      const nombre = existingRow ? JSON.parse(existingRow.dataset.archivo).nombre : "este archivo";

      if (!window.confirm(`¿Eliminar "${nombre}"? Esta acción no se puede deshacer.`)) return;

      request(`/admin/archivos/${id}`, "DELETE")
        .then(() => {
          // Remove BOTH twins (the list <tr> and the grid card) — both are
          // kept in the DOM simultaneously so the view toggle never has to
          // rebuild anything.
          document.querySelectorAll(`[data-archivo-row][data-archivo-id="${id}"]`).forEach((el) => {
            const section = el.closest("[data-archivo-category-section]");
            el.remove();
            if (section) {
              if (section.querySelector("[data-archivo-row]")) updateArchivoCategoryHeadingCount(section);
              else section.remove();
            }
          });

          window.AgencyOS.closeModal("archivoDetailModal");
          patchArchivoKpis();
          applyArchivoFilters();
          toast("Archivo eliminado.", "success");
        })
        .catch(() => toast("No se pudo eliminar el archivo.", "error"));
    });
  }

  // ---------- Upload ----------

  function buildArchivoListRowElement(a) {
    const tr = document.createElement("tr");
    tr.className = "is-clickable";
    tr.setAttribute("data-archivo-row", "");
    tr.dataset.archivoId = String(a.id);
    tr.dataset.tipo = a.tipo;
    tr.dataset.search = (a.nombre || "").toLowerCase();
    tr.dataset.nombre = a.nombre;
    tr.dataset.fecha = a.fecha;
    tr.dataset.tamano = String(a.tamano || 0);
    tr.dataset.archivo = JSON.stringify(a);

    const color = archivoColor(a.extension);
    tr.innerHTML = `
      <td>
        <div style="display:flex; align-items:center; gap:10px; min-width:0;">
          <i class="fa-solid fa-file" style="color:${color}; flex-shrink:0;"></i>
          <span style="font-weight:500; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${escapeHtml(a.nombre)}</span>
        </div>
      </td>
      <td>${escapeHtml(a.tipo_label)}</td>
      <td><span class="archivo-ext-pill" style="--pill-color:${color}">${escapeHtml(a.extension || "—")}</span></td>
      <td class="u-mono">${escapeHtml(a.tamano_label)}</td>
      <td>${escapeHtml(a.subido_por || "—")}</td>
      <td class="u-mono" style="font-size:var(--text-xs); color:var(--color-muted-foreground);">${escapeHtml(a.fecha)}</td>
      <td>
        <div style="display:flex; gap:4px;">
          <a href="${a.download_url}" class="btn--icon" title="Descargar"><i class="fa-solid fa-download"></i></a>
          <button type="button" class="btn--icon" title="Eliminar" style="color:var(--text-danger);" data-delete-archivo="${a.id}"><i class="fa-solid fa-trash"></i></button>
        </div>
      </td>`;
    return tr;
  }

  function buildArchivoGridCardElement(a) {
    const div = document.createElement("div");
    div.className = "card archivos-file";
    div.setAttribute("data-archivo-row", "");
    div.dataset.archivoId = String(a.id);
    div.dataset.tipo = a.tipo;
    div.dataset.search = (a.nombre || "").toLowerCase();
    div.dataset.nombre = a.nombre;
    div.dataset.fecha = a.fecha;
    div.dataset.tamano = String(a.tamano || 0);
    div.dataset.archivo = JSON.stringify(a);

    const color = archivoColor(a.extension);
    div.innerHTML = `
      <span class="archivos-file__icon" style="background:${color}18; border-color:${color}35;">
        <i class="fa-solid fa-file" style="color:${color}"></i>
      </span>
      <div style="min-width:0; flex:1;">
        <div class="archivos-file__name">${escapeHtml(a.nombre)}</div>
        <div class="archivos-file__meta">${escapeHtml(a.tamano_label)} · ${escapeHtml(a.fecha)} · ${escapeHtml(a.subido_por || "—")}</div>
      </div>
      <div style="display:flex; gap:4px; flex-shrink:0;">
        <a href="${a.download_url}" class="btn--icon" title="Descargar"><i class="fa-solid fa-download"></i></a>
        <button type="button" class="btn--icon" title="Eliminar" style="color:var(--text-danger);" data-delete-archivo="${a.id}"><i class="fa-solid fa-trash"></i></button>
      </div>`;
    return div;
  }

  /** Finds (or creates, for a category with zero prior files) the grid section for a tipo. */
  function ensureArchivoCategorySection(tipo) {
    const wrap = document.querySelector("[data-archivo-category-wrap]");
    if (!wrap) return null;

    let section = wrap.querySelector(`[data-archivo-category-section][data-tipo="${tipo}"]`);
    if (section) return section;

    section = document.createElement("div");
    section.className = "archivo-category";
    section.setAttribute("data-archivo-category-section", "");
    section.dataset.tipo = tipo;
    section.innerHTML = `<h2 class="archivos-category__title"><i class="fa-solid fa-folder-open"></i> <span data-archivo-category-heading>${escapeHtml(archivoCategoriaLabel(tipo))} (0)</span></h2><div class="archivos-grid"></div>`;
    wrap.appendChild(section);
    return section;
  }

  /** Prepends a freshly-uploaded file into both views (newest-first, matching the server's own orderBy('created_at', 'desc')). */
  function insertNewArchivo(a) {
    const tbody = document.querySelector('[data-archivo-list-table] tbody');
    if (tbody) tbody.insertBefore(buildArchivoListRowElement(a), tbody.firstChild);

    const section = ensureArchivoCategorySection(a.tipo);
    if (section) {
      const grid = section.querySelector(".archivos-grid");
      if (grid) grid.insertBefore(buildArchivoGridCardElement(a), grid.firstChild);
      updateArchivoCategoryHeadingCount(section);
    }
  }

  function initArchivoUpload() {
    const form = document.getElementById("archivoUploadForm");
    if (!form) return;

    document.querySelectorAll("[data-open-archivo-modal]").forEach((btn) => {
      btn.addEventListener("click", () => {
        form.reset();
        form.querySelectorAll("[data-error-for]").forEach((span) => (span.textContent = ""));
        window.AgencyOS.openModal("archivoUploadModal");
      });
    });

    form.addEventListener("submit", (e) => {
      e.preventDefault();
      const formData = new FormData(form);
      const submitBtn = form.querySelector('button[type="submit"]');
      if (submitBtn) submitBtn.disabled = true;

      archivoUploadRequest(form.dataset.storeAction, formData)
        .then((archivo) => {
          // The filter bar / view panels only exist once the client has
          // >=1 file (see index.blade.php's @if ($archivos->isEmpty())
          // branch) — for the very first upload on an empty client, rebuilding
          // that whole layout live isn't worth the complexity, so just reload.
          const hasLiveUi = !!document.querySelector('[data-archivo-view-panel="list"]');
          window.AgencyOS.closeModal("archivoUploadModal");
          form.reset();

          if (hasLiveUi) {
            insertNewArchivo(archivo);
            patchArchivoKpis();
            applyArchivoFilters();
            toast("Archivo subido.", "success");
          } else {
            toast("Archivo subido. Recargando…", "success");
            window.location.reload();
          }
        })
        .catch((err) => {
          if (err.message === "validation_failed") {
            renderArchivoFieldErrors(form, err.data.errors || {});
            toast("Revisa los campos marcados.", "error");
          } else {
            toast("No se pudo subir el archivo.", "error");
          }
        })
        .finally(() => {
          if (submitBtn) submitBtn.disabled = false;
        });
    });
  }

  document.addEventListener("shell:ready", () => {
    initArchivoClienteFilters();
    initArchivoViewToggle();
    initArchivoRowClicks();
    initArchivoDelete();
    initArchivoUpload();

    const searchInput = document.getElementById("archivoSearch");
    const categoriaSelect = document.getElementById("archivoCategoriaFilter");
    const sortSelect = document.getElementById("archivoSort");
    if (searchInput) searchInput.addEventListener("input", window.AgencyOS.debounce(applyArchivoFilters, 200));
    if (categoriaSelect) categoriaSelect.addEventListener("change", applyArchivoFilters);
    if (sortSelect) sortSelect.addEventListener("change", applyArchivoFilters);
    applyArchivoFilters();
  });
})();
