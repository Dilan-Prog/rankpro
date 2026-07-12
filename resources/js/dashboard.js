/**
 * Dashboard module — client-side behavior only.
 * KPIs, alerts, top-campaigns table and contracts list are rendered
 * server-side by resources/views/admin/dashboard/index.blade.php; this
 * file just draws the revenue chart (Chart.js needs JS regardless) and
 * wires the export button.
 */
(function () {
  "use strict";

  const { formatCurrency, formatCompact, toast, chartColors } = window.AgencyOS;

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

  function init() {
    renderChart();

    const exportBtn = document.getElementById("exportReportBtn");
    if (exportBtn) {
      exportBtn.addEventListener("click", () => {
        toast("Generando reporte ejecutivo…", "success");
      });
    }

    document.addEventListener("theme:change", renderChart);
  }

  document.addEventListener("shell:ready", init);
})();
