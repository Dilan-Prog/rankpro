/**
 * Auth pages — password visibility toggle.
 * Each [data-password-toggle="<inputId>"] button flips its target input
 * between type="password" and type="text" and swaps the eye icon.
 */
(function () {
  "use strict";

  document.querySelectorAll("[data-password-toggle]").forEach((btn) => {
    btn.addEventListener("click", () => {
      const input = document.getElementById(btn.dataset.passwordToggle);
      if (!input) return;

      const isHidden = input.type === "password";
      input.type = isHidden ? "text" : "password";

      const icon = btn.querySelector("i");
      if (icon) {
        icon.classList.toggle("fa-eye", !isHidden);
        icon.classList.toggle("fa-eye-slash", isHidden);
      }
      btn.setAttribute("aria-label", isHidden ? "Ocultar contraseña" : "Mostrar contraseña");
    });
  });
})();
