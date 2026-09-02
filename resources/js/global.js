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

  const SIDEBAR_COLLAPSE_KEY = "agencyos-sidebar-collapsed";

  /**
   * Sidebar collapse/expand, mirroring theme.js's localStorage pattern.
   * The collapsed state is applied pre-paint by an inline script in
   * layouts/admin.blade.php (on <html>, same as data-theme) — this only
   * wires the toggle button and keeps localStorage in sync afterwards.
   */
  function initSidebarCollapse() {
    const toggle = document.getElementById("sidebarCollapseToggle");
    if (!toggle) return;

    toggle.addEventListener("click", () => {
      const collapsed = document.documentElement.classList.toggle("sidebar--collapsed");
      localStorage.setItem(SIDEBAR_COLLAPSE_KEY, collapsed ? "1" : "0");
      document.dispatchEvent(new CustomEvent("sidebar:toggle", { detail: { collapsed } }));
    });
  }

  /**
   * Generic [data-dropdown-trigger="panelId"] / [data-dropdown-panel] wiring
   * reused by the notifications panel and the user menu(s): click toggles,
   * click-outside and Escape close, and opening one closes any other open
   * dropdown panel (mutually exclusive).
   */
  function initDropdowns() {
    const triggers = Array.from(document.querySelectorAll("[data-dropdown-trigger]"));
    if (!triggers.length) return;

    function closeAll(except) {
      document.querySelectorAll("[data-dropdown-panel]").forEach((panel) => {
        if (panel !== except) panel.hidden = true;
      });
    }

    triggers.forEach((trigger) => {
      const panel = document.getElementById(trigger.dataset.dropdownTrigger);
      if (!panel) return;

      trigger.addEventListener("click", (e) => {
        e.stopPropagation();
        const willOpen = panel.hidden;
        closeAll();
        panel.hidden = !willOpen;
      });
    });

    document.addEventListener("click", (e) => {
      if (!e.target.closest("[data-dropdown-panel]") && !e.target.closest("[data-dropdown-trigger]")) {
        closeAll();
      }
    });

    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape") closeAll();
    });
  }

  /**
   * Command palette (Cmd/Ctrl+K): filters a JSON index embedded in the
   * layout (#agencyos-search-index) client-side — no network round-trip.
   * See App\Support\CommandPaletteIndex for how the index is built.
   */
  function initCommandPalette() {
    const overlay = document.getElementById("commandPaletteOverlay");
    const input = document.getElementById("commandPaletteInput");
    const results = document.getElementById("commandPaletteResults");
    const indexScript = document.getElementById("agencyos-search-index");
    if (!overlay || !input || !results || !indexScript) return;

    let index = { modulos: [], clientes: [], keywords: [] };
    try {
      index = JSON.parse(indexScript.textContent || "{}");
    } catch (e) {
      index = { modulos: [], clientes: [], keywords: [] };
    }

    const GROUPS = [
      { key: "modulos", label: "Módulos", icon: "fa-grip" },
      { key: "clientes", label: "Clientes", icon: "fa-building" },
      { key: "keywords", label: "Keywords", icon: "fa-key" },
    ];

    function open() {
      overlay.hidden = false;
      input.value = "";
      render("");
      document.body.style.overflow = "hidden";
      setTimeout(() => input.focus(), 0);
    }

    function close() {
      overlay.hidden = true;
      document.body.style.overflow = "";
    }

    function render(query) {
      const q = query.trim().toLowerCase();
      results.innerHTML = "";
      let rowCount = 0;

      GROUPS.forEach((group) => {
        const items = (index[group.key] || []).filter((item) => !q || item.label.toLowerCase().includes(q));
        if (!items.length) return;

        const groupLabel = document.createElement("div");
        groupLabel.className = "command-palette__group-label";
        groupLabel.textContent = group.label;
        results.appendChild(groupLabel);

        items.slice(0, 8).forEach((item) => {
          const row = document.createElement("a");
          row.href = item.url;
          row.className = "command-palette__row";
          row.setAttribute("role", "option");
          if (rowCount === 0) row.classList.add("is-active");
          row.innerHTML = `<i class="fa-solid ${group.icon}"></i><span class="command-palette__row-label">${item.label}</span>${item.meta ? `<span class="command-palette__row-meta">${item.meta}</span>` : ""}`;
          results.appendChild(row);
          rowCount++;
        });
      });

      if (!rowCount) {
        const empty = document.createElement("div");
        empty.className = "command-palette__empty";
        empty.textContent = "Sin resultados.";
        results.appendChild(empty);
      }
    }

    function moveActive(delta) {
      const rows = Array.from(results.querySelectorAll(".command-palette__row"));
      if (!rows.length) return;
      const currentIndex = rows.findIndex((r) => r.classList.contains("is-active"));
      const nextIndex = (currentIndex + delta + rows.length) % rows.length;
      rows.forEach((r) => r.classList.remove("is-active"));
      rows[nextIndex].classList.add("is-active");
      rows[nextIndex].scrollIntoView({ block: "nearest" });
    }

    document.addEventListener("keydown", (e) => {
      const isMac = navigator.platform.toUpperCase().indexOf("MAC") >= 0;
      const modifierPressed = isMac ? e.metaKey : e.ctrlKey;
      if (modifierPressed && e.key.toLowerCase() === "k") {
        e.preventDefault();
        overlay.hidden ? open() : close();
      }
    });

    document.querySelectorAll("[data-command-palette-trigger]").forEach((btn) => {
      btn.addEventListener("click", open);
    });

    overlay.addEventListener("click", (e) => {
      if (e.target === overlay) close();
    });

    input.addEventListener("input", () => render(input.value));

    input.addEventListener("keydown", (e) => {
      if (e.key === "Escape") {
        close();
      } else if (e.key === "ArrowDown") {
        e.preventDefault();
        moveActive(1);
      } else if (e.key === "ArrowUp") {
        e.preventDefault();
        moveActive(-1);
      } else if (e.key === "Enter") {
        e.preventDefault();
        const active = results.querySelector(".command-palette__row.is-active");
        if (active) window.location.href = active.href;
      }
    });
  }

  /**
   * Client-side table pagination: any element carrying [data-paginate="N"]
   * (in practice, the .table-wrap rendered by <x-data-table :headers="[...]"
   * data-paginate="15">, since Blade forwards unknown attributes onto that
   * div) gets its <tbody> rows paginated N at a time, with a
   * .table-pagination bar (info text + numbered pages w/ ellipsis truncation
   * + prev/next) appended after the <table>, inside the wrap.
   *
   * Several modules already filter rows client-side by toggling
   * `row.style.display` directly (clientes.js, servicios.js, keywords.js).
   * Rather than requiring every filter script to learn a pagination-specific
   * API, a MutationObserver watches each row's `style` attribute: any change
   * made from outside this function (a filter script running elsewhere) is
   * treated as "the filtered set changed", and the table re-paginates from
   * page 1 over whatever is now visible. Our own writes are made with the
   * observer briefly disconnected, so we never react to ourselves.
   *
   * The observer also watches tbody's childList: several modules (seo.js,
   * ads.js, automatizaciones.js) append/replace/remove <tr> rows via
   * fetch()-driven CRUD (insertAdjacentHTML/outerHTML/.remove()) without any
   * page reload — without this, a freshly-inserted row would render with no
   * inline style (i.e. always visible) regardless of the current page, and
   * the "Mostrando X–Y de Z" count would go stale until the user manually
   * changed pages.
   */
  function initTablePagination() {
    document.querySelectorAll("[data-paginate]").forEach(setupTablePagination);
  }

  function setupTablePagination(wrap) {
    const pageSize = parseInt(wrap.getAttribute("data-paginate"), 10) || 15;
    const table = wrap.querySelector("table");
    const tbody = table && table.querySelector("tbody");
    if (!table || !tbody) return;

    let currentPage = 1;
    let filteredRows = [];
    let bar = null;

    function dataRows() {
      return Array.from(tbody.children).filter(
        (el) => el.tagName === "TR" && !el.classList.contains("table__empty")
      );
    }

    /** Reads which rows currently pass any external filter (display !== "none"). */
    function recomputeFilteredRows() {
      filteredRows = dataRows().filter((tr) => tr.style.display !== "none");
    }

    /** Always shows first, last, current ± 1, with "…" for gaps. */
    function buildPageList(current, total) {
      if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1);
      const pages = new Set([1, total, current]);
      if (current - 1 >= 1) pages.add(current - 1);
      if (current + 1 <= total) pages.add(current + 1);
      const sorted = Array.from(pages)
        .filter((p) => p >= 1 && p <= total)
        .sort((a, b) => a - b);
      const result = [];
      let prev = 0;
      sorted.forEach((p) => {
        if (prev && p - prev > 1) result.push("…");
        result.push(p);
        prev = p;
      });
      return result;
    }

    function removeBar() {
      if (bar) {
        bar.remove();
        bar = null;
      }
    }

    function ensureBar() {
      if (!bar) {
        bar = document.createElement("div");
        bar.className = "table-pagination";
        wrap.appendChild(bar);
      }
      return bar;
    }

    function makeNavButton(label, iconClass, disabled, onClick) {
      const btn = document.createElement("button");
      btn.type = "button";
      btn.className = "table-pagination__nav";
      btn.setAttribute("aria-label", label);
      btn.innerHTML = `<i class="fa-solid ${iconClass}"></i>`;
      btn.disabled = disabled;
      btn.addEventListener("click", onClick);
      return btn;
    }

    /** Applies the current page's slice of `filteredRows` to the DOM and (re)builds the bar. */
    function paginateAndRender() {
      const total = filteredRows.length;

      dataRows().forEach((tr) => {
        if (!filteredRows.includes(tr)) tr.style.display = "none";
      });

      if (!total) {
        removeBar();
        return;
      }

      const totalPages = Math.max(1, Math.ceil(total / pageSize));
      if (currentPage > totalPages) currentPage = totalPages;
      if (currentPage < 1) currentPage = 1;

      if (total <= pageSize) {
        filteredRows.forEach((tr) => {
          tr.style.display = "";
        });
        removeBar();
        return;
      }

      const start = (currentPage - 1) * pageSize;
      const end = Math.min(start + pageSize, total);
      filteredRows.forEach((tr, i) => {
        tr.style.display = i >= start && i < end ? "" : "none";
      });

      const barEl = ensureBar();
      barEl.innerHTML = "";

      const info = document.createElement("div");
      info.className = "table-pagination__info";
      info.textContent = `Mostrando ${start + 1}–${end} de ${total} registros`;
      barEl.appendChild(info);

      const pagesWrap = document.createElement("div");
      pagesWrap.className = "table-pagination__pages";

      pagesWrap.appendChild(
        makeNavButton("Página anterior", "fa-chevron-left", currentPage === 1, () => {
          currentPage -= 1;
          render();
        })
      );

      buildPageList(currentPage, totalPages).forEach((item) => {
        if (item === "…") {
          const span = document.createElement("span");
          span.className = "table-pagination__ellipsis";
          span.textContent = "…";
          pagesWrap.appendChild(span);
        } else {
          const btn = document.createElement("button");
          btn.type = "button";
          btn.className = "table-pagination__page" + (item === currentPage ? " is-active" : "");
          if (item === currentPage) btn.setAttribute("aria-current", "page");
          btn.textContent = String(item);
          btn.addEventListener("click", () => {
            currentPage = item;
            render();
          });
          pagesWrap.appendChild(btn);
        }
      });

      pagesWrap.appendChild(
        makeNavButton("Página siguiente", "fa-chevron-right", currentPage === totalPages, () => {
          currentPage += 1;
          render();
        })
      );

      barEl.appendChild(pagesWrap);
    }

    const observer = new MutationObserver(() => {
      currentPage = 1;
      recomputeFilteredRows();
      render();
    });

    /** Wraps any DOM-writing pass so our own style.display writes never re-trigger the observer. */
    function render() {
      observer.disconnect();
      paginateAndRender();
      observer.observe(tbody, { attributes: true, attributeFilter: ["style"], subtree: true, childList: true });
    }

    recomputeFilteredRows();
    render();
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
    el.innerHTML = `<i class="fa-solid ${icon}" style="color:${color}"></i><span></span>`; el.lastElementChild.textContent = message == null ? "" : String(message);
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
    initSidebarCollapse();
    initDropdowns();
    initCommandPalette();
    initTablePagination();
    document.dispatchEvent(new CustomEvent("shell:ready"));
  });
})();
