/**
 * AgencyOS Admin — Theme toggle (dark default, light opt-in).
 * The initial theme is already applied pre-paint by an inline script in
 * layouts/admin.blade.php; this file only wires up the toggle button and
 * keeps localStorage + the toggle icon/label in sync afterwards.
 */
(function () {
  "use strict";

  const STORAGE_KEY = "agencyos-theme";

  function getTheme() {
    return document.documentElement.getAttribute("data-theme") === "light" ? "light" : "dark";
  }

  function setThemeLabel(theme, toggle) {
    toggle.setAttribute("aria-label", theme === "light" ? "Cambiar a modo oscuro" : "Cambiar a modo claro");
  }

  function switchTheme(theme, toggle) {
    if (theme === "light") {
      document.documentElement.setAttribute("data-theme", "light");
    } else {
      document.documentElement.removeAttribute("data-theme");
    }
    localStorage.setItem(STORAGE_KEY, theme);
    setThemeLabel(theme, toggle);
    document.dispatchEvent(new CustomEvent("theme:change", { detail: { theme } }));
  }

  document.addEventListener("DOMContentLoaded", () => {
    const toggle = document.getElementById("themeToggle");
    if (!toggle) return;

    setThemeLabel(getTheme(), toggle);

    toggle.addEventListener("click", () => {
      switchTheme(getTheme() === "light" ? "dark" : "light", toggle);
    });
  });
})();
