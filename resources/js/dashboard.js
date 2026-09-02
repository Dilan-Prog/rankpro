/**
 * Dashboard module — client-side behavior only.
 * KPIs, alerts and contracts list are rendered server-side by
 * resources/views/admin/dashboard/index.blade.php; this file draws the
 * revenue chart (Chart.js needs JS regardless) and wires the campaign
 * detail modal (row click → populate from its data-campana JSON attribute).
 * The export button is now a plain download link, no JS needed for it.
 */
(function () {
  "use strict";

  const { formatCurrency, formatNumber, formatCompact, chartColors } = window.AgencyOS;

  // estado/fase => [label, badge class]. Must mirror the server-side map in
  // resources/views/components/badge.blade.php (ads_campanas keys).
  const ESTADO_MAP = {
    activa: ["Activa", "badge--success"],
    pausada: ["Pausada", "badge--warning"],
    finalizada: ["Finalizada", "badge--neutral"],
  };
  const FASE_MAP = {
    briefing: ["Briefing", "badge--neutral"],
    configuracion: ["Configuración", "badge--primary"],
    lanzamiento: ["Lanzamiento", "badge--success"],
    reporte: ["Reporte", "badge--orange"],
    cerrada: ["Cerrada", "badge--success"],
  };

  let chart = null;

  function renderChart() {
    const canvas = document.getElementById("revenueChart");
    if (!canvas) return;

    const revenueData = JSON.parse(canvas.dataset.revenue || "[]");
    const colors = chartColors();

    if (chart) chart.destroy();
    chart = new Chart(canvas, {
      type: "line",
      data: {
        labels: revenueData.map((d) => d.month),
        datasets: [
          {
            label: "Ingresos",
            data: revenueData.map((d) => d.income),
            borderColor: "#0F9D6E",
            backgroundColor: "rgba(15, 157, 110, 0.12)",
            fill: true,
            tension: 0.35,
            borderWidth: 2,
            pointRadius: 0,
          },
          {
            label: "Inversión",
            data: revenueData.map((d) => d.expense),
            borderColor: "#14B8A6",
            backgroundColor: "rgba(20, 184, 166, 0.12)",
            fill: true,
            tension: 0.35,
            borderWidth: 2,
            pointRadius: 0,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: "index", intersect: false },
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: colors.tooltipBg,
            borderColor: colors.tooltipBorder,
            borderWidth: 1,
            titleColor: colors.tooltipText,
            bodyColor: colors.tooltipText,
            padding: 10,
            callbacks: {
              label: (item) => formatCurrency(item.parsed.y),
            },
          },
        },
        scales: {
          x: {
            grid: { display: false },
            ticks: { color: colors.tick, font: { size: 11 } },
          },
          y: {
            grid: { color: colors.grid },
            ticks: {
              color: colors.tick,
              font: { size: 11 },
              callback: (v) => `$${formatCompact(v)}`,
            },
          },
        },
      },
    });
  }

  function badgeHtml(id, value, map) {
    const [label, cls] = map[value] || [value, "badge--neutral"];
    return `<span id="${id}" class="badge ${cls}">${label}</span>`;
  }

  function populateCampanaModal(c) {
    document.getElementById("campanaModalName").textContent = c.name;
    document.getElementById("campanaModalBadgeEstado").outerHTML = badgeHtml("campanaModalBadgeEstado", c.estado, ESTADO_MAP);
    document.getElementById("campanaModalBadgeFase").outerHTML = badgeHtml("campanaModalBadgeFase", c.fase, FASE_MAP);

    document.getElementById("campanaModalClient").textContent = c.client;
    document.getElementById("campanaModalPlatform").textContent = c.platform;
    document.getElementById("campanaModalPresupuesto").textContent = formatCurrency(c.presupuesto_mensual) + " MXN/mes";
    document.getElementById("campanaModalGasto").textContent = formatCurrency(c.gasto_total) + " MXN";
    document.getElementById("campanaModalRoas").textContent = c.roas + "x";

    const ultima = c.ultima_metrica;
    document.getElementById("campanaModalUltimaMetrica").textContent = ultima
      ? `${formatNumber(ultima.impresiones)} impresiones · ${formatNumber(ultima.clics)} clics · CTR ${ultima.ctr}% · CPC ${formatCurrency(ultima.cpc)} · ${formatNumber(ultima.conversiones)} conversiones`
      : "Sin métricas registradas todavía.";

    const modal = document.getElementById("campanaModal");
    const adsBase = modal.closest("[data-ads-base]")?.dataset.adsBase || "";
    document.getElementById("campanaModalLink").href = `${adsBase}/${c.id}`;
  }

  function initCampanaModal() {
    document.querySelectorAll("[data-campana-row]").forEach((row) => {
      row.addEventListener("click", (e) => {
        if (e.target.closest("a, button, form")) return; // let action links/buttons behave normally
        const c = JSON.parse(row.dataset.campana);
        populateCampanaModal(c);
        window.AgencyOS.openModal("campanaModal");
      });
    });
  }

  function init() {
    renderChart();
    initCampanaModal();

    document.addEventListener("theme:change", renderChart);
  }

  document.addEventListener("shell:ready", init);
})();
