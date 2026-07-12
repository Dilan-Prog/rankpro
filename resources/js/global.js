/**
 * AgencyOS Admin — Global script.
 * Sidebar/header markup and active-nav state are now rendered server-side
 * by Blade (components/sidebar.blade.php, components/header.blade.php), so
 * this file only wires up the mobile menu, modal dismissal, and exposes
 * small shared helpers on window.AgencyOS used by module scripts.
 */
(function () {
  "use strict";

  function initMobileSidebar() {
    const toggle = document.getElementById("sidebarToggle");
    const sidebar = document.getElementById("sidebar");
    const backdrop = document.getElementById("sidebarBackdrop");
    if (!toggle || !sidebar || !backdrop) return;

    function open() {
      sidebar.classList.add("is-open");
      backdrop.hidden = false;
      toggle.setAttribute("aria-expanded", "true");
    }
    function close() {
      sidebar.classList.remove("is-open");
      backdrop.hidden = true;
      toggle.setAttribute("aria-expanded", "false");
    }

    toggle.addEventListener("click", () => {
      sidebar.classList.contains("is-open") ? close() : open();
    });
    backdrop.addEventListener("click", close);
    sidebar.querySelectorAll(".sidebar__link").forEach((link) => {
      link.addEventListener("click", close);
    });
    window.addEventListener("resize", () => {
      if (window.innerWidth >= 1024) close();
    });
  }

  /** Wires up overlay click / [data-modal-close] / Escape to close any .modal-overlay. */
  function initModalDismissals() {
    document.querySelectorAll(".modal-overlay").forEach((overlay) => {
      overlay.addEventListener("click", (e) => {
        if (e.target === overlay) closeModal(overlay.id);
      });
    });
    document.querySelectorAll("[data-modal-close]").forEach((btn) => {
      btn.addEventListener("click", () => closeModal(btn.dataset.modalClose));
    });
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape") {
        document.querySelectorAll(".modal-overlay:not([hidden])").forEach((o) => closeModal(o.id));
      }
    });
  }

  /**
   * Any <form data-confirm="message"> anywhere in the document (including
   * ones added after load) shows a confirm() dialog before submitting —
   * used by every module's delete buttons. Delegated on document so it
   * works regardless of when/where the form is rendered.
   */
  function initDeleteConfirmations() {
    document.addEventListener("submit", (e) => {
      const form = e.target.closest("[data-confirm]");
      if (form && !window.confirm(form.dataset.confirm)) {
        e.preventDefault();
      }
    });
  }

  // ---------- Shared helpers ----------

  function formatCurrency(amount, currency) {
    currency = currency || "MXN";
    return new Intl.NumberFormat("es-MX", {
      style: "currency",
      currency: currency,
      maximumFractionDigits: 0,
    }).format(amount);
  }

  function formatNumber(num) {
    return new Intl.NumberFormat("es-MX").format(num);
  }

  function formatCompact(num) {
    if (Math.abs(num) >= 1000000) return (num / 1000000).toFixed(1).replace(/\.0$/, "") + "M";
    if (Math.abs(num) >= 1000) return (num / 1000).toFixed(1).replace(/\.0$/, "") + "K";
    return String(num);
  }

  function debounce(fn, wait) {
    let timer;
    return function debounced(...args) {
      clearTimeout(timer);
      timer = setTimeout(() => fn.apply(this, args), wait || 200);
    };
  }

  /** Reads the current theme's chart colors from CSS custom properties, for Chart.js options. */
  function chartColors() {
    const styles = getComputedStyle(document.documentElement);
    const read = (name) => styles.getPropertyValue(name).trim();
    return {
      tick: read("--chart-tick-color"),
      grid: read("--chart-grid-color"),
      tooltipBg: read("--chart-tooltip-bg"),
      tooltipBorder: read("--chart-tooltip-border"),
      tooltipText: read("--chart-tooltip-text"),
    };
  }

  function openModal(id) {
    const overlay = document.getElementById(id);
    if (!overlay) return;
    overlay.hidden = false;
    document.body.style.overflow = "hidden";
  }

  function closeModal(id) {
    const overlay = document.getElementById(id);
    if (!overlay) return;
    overlay.hidden = true;
    document.body.style.overflow = "";
  }

  function toast(message, type) {
    let region = document.querySelector(".toast-region");
    if (!region) {
      region = document.createElement("div");
      region.className = "toast-region";
      document.body.appendChild(region);
    }
    const el = document.createElement("div");
    el.className = "toast";
    const icon = type === "error" ? "fa-circle-exclamation" : type === "warning" ? "fa-triangle-exclamation" : "fa-circle-check";
    const color = type === "error" ? "var(--text-danger)" : type === "warning" ? "var(--text-warning)" : "var(--text-success)";
    el.innerHTML = `<i class="fa-solid ${icon}" style="color:${color}"></i><span>${message}</span>`;
    region.appendChild(el);
    setTimeout(() => el.remove(), 3500);
  }

  window.AgencyOS = {
    formatCurrency,
    formatNumber,
    formatCompact,
    debounce,
    openModal,
    closeModal,
    initModalDismissals,
    toast,
    chartColors,
  };

  document.addEventListener("DOMContentLoaded", () => {
    initMobileSidebar();
    initModalDismissals();
    initDeleteConfirmations();
    document.dispatchEvent(new CustomEvent("shell:ready"));
  });
})();
