/**
 * Clientes (CRM) module — client-side search/filter over the server-rendered
 * table, populating the client-detail modal from each row's data-client
 * JSON attribute (no AJAX needed, the row already carries its full data),
 * and the create/edit/delete modal CRUD (fetch()-driven, mirrors the
 * pattern in automatizaciones.js's initFlujos()).
 */
(function () {
  "use strict";

  const { toast } = window.AgencyOS;
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

  const SERVICIO_LABELS = {
    seo: "SEO",
    google_ads: "Google Ads",
    meta_ads: "Meta Ads",
    tiktok_ads: "TikTok Ads",
    rediseno: "Rediseño",
    software: "Software",
    automatizacion: "Automatización",
  };

  const FORMA_PAGO_LABELS = { mensual: "Mensual", trimestral: "Trimestral", anual: "Anual" };
  const METODO_PAGO_LABELS = { transferencia: "Transferencia", tarjeta: "Tarjeta", efectivo: "Efectivo", paypal: "PayPal" };

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

  function initFilters() {
    const search = document.getElementById("clientSearch");
    const estadoFilter = document.getElementById("clientEstadoFilter");
    const noResults = document.getElementById("clientNoResults");
    if (!search || !estadoFilter) return;

    function applyFilters() {
      // Re-query fresh every call — rows can be inserted/removed via AJAX
      // CRUD after this listener was wired, and a closed-over NodeList
      // would go stale and miss them.
      const rows = document.querySelectorAll("[data-client-row]");
      const term = search.value.trim().toLowerCase();
      const estado = estadoFilter.value;
      let visible = 0;

      rows.forEach((row) => {
        const matchesSearch = !term || row.dataset.search.includes(term);
        const matchesEstado = estado === "all" || row.dataset.estado === estado;
        const show = matchesSearch && matchesEstado;
        row.style.display = show ? "" : "none";
        if (show) visible++;
      });

      if (noResults) noResults.hidden = visible !== 0;
    }

    search.addEventListener("input", window.AgencyOS.debounce(applyFilters, 150));
    estadoFilter.addEventListener("change", applyFilters);
  }

  function formatCurrency(amount) {
    return "$" + Math.round(amount).toLocaleString("es-MX");
  }

  /**
   * Delegated on document (not attached per-row) so rows inserted later by
   * upsertClientRow (a newly created client, or the first row ever built
   * via ensureClientTable) get the quick-view click behavior for free.
   */
  function initModal() {
    document.addEventListener("click", (e) => {
      const row = e.target.closest("[data-client-row]");
      if (!row) return;
      if (e.target.closest("a, button, form")) return; // let action links/buttons behave normally
      const client = JSON.parse(row.dataset.client);
      populateModal(client);
      window.AgencyOS.openModal("clientModal");
    });
  }

  function populateModal(client) {
    document.getElementById("clientModalName").textContent = client.empresa || client.nombre;
    document.getElementById("clientModalBadge").outerHTML = badgeHtml(client.estado);
    document.getElementById("clientModalContact").textContent = client.contacto_nombre || "—";
    document.getElementById("clientModalEmail").textContent = client.email || "—";
    document.getElementById("clientModalPhone").textContent = client.telefono || "—";
    document.getElementById("clientModalPayment").textContent =
      (FORMA_PAGO_LABELS[client.forma_pago] || "—") + " · " + (METODO_PAGO_LABELS[client.metodo_pago] || "—");
    document.getElementById("clientModalStart").textContent = client.fecha_inicio_contrato || "—";
    document.getElementById("clientModalEnd").textContent = client.fecha_renovacion_contrato || "—";
    document.getElementById("clientModalMrr").textContent = client.mrr > 0 ? formatCurrency(client.mrr) + " MXN/mes" : "—";

    const servicesEl = document.getElementById("clientModalServices");
    servicesEl.innerHTML = client.servicios.length
      ? client.servicios.map((s) => `<span class="badge badge--primary">${SERVICIO_LABELS[s] || s}</span>`).join("")
      : '<span style="color:var(--color-muted-foreground);font-size:var(--text-sm)">Sin servicios asignados</span>';

    const notesWrap = document.getElementById("clientModalNotesWrap");
    const notesEl = document.getElementById("clientModalNotes");
    if (client.notas) {
      notesWrap.hidden = false;
      notesEl.textContent = client.notas;
    } else {
      notesWrap.hidden = true;
    }
  }

  function badgeHtml(estado) {
    const map = {
      activo: ["Activo", "badge--success"],
      pausado: ["Pausado", "badge--warning"],
      cancelado: ["Cancelado", "badge--danger"],
    };
    const [label, cls] = map[estado] || [estado, "badge--neutral"];
    return `<span id="clientModalBadge" class="badge ${cls}">${label}</span>`;
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

  function fillClientForm(form, client) {
    if (!client) {
      form.reset();
      return;
    }
    Array.from(form.elements).forEach((el) => {
      if (!el.name || !(el.name in client)) return;
      el.value = client[el.name] ?? "";
    });
  }

  function openClientFormModal(client) {
    const form = document.getElementById("clientForm");
    if (!form) return;

    const title = document.getElementById("clientFormModalTitle");
    const submitBtn = document.getElementById("clientFormSubmit");

    clearFieldErrors(form);
    fillClientForm(form, client);
    form.dataset.editingId = client?.id ?? "";

    if (title) title.textContent = client ? "Editar Cliente" : "Nuevo Cliente";
    if (submitBtn) submitBtn.innerHTML = client
      ? '<i class="fa-solid fa-check"></i> Guardar Cambios'
      : '<i class="fa-solid fa-check"></i> Crear Cliente';

    window.AgencyOS.openModal("clientFormModal");
  }

  function initOpenClientModalButtons() {
    document.querySelectorAll("[data-open-client-modal]").forEach((btn) => {
      btn.addEventListener("click", () => openClientFormModal(null));
    });
  }

  function initEditClientButtons() {
    document.addEventListener("click", (e) => {
      const btn = e.target.closest("[data-edit-client]");
      if (!btn) return;
      const row = btn.closest("[data-client-row]");
      if (!row) return;
      const client = JSON.parse(row.dataset.client);
      openClientFormModal(client);
    });
  }

  /** Builds a fresh <tr> matching index.blade.php's server-rendered row markup. */
  function clientRowHtml(client) {
    const servicios = client.servicios && client.servicios.length
      ? client.servicios.map((s) => `<span class="badge badge--primary">${SERVICIO_LABELS[s] || s}</span>`).join("")
      : '<span style="color:var(--color-muted-foreground);font-size:var(--text-xs)">Sin servicios</span>';

    return `
      <td><div style="font-weight:500">${escapeHtml(client.empresa || client.nombre)}</div></td>
      <td>
        <div>${escapeHtml(client.contacto_nombre || "—")}</div>
        <div style="font-size:var(--text-xs);color:var(--color-muted-foreground)">${escapeHtml(client.email || "—")}</div>
      </td>
      <td><div style="display:flex;flex-wrap:wrap;gap:4px;">${servicios}</div></td>
      <td class="u-mono">${client.mrr > 0 ? formatCurrency(client.mrr) : "—"}</td>
      <td><span style="font-size:var(--text-xs);color:var(--color-muted-foreground)">${escapeHtml(client.fecha_renovacion_contrato || "—")}</span></td>
      <td>${badgeHtml(client.estado)}</td>
      <td>
        <div style="display:flex; gap:4px;">
          <a href="/admin/clientes/${client.id}" class="btn--icon" title="Ver ficha completa">
            <i class="fa-solid fa-arrow-up-right-from-square"></i>
          </a>
          <a href="/admin/clientes/${client.id}/integraciones" class="btn--icon" title="Integraciones y tracking">
            <i class="fa-solid fa-plug"></i>
          </a>
          <button type="button" class="btn--icon" title="Editar" data-edit-client="${client.id}">
            <i class="fa-solid fa-pen"></i>
          </button>
          <button type="button" class="btn--icon" title="Eliminar" style="color:var(--text-danger);" data-delete-client="${client.id}">
            <i class="fa-solid fa-trash"></i>
          </button>
        </div>
      </td>`;
  }

  /** Builds the table shell (matching <x-data-table>'s markup) when the page loaded with zero clients (empty-state branch, no table at all). */
  function ensureClientTable() {
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
          <tr><th>Empresa</th><th>Contacto</th><th>Servicios</th><th>MRR</th><th>Vencimiento</th><th>Estado</th><th></th></tr>
        </thead>
        <tbody></tbody>
      </table>`;

    const noResults = document.createElement("p");
    noResults.className = "table__empty";
    noResults.id = "clientNoResults";
    noResults.hidden = true;
    noResults.textContent = "No se encontraron clientes con esos filtros.";

    emptyState.hidden = true;
    emptyState.insertAdjacentElement("afterend", noResults);
    emptyState.insertAdjacentElement("afterend", wrap);

    return wrap.querySelector("tbody");
  }

  function upsertClientRow(client) {
    const tbody = ensureClientTable();
    if (!tbody) return;

    let row = tbody.querySelector(`[data-client-row-id="${client.id}"]`);
    if (!row) {
      row = document.createElement("tr");
      row.className = "is-clickable";
      row.setAttribute("data-client-row", "");
      tbody.appendChild(row);
    }

    row.dataset.clientRowId = String(client.id);
    row.dataset.search = ((client.empresa || "") + " " + (client.contacto_nombre || "")).toLowerCase();
    row.dataset.estado = client.estado;
    row.dataset.client = JSON.stringify(client);
    row.innerHTML = clientRowHtml(client);
    updateClientesCountSubtitle();
  }

  /** Keeps the "N clientes · N activos" header text in sync after AJAX create/delete (it's only server-rendered once at page load). */
  function updateClientesCountSubtitle() {
    const subtitle = document.getElementById("clientesCountSubtitle");
    if (!subtitle) return;
    const rows = document.querySelectorAll("[data-client-row]");
    const activos = Array.from(rows).filter((r) => r.dataset.estado === "activo").length;
    subtitle.textContent = `${rows.length} clientes · ${activos} activos`;
  }

  function initClientForm() {
    const form = document.getElementById("clientForm");
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
        .then((client) => {
          upsertClientRow(client);
          window.AgencyOS.closeModal("clientFormModal");
          toast("Cliente guardado.", "success");
        })
        .catch((err) => {
          if (err.message !== "validation_failed") {
            toast("No se pudo guardar el cliente.", "error");
          }
        });
    });
  }

  // ---------- Delete confirmation modal ----------

  function initClientDelete() {
    const confirmBtn = document.getElementById("clientDeleteConfirm");
    if (!confirmBtn) return;

    document.addEventListener("click", (e) => {
      const btn = e.target.closest("[data-delete-client]");
      if (!btn) return;
      const row = btn.closest("[data-client-row]");
      if (!row) return;
      const client = JSON.parse(row.dataset.client);

      document.getElementById("clientDeleteName").textContent = client.empresa || client.nombre;
      document.getElementById("clientDeleteMrr").textContent = client.mrr > 0 ? formatCurrency(client.mrr) + " MXN/mes" : "—";
      document.getElementById("clientDeleteEnd").textContent = client.fecha_renovacion_contrato || "—";
      confirmBtn.dataset.clientId = client.id;

      window.AgencyOS.openModal("clientDeleteModal");
    });

    confirmBtn.addEventListener("click", () => {
      const id = confirmBtn.dataset.clientId;
      if (!id) return;
      const url = confirmBtn.dataset.destroyActionTemplate.replace("__ID__", id);

      fetch(url, { method: "DELETE", headers: jsonHeaders() })
        .then((res) => {
          if (!res.ok) throw new Error("request_failed");
          return res.json();
        })
        .then(() => {
          document.querySelector(`[data-client-row-id="${id}"]`)?.remove();
          updateClientesCountSubtitle();
          window.AgencyOS.closeModal("clientDeleteModal");
          toast("Cliente eliminado.", "success");
        })
        .catch(() => toast("No se pudo eliminar el cliente.", "error"));
    });
  }

  document.addEventListener("shell:ready", () => {
    initFilters();
    initModal();
    initOpenClientModalButtons();
    initEditClientButtons();
    initClientForm();
    initClientDelete();
  });
})();
