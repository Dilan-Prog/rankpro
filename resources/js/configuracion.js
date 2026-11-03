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

  /**
   * Medición del sitio: detecta en vivo los IDs de Google dentro de lo que la
   * persona pega y rellena los campos. Seguridad: el texto pegado solo se lee
   * con regex; nunca se evalúa ni se inserta como HTML (chips con textContent).
   * Las regex son equivalentes a las del servidor (App\Support\Medicion).
   * La detección solo AYUDA: guardar funciona igual sin JS.
   */
  const PATRONES_MEDICION = {
    gtm: /(?:^|[^A-Za-z0-9_-])(GTM-[A-Z0-9]{6,8})(?![A-Za-z0-9_-])/g,
    ga4: /(?:^|[^A-Za-z0-9_-])(G-[A-Z0-9]{6,12})(?![A-Za-z0-9_-])/g,
    ads: /(?:^|[^A-Za-z0-9_-])(AW-\d{6,12})(?:\/([A-Za-z0-9_-]{6,}))?(?![A-Za-z0-9_-])/g,
  };

  const NOMBRES_MEDICION = {
    gtm: "Google Tag Manager",
    ga4: "Google Analytics 4",
    ads: "Google Ads",
    conversion: "Etiqueta de conversión",
  };

  function detectarMedicion(texto) {
    const hallado = { gtm: [], ga4: [], ads: [], conversion: [] };
    const agregar = (lista, valor) => {
      if (!lista.includes(valor)) lista.push(valor);
    };

    for (const m of texto.matchAll(PATRONES_MEDICION.gtm)) agregar(hallado.gtm, m[1]);
    for (const m of texto.matchAll(PATRONES_MEDICION.ga4)) agregar(hallado.ga4, m[1]);
    for (const m of texto.matchAll(PATRONES_MEDICION.ads)) {
      agregar(hallado.ads, m[1]);
      if (m[2]) agregar(hallado.conversion, `${m[1]}/${m[2]}`);
    }

    return hallado;
  }

  function initMedicion() {
    const root = document.querySelector("[data-configuracion-medicion]");
    if (!root) return;

    const form = root.querySelector("[data-med-form]");
    const codigo = root.querySelector("[data-med-codigo]");
    const chips = root.querySelector("[data-med-chips]");
    const titulo = root.querySelector("[data-med-detectado-titulo]");
    const btnLimpiar = root.querySelector("[data-med-limpiar]");
    const campos = {
      gtm: root.querySelector('[data-med-campo="gtm"]'),
      ga4: root.querySelector('[data-med-campo="ga4"]'),
      ads: root.querySelector('[data-med-campo="ads"]'),
    };
    const btnGuardar = root.querySelector("[data-med-guardar]");
    const textoGuardar = root.querySelector("[data-med-guardar-texto]");
    const avisoSinGuardar = root.querySelector("[data-med-sin-guardar]");

    let sucio = false;
    let enviando = false;

    const marcarSucio = () => {
      sucio = true;
      if (avisoSinGuardar) avisoSinGuardar.hidden = false;
    };

    function pintarChip(tipo, valor) {
      const chip = document.createElement("span");
      chip.className = "cfg-med__chip u-mono";
      const etiqueta = document.createElement("strong");
      etiqueta.textContent = NOMBRES_MEDICION[tipo] + ":";
      chip.append(etiqueta, " ", document.createTextNode(valor));
      chips.appendChild(chip);
    }

    function alPegar() {
      const hallado = detectarMedicion(codigo.value);
      chips.textContent = "";

      let total = 0;
      for (const tipo of ["gtm", "ga4", "ads", "conversion"]) {
        hallado[tipo].forEach((valor) => {
          pintarChip(tipo, valor);
          total++;
        });
      }

      btnLimpiar.hidden = codigo.value.trim() === "";

      if (total === 0) {
        titulo.textContent = codigo.value.trim() === ""
          ? "Aún no hemos detectado nada."
          : "No encontramos ningún ID. Revisa que hayas copiado el código completo.";
        return;
      }

      titulo.textContent = hallado.conversion.length
        ? "Detectamos (la etiqueta de conversión pégala tú en la conversión que corresponda):"
        : "Detectamos:";

      // Solo se rellena lo detectado; si hay varios del mismo tipo gana el primero.
      for (const tipo of ["gtm", "ga4", "ads"]) {
        if (hallado[tipo].length && campos[tipo] && campos[tipo].value !== hallado[tipo][0]) {
          campos[tipo].value = hallado[tipo][0];
          marcarSucio();
        }
      }
    }

    if (codigo) {
      codigo.addEventListener("input", alPegar);
      alPegar();
    }

    if (btnLimpiar) {
      btnLimpiar.addEventListener("click", () => {
        codigo.value = "";
        alPegar();
        codigo.focus();
      });
    }

    // Copiar los nombres de evento para Tag Manager.
    root.querySelectorAll("[data-med-copiar]").forEach((btn) => {
      btn.addEventListener("click", async () => {
        const valor = btn.dataset.medCopiar;
        try {
          await navigator.clipboard.writeText(valor);
          toast(`Copiado: ${valor}`, "success");
        } catch (e) {
          // Sin permiso de portapapeles (http o navegador viejo): se pide copiarlo a mano.
          toast(`No se pudo copiar. Escríbelo así: ${valor}`, "warning");
        }
      });
    });

    if (form) {
      // Cambios sin guardar: cualquier edición real (el cuadro de pegado no se guarda).
      const alCambiar = (e) => {
        if (e.target !== codigo) marcarSucio();
      };
      form.addEventListener("input", alCambiar);
      form.addEventListener("change", alCambiar);

      form.addEventListener("submit", (e) => {
        if (enviando) {
          e.preventDefault();
          return;
        }
        enviando = true;
        sucio = false;
        if (btnGuardar) btnGuardar.disabled = true;
        if (textoGuardar) textoGuardar.textContent = "Guardando...";
      });

      window.addEventListener("beforeunload", (e) => {
        if (sucio && !enviando) {
          e.preventDefault();
          e.returnValue = "";
        }
      });

      // Volver atrás (bfcache) no debe dejar el botón bloqueado.
      window.addEventListener("pageshow", (e) => {
        if (e.persisted) {
          enviando = false;
          if (btnGuardar) btnGuardar.disabled = false;
          if (textoGuardar) textoGuardar.textContent = "Guardar";
        }
      });
    }
  }

  document.addEventListener("shell:ready", initSmtp);
  document.addEventListener("shell:ready", initMedicion);
})();
