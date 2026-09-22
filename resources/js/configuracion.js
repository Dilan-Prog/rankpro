/**
 * Módulo Configuración — Configuración de correo (SMTP).
 *
 * El formulario en sí es clásico (POST + @method PUT, recarga la página):
 * no hace falta JS para eso. Aquí solo van dos cosas que sí necesitan
 * interacción sin recargar: desbloquear el campo de contraseña (que llega
 * oculto detrás de "Cambiar" para no invitar a borrarla sin querer) y
 * "Enviar prueba", que manda lo que hay escrito en el formulario ahora mismo
 * (aunque no se haya guardado) — mismo espíritu que "Enviarme una prueba" en
 * Enviar correo.
 */
(function () {
  "use strict";

  const { toast } = window.AgencyOS;
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

  function initSmtp() {
    const root = document.querySelector("[data-configuracion-smtp]");
    if (!root) return;

    const btnCambiar = root.querySelector("[data-cfg-password-cambiar]");
    const bloqueada = root.querySelector("[data-cfg-password-bloqueada]");
    const campoPassword = root.querySelector("[data-cfg-password-campo]");

    if (btnCambiar && bloqueada && campoPassword) {
      btnCambiar.addEventListener("click", () => {
        bloqueada.hidden = true;
        campoPassword.hidden = false;
        campoPassword.focus();
      });
    }

    const btnPrueba = root.querySelector("[data-cfg-prueba-enviar]");
    const campoEmail = root.querySelector("[data-cfg-prueba-email]");
    const form = document.getElementById("smtpForm");

    if (btnPrueba && campoEmail && form) {
      btnPrueba.addEventListener("click", () => {
        const email = campoEmail.value.trim();
        if (!email) {
          toast("Escribe a qué correo mandar la prueba.", "warning");
          campoEmail.focus();
          return;
        }

        const datos = new FormData(form);
        const payload = {
          email,
          host: datos.get("host") || "",
          puerto: datos.get("puerto") || "",
          cifrado: datos.get("cifrado") || null,
          usuario: datos.get("usuario") || null,
          // El campo de contraseña puede estar oculto (ya hay una guardada y
          // no se tocó): en ese caso el backend usa la que ya tiene guardada.
          password: datos.get("password") || null,
          remitente_email: datos.get("remitente_email") || null,
          remitente_nombre: datos.get("remitente_nombre") || null,
        };

        btnPrueba.disabled = true;
        const htmlOriginal = btnPrueba.innerHTML;
        btnPrueba.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Enviando…';

        fetch(root.dataset.pruebaUrl, {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
            "X-CSRF-TOKEN": csrfToken,
          },
          body: JSON.stringify(payload),
        })
          .then((res) =>
            res
              .json()
              .catch(() => ({}))
              .then((body) => {
                if (!res.ok) {
                  const error = new Error("request_failed");
                  error.body = body;
                  throw error;
                }
                return body;
              })
          )
          .then((data) => {
            toast(data.mensaje || "Prueba enviada.", "success");
          })
          .catch((err) => {
            toast((err.body && err.body.message) || "No se pudo enviar la prueba.", "error");
          })
          .finally(() => {
            btnPrueba.disabled = false;
            btnPrueba.innerHTML = htmlOriginal;
          });
      });
    }
  }

  document.addEventListener("shell:ready", initSmtp);
})();
