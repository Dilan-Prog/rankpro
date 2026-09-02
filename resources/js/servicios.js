/**
 * Servicios module — client-side tab filter over the server-rendered table,
 * a detail modal populated from each row's data-servicio JSON attribute (no
 * AJAX needed, the row already carries its full data plus its eventos
 * history), and the create/edit/delete modal CRUD (fetch()-driven, mirrors
 * clientes.js's pattern).
 */
(function () {
  "use strict";

  const { toast } = window.AgencyOS;
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

  const TIPO_LABELS = {
    seo: "SEO",
    google_ads: "Google Ads",
    meta_ads: "Meta Ads",
    tiktok_ads: "TikTok Ads",
    rediseno: "Rediseño",
    software: "Software",
    automatizacion: "Automatización",
  };

  const ESTADO_LABELS = { activo: "Activo", pausado: "Pausado", cancelado: "Cancelado" };

  // Holds the servicio object currently shown in the detail modal, so the
  // "Editar servicio" button can hand it off to the form modal without a
  // second fetch/lookup.
  let currentDetailServicio = null;

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

  function formatCurrency(amount) {
    return "$" + Math.round(amount).toLocaleString("es-MX");
  }

  /** "2026-08-30 14:32" -> "30/08/2026 14:32" */
  function formatFechaHora(value) {
    if (!value) return "—";
    const [fecha, hora] = value.split(" ");
    const [y, m, d] = (fecha || "").split("-");
    if (!y || !m || !d) return value;
    return `${d}/${m}/${y}${hora ? " " + hora : ""}`;
  }

  function badgeHtml(estado) {
    const map = {
      activo: ["Activo", "badge--success"],
      pausado: ["Pausado", "badge--warning"],
      cancelado: ["Cancelado", "badge--danger"],
    };
    const [label, cls] = map[estado] || [estado, "badge--neutral"];
    return `<span id="servicioDetailBadge" class="badge ${cls}">${label}</span>`;
  }

  // ---------- Tab filter (fresh re-query per call, see the note below) ----------

  function applyTabFilter() {
    const activeTab = document.querySelector("#servicioTabs .tabs__item.is-active");
    const tipo = activeTab ? activeTab.dataset.tipo : "all";
    const noResults = document.getElementById("servicioNoResults");
    // Re-query fresh every call — rows can be inserted by AJAX CRUD after
    // this was wired, and a closed-over NodeList would go stale and miss
    // them (this was the tab-filter bug in the previous version of this file).
    const rows = document.querySelectorAll("[data-servicio-row]");
    let visible = 0;
    rows.forEach((row) => {
      const show = tipo === "all" || row.dataset.tipo === tipo;
      row.style.display = show ? "" : "none";
      if (show) visible++;
    });
    if (noResults) noResults.hidden = visible !== 0;
  }

  function initTabs() {
    const tabs = document.querySelectorAll("#servicioTabs .tabs__item");
    if (!tabs.length) return;

    tabs.forEach((tab) => {
      tab.addEventListener("click", () => {
        tabs.forEach((t) => t.classList.remove("is-active"));
        tab.classList.add("is-active");
        applyTabFilter();
      });
    });
  }

  // ---------- Detail modal ----------

  function buildTimelineHtml(eventos) {
    if (!eventos || !eventos.length) {
      return '<p style="color:var(--color-muted-foreground);font-size:var(--text-sm)">Sin eventos registrados</p>';
    }
    return eventos
      .map((evento) => {
        const meta = formatFechaHora(evento.fecha) + (evento.usuario ? ` · por ${escapeHtml(evento.usuario)}` : "");
        return `
          <div class="record-modal__timeline-item">
            <div class="record-modal__timeline-text">${escapeHtml(evento.descripcion)}</div>
            <div class="record-modal__timeline-meta">${meta}</div>
          </div>`;
      })
      .join("");
  }

  function openServicioDetailModal(servicio) {
    currentDetailServicio = servicio;

    document.getElementById("servicioDetailTitle").textContent =
      `${TIPO_LABELS[servicio.tipo] || servicio.tipo} — ${servicio.cliente}`;
    document.getElementById("servicioDetailBadge").outerHTML = badgeHtml(servicio.estado);

    document.getElementById("servicioDetailMrr").textContent =
      servicio.precio_mensual > 0 ? formatCurrency(servicio.precio_mensual) + " MXN/mes" : "—";
    document.getElementById("servicioDetailAnualizado").textContent =
      servicio.anualizado > 0 ? formatCurrency(servicio.anualizado) + " MXN" : "—";
    document.getElementById("servicioDetailMeses").textContent = servicio.meses_activos;
    document.getElementById("servicioDetailEstadoLabel").textContent = ESTADO_LABELS[servicio.estado] || servicio.estado;

    document.getElementById("servicioDetailCliente").textContent = servicio.cliente || "—";
    document.getElementById("servicioDetailNombre").textContent = servicio.nombre || "—";
    document.getElementById("servicioDetailInicio").textContent = servicio.fecha_inicio || "—";
    document.getElementById("servicioDetailResponsable").textContent = servicio.responsable_nombre || "— Sin asignar —";
    document.getElementById("servicioDetailIngresoAcumulado").textContent =
      servicio.ingreso_acumulado > 0 ? formatCurrency(servicio.ingreso_acumulado) + " MXN" : "—";

    document.getElementById("servicioDetailTimeline").innerHTML = buildTimelineHtml(servicio.eventos);
    document.getElementById("servicioDetailContrato").href = `/admin/archivos?cliente=${servicio.cliente_id}`;

    window.AgencyOS.openModal("servicioDetailModal");
  }

  /**
   * Delegated on document (not attached per-row) so rows inserted later by
   * upsertServicioRow (a newly created servicio) get the quick-view click
   * behavior for free.
   */
  function initDetailModal() {
    document.addEventListener("click", (e) => {
      const row = e.target.closest("[data-servicio-row]");
      if (!row) return;
      if (e.target.closest("a, button, form")) return; // let action links/buttons behave normally
      openServicioDetailModal(JSON.parse(row.dataset.servicio));
    });

    document.getElementById("servicioDetailEditar")?.addEventListener("click", () => {
      window.AgencyOS.closeModal("servicioDetailModal");
      openServicioFormModal(currentDetailServicio);
    });
  }

  // ---------- Create/edit form modal ----------

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

  function fillServicioForm(form, servicio) {
    if (!servicio) {
      form.reset();
      return;
    }
    Array.from(form.elements).forEach((el) => {
      if (!el.name || !(el.name in servicio)) return;
      el.value = servicio[el.name] ?? "";
    });
  }

  function openServicioFormModal(servicio) {
    const form = document.getElementById("servicioForm");
    if (!form) return;

    const title = document.getElementById("servicioFormModalTitle");
    const submitBtn = document.getElementById("servicioFormSubmit");

    clearFieldErrors(form);
    fillServicioForm(form, servicio);
    form.dataset.editingId = servicio?.id ?? "";

    if (title) title.textContent = servicio ? "Editar Servicio" : "Asignar Servicio";
    if (submitBtn) submitBtn.innerHTML = servicio
      ? '<i class="fa-solid fa-check"></i> Guardar Cambios'
      : '<i class="fa-solid fa-check"></i> Crear Servicio';

    window.AgencyOS.openModal("servicioFormModal");
  }

  function initOpenServicioModalButtons() {
    document.querySelectorAll("[data-open-servicio-modal]").forEach((btn) => {
      btn.addEventListener("click", () => openServicioFormModal(null));
    });
  }

  function initEditServicioButtons() {
    document.addEventListener("click", (e) => {
      const btn = e.target.closest("[data-edit-servicio]");
      if (!btn) return;
      const row = btn.closest("[data-servicio-row]");
      if (!row) return;
      openServicioFormModal(JSON.parse(row.dataset.servicio));
    });
  }

  /** Builds a fresh <tr>'s inner cells matching index.blade.php's server-rendered row markup. */
  function servicioRowHtml(servicio) {
    return `
      <td><div style="font-weight:500">${escapeHtml(servicio.cliente)}</div></td>
      <td>${escapeHtml(servicio.nombre)}</td>
      <td>${TIPO_LABELS[servicio.tipo] || escapeHtml(servicio.tipo)}</td>
      <td>${badgeEstadoHtml(servicio.estado)}</td>
      <td><span style="font-size:var(--text-xs);color:var(--color-muted-foreground)">${escapeHtml(servicio.fecha_inicio || "—")}</span></td>
      <td class="u-mono">${servicio.precio_mensual > 0 ? formatCurrency(servicio.precio_mensual) : "—"}</td>
      <td>
        <div style="display:flex; gap:4px;">
          <button type="button" class="btn--icon" title="Editar" data-edit-servicio="${servicio.id}">
            <i class="fa-solid fa-pen"></i>
          </button>
          <button type="button" class="btn--icon" title="Eliminar" style="color:var(--text-danger);" data-delete-servicio="${servicio.id}">
            <i class="fa-solid fa-trash"></i>
          </button>
        </div>
      </td>`;
  }

  /** Same status->[label,class] map as the <x-badge> component, for row cells (doesn't need a stable id like the modal's badge does). */
  function badgeEstadoHtml(estado) {
    const map = {
      activo: ["Activo", "badge--success"],
      pausado: ["Pausado", "badge--warning"],
      cancelado: ["Cancelado", "badge--danger"],
    };
    const [label, cls] = map[estado] || [estado, "badge--neutral"];
    return `<span class="badge ${cls}">${label}</span>`;
  }

  /** Builds the table shell (matching <x-data-table>'s markup) when the page loaded with zero servicios (empty-state branch, no table at all). */
  function ensureServicioTable() {
    const existing = document.querySelector(".table-wrap table tbody");
    if (existing) return existing;

    const emptyState = document.querySelector(".empty-state");
    if (!emptyState) return null;

    const wrap = document.createElement("div");
    wrap.className = "table-wrap";
    wrap.setAttribute("data-paginate", "15");
    wrap.innerHTML = `
      <table class="table">
        <thead>
          <tr><th>Cliente</th><th>Servicio</th><th>Tipo</th><th>Estado</th><th>Inicio</th><th>Precio Mensual</th><th></th></tr>
        </thead>
        <tbody></tbody>
      </table>`;

    const noResults = document.createElement("p");
    noResults.className = "table__empty";
    noResults.id = "servicioNoResults";
    noResults.hidden = true;
    noResults.textContent = "No hay servicios de este tipo.";

    emptyState.hidden = true;
    emptyState.insertAdjacentElement("afterend", noResults);
    emptyState.insertAdjacentElement("afterend", wrap);

    return wrap.querySelector("tbody");
  }

  function upsertServicioRow(servicio) {
    const tbody = ensureServicioTable();
    if (!tbody) return;

    let row = tbody.querySelector(`[data-servicio-row-id="${servicio.id}"]`);
    if (!row) {
      row = document.createElement("tr");
      row.className = "is-clickable";
      row.setAttribute("data-servicio-row", "");
      tbody.appendChild(row);
    }

    row.dataset.servicioRowId = String(servicio.id);
    row.dataset.tipo = servicio.tipo;
    row.dataset.estado = servicio.estado;
    row.dataset.servicio = JSON.stringify(servicio);
    row.innerHTML = servicioRowHtml(servicio);

    updateServiciosCountSubtitle();
    applyTabFilter();
  }

  /** Keeps the "N servicios activos" header text in sync after AJAX create/update/delete (it's only server-rendered once at page load). */
  function updateServiciosCountSubtitle() {
    const subtitle = document.getElementById("serviciosCountSubtitle");
    if (!subtitle) return;
    const rows = document.querySelectorAll("[data-servicio-row]");
    const activos = Array.from(rows).filter((r) => r.dataset.estado === "activo").length;
    subtitle.textContent = `${activos} servicios activos`;
  }

  function initServicioForm() {
    const form = document.getElementById("servicioForm");
    if (!form) return;

    form.addEventListener("submit", (e) => {
      e.preventDefault();
      const editingId = form.dataset.editingId;
      const url = editingId
        ? form.dataset.updateActionTemplate.replace("__ID__", editingId)
        : form.dataset.storeAction;
      const method = editingId ? "PUT" : "POST";
      const payload = Object.fromEntries(new FormData(form).entries());

      fetch(url, { method, headers: jsonHeaders(), body: JSON.stringify(payload) })
        .then((res) => {
          if (res.status === 422) {
            return res.json().then((data) => {
              renderFieldErrors(form, data.errors || {});
              toast("Revisa los campos marcados.", "error");
              throw new Error("validation_failed");
            });
          }
          if (!res.ok) throw new Error("request_failed");
          return res.json();
        })
        .then((servicio) => {
          upsertServicioRow(servicio);
          window.AgencyOS.closeModal("servicioFormModal");
          toast("Servicio guardado.", "success");
        })
        .catch((err) => {
          if (err.message !== "validation_failed") {
            toast("No se pudo guardar el servicio.", "error");
          }
        });
    });
  }

  // ---------- Delete confirmation modal ----------

  function initServicioDelete() {
    const confirmBtn = document.getElementById("servicioDeleteConfirm");
    if (!confirmBtn) return;

    document.addEventListener("click", (e) => {
      const btn = e.target.closest("[data-delete-servicio]");
      if (!btn) return;
      const row = btn.closest("[data-servicio-row]");
      if (!row) return;
      const servicio = JSON.parse(row.dataset.servicio);

      document.getElementById("servicioDeleteName").textContent =
        `${servicio.nombre} (${TIPO_LABELS[servicio.tipo] || servicio.tipo} — ${servicio.cliente})`;
      document.getElementById("servicioDeleteMrr").textContent =
        servicio.precio_mensual > 0 ? formatCurrency(servicio.precio_mensual) + " MXN/mes" : "—";
      document.getElementById("servicioDeleteResponsable").textContent = servicio.responsable_nombre || "— Sin asignar —";
      confirmBtn.dataset.servicioId = servicio.id;

      window.AgencyOS.openModal("servicioDeleteModal");
    });

    confirmBtn.addEventListener("click", () => {
      const id = confirmBtn.dataset.servicioId;
      if (!id) return;
      const url = confirmBtn.dataset.destroyActionTemplate.replace("__ID__", id);

      fetch(url, { method: "DELETE", headers: jsonHeaders() })
        .then((res) => {
          if (!res.ok) throw new Error("request_failed");
          return res.json();
        })
        .then(() => {
          document.querySelector(`[data-servicio-row-id="${id}"]`)?.remove();
          updateServiciosCountSubtitle();
          window.AgencyOS.closeModal("servicioDeleteModal");
          toast("Servicio eliminado.", "success");
        })
        .catch(() => toast("No se pudo eliminar el servicio.", "error"));
    });
  }

  document.addEventListener("shell:ready", () => {
    initTabs();
    initDetailModal();
    initOpenServicioModalButtons();
    initEditServicioButtons();
    initServicioForm();
    initServicioDelete();
  });
})();
