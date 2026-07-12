/**
 * Finanzas module — renders the Ingresos vs Gastos chart from the JSON
 * embedded in the canvas's data-revenue attribute (same pattern as the
 * Dashboard's revenue chart).
 */
(function () {
  "use strict";

  const { formatCurrency, formatCompact, chartColors } = window.AgencyOS;

  let chart = null;

  function renderChart() {
    const canvas = document.getElementById("financeChart");
    if (!canvas) return;

    const data = JSON.parse(canvas.dataset.revenue || "[]");
    if (!data.length) return;

    const colors = chartColors();

    if (chart) chart.destroy();
    chart = new Chart(canvas, {
      type: "bar",
      data: {
        labels: data.map((d) => d.month),
        datasets: [
          {
            label: "Ingresos",
            data: data.map((d) => d.income),
            backgroundColor: "rgba(15, 157, 110, 0.85)",
            borderRadius: 4,
            maxBarSize: 28,
          },
          {
            label: "Gastos",
            data: data.map((d) => d.expense),
            backgroundColor: "rgba(20, 184, 166, 0.85)",
            borderRadius: 4,
            maxBarSize: 28,
          },
        ],
      },
      options: {
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
            callbacks: { label: (item) => formatCurrency(item.parsed.y) },
          },
        },
        scales: {
          x: { grid: { display: false }, ticks: { color: colors.tick, font: { size: 11 } } },
          y: {
            grid: { color: colors.grid },
            ticks: { color: colors.tick, font: { size: 11 }, callback: (v) => `$${formatCompact(v)}` },
          },
        },
      },
    });
  }

  /** On the create/edit form, filters "Servicio" options to the chosen client's services. */
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

  document.addEventListener("shell:ready", () => {
    renderChart();
    initServicioCascade();
    document.addEventListener("theme:change", renderChart);
  });
})();
