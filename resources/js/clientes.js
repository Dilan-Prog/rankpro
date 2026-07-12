/**
 * Clientes (CRM) module — client-side search/filter over the server-rendered
 * table, and populating the client-detail modal from each row's data-client
 * JSON attribute (no AJAX needed, the row already carries its full data).
 */
(function () {
  "use strict";

  const SERVICIO_LABELS = {
    seo: "SEO",
    google_ads: "Google Ads",
    meta_ads: "Meta Ads",
    tiktok_ads: "TikTok Ads",
    rediseno: "Rediseño",
    software: "Software",
  };

  const FORMA_PAGO_LABELS = { mensual: "Mensual", trimestral: "Trimestral", anual: "Anual" };
  const METODO_PAGO_LABELS = { transferencia: "Transferencia", tarjeta: "Tarjeta", efectivo: "Efectivo", paypal: "PayPal" };

  function initFilters() {
    const search = document.getElementById("clientSearch");
    const estadoFilter = document.getElementById("clientEstadoFilter");
    const rows = document.querySelectorAll("[data-client-row]");
    const noResults = document.getElementById("clientNoResults");
    if (!search || !estadoFilter || !rows.length) return;

    function applyFilters() {
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

  function initModal() {
    document.querySelectorAll("[data-client-row]").forEach((row) => {
      row.addEventListener("click", (e) => {
        if (e.target.closest("a, button, form")) return; // let action links/buttons behave normally
        const client = JSON.parse(row.dataset.client);
        populateModal(client);
        window.AgencyOS.openModal("clientModal");
      });
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

  document.addEventListener("shell:ready", () => {
    initFilters();
    initModal();
  });
})();
