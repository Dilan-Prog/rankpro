/**
 * Finanzas module — AJAX-modal CRUD (mirrors Ads/Desarrollo's pattern exactly),
 * Resumen/Facturación/Por cliente sub-tabs, client-side table filters, a
 * filter-aware CSV export link, and two Chart.js charts (Ingresos/Gastos/
 * Utilidad bar chart + MRR-por-cliente horizontal bar chart).
 *
 * servicio_id is intentionally NOT part of the create/edit form: the new
 * FinanzasController's `clientes` view var is id/nombre only (no eager-loaded
 * `servicios`), so the old client->servicio cascade dropdown (initServicioCascade
 * in the previous version of this file) has no data to filter against. Since
 * servicio_id is nullable both in validation and in the DB, omitting it from
 * the form simply means every record created/edited here keeps servicio_id
 * null — which was already the common case before.
 */
(function () {
  "use strict";

  const { toast } = window.AgencyOS;
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

  const ESTADO_FINANZA_BADGE = {
    pagado: ["Pagado", "badge--success"],
    pendiente: ["Pendiente", "badge--warning"],
    vencido: ["Vencido", "badge--danger"],
  };

  function escapeHtml(value) {
    return String(value == null ? "" : value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  function badgeHtml(status) {
    const [label, cls] = ESTADO_FINANZA_BADGE[status] || [status, "badge--neutral"];
    return `<span class="badge ${cls}">${label}</span>`;
  }

  function fmtMoney(n) {
    return "$" + Math.round(Number(n) || 0).toLocaleString("es-MX");
  }

  function jsonHeaders() {
    return {
      "Content-Type": "application/json",
      Accept: "application/json",
      "X-CSRF-TOKEN": csrfToken,
    };
  }

  /** Rejects with err.message "validation_failed" (err.data.errors) on 422, "request_failed" otherwise — mirrors ads.js's adsRequest(). */
  function finanzaRequest(url, method, payload) {
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

  function renderFinanzaFieldErrors(form, errors) {
    form.querySelectorAll("[data-error-for]").forEach((span) => {
      span.textContent = "";
    });
    Object.keys(errors || {}).forEach((field) => {
      const span = form.querySelector(`[data-error-for="${field}"]`);
      if (span) span.textContent = errors[field][0];
    });
  }

  // ---------- Resumen chart: Ingresos / Gastos / Utilidad ----------

  let financeChart = null;

  function renderFinanceChart() {
    const canvas = document.getElementById("financeChart");
    if (!canvas || typeof Chart === "undefined") return;

    const data = JSON.parse(canvas.dataset.revenue || "[]");
    if (!data.length) return;

    const colors = window.AgencyOS.chartColors();

    if (financeChart) financeChart.destroy();
    financeChart = new Chart(canvas, {
      type: "bar",
      data: {
        labels: data.map((d) => d.month),
        datasets: [
          { label: "Ingresos", data: data.map((d) => d.income), backgroundColor: "rgba(15, 157, 110, 0.85)", borderRadius: 4, maxBarThickness: 28 },
          { label: "Gastos", data: data.map((d) => d.expense), backgroundColor: "rgba(239, 68, 68, 0.75)", borderRadius: 4, maxBarThickness: 28 },
          { label: "Utilidad", data: data.map((d) => d.utilidad), backgroundColor: "rgba(59, 130, 246, 0.85)", borderRadius: 4, maxBarThickness: 28 },
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
            callbacks: { label: (item) => `${item.dataset.label}: ${window.AgencyOS.formatCurrency(item.parsed.y)}` },
          },
        },
        scales: {
          x: { grid: { display: false }, ticks: { color: colors.tick, font: { size: 11 } } },
          y: {
            grid: { color: colors.grid },
            ticks: { color: colors.tick, font: { size: 11 }, callback: (v) => "$" + window.AgencyOS.formatCompact(v) },
          },
        },
      },
    });
  }

  // ---------- Por cliente chart: horizontal MRR bar ----------

  let clienteChart = null;

  function renderClienteChart() {
    const canvas = document.getElementById("finanzasClienteChart");
    if (!canvas || typeof Chart === "undefined") return;

    const data = JSON.parse(canvas.dataset.mrrPorCliente || "[]");
    if (!data.length) return;

    const colors = window.AgencyOS.chartColors();

    if (clienteChart) clienteChart.destroy();
    clienteChart = new Chart(canvas, {
      type: "bar",
      data: {
        labels: data.map((d) => d.cliente),
        datasets: [{ label: "MRR", data: data.map((d) => d.mrr), backgroundColor: "rgba(15, 157, 110, 0.85)", borderRadius: 4, maxBarThickness: 22 }],
      },
      options: {
        indexAxis: "y",
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: colors.tooltipBg,
            borderColor: colors.tooltipBorder,
            borderWidth: 1,
            titleColor: colors.tooltipText,
            bodyColor: colors.tooltipText,
            padding: 10,
            callbacks: { label: (item) => window.AgencyOS.formatCurrency(item.parsed.x) },
          },
        },
        scales: {
          x: { grid: { color: colors.grid }, ticks: { color: colors.tick, font: { size: 11 }, callback: (v) => "$" + window.AgencyOS.formatCompact(v) } },
          y: { grid: { display: false }, ticks: { color: colors.tick, font: { size: 11 } } },
        },
      },
    });
  }

  // ---------- Row rendering — matches index.blade.php's <tr data-factura-row> markup exactly ----------

  function facturaRowCellsHtml(f) {
    const tipoColor = f.tipo === "ingreso" ? "var(--text-success)" : "var(--text-danger)";
    return `
      <td class="u-mono" style="font-size:var(--text-xs); color:var(--color-muted-foreground);">${escapeHtml(f.folio)}</td>
      <td><div style="font-weight:500">${escapeHtml(f.cliente)}</div></td>
      <td><span style="font-size:var(--text-sm); color:var(--color-muted-foreground);">${escapeHtml(f.concepto)}</span></td>
      <td><span style="font-size:var(--text-xs); text-transform:capitalize; color:${tipoColor};">${escapeHtml(f.tipo)}</span></td>
      <td class="u-mono"><strong>${fmtMoney(f.monto)}</strong></td>
      <td class="u-mono" style="font-size:var(--text-xs); color:var(--color-muted-foreground);">${f.fecha_vencimiento || "—"}</td>
      <td>${badgeHtml(f.estado)}</td>
      <td class="u-mono" style="font-size:var(--text-xs); color:var(--color-muted-foreground);">${f.fecha_pago || "—"}</td>
      <td>
        <div style="display:flex; gap:4px;">
          <button type="button" class="btn--icon" title="Editar" data-edit-finanza="${f.id}"><i class="fa-solid fa-pen"></i></button>
          <button type="button" class="btn--icon" title="Eliminar" style="color:var(--text-danger);" data-delete-finanza="${f.id}"><i class="fa-solid fa-trash"></i></button>
        </div>
      </td>`;
  }

  /** Dataset assigned via properties (not string-embedded JSON), safe regardless of quote characters — mirrors ads.js's buildCampanaRowElement. */
  function buildFacturaRowElement(f) {
    const tr = document.createElement("tr");
    tr.setAttribute("data-factura-row", "");
    tr.dataset.facturaId = String(f.id);
    tr.dataset.clienteId = String(f.cliente_id);
    tr.dataset.estado = f.estado;
    tr.dataset.search = `${f.cliente} ${f.concepto} ${f.folio}`.toLowerCase();
    tr.dataset.factura = JSON.stringify(f);
    tr.innerHTML = facturaRowCellsHtml(f);
    return tr;
  }

  /** Inserts/replaces a record's <tr> (create -> prepended at top, approximating the controller's fecha_emision desc ordering; update -> replaced in place). */
  function upsertFacturaRow(f) {
    const tbody = document.querySelector("[data-facturas-table] tbody");
    if (!tbody) return;

    const existing = tbody.querySelector(`[data-factura-row][data-factura-id="${f.id}"]`);
    const newRow = buildFacturaRowElement(f);
    if (existing) existing.replaceWith(newRow);
    else tbody.insertBefore(newRow, tbody.firstChild);

    applyFinanzasFilters();
  }

  // ---------- Create/edit modal ----------

  function openFinanzaFormModal(f) {
    const form = document.getElementById("finanzaForm");
    if (!form) return;

    form.reset();
    form.querySelectorAll("[data-error-for]").forEach((span) => {
      span.textContent = "";
    });
    form.dataset.editingId = f?.id ?? "";

    document.getElementById("finanzaFormModalTitle").textContent = f ? "Editar Registro" : "Nuevo Registro";
    document.getElementById("finanzaFormSubmit").innerHTML = f
      ? '<i class="fa-solid fa-check"></i> Guardar Cambios'
      : '<i class="fa-solid fa-check"></i> Crear Registro';

    if (f) {
      form.querySelector("#cliente_id").value = String(f.cliente_id);
      form.querySelector("#ff_tipo").value = f.tipo;
      form.querySelector("#ff_concepto").value = f.concepto;
      form.querySelector("#ff_monto").value = f.monto;
      form.querySelector("#ff_estado").value = f.estado;
      form.querySelector("#ff_mes").value = f.mes;
      form.querySelector("#ff_anio").value = f.anio;
      form.querySelector("#ff_fecha_emision").value = f.fecha_emision || "";
      form.querySelector("#ff_fecha_vencimiento").value = f.fecha_vencimiento || "";
      form.querySelector("#ff_fecha_pago").value = f.fecha_pago || "";
      form.querySelector("#ff_notas").value = f.notas || "";
    }

    window.AgencyOS.openModal("finanzaFormModal");
  }

  function initFinanzaCrud() {
    const form = document.getElementById("finanzaForm");
    if (!form) return;

    document.querySelector("[data-open-finanza-modal]")?.addEventListener("click", () => openFinanzaFormModal(null));

    document.addEventListener("click", (e) => {
      const editBtn = e.target.closest("[data-edit-finanza]");
      if (!editBtn) return;
      const row = editBtn.closest("[data-factura-row]");
      if (row) openFinanzaFormModal(JSON.parse(row.dataset.factura));
    });

    form.addEventListener("submit", (e) => {
      e.preventDefault();
      const editingId = form.dataset.editingId;
      const url = editingId ? form.dataset.updateActionTemplate.replace("__ID__", editingId) : form.dataset.storeAction;
      const payload = Object.fromEntries(new FormData(form).entries());

      finanzaRequest(url, editingId ? "PUT" : "POST", payload)
        .then((f) => {
          upsertFacturaRow(f);
          window.AgencyOS.closeModal("finanzaFormModal");
          toast(editingId ? "Registro actualizado." : "Registro creado.", "success");
        })
        .catch((err) => {
          if (err.message === "validation_failed") {
            renderFinanzaFieldErrors(form, err.data.errors || {});
            toast("Revisa los campos marcados.", "error");
          } else {
            toast("No se pudo guardar el registro.", "error");
          }
        });
    });
  }

  // ---------- Delete — native window.confirm(), matching every other delete flow in this codebase ----------

  function initFinanzaDelete() {
    document.addEventListener("click", (e) => {
      const btn = e.target.closest("[data-delete-finanza]");
      if (!btn) return;
      const row = document.querySelector(`[data-factura-row][data-factura-id="${btn.dataset.deleteFinanza}"]`);
      if (!row) return;

      if (!window.confirm("¿Eliminar este registro financiero? Esta acción no se puede deshacer.")) return;

      finanzaRequest(`/admin/finanzas/${btn.dataset.deleteFinanza}`, "DELETE")
        .then(() => {
          row.remove();
          applyFinanzasFilters();
          toast("Registro eliminado.", "success");
        })
        .catch(() => toast("No se pudo eliminar el registro.", "error"));
    });
  }

  // ---------- Sub-tabs (Resumen / Facturación / Por cliente) — exact pattern from desarrollo.js's initDesarrolloSubTabs() ----------

  function initFinanzasSubTabs() {
    const tabs = document.querySelectorAll("#finanzasTabs .tabs__item");
    if (!tabs.length) return;

    tabs.forEach((tab) => {
      tab.addEventListener("click", () => {
        tabs.forEach((t) => t.classList.remove("is-active"));
        tab.classList.add("is-active");

        document.querySelectorAll("[data-panel-content]").forEach((panel) => {
          panel.hidden = panel.dataset.panelContent !== tab.dataset.panel;
        });

        // The "Resumen"/"Por cliente" charts are created while their panel may
        // still be hidden (zero-height container), so Chart.js can lay them
        // out with a collapsed canvas. Forcing a resize the moment their tab
        // becomes visible fixes that without needing to defer chart creation.
        if (tab.dataset.panel === "resumen") financeChart?.resize();
        if (tab.dataset.panel === "cliente") clienteChart?.resize();
      });
    });
  }

  // ---------- Facturación tab: client-side filters + totals footer + export link ----------

  function applyFinanzasFilters() {
    const search = (document.getElementById("facturasSearch")?.value || "").trim().toLowerCase();
    const cliente = document.getElementById("facturasClienteFilter")?.value || "all";
    const estado = document.getElementById("facturasEstadoFilter")?.value || "all";

    let visibleCount = 0;
    let totalIngreso = 0;
    let totalGasto = 0;

    document.querySelectorAll("[data-factura-row]").forEach((row) => {
      const matchesSearch = !search || (row.dataset.search || "").includes(search);
      const matchesCliente = cliente === "all" || row.dataset.clienteId === cliente;
      const matchesEstado = estado === "all" || row.dataset.estado === estado;
      const show = matchesSearch && matchesCliente && matchesEstado;
      row.style.display = show ? "" : "none";

      if (show) {
        visibleCount++;
        const f = JSON.parse(row.dataset.factura);
        if (f.tipo === "ingreso") totalIngreso += Number(f.monto) || 0;
        else totalGasto += Number(f.monto) || 0;
      }
    });

    const emptyEl = document.querySelector("[data-facturas-empty]");
    const tableEl = document.querySelector("[data-facturas-table]");
    if (emptyEl) emptyEl.hidden = visibleCount > 0;
    if (tableEl) tableEl.hidden = visibleCount === 0;

    const totalIngresoEl = document.querySelector("[data-total-ingreso]");
    const totalGastoEl = document.querySelector("[data-total-gasto]");
    const totalBalanceEl = document.querySelector("[data-total-balance]");
    if (totalIngresoEl) totalIngresoEl.textContent = fmtMoney(totalIngreso);
    if (totalGastoEl) totalGastoEl.textContent = fmtMoney(totalGasto);
    if (totalBalanceEl) totalBalanceEl.textContent = fmtMoney(totalIngreso - totalGasto);

    updateExportLink();
  }

  function initFinanzasFilters() {
    const search = document.getElementById("facturasSearch");
    const cliente = document.getElementById("facturasClienteFilter");
    const estado = document.getElementById("facturasEstadoFilter");
    if (!search && !cliente && !estado) return;

    const debouncedApply = window.AgencyOS.debounce(applyFinanzasFilters, 200);
    search?.addEventListener("input", debouncedApply);
    cliente?.addEventListener("change", applyFinanzasFilters);
    estado?.addEventListener("change", applyFinanzasFilters);
  }

  /** Rewrites #finanzasExportBtn's href so the CSV download honors the currently-active filters — reads the route's original href once as the base, then reconstructs the query string on every call (never accumulates stale params). */
  let exportBaseHref = null;

  function updateExportLink() {
    const link = document.getElementById("finanzasExportBtn");
    if (!link) return;
    if (exportBaseHref === null) exportBaseHref = link.getAttribute("href");

    const cliente = document.getElementById("facturasClienteFilter")?.value || "all";
    const estado = document.getElementById("facturasEstadoFilter")?.value || "all";
    const search = (document.getElementById("facturasSearch")?.value || "").trim();

    const params = new URLSearchParams();
    if (cliente !== "all") params.set("cliente_id", cliente);
    if (estado !== "all") params.set("estado", estado);
    if (search) params.set("search", search);

    const query = params.toString();
    link.setAttribute("href", query ? `${exportBaseHref}?${query}` : exportBaseHref);
  }

  document.addEventListener("shell:ready", () => {
    renderFinanceChart();
    renderClienteChart();
    initFinanzasSubTabs();
    initFinanzaCrud();
    initFinanzaDelete();
    initFinanzasFilters();
    applyFinanzasFilters();
    document.addEventListener("theme:change", () => {
      renderFinanceChart();
      renderClienteChart();
    });
  });
})();
