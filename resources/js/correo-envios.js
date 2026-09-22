/**
 * Módulo Correo — Enviar correo (envíos + historial).
 *
 * Tres pantallas, una por atributo raíz:
 *   [data-correo-envios-index]    historial: filtros GET, cancelar/eliminar.
 *   [data-correo-envio-redactar]  redactar/editar: todo el estado inicial
 *                                 viene en #envio-data (plantillas con sus
 *                                 bloques/marca, clientes activos, catálogo de
 *                                 variables, envío en edición) y el JS pinta
 *                                 tarjetas, chips, campos de variables y la
 *                                 previa (POST a la previa de plantillas,
 *                                 resultado en <iframe srcdoc>).
 *   [data-correo-envio-show]      detalle: enviar ahora, cancelar, eliminar.
 *
 * Mismo patrón que propuestas.js: fetch JSON, URLs por data-* con __ID__,
 * confirmaciones con window.confirm(), toasts de window.AgencyOS.
 */
(function () {
  "use strict";

  const { toast, debounce } = window.AgencyOS;
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

  const EMAIL_RE = /^[^\s@<>]+@[^\s@<>]+\.[^\s@<>]+$/;

  function escapeHtml(value) {
    return String(value == null ? "" : value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  function jsonHeaders() {
    return {
      "Content-Type": "application/json",
      Accept: "application/json",
      "X-CSRF-TOKEN": csrfToken,
    };
  }

  /** Como request() de propuestas.js: rechaza con err.status/err.body en cualquier respuesta no-2xx. */
  function request(url, method, payload) {
    return fetch(url, {
      method,
      headers: jsonHeaders(),
      body: method === "GET" ? undefined : JSON.stringify(payload || {}),
    }).then((res) =>
      res
        .json()
        .catch(() => ({}))
        .then((body) => {
          if (!res.ok) {
            const error = new Error("request_failed");
            error.status = res.status;
            error.body = body;
            throw error;
          }
          return body;
        })
    );
  }

  function urlTemplate(template, id) {
    return String(template || "").replace("__ID__", id);
  }

  function mensajeDe(error, porDefecto) {
    return (error && error.body && error.body.message) || porDefecto;
  }

  function clonar(valor) {
    return JSON.parse(JSON.stringify(valor));
  }

  function marcaVacia() {
    return { color: "#0F9D6E", logo: "wordmark", logo_url: "", tagline: "", redes: [] };
  }

  // ==========================================================================
  // Historial
  // ==========================================================================

  function initIndex() {
    const form = document.querySelector("[data-correo-envios-index]");
    if (!form) return;

    // El select filtra al cambiar; el buscador espera Enter o el botón.
    form.querySelector("[data-filtro-estado]")?.addEventListener("change", () => form.submit());

    const tabla = document.querySelector("[data-envios-tabla]");
    if (!tabla) return;

    tabla.addEventListener("click", (e) => {
      const del = e.target.closest("[data-delete-envio]");
      if (del) {
        const id = del.dataset.deleteEnvio;
        if (!window.confirm("¿Eliminar este envío? No se puede deshacer.")) return;
        del.disabled = true;
        request(urlTemplate(tabla.dataset.destroyUrl, id), "DELETE")
          .then(() => {
            tabla.querySelector(`[data-envio-row="${id}"]`)?.remove();
            toast("Envío eliminado.", "success");
          })
          .catch((err) => {
            del.disabled = false;
            toast(mensajeDe(err, "No se pudo eliminar el envío."), "error");
          });
        return;
      }

      const cancelar = e.target.closest("[data-cancelar-envio]");
      if (cancelar) {
        const id = cancelar.dataset.cancelarEnvio;
        if (!window.confirm("¿Cancelar la programación? El envío quedará como cancelado y no saldrá.")) return;
        cancelar.disabled = true;
        request(urlTemplate(tabla.dataset.cancelarUrl, id), "POST")
          .then((data) => {
            toast(data.mensaje || "Programación cancelada.", "success");
            // La fila cambia de acciones (aparece Eliminar, desaparece Cancelar): recargar es más simple que reconstruirla.
            window.location.reload();
          })
          .catch((err) => {
            cancelar.disabled = false;
            toast(mensajeDe(err, "No se pudo cancelar la programación."), "error");
          });
      }
    });
  }

  // ==========================================================================
  // Detalle
  // ==========================================================================

  function initShow() {
    const root = document.querySelector("[data-correo-envio-show]");
    if (!root) return;

    root.querySelector("[data-enviar-ahora]")?.addEventListener("click", (e) => {
      const btn = e.currentTarget;
      const n = Number(root.dataset.destinatarios || 0);
      if (n === 0) {
        toast("Agrega al menos un destinatario antes de enviar.", "warning");
        return;
      }
      if (!window.confirm(`¿Enviar ahora a ${n} ${n === 1 ? "destinatario" : "destinatarios"}?`)) return;
      btn.disabled = true;
      btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Enviando…';
      request(root.dataset.enviarUrl, "POST")
        .then((data) => {
          toast(data.mensaje || "Correo enviado.", data.ok ? "success" : "warning");
          window.location.reload();
        })
        .catch((err) => {
          btn.disabled = false;
          btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Enviar ahora';
          toast(mensajeDe(err, "No se pudo enviar el correo."), "error");
        });
    });

    root.querySelector("[data-cancelar-programacion]")?.addEventListener("click", (e) => {
      const btn = e.currentTarget;
      if (!window.confirm("¿Cancelar la programación? El envío quedará como cancelado y no saldrá.")) return;
      btn.disabled = true;
      request(root.dataset.cancelarUrl, "POST")
        .then((data) => {
          toast(data.mensaje || "Programación cancelada.", "success");
          window.location.reload();
        })
        .catch((err) => {
          btn.disabled = false;
          toast(mensajeDe(err, "No se pudo cancelar la programación."), "error");
        });
    });

    root.querySelector("[data-eliminar-envio]")?.addEventListener("click", (e) => {
      const btn = e.currentTarget;
      if (!window.confirm("¿Eliminar este envío? No se puede deshacer.")) return;
      btn.disabled = true;
      request(root.dataset.destroyUrl, "DELETE")
        .then(() => {
          window.location.href = root.dataset.indexUrl;
        })
        .catch((err) => {
          btn.disabled = false;
          toast(mensajeDe(err, "No se pudo eliminar el envío."), "error");
        });
    });

    initPreviaAncho(document);
  }

  /** Botones escritorio/móvil de la previa: solo cambian el ancho del iframe. */
  function initPreviaAncho(scope) {
    const frame = scope.querySelector("[data-previa-frame]");
    const botones = scope.querySelectorAll("[data-previa-ancho]");
    if (!frame || !botones.length) return;
    botones.forEach((b) => {
      b.addEventListener("click", () => {
        botones.forEach((x) => x.classList.toggle("is-active", x === b));
        frame.style.width = b.dataset.previaAncho + "px";
      });
    });
  }

  // ==========================================================================
  // Redactar / editar
  // ==========================================================================

  function initRedactar() {
    const root = document.querySelector("[data-correo-envio-redactar]");
    if (!root) return;

    let datos;
    try {
      datos = JSON.parse(document.getElementById("envio-data")?.textContent || "{}");
    } catch (e) {
      datos = {};
    }

    const plantillas = datos.plantillas || [];
    const clientes = datos.clientes || [];
    const catalogo = datos.catalogo || {};
    const catalogoBloques = datos.catalogoBloques || {};
    const tiposBloque = datos.tiposBloque || Object.keys(catalogoBloques);
    const urls = datos.urls || {};
    const envio = datos.envio || null;

    const form = root.querySelector("#envioForm");
    const el = {
      plantillasLista: root.querySelector("[data-plantillas-lista]"),
      plantillasVacio: root.querySelector("[data-plantillas-vacio]"),
      asunto: root.querySelector("[data-campo=asunto]"),
      remitenteNombre: root.querySelector("[data-campo=remitente_nombre]"),
      remitenteEmail: root.querySelector("[data-campo=remitente_email]"),
      chips: root.querySelector("[data-chips]"),
      chipsVacio: root.querySelector("[data-chips-vacio]"),
      contador: root.querySelector("[data-destinatarios-contador]"),
      todosActivos: root.querySelector("[data-todos-activos]"),
      limpiar: root.querySelector("[data-limpiar-destinatarios]"),
      buscadorInput: root.querySelector("[data-buscador-input]"),
      buscadorLista: root.querySelector("[data-buscador-lista]"),
      sueltos: root.querySelector("[data-sueltos]"),
      agregarSueltos: root.querySelector("[data-agregar-sueltos]"),
      variablesCampos: root.querySelector("[data-variables-campos]"),
      variablesVacio: root.querySelector("[data-variables-vacio]"),
      variablesPersona: root.querySelector("[data-variables-persona]"),
      variablesPersonaTexto: root.querySelector("[data-variables-persona-texto]"),
      cuando: root.querySelectorAll("[data-cuando]"),
      programarCampo: root.querySelector("[data-programar-campo]"),
      programadoPara: root.querySelector("[data-campo=programado_para]"),
      formError: root.querySelector("[data-form-error]"),
      accionPrincipal: root.querySelector("[data-accion-principal]"),
      accionPrincipalTexto: root.querySelector("[data-accion-principal-texto]"),
      previaPlantilla: root.querySelector("[data-previa-plantilla]"),
      previaDe: root.querySelector("[data-previa-de]"),
      previaPara: root.querySelector("[data-previa-para]"),
      previaAsunto: root.querySelector("[data-previa-asunto]"),
      previaSalida: root.querySelector("[data-previa-salida]"),
      previaFrame: root.querySelector("[data-previa-frame]"),
      previaEstado: root.querySelector("[data-previa-estado]"),
      // Personalizar contenido
      personalizarToggle: root.querySelector("[data-personalizar-toggle]"),
      personalizarPanel: root.querySelector("[data-personalizar-panel]"),
      personalizarVariables: root.querySelector("[data-personalizar-variables]"),
      personalizarHtmlToggle: root.querySelector("[data-personalizar-html-toggle]"),
      personalizarHtmlPanel: root.querySelector("[data-personalizar-html-panel]"),
      personalizarHtmlCodigo: root.querySelector("[data-personalizar-html-codigo]"),
      personalizarHtmlLongitud: root.querySelector("[data-personalizar-html-longitud]"),
      detallesAvanzado: root.querySelector("[data-personalizar-html-panel-detalles]"),
      personalizarRedes: root.querySelector("[data-personalizar-redes]"),
      personalizarColores: root.querySelector("[data-personalizar-colores]"),
      personalizarColorLibre: root.querySelector("[data-personalizar-color-libre]"),
      personalizarColorHex: root.querySelector("[data-personalizar-color-hex]"),
      personalizarLogoUrlCampo: root.querySelector("[data-personalizar-logo-url-campo]"),
      personalizarSeccionesBloques: Array.from(root.querySelectorAll("[data-personalizar-seccion-bloques]")),
      ayudaBloques: root.querySelector("[data-cp-ayuda-bloques]"),
      ayudaHtmlLibre: root.querySelector("[data-cp-ayuda-html-libre]"),
      variablesPersonalizadasLista: root.querySelector("[data-variables-personalizadas-lista]"),
      variableNuevaClave: root.querySelector("[data-variable-nueva-clave]"),
      variableNuevaValor: root.querySelector("[data-variable-nueva-valor]"),
      variableAgregar: root.querySelector("[data-variable-agregar]"),
      // Motor de edición en vivo sobre la previa (ver "---- Editor en vivo ----").
      cpFlot: root.querySelector("[data-cp-flot]"),
      cpPopover: root.querySelector("[data-cp-popover]"),
    };

    // ---- Estado ------------------------------------------------------------

    // Variables "personalizadas": claves en las variables del envío que no
    // pertenecen al catálogo fijo (ver Support\Correo\Variables::catalogo()).
    // Se listan aparte para no perderlas al cambiar de plantilla.
    const variablesIniciales = envio ? { ...(envio.variables || {}) } : {};
    const variablesPersonalizadasIniciales = Object.keys(variablesIniciales).filter((k) => !catalogo[k]);

    const state = {
      plantillaId: envio ? envio.plantilla_id : null,
      asunto: envio ? envio.asunto || "" : "",
      remitenteNombre: envio ? envio.remitente_nombre || "" : datos.remitente?.nombre || "",
      remitenteEmail: envio ? envio.remitente_email || "" : datos.remitente?.email || "",
      destinatarios: envio ? (envio.destinatarios || []).map((d) => ({ ...d })) : [],
      variables: variablesIniciales,
      variablesPersonalizadas: variablesPersonalizadasIniciales,
      cuando: envio && envio.programado_para ? "programar" : "ahora",
      programadoPara: envio ? envio.programado_para || "" : "",
      // Contenido personalizado del envío: si el envío ya tenía uno propio se
      // respeta tal cual; si no, arranca como copia de la plantilla elegida
      // (elegirPlantilla() lo mantiene sincronizado mientras no se active).
      personalizar: envio ? !!envio.personalizado : false,
      bloques: envio && envio.personalizado ? envio.bloques || [] : [],
      marca: Object.assign(marcaVacia(), envio && envio.personalizado ? envio.marca || {} : {}),
      htmlPersonalizado: envio && envio.personalizado ? envio.html_personalizado || "" : "",
      // true cuando un contentEditable de la previa cambió y aún no se refrescó
      // el iframe (ver engancharEdicionPrevia): evita refrescar en cada tecla,
      // que perdería el foco al reemplazar el srcdoc.
      previaDesincronizada: false,
    };
    if (!Array.isArray(state.marca.redes)) state.marca.redes = [];

    function plantillaActual() {
      return plantillas.find((p) => p.id === state.plantillaId) || null;
    }

    /** Copia el contenido de la plantilla al estado editable, si no se está personalizando. */
    function sincronizarContenidoConPlantilla() {
      if (state.personalizar) return;
      const p = plantillaActual();
      state.bloques = p ? clonar(p.bloques || []) : [];
      state.marca = p ? Object.assign(marcaVacia(), clonar(p.marca || {})) : marcaVacia();
      if (!Array.isArray(state.marca.redes)) state.marca.redes = [];
      state.htmlPersonalizado = p && p.html_libre ? p.html_personalizado || "" : "";
    }

    function tieneEmail(email) {
      const e = String(email).toLowerCase();
      return state.destinatarios.some((d) => String(d.email).toLowerCase() === e);
    }

    // ---- 1 · Plantillas ------------------------------------------------------

    function pintarPlantillas() {
      if (!plantillas.length) {
        el.plantillasVacio.hidden = false;
        el.plantillasLista.innerHTML = "";
        return;
      }
      el.plantillasLista.innerHTML = plantillas
        .map(
          (p) => `
          <button type="button" class="correo-plantilla ${p.id === state.plantillaId ? "is-active" : ""}" data-plantilla="${p.id}">
            <span class="correo-plantilla__cat">${escapeHtml(p.categoria_label)}${p.html_libre ? " · HTML" : ""}</span>
            <span class="correo-plantilla__nombre">${escapeHtml(p.nombre)}</span>
            <span class="correo-plantilla__asunto">${escapeHtml(p.asunto)}</span>
          </button>`
        )
        .join("");
    }

    function elegirPlantilla(id) {
      const anterior = plantillaActual();
      state.plantillaId = id;
      const p = plantillaActual();
      // Se prellena el asunto solo si estaba vacío o era el de la plantilla
      // anterior: lo que escribió a mano no se pisa.
      if (p && (!state.asunto.trim() || (anterior && state.asunto === anterior.asunto))) {
        state.asunto = p.asunto || "";
        el.asunto.value = state.asunto;
      }
      sincronizarContenidoConPlantilla();
      pintarPersonalizarPanel();
      pintarPlantillas();
      pintarVariables();
      limpiarError("plantilla_id");
      actualizarPrevia();
    }

    el.plantillasLista.addEventListener("click", (e) => {
      const btn = e.target.closest("[data-plantilla]");
      if (btn) elegirPlantilla(Number(btn.dataset.plantilla));
    });

    // ---- 2 · Asunto y remitente --------------------------------------------

    el.asunto.value = state.asunto;
    el.remitenteNombre.value = state.remitenteNombre;
    el.remitenteEmail.value = state.remitenteEmail;

    el.asunto.addEventListener("input", () => {
      state.asunto = el.asunto.value;
      limpiarError("asunto");
      actualizarPreviaMeta();
      programarPrevia();
    });
    el.remitenteNombre.addEventListener("input", () => {
      state.remitenteNombre = el.remitenteNombre.value;
      actualizarPreviaMeta();
    });
    el.remitenteEmail.addEventListener("input", () => {
      state.remitenteEmail = el.remitenteEmail.value;
      limpiarError("remitente_email");
      actualizarPreviaMeta();
    });

    // ---- 3 · Destinatarios ---------------------------------------------------

    function etiquetaChip(d) {
      const c = d.cliente_id ? clientes.find((x) => x.id === d.cliente_id) : null;
      const partes = [];
      if (c) partes.push(c.empresa || c.nombre);
      if (c && c.contacto_nombre) partes.push(c.contacto_nombre);
      else if (!c && d.nombre) partes.push(d.nombre);
      return partes.join(" · ");
    }

    function pintarChips() {
      const chips = state.destinatarios
        .map((d, i) => {
          const extra = etiquetaChip(d);
          return `
          <span class="correo-chip ${d.cliente_id ? "correo-chip--cliente" : ""}" title="${escapeHtml(d.email)}">
            <i class="fa-solid ${d.cliente_id ? "fa-building" : "fa-at"}"></i>
            ${extra ? `<span class="correo-chip__extra">${escapeHtml(extra)}</span>` : ""}
            <span class="correo-chip__email u-mono">${escapeHtml(d.email)}</span>
            <button type="button" class="correo-chip__quitar" data-quitar="${i}" aria-label="Quitar ${escapeHtml(d.email)}"><i class="fa-solid fa-xmark"></i></button>
          </span>`;
        })
        .join("");
      el.chips.innerHTML = chips || '<span class="correo-chips__vacio">Sin destinatarios todavía</span>';
      el.contador.textContent = String(state.destinatarios.length);
      el.limpiar.hidden = state.destinatarios.length === 0;
      if (state.destinatarios.length) limpiarError("destinatarios");
      actualizarPreviaMeta();
      actualizarAccionPrincipal();
    }

    el.chips.addEventListener("click", (e) => {
      const btn = e.target.closest("[data-quitar]");
      if (!btn) return;
      state.destinatarios.splice(Number(btn.dataset.quitar), 1);
      pintarChips();
      pintarBuscador();
    });

    function agregarCliente(c) {
      if (!c || !c.email || tieneEmail(c.email)) return false;
      state.destinatarios.push({ cliente_id: c.id, email: c.email, nombre: c.contacto_nombre || c.nombre || null, variables: {} });
      return true;
    }

    el.todosActivos.addEventListener("click", () => {
      let n = 0;
      clientes.forEach((c) => {
        if (agregarCliente(c)) n++;
      });
      pintarChips();
      pintarBuscador();
      toast(n ? `${n} ${n === 1 ? "cliente agregado" : "clientes agregados"}.` : "Todos los clientes activos ya estaban en la lista.", n ? "success" : "warning");
    });

    el.limpiar.addEventListener("click", () => {
      state.destinatarios = [];
      pintarChips();
      pintarBuscador();
    });

    function pintarBuscador() {
      const term = el.buscadorInput.value.trim().toLowerCase();
      const candidatos = clientes.filter((c) => {
        if (tieneEmail(c.email)) return false;
        if (!term) return true;
        return [c.empresa, c.nombre, c.contacto_nombre, c.email].some((v) => String(v || "").toLowerCase().includes(term));
      });
      if (document.activeElement !== el.buscadorInput && !term) {
        el.buscadorLista.hidden = true;
        return;
      }
      el.buscadorLista.hidden = false;
      el.buscadorLista.innerHTML = candidatos.length
        ? candidatos
            .slice(0, 30)
            .map(
              (c) => `
            <button type="button" class="correo-buscador__item" data-cliente="${c.id}">
              <span class="correo-buscador__principal">${escapeHtml(c.empresa || c.nombre)}${c.contacto_nombre ? ` · ${escapeHtml(c.contacto_nombre)}` : ""}</span>
              <span class="correo-buscador__email u-mono">${escapeHtml(c.email)}</span>
            </button>`
            )
            .join("")
        : `<span class="correo-buscador__vacio">${clientes.length ? "Sin coincidencias o ya están todos agregados." : "No hay clientes activos con correo."}</span>`;
    }

    el.buscadorInput.addEventListener("input", pintarBuscador);
    el.buscadorInput.addEventListener("focus", pintarBuscador);
    el.buscadorInput.addEventListener("keydown", (e) => {
      if (e.key === "Escape") {
        el.buscadorLista.hidden = true;
        el.buscadorInput.blur();
      }
      if (e.key === "Enter") {
        e.preventDefault();
        el.buscadorLista.querySelector("[data-cliente]")?.click();
      }
    });
    // mousedown y no click: el blur del input cerraría la lista antes del click.
    el.buscadorLista.addEventListener("mousedown", (e) => {
      const btn = e.target.closest("[data-cliente]");
      if (!btn) return;
      e.preventDefault();
      agregarCliente(clientes.find((c) => c.id === Number(btn.dataset.cliente)));
      el.buscadorInput.value = "";
      pintarChips();
      pintarBuscador();
    });
    document.addEventListener("click", (e) => {
      if (!e.target.closest("[data-buscador]")) el.buscadorLista.hidden = true;
    });

    /** "Nombre <correo>", "correo" — separados por coma, punto y coma o salto de línea. */
    function parsearSueltos(texto) {
      const validos = [];
      const invalidos = [];
      String(texto)
        .split(/[\n;,]+/)
        .map((t) => t.trim())
        .filter(Boolean)
        .forEach((token) => {
          const m = token.match(/^(.*?)\s*<([^<>]+)>$/);
          const nombre = m ? m[1].replace(/^["']|["']$/g, "").trim() : "";
          const email = (m ? m[2] : token).trim();
          if (EMAIL_RE.test(email)) validos.push({ email, nombre: nombre || null });
          else invalidos.push(token);
        });
      return { validos, invalidos };
    }

    function agregarSueltos() {
      const { validos, invalidos } = parsearSueltos(el.sueltos.value);
      let n = 0;
      validos.forEach((v) => {
        if (tieneEmail(v.email)) return;
        // Si el correo pertenece a un cliente activo se vincula: así {{cliente}} sale del CRM.
        const c = clientes.find((x) => String(x.email).toLowerCase() === v.email.toLowerCase());
        if (c) agregarCliente(c);
        else state.destinatarios.push({ cliente_id: null, email: v.email, nombre: v.nombre, variables: v.nombre ? { contacto: v.nombre } : {} });
        n++;
      });
      el.sueltos.value = invalidos.join("\n");
      pintarChips();
      pintarBuscador();
      if (invalidos.length) toast(`${invalidos.length} ${invalidos.length === 1 ? "entrada no es un correo válido" : "entradas no son correos válidos"}; se dejaron en el cuadro.`, "warning");
      else if (n) toast(`${n} ${n === 1 ? "correo agregado" : "correos agregados"}.`, "success");
    }

    el.agregarSueltos.addEventListener("click", agregarSueltos);
    el.sueltos.addEventListener("keydown", (e) => {
      if (e.key === "Enter" && (e.ctrlKey || e.metaKey)) {
        e.preventDefault();
        agregarSueltos();
      }
    });

    // ---- 4 · Variables -------------------------------------------------------

    function pintarVariables() {
      const p = plantillaActual();
      const claves = p ? p.variables_envio || [] : [];
      const persona = p ? p.variables_persona || [] : [];

      el.variablesCampos.innerHTML = claves
        .map((k) => {
          const def = catalogo[k] || { label: k, ejemplo: "" };
          return `
          <div class="field">
            <label class="field__label" for="ev_${escapeHtml(k)}">${escapeHtml(def.label)} <code class="correo-var">{{${escapeHtml(k)}}}</code></label>
            <input class="input" type="text" id="ev_${escapeHtml(k)}" maxlength="2000" data-variable="${escapeHtml(k)}"
                   value="${escapeHtml(state.variables[k] || "")}" placeholder="${escapeHtml(def.ejemplo || "")}">
          </div>`;
        })
        .join("");
      el.variablesVacio.hidden = !p || claves.length > 0;

      el.variablesPersona.hidden = persona.length === 0;
      el.variablesPersonaTexto.textContent = persona.map((k) => `{{${k}}}`).join(", ");
    }

    el.variablesCampos.addEventListener("input", (e) => {
      const input = e.target.closest("[data-variable]");
      if (!input) return;
      state.variables[input.dataset.variable] = input.value;
      limpiarError("variables");
      programarPrevia();
    });

    // ---- Variables personalizadas (además de las que declara la plantilla) --

    const NOMBRE_VARIABLE_RE = /^[a-z_]+$/;

    function pintarVariablesPersonalizadas() {
      if (!el.variablesPersonalizadasLista) return;
      el.variablesPersonalizadasLista.innerHTML = state.variablesPersonalizadas
        .map(
          (k) => `
          <div class="field">
            <label class="field__label" for="evp_${escapeHtml(k)}">
              <code class="correo-var">{{${escapeHtml(k)}}}</code>
              <button type="button" class="cp-link" data-variable-personalizada-quitar="${escapeHtml(k)}" style="margin-left:6px;">Quitar</button>
            </label>
            <input class="input" type="text" id="evp_${escapeHtml(k)}" maxlength="2000" data-variable="${escapeHtml(k)}" value="${escapeHtml(state.variables[k] || "")}">
          </div>`
        )
        .join("");
    }

    function agregarVariablePersonalizada() {
      const clave = String(el.variableNuevaClave.value || "").trim().toLowerCase();
      const valor = String(el.variableNuevaValor.value || "");
      if (!clave) return;
      if (!NOMBRE_VARIABLE_RE.test(clave)) {
        toast("El nombre de la variable solo admite minúsculas y guiones bajos.", "error");
        return;
      }
      const p = plantillaActual();
      const yaExiste = (p && (p.variables_envio || []).includes(clave)) || state.variablesPersonalizadas.includes(clave);
      if (yaExiste) {
        toast("Ya existe una variable con ese nombre.", "warning");
        return;
      }
      state.variablesPersonalizadas.push(clave);
      state.variables[clave] = valor;
      el.variableNuevaClave.value = "";
      el.variableNuevaValor.value = "";
      pintarVariablesPersonalizadas();
      pintarChipsVariablesPersonalizar();
      limpiarError("variables");
      programarPrevia();
    }

    if (el.variableAgregar) el.variableAgregar.addEventListener("click", agregarVariablePersonalizada);
    if (el.variableNuevaValor) {
      el.variableNuevaValor.addEventListener("keydown", (e) => {
        if (e.key === "Enter") {
          e.preventDefault();
          agregarVariablePersonalizada();
        }
      });
    }

    if (el.variablesPersonalizadasLista) {
      el.variablesPersonalizadasLista.addEventListener("input", (e) => {
        const input = e.target.closest("[data-variable]");
        if (!input) return;
        state.variables[input.dataset.variable] = input.value;
        programarPrevia();
      });
      el.variablesPersonalizadasLista.addEventListener("click", (e) => {
        const btn = e.target.closest("[data-variable-personalizada-quitar]");
        if (!btn) return;
        const clave = btn.dataset.variablePersonalizadaQuitar;
        state.variablesPersonalizadas = state.variablesPersonalizadas.filter((k) => k !== clave);
        delete state.variables[clave];
        pintarVariablesPersonalizadas();
        pintarChipsVariablesPersonalizar();
        programarPrevia();
      });
    }

    // ---- Personalizar contenido (marca, HTML propio, edición en vivo) --------
    // El contenido (bloques) ya no se edita en un panel de formularios: se
    // edita directo sobre la previa (ver "Editor en vivo sobre la previa" más
    // abajo). Aquí solo queda lo que sigue siendo formulario: marca y HTML
    // propio. No hay autosave por campo; el guardado real va en el payload de
    // guardar()/prueba junto con todo lo demás.

    const ICONOS_BLOQUE = {
      heading: "fa-heading",
      text: "fa-align-left",
      list: "fa-list-ul",
      kpi: "fa-chart-simple",
      button: "fa-hand-pointer",
      image: "fa-image",
      divider: "fa-minus",
      footer: "fa-signature",
    };

    function pintarRedesPersonalizadas() {
      if (!el.personalizarRedes) return;
      const redes = state.marca.redes;
      if (!redes.length) {
        el.personalizarRedes.innerHTML = '<p class="field__hint">Sin enlaces en el pie.</p>';
        return;
      }
      el.personalizarRedes.innerHTML = redes
        .map(
          (r, i) => `
          <div class="cp-red">
            <input class="input" type="text" placeholder="Nombre" value="${escapeHtml(r.nombre)}" data-personalizar-red="${i}" data-personalizar-red-campo="nombre" maxlength="40" aria-label="Nombre del enlace">
            <input class="input u-mono" type="text" placeholder="https://…" value="${escapeHtml(r.url)}" data-personalizar-red="${i}" data-personalizar-red-campo="url" aria-label="URL del enlace">
            <button type="button" class="btn--icon cp-accion-danger" data-personalizar-red-quitar="${i}" title="Quitar"><i class="fa-solid fa-xmark"></i></button>
          </div>`
        )
        .join("");
    }

    function reflejarColorPersonalizado() {
      const actual = String(state.marca.color || "").toUpperCase();
      if (el.personalizarColores) {
        el.personalizarColores.querySelectorAll("[data-color]").forEach((btn) => {
          btn.classList.toggle("is-active", btn.dataset.color.toUpperCase() === actual);
        });
      }
      if (el.personalizarColorLibre && /^#[0-9A-F]{6}$/.test(actual)) el.personalizarColorLibre.value = actual.toLowerCase();
      if (el.personalizarColorHex) el.personalizarColorHex.textContent = actual;
    }

    function reflejarLogoPersonalizado() {
      if (el.personalizarLogoUrlCampo) el.personalizarLogoUrlCampo.hidden = state.marca.logo !== "imagen";
      const select = root.querySelector('[data-personalizar-marca="logo"]');
      if (select) select.value = state.marca.logo || "wordmark";
      const tagline = root.querySelector('[data-personalizar-marca="tagline"]');
      if (tagline) tagline.value = state.marca.tagline || "";
      const logoUrl = root.querySelector('[data-personalizar-marca="logo_url"]');
      if (logoUrl) logoUrl.value = state.marca.logo_url || "";
    }

    function reflejarHtmlPersonalizado() {
      const activo = state.htmlPersonalizado.trim() !== "";
      if (el.personalizarHtmlLongitud) el.personalizarHtmlLongitud.textContent = `${state.htmlPersonalizado.length.toLocaleString("es-MX")} caracteres`;
      el.personalizarSeccionesBloques.forEach((s) => s.classList.toggle("is-atenuado", activo));
      if (el.ayudaBloques) el.ayudaBloques.hidden = activo;
      if (el.ayudaHtmlLibre) el.ayudaHtmlLibre.hidden = !activo;
    }

    function pintarPersonalizarPanel() {
      pintarRedesPersonalizadas();
      reflejarColorPersonalizado();
      reflejarLogoPersonalizado();
      reflejarHtmlPersonalizado();
      if (el.personalizarHtmlCodigo) el.personalizarHtmlCodigo.value = state.htmlPersonalizado;
    }

    function nuevoBloquePersonalizado(tipo) {
      const defecto = (catalogoBloques[tipo] && catalogoBloques[tipo].defecto) || {};
      return Object.assign({ tipo }, clonar(defecto));
    }

    if (el.personalizarToggle) {
      el.personalizarToggle.addEventListener("change", () => {
        state.personalizar = el.personalizarToggle.checked;
        if (el.personalizarPanel) el.personalizarPanel.hidden = !state.personalizar;
        if (state.personalizar && !state.bloques.length && !state.htmlPersonalizado.trim()) {
          // Al activar por primera vez arranca con el contenido de la plantilla como base editable.
          sincronizarContenidoConPlantilla();
          state.personalizar = true;
          pintarPersonalizarPanel();
        }
        if (!state.personalizar) {
          // Se apaga: nada que enganchar en el próximo refresco (previa de solo lectura).
          ocultarFlot();
          ocultarPopover();
        }
        programarPrevia();
      });
    }

    root.addEventListener("click", (e) => {
      const t = e.target;

      if (t.closest("[data-personalizar-reiniciar]")) {
        if (!window.confirm("¿Reiniciar al contenido de la plantilla? Se pierden los cambios de este envío.")) return;
        const antes = state.personalizar;
        state.personalizar = false;
        sincronizarContenidoConPlantilla();
        state.personalizar = antes;
        pintarPersonalizarPanel();
        programarPrevia();
        return;
      }

      if (t.closest("[data-personalizar-html-descartar]")) {
        if (state.htmlPersonalizado.trim() !== "" && !window.confirm("¿Descartar el HTML propio? Los bloques volverán a controlar el correo.")) return;
        state.htmlPersonalizado = "";
        if (el.personalizarHtmlCodigo) el.personalizarHtmlCodigo.value = "";
        if (el.personalizarHtmlToggle) el.personalizarHtmlToggle.checked = false;
        if (el.personalizarHtmlPanel) el.personalizarHtmlPanel.hidden = true;
        reflejarHtmlPersonalizado();
        programarPrevia();
        return;
      }

      const color = t.closest("[data-color]");
      if (color && color.closest("[data-personalizar-colores]")) {
        state.marca.color = color.dataset.color.toUpperCase();
        reflejarColorPersonalizado();
        programarPrevia();
        return;
      }

      if (t.closest("[data-personalizar-red-agregar]")) {
        if (state.marca.redes.length >= 8) {
          toast("Máximo 8 enlaces en el pie.", "warning");
          return;
        }
        state.marca.redes.push({ nombre: "", url: "" });
        pintarRedesPersonalizadas();
        programarPrevia();
        return;
      }

      const quitarRed = t.closest("[data-personalizar-red-quitar]");
      if (quitarRed) {
        state.marca.redes.splice(Number(quitarRed.dataset.personalizarRedQuitar), 1);
        pintarRedesPersonalizadas();
        programarPrevia();
        return;
      }
    });

    root.addEventListener("input", (e) => {
      const t = e.target;

      if (t.hasAttribute && t.hasAttribute("data-personalizar-html-codigo")) {
        state.htmlPersonalizado = t.value;
        reflejarHtmlPersonalizado();
        programarPrevia();
        return;
      }

      if (t.dataset && t.dataset.personalizarMarca) {
        state.marca[t.dataset.personalizarMarca] = t.value;
        if (t.dataset.personalizarMarca === "logo") reflejarLogoPersonalizado();
        programarPrevia();
        return;
      }

      if (t.hasAttribute && t.hasAttribute("data-personalizar-color-libre")) {
        state.marca.color = String(t.value).toUpperCase();
        reflejarColorPersonalizado();
        programarPrevia();
        return;
      }

      if (t.dataset && t.dataset.personalizarRed !== undefined) {
        const r = state.marca.redes[Number(t.dataset.personalizarRed)];
        if (r) r[t.dataset.personalizarRedCampo] = t.value;
        programarPrevia();
        return;
      }
    });

    // ---- Editor en vivo sobre la previa ---------------------------------------
    // Sustituye al panel de formularios de bloques: la edición ocurre
    // directamente sobre el <iframe> de la previa, que el backend anota con
    // data-rp-bloque/data-rp-tipo/data-rp-texto cuando se le pide editor:true
    // (ver contrato en CorreoPlantillasController::preview() y
    // RenderizadorCorreo). Aquí solo se leen/escriben esos atributos; el HTML
    // en sí siempre lo genera el servidor.

    let bloqueHoverIndice = null;
    let ocultarFlotTimer = null;
    let escrituraTimer = null;
    let popoverBloqueIndice = null;
    let elExtremoInicio = null;
    let elExtremoFin = null;
    let elOutlineDivisor = null;
    // Último nodo/rango con foco dentro de un bloque editable de la previa:
    // permite que los chips de variables inserten ahí aunque el clic en el
    // chip (que vive en el documento padre) le quite el foco al iframe antes
    // de que se procese el click.
    let nodoEditableActivoIframe = null;
    let rangoActivoIframe = null;

    function bloquesIframe() {
      try {
        return Array.from(el.previaFrame.contentDocument.querySelectorAll("[data-rp-bloque]"));
      } catch (e) {
        return [];
      }
    }

    function ocultarFlot() {
      if (el.cpFlot) {
        el.cpFlot.hidden = true;
        el.cpFlot.innerHTML = "";
        delete el.cpFlot.dataset.cpFlotIndice;
        delete el.cpFlot.dataset.cpFlotModo;
      }
      bloqueHoverIndice = null;
      if (elOutlineDivisor) elOutlineDivisor.hidden = true;
    }

    function ocultarPopover() {
      if (el.cpPopover) {
        el.cpPopover.hidden = true;
        el.cpPopover.innerHTML = "";
      }
      popoverBloqueIndice = null;
    }

    function programarOcultarFlot() {
      clearTimeout(ocultarFlotTimer);
      // Deja ~150ms para que el mouse cruce del bloque a la barra flotante sin
      // que esta parpadee al ocultarse y volver a mostrarse.
      ocultarFlotTimer = setTimeout(ocultarFlot, 150);
    }

    function asegurarOutlineDivisor() {
      if (elOutlineDivisor) return elOutlineDivisor;
      elOutlineDivisor = document.createElement("div");
      elOutlineDivisor.className = "cp-flot__outline";
      elOutlineDivisor.hidden = true;
      el.previaFrame.parentElement.appendChild(elOutlineDivisor);
      return elOutlineDivisor;
    }

    /** Un divider no tiene texto: se marca con un contorno para saber qué se moverá/borrará. */
    function reflejarOutlineDivisor(indice) {
      const outline = asegurarOutlineDivisor();
      const b = state.bloques[indice];
      const nodo = bloquesIframe()[indice];
      if (!b || !nodo || b.tipo !== "divider") {
        outline.hidden = true;
        return;
      }
      const rect = nodo.getBoundingClientRect();
      const rectIframe = el.previaFrame.getBoundingClientRect();
      outline.style.top = `${rectIframe.top + rect.top}px`;
      outline.style.left = `${rectIframe.left + rect.left}px`;
      outline.style.width = `${rect.width}px`;
      outline.style.height = `${Math.max(rect.height, 10)}px`;
      outline.hidden = false;
    }

    function posicionarFlotEnBloque(indice) {
      const nodo = bloquesIframe()[indice];
      if (!nodo || !el.cpFlot) return false;
      const rect = nodo.getBoundingClientRect();
      const rectIframe = el.previaFrame.getBoundingClientRect();
      el.cpFlot.style.top = `${rectIframe.top + rect.top}px`;
      el.cpFlot.style.left = `${rectIframe.left + rect.left}px`;
      return true;
    }

    /** Reposiciona la barra flotante activa (si hay una) tras scroll/resize. */
    function reposicionarFlotActiva() {
      if (bloqueHoverIndice === null || !el.cpFlot || el.cpFlot.hidden) return;
      if (el.cpFlot.dataset.cpFlotModo === "menu") return; // el menú no sigue al bloque, se cierra si se pierde.
      if (!posicionarFlotEnBloque(bloqueHoverIndice)) {
        ocultarFlot();
        return;
      }
      reflejarOutlineDivisor(bloqueHoverIndice);
    }

    function pintarBarraFlot(indice) {
      if (!el.cpFlot) return;
      const total = state.bloques.length;
      el.cpFlot.dataset.cpFlotModo = "barra";
      el.cpFlot.dataset.cpFlotIndice = String(indice);
      el.cpFlot.innerHTML = `
        <button type="button" class="cp-flot__btn" data-cp-flot-mover="-1" title="Subir bloque" ${indice === 0 ? "disabled" : ""}><i class="fa-solid fa-arrow-up"></i></button>
        <button type="button" class="cp-flot__btn" data-cp-flot-mover="1" title="Bajar bloque" ${indice === total - 1 ? "disabled" : ""}><i class="fa-solid fa-arrow-down"></i></button>
        <button type="button" class="cp-flot__btn" data-cp-flot-duplicar title="Duplicar bloque"><i class="fa-solid fa-copy"></i></button>
        <button type="button" class="cp-flot__btn cp-flot__btn--danger" data-cp-flot-borrar title="Eliminar bloque"><i class="fa-solid fa-trash"></i></button>
        <span class="cp-flot__separador"></span>
        <button type="button" class="cp-flot__btn" data-cp-flot-agregar title="Añadir bloque después"><i class="fa-solid fa-plus"></i></button>
      `;
    }

    function mostrarFlotSobre(indice) {
      if (!state.personalizar || !el.cpFlot) return;
      if (!state.bloques[indice]) return;
      clearTimeout(ocultarFlotTimer);
      bloqueHoverIndice = indice;
      pintarBarraFlot(indice);
      if (!posicionarFlotEnBloque(indice)) return;
      el.cpFlot.hidden = false;
      reflejarOutlineDivisor(indice);
    }

    function menuTiposHtml() {
      return `<div class="cp-flot__menu">${tiposBloque
        .map((tipo) => {
          const label = (catalogoBloques[tipo] && catalogoBloques[tipo].label) || tipo;
          return `<button type="button" class="cp-flot__menu-item" data-cp-flot-tipo="${escapeHtml(tipo)}"><i class="fa-solid ${ICONOS_BLOQUE[tipo] || "fa-square"}"></i>${escapeHtml(label)}</button>`;
        })
        .join("")}</div>`;
    }

    /** Abre el submenú de tipos en la posición actual de data-cp-flot (no la mueve). */
    function mostrarMenuAgregarAqui(indiceDespuesDe) {
      if (!el.cpFlot) return;
      clearTimeout(ocultarFlotTimer);
      el.cpFlot.dataset.cpFlotModo = "menu";
      el.cpFlot.dataset.cpFlotIndiceDespues = String(indiceDespuesDe);
      el.cpFlot.innerHTML = menuTiposHtml();
      el.cpFlot.hidden = false;
    }

    function insertarBloquePersonalizado(despuesDe, tipo) {
      if (state.bloques.length >= 40) {
        toast("Un correo admite como máximo 40 bloques.", "warning");
        return;
      }
      const nuevo = nuevoBloquePersonalizado(tipo);
      let posicion = despuesDe + 1;
      const ultimo = state.bloques[state.bloques.length - 1];
      // El pie va al final: un bloque nuevo se inserta antes de él si existe.
      if (nuevo.tipo !== "footer" && posicion >= state.bloques.length && ultimo && ultimo.tipo === "footer") {
        posicion = state.bloques.length - 1;
      }
      state.bloques.splice(posicion, 0, nuevo);
      programarPrevia();
    }

    function moverBloquePersonalizado(i, dir) {
      const j = i + dir;
      if (j < 0 || j >= state.bloques.length) return;
      const copia = state.bloques.slice();
      [copia[i], copia[j]] = [copia[j], copia[i]];
      state.bloques = copia;
      programarPrevia();
    }

    function duplicarBloquePersonalizado(i) {
      const b = state.bloques[i];
      if (!b) return;
      if (state.bloques.length >= 40) {
        toast("Un correo admite como máximo 40 bloques.", "warning");
        return;
      }
      state.bloques.splice(i + 1, 0, clonar(b));
      programarPrevia();
    }

    function borrarBloquePersonalizado(i) {
      if (!state.bloques[i]) return;
      state.bloques.splice(i, 1);
      ocultarFlot();
      programarPrevia();
    }

    if (el.cpFlot) {
      el.cpFlot.addEventListener("pointerover", () => clearTimeout(ocultarFlotTimer));
      el.cpFlot.addEventListener("pointerout", () => programarOcultarFlot());
      el.cpFlot.addEventListener("click", (e) => {
        const tipoBtn = e.target.closest("[data-cp-flot-tipo]");
        if (tipoBtn) {
          const despues = Number(el.cpFlot.dataset.cpFlotIndiceDespues);
          insertarBloquePersonalizado(Number.isNaN(despues) ? state.bloques.length - 1 : despues, tipoBtn.dataset.cpFlotTipo);
          ocultarFlot();
          return;
        }
        const indice = Number(el.cpFlot.dataset.cpFlotIndice);
        if (Number.isNaN(indice)) return;
        if (e.target.closest("[data-cp-flot-mover]")) {
          moverBloquePersonalizado(indice, Number(e.target.closest("[data-cp-flot-mover]").dataset.cpFlotMover));
          return;
        }
        if (e.target.closest("[data-cp-flot-duplicar]")) {
          duplicarBloquePersonalizado(indice);
          return;
        }
        if (e.target.closest("[data-cp-flot-borrar]")) {
          borrarBloquePersonalizado(indice);
          return;
        }
        if (e.target.closest("[data-cp-flot-agregar]")) {
          mostrarMenuAgregarAqui(indice);
        }
      });
    }

    /** "+" fijos antes del primer bloque y después del último (siempre visibles al personalizar). */
    function asegurarExtremos() {
      const lienzo = el.previaFrame.parentElement;
      if (!lienzo) return;
      if (!elExtremoInicio) {
        elExtremoInicio = document.createElement("button");
        elExtremoInicio.type = "button";
        elExtremoInicio.className = "cp-flot__extremo";
        elExtremoInicio.innerHTML = '<i class="fa-solid fa-plus"></i>';
        elExtremoInicio.setAttribute("aria-label", "Añadir bloque al inicio");
        elExtremoInicio.hidden = true;
        elExtremoInicio.addEventListener("click", () => {
          el.cpFlot.style.top = elExtremoInicio.style.top;
          el.cpFlot.style.left = elExtremoInicio.style.left;
          mostrarMenuAgregarAqui(-1);
        });
        lienzo.appendChild(elExtremoInicio);
      }
      if (!elExtremoFin) {
        elExtremoFin = document.createElement("button");
        elExtremoFin.type = "button";
        elExtremoFin.className = "cp-flot__extremo";
        elExtremoFin.innerHTML = '<i class="fa-solid fa-plus"></i>';
        elExtremoFin.setAttribute("aria-label", "Añadir bloque al final");
        elExtremoFin.hidden = true;
        elExtremoFin.addEventListener("click", () => {
          el.cpFlot.style.top = elExtremoFin.style.top;
          el.cpFlot.style.left = elExtremoFin.style.left;
          mostrarMenuAgregarAqui(state.bloques.length - 1);
        });
        lienzo.appendChild(elExtremoFin);
      }
    }

    function posicionarExtremos() {
      // En HTML propio no hay bloques que mover/añadir: los "+" de los
      // extremos no aplican (ver engancharEdicionHtmlLibre).
      if (!state.personalizar || state.htmlPersonalizado.trim() !== "") {
        if (elExtremoInicio) elExtremoInicio.hidden = true;
        if (elExtremoFin) elExtremoFin.hidden = true;
        return;
      }
      asegurarExtremos();
      let iframeDoc;
      try {
        iframeDoc = el.previaFrame.contentDocument;
      } catch (e) {
        return;
      }
      if (!iframeDoc || !iframeDoc.body) return;
      const rectIframe = el.previaFrame.getBoundingClientRect();
      const bloques = bloquesIframe();
      if (bloques.length) {
        const rPrimero = bloques[0].getBoundingClientRect();
        elExtremoInicio.style.top = `${rectIframe.top + rPrimero.top - 14}px`;
        elExtremoInicio.style.left = `${rectIframe.left + rPrimero.left + rPrimero.width / 2 - 14}px`;
        elExtremoInicio.hidden = false;

        const rUltimo = bloques[bloques.length - 1].getBoundingClientRect();
        elExtremoFin.style.top = `${rectIframe.top + rUltimo.bottom - 14}px`;
        elExtremoFin.style.left = `${rectIframe.left + rUltimo.left + rUltimo.width / 2 - 14}px`;
        elExtremoFin.hidden = false;
      } else {
        const rectBody = iframeDoc.body.getBoundingClientRect();
        elExtremoInicio.style.top = `${rectIframe.top + rectBody.top}px`;
        elExtremoInicio.style.left = `${rectIframe.left + rectBody.left + rectBody.width / 2 - 14}px`;
        elExtremoInicio.hidden = false;
        elExtremoFin.hidden = true;
      }
    }

    // ---- Popover de botón/imagen ----------------------------------------------

    function abrirPopover(indice, tipo, nodoBloque) {
      if (!el.cpPopover) return;
      const b = state.bloques[indice];
      if (!b) return;
      popoverBloqueIndice = indice;
      if (tipo === "button") {
        el.cpPopover.innerHTML = `
          <div class="cp-popover__campo">
            <label class="field__label">Texto del botón</label>
            <input type="text" class="input" data-cp-popover-campo="texto" value="${escapeHtml(b.texto || "")}">
          </div>
          <div class="cp-popover__campo">
            <label class="field__label">Enlace</label>
            <input type="text" class="input u-mono" data-cp-popover-campo="url" value="${escapeHtml(b.url || "")}" placeholder="https://… o {{enlace_reporte}}">
          </div>
          <div class="cp-popover__pie">
            <button type="button" class="btn btn--primary btn--sm" data-cp-popover-aplicar>Aplicar</button>
          </div>`;
      } else if (tipo === "image") {
        el.cpPopover.innerHTML = `
          <div class="cp-popover__campo">
            <label class="field__label">URL de la imagen</label>
            <input type="text" class="input u-mono" data-cp-popover-campo="url" value="${escapeHtml(b.url || "")}" placeholder="https://…">
          </div>
          <div class="cp-popover__campo">
            <label class="field__label">Texto alternativo</label>
            <input type="text" class="input" data-cp-popover-campo="alt" value="${escapeHtml(b.alt || "")}">
          </div>
          <div class="cp-popover__pie">
            <button type="button" class="btn btn--primary btn--sm" data-cp-popover-aplicar>Aplicar</button>
          </div>`;
      } else {
        return;
      }
      const rect = nodoBloque.getBoundingClientRect();
      const rectIframe = el.previaFrame.getBoundingClientRect();
      el.cpPopover.style.top = `${rectIframe.top + rect.bottom + 6}px`;
      el.cpPopover.style.left = `${rectIframe.left + rect.left}px`;
      el.cpPopover.hidden = false;
    }

    if (el.cpPopover) {
      el.cpPopover.addEventListener("click", (e) => {
        if (!e.target.closest("[data-cp-popover-aplicar]")) return;
        if (popoverBloqueIndice === null) return;
        const b = state.bloques[popoverBloqueIndice];
        if (b) {
          el.cpPopover.querySelectorAll("[data-cp-popover-campo]").forEach((input) => {
            b[input.dataset.cpPopoverCampo] = input.value;
          });
        }
        ocultarPopover();
        programarPrevia();
      });
    }

    // Cierra el popover si se hace click fuera de él (no se aplica nada): el
    // click que lo abrió llega desde dentro del iframe, así que nunca coincide
    // con este listener del documento padre.
    document.addEventListener("pointerdown", (e) => {
      if (!el.cpPopover || el.cpPopover.hidden) return;
      if (el.cpPopover.contains(e.target)) return;
      ocultarPopover();
    });

    // ---- Enganche de la edición dentro del iframe ------------------------------

    function marcarEditable(nodo, etiqueta) {
      if (!nodo) return;
      nodo.setAttribute("contenteditable", "true");
      nodo.setAttribute("role", "textbox");
      nodo.setAttribute("aria-label", etiqueta);
    }

    /**
     * Contorno punteado sobre todo lo editable, para que se note sin necesidad
     * de pasar el mouse encima. Va como <style> dentro del propio documento
     * del iframe (srcdoc es un documento aparte: el CSS de este archivo no le
     * llega) y se limpia solo en cada refresco porque srcdoc reemplaza el
     * documento entero.
     */
    function inyectarEstilosEdicion(iframeDoc) {
      if (!iframeDoc.head || iframeDoc.getElementById("cp-editor-estilos")) return;
      const estilo = iframeDoc.createElement("style");
      estilo.id = "cp-editor-estilos";
      estilo.textContent = `
        [contenteditable="true"] { cursor: text; outline: 1px dashed rgba(15,157,110,.4); outline-offset: 2px; border-radius: 2px; transition: outline-color .12s ease, background-color .12s ease; }
        [contenteditable="true"]:hover { outline-color: rgba(15,157,110,.8); background-color: rgba(15,157,110,.06); }
        [contenteditable="true"]:focus { outline: 2px solid rgba(15,157,110,.9); background-color: rgba(15,157,110,.08); }
      `;
      iframeDoc.head.appendChild(estilo);
    }

    function indiceDeBloque(nodoBloque, iframeDoc) {
      const bloques = Array.from(iframeDoc.querySelectorAll("[data-rp-bloque]"));
      return bloques.indexOf(nodoBloque);
    }

    function manejarInputEdicion(e) {
      const t = e.target;
      if (!t.hasAttribute || !t.hasAttribute("contenteditable")) return;
      const nodoBloque = t.closest("[data-rp-bloque]");
      if (!nodoBloque) return;
      const i = Number(nodoBloque.dataset.rpBloque);
      const tipo = nodoBloque.dataset.rpTipo;
      const b = state.bloques[i];
      if (!b) return;

      if (tipo === "heading") {
        b.texto = t.textContent;
      } else if (tipo === "text" || tipo === "footer") {
        // innerText conserva los saltos de línea (cada uno es un párrafo distinto en el render).
        b.texto = t.innerText;
      } else if (tipo === "list") {
        const filas = Array.from(nodoBloque.querySelectorAll("table tr"));
        const fila = t.closest("tr");
        const k = filas.indexOf(fila);
        if (k !== -1) {
          if (!Array.isArray(b.items)) b.items = [];
          b.items[k] = t.textContent;
        }
      } else if (tipo === "kpi") {
        const td = t.closest("td");
        const celdas = Array.from(nodoBloque.querySelectorAll("table.rp-kpi td:not(.rp-gap)"));
        const k = celdas.indexOf(td);
        if (k !== -1 && td) {
          if (!Array.isArray(b.items)) b.items = [];
          if (!b.items[k] || typeof b.items[k] !== "object") b.items[k] = { label: "", valor: "" };
          // Dentro de cada <td> el primer <div> es el valor, el segundo la etiqueta (orden del contrato del backend).
          const divs = Array.from(td.querySelectorAll("div"));
          const posicion = divs.indexOf(t);
          if (posicion === 0) b.items[k].valor = t.textContent;
          else if (posicion === 1) b.items[k].label = t.textContent;
        }
      } else {
        return;
      }

      state.previaDesincronizada = true;
      clearTimeout(escrituraTimer);
      // Debounce independiente del de 500ms de programarPrevia: por si el
      // usuario escribe un párrafo largo sin salir del campo (sin esto, el
      // refresco solo llegaría al perder el foco).
      escrituraTimer = setTimeout(() => {
        if (state.previaDesincronizada) programarPrevia();
      }, 1500);
    }

    function manejarBlurEdicion(e) {
      const t = e.target;
      if (!t.hasAttribute || !t.hasAttribute("contenteditable")) return;
      if (state.previaDesincronizada) programarPrevia();
      // El foco puede haber ido hacia la barra flotante: se decide tras el margen de programarOcultarFlot.
      programarOcultarFlot();
    }

    function manejarFocoEdicion(e, iframeDoc) {
      const editable = e.target.closest && e.target.closest("[contenteditable]");
      if (!editable) return;
      const nodoBloque = editable.closest("[data-rp-bloque]");
      if (!nodoBloque) return;
      const indice = indiceDeBloque(nodoBloque, iframeDoc);
      if (indice !== -1) mostrarFlotSobre(indice);
    }

    function manejarHoverEdicion(e, iframeDoc) {
      const nodoBloque = e.target.closest && e.target.closest("[data-rp-bloque]");
      if (!nodoBloque) return;
      clearTimeout(ocultarFlotTimer);
      const indice = indiceDeBloque(nodoBloque, iframeDoc);
      if (indice !== -1) mostrarFlotSobre(indice);
    }

    function manejarSalidaHover() {
      programarOcultarFlot();
    }

    function manejarClickBotonImagen(e) {
      const nodoBloque = e.target.closest && e.target.closest('[data-rp-bloque][data-rp-tipo="button"], [data-rp-bloque][data-rp-tipo="image"]');
      if (!nodoBloque) return;
      if (e.target.closest("a")) e.preventDefault();
      if (e.target.closest("img")) e.preventDefault();
      const i = Number(nodoBloque.dataset.rpBloque);
      const tipo = nodoBloque.dataset.rpTipo;
      abrirPopover(i, tipo, nodoBloque);
    }

    // ---- Edición de texto genérica para HTML propio ---------------------------
    // Cuando el envío usa HTML propio (state.htmlPersonalizado), el backend no
    // envuelve nada en data-rp-bloque (RenderizadorCorreo::render() solo lo
    // hace para el árbol de bloques, ver documento()/bloque()): el HTML es el
    // que pegó el usuario, de estructura arbitraria. En vez de un editor de
    // bloques, se hace contenteditable cada nodo "hoja de texto" (sin hijos
    // elemento, o solo hijos de formato en línea) y, al editar, se recaptura
    // el documento completo de vuelta a state.htmlPersonalizado. Esto es
    // seguro solo porque editor:true hace que el backend deje los {{marcador}}
    // literales en vez de sustituirlos (ver RenderizadorCorreo::texto()): el
    // round-trip nunca puede hornear un valor de ejemplo sobre un marcador.

    const CP_ETIQUETAS_INLINE = new Set(["SPAN", "STRONG", "B", "EM", "I", "A", "U", "BR", "SMALL", "SUP", "SUB", "MARK", "Q", "ABBR", "CODE", "FONT"]);
    const CP_ETIQUETAS_NO_EDITABLES = new Set(["SCRIPT", "STYLE", "HEAD", "TITLE", "IMG", "BR", "HR", "INPUT", "BUTTON", "SELECT", "TEXTAREA", "IFRAME", "SVG", "META", "LINK", "VIDEO", "AUDIO", "CANVAS"]);

    function esNodoTextoHojaHtmlLibre(nodo) {
      if (!nodo || nodo.nodeType !== 1 || CP_ETIQUETAS_NO_EDITABLES.has(nodo.tagName)) return false;
      const hijos = Array.from(nodo.children);
      if (hijos.length === 0) return nodo.textContent.trim() !== "";
      return hijos.every((h) => CP_ETIQUETAS_INLINE.has(h.tagName)) && nodo.textContent.trim() !== "";
    }

    /** Recorre el árbol marcando el primer nodo-hoja de texto que encuentra en cada rama (no desciende más allá). */
    function marcarEditablesHtmlLibre(nodo) {
      if (!nodo || nodo.nodeType !== 1 || nodo.tagName === "SCRIPT" || nodo.tagName === "STYLE" || nodo.tagName === "HEAD") return;
      if (esNodoTextoHojaHtmlLibre(nodo)) {
        marcarEditable(nodo, "Texto del correo");
        return;
      }
      Array.from(nodo.children).forEach(marcarEditablesHtmlLibre);
    }

    /** Evita que un <a> navegue el iframe al hacer clic para editar el texto que contiene. */
    function manejarClickEnlaceHtmlLibre(e) {
      const a = e.target.closest && e.target.closest("a");
      if (a) e.preventDefault();
    }

    /** Recorta contenteditable/role/aria-label (los puso marcarEditablesHtmlLibre, no son parte del correo) antes de guardar. */
    function capturarHtmlDesdeIframe(iframeDoc) {
      const clon = iframeDoc.documentElement.cloneNode(true);
      clon.querySelectorAll("[contenteditable]").forEach((n) => {
        n.removeAttribute("contenteditable");
        n.removeAttribute("role");
        n.removeAttribute("aria-label");
      });
      return "<!doctype html>\n" + clon.outerHTML;
    }

    function capturarYProgramarHtmlLibre() {
      let iframeDoc;
      try {
        iframeDoc = el.previaFrame.contentDocument;
      } catch (e) {
        return;
      }
      if (!iframeDoc || !iframeDoc.documentElement) return;
      state.htmlPersonalizado = capturarHtmlDesdeIframe(iframeDoc);
      state.previaDesincronizada = false;
      if (el.personalizarHtmlCodigo) el.personalizarHtmlCodigo.value = state.htmlPersonalizado;
      reflejarHtmlPersonalizado();
      programarPrevia();
    }

    function manejarInputHtmlLibre(e) {
      const t = e.target;
      if (!t.hasAttribute || !t.hasAttribute("contenteditable")) return;
      state.previaDesincronizada = true;
      clearTimeout(escrituraTimer);
      // Mismo debounce de 1500ms que la edición por bloques: por si el usuario
      // escribe un párrafo largo sin salir del campo.
      escrituraTimer = setTimeout(() => {
        if (state.previaDesincronizada) capturarYProgramarHtmlLibre();
      }, 1500);
    }

    function manejarBlurHtmlLibre(e) {
      const t = e.target;
      if (!t.hasAttribute || !t.hasAttribute("contenteditable")) return;
      if (state.previaDesincronizada) capturarYProgramarHtmlLibre();
    }

    function engancharEdicionHtmlLibre(iframeDoc) {
      marcarEditablesHtmlLibre(iframeDoc.body);
      iframeDoc.body.addEventListener("input", manejarInputHtmlLibre);
      iframeDoc.body.addEventListener("focusout", manejarBlurHtmlLibre);
      iframeDoc.body.addEventListener("click", manejarClickEnlaceHtmlLibre);
      iframeDoc.addEventListener("selectionchange", () => manejarCambioSeleccionEdicion(iframeDoc));
    }

    /**
     * Recuerda dónde estaba el cursor dentro de un nodo editable de la previa,
     * para que un clic en un chip de variable (que vive en el documento padre
     * y por tanto le quita el foco al iframe antes de procesarse) sepa dónde
     * insertar el marcador. Se actualiza en cada cambio de selección, no solo
     * al enfocar, para que el usuario pueda mover el cursor dentro del texto
     * antes de pulsar el chip.
     */
    function manejarCambioSeleccionEdicion(iframeDoc) {
      const win = iframeDoc.defaultView;
      const sel = win && win.getSelection();
      if (!sel || sel.rangeCount === 0) return;
      const rango = sel.getRangeAt(0);
      const contenedor = rango.commonAncestorContainer;
      const base = contenedor.nodeType === 1 ? contenedor : contenedor.parentElement;
      const nodoEditable = base && base.closest ? base.closest("[contenteditable]") : null;
      if (!nodoEditable) return;
      nodoEditableActivoIframe = nodoEditable;
      rangoActivoIframe = rango.cloneRange();
    }

    /** Inserta `{{clave}}` en el último punto de edición recordado dentro de la previa. */
    function insertarVariableEnPrevia(clave) {
      const iframeDoc = el.previaFrame.contentDocument;
      if (!nodoEditableActivoIframe || !iframeDoc || !iframeDoc.contains(nodoEditableActivoIframe)) return false;

      const win = iframeDoc.defaultView;
      const marcador = `{{${clave}}}`;
      nodoEditableActivoIframe.focus();

      const rangoValido = rangoActivoIframe && nodoEditableActivoIframe.contains(rangoActivoIframe.commonAncestorContainer);
      const rango = rangoValido ? rangoActivoIframe.cloneRange() : iframeDoc.createRange();
      if (!rangoValido) {
        rango.selectNodeContents(nodoEditableActivoIframe);
        rango.collapse(false);
      }

      rango.deleteContents();
      const nodoTexto = iframeDoc.createTextNode(marcador);
      rango.insertNode(nodoTexto);
      rango.setStartAfter(nodoTexto);
      rango.collapse(true);

      const sel = win.getSelection();
      sel.removeAllRanges();
      sel.addRange(rango);
      rangoActivoIframe = rango.cloneRange();

      nodoEditableActivoIframe.dispatchEvent(new win.Event("input", { bubbles: true }));

      return true;
    }

    /**
     * Engancha (o limpia) la edición en vivo tras cada carga del iframe.
     * Se reengancha por completo en cada refresco porque `srcdoc` reemplaza el
     * documento entero del iframe (contentDocument es un objeto nuevo cada
     * vez): no hay nodos ni listeners previos que sobrevivan.
     */
    function engancharEdicionPrevia() {
      let iframeDoc;
      try {
        iframeDoc = el.previaFrame.contentDocument;
      } catch (e) {
        return;
      }
      if (!iframeDoc || !iframeDoc.body) return;

      ocultarFlot();
      ocultarPopover();
      // srcdoc crea un documento nuevo en cada refresco: las referencias del
      // refresco anterior ya no existen en este documento.
      nodoEditableActivoIframe = null;
      rangoActivoIframe = null;

      // El click en botón/imagen abre el popover incluso en previa de solo
      // lectura sería confuso; solo se activa si se está personalizando.
      if (!state.personalizar) {
        posicionarExtremos();
        return;
      }

      inyectarEstilosEdicion(iframeDoc);

      // HTML propio: no hay data-rp-bloque (el backend no envuelve el html
      // libre), así que se usa el motor de edición de texto genérico en vez
      // del de bloques.
      if (state.htmlPersonalizado.trim() !== "") {
        engancharEdicionHtmlLibre(iframeDoc);
        posicionarExtremos();
        return;
      }

      const bloques = Array.from(iframeDoc.querySelectorAll("[data-rp-bloque]"));
      bloques.forEach((nodoBloque) => {
        const tipo = nodoBloque.dataset.rpTipo;
        if (tipo === "heading") {
          marcarEditable(nodoBloque.querySelector("h1"), "Texto del encabezado");
        } else if (tipo === "text") {
          marcarEditable(nodoBloque.querySelector("[data-rp-texto]"), "Texto del párrafo");
        } else if (tipo === "footer") {
          marcarEditable(nodoBloque.querySelector("[data-rp-texto]"), "Texto del pie");
        } else if (tipo === "list") {
          nodoBloque.querySelectorAll("table tr").forEach((tr, k) => {
            const celdas = tr.querySelectorAll("td");
            marcarEditable(celdas[1], `Punto ${k + 1}`);
          });
        } else if (tipo === "kpi") {
          nodoBloque.querySelectorAll("table.rp-kpi td:not(.rp-gap)").forEach((td, k) => {
            const divs = td.querySelectorAll("div");
            marcarEditable(divs[0], `Valor ${k + 1}`);
            marcarEditable(divs[1], `Etiqueta ${k + 1}`);
          });
        }
      });

      // Un solo listener delegado por evento (no uno por nodo): así no hace
      // falta reengancharlos nodo a nodo, solo reasignarlos aquí en cada
      // refresco (que sí crea un documento nuevo).
      iframeDoc.body.addEventListener("input", manejarInputEdicion);
      // focusout en vez de blur: blur no burbujea y aquí se delega en <body>.
      iframeDoc.body.addEventListener("focusout", manejarBlurEdicion);
      iframeDoc.body.addEventListener("focusin", (e) => manejarFocoEdicion(e, iframeDoc));
      iframeDoc.body.addEventListener("pointerover", (e) => manejarHoverEdicion(e, iframeDoc));
      iframeDoc.body.addEventListener("pointerout", manejarSalidaHover);
      iframeDoc.body.addEventListener("click", manejarClickBotonImagen);
      iframeDoc.addEventListener("selectionchange", () => manejarCambioSeleccionEdicion(iframeDoc));
      if (iframeDoc.defaultView) iframeDoc.defaultView.addEventListener("scroll", reposicionarFlotActiva);

      posicionarExtremos();
    }

    if (el.previaFrame) {
      // srcdoc dispara el evento "load" del iframe de forma fiable en todos los
      // navegadores evergreen (Chrome, Firefox, Safari, Edge): es parte del
      // ciclo de vida estándar del iframe, no algo específico de src=URL. Se
      // usa un único listener persistente (el iframe nunca se recrea) en vez
      // de un setTimeout(0) tras asignar srcdoc, que sería una señal más floja
      // (no garantiza que el documento ya haya terminado de parsear).
      el.previaFrame.addEventListener("load", engancharEdicionPrevia);
      // Los "+" de los extremos son position:fixed y se calculan a mano (no
      // siguen el layout por CSS): sin este listener se quedan pegados en el
      // píxel donde se calcularon la última vez y "flotan" sueltos sobre el
      // contenido al hacer scroll de la página.
      window.addEventListener("scroll", () => {
        reposicionarFlotActiva();
        posicionarExtremos();
      });
      window.addEventListener("resize", () => {
        reposicionarFlotActiva();
        posicionarExtremos();
      });
      // El mouse puede salir del iframe sin disparar pointerout/mouseout
      // DENTRO del iframe (comportamiento inconsistente entre navegadores al
      // cruzar el borde de un iframe): escuchar la salida sobre el propio
      // elemento <iframe>, en el documento padre, sí es fiable siempre y
      // evita que la barra de controles se quede "pegada" visible.
      el.previaFrame.addEventListener("pointerleave", () => programarOcultarFlot());
    }

    if (el.personalizarHtmlToggle) {
      el.personalizarHtmlToggle.addEventListener("change", () => {
        const activar = el.personalizarHtmlToggle.checked;
        if (!activar && state.htmlPersonalizado.trim() !== "") {
          if (!window.confirm("¿Descartar el HTML propio? Los bloques volverán a controlar el correo.")) {
            el.personalizarHtmlToggle.checked = true;
            return;
          }
          state.htmlPersonalizado = "";
          if (el.personalizarHtmlCodigo) el.personalizarHtmlCodigo.value = "";
          reflejarHtmlPersonalizado();
          programarPrevia();
        }
        if (el.personalizarHtmlPanel) el.personalizarHtmlPanel.hidden = !activar;
        if (activar && el.personalizarHtmlCodigo) el.personalizarHtmlCodigo.focus();
      });
    }

    // Insertar variable: primero se intenta en el último punto de edición
    // dentro de la previa (texto de un bloque); si no hay ninguno activo, en
    // el campo [data-var-target] con foco (hoy solo el HTML propio avanzado).
    let campoActivoPersonalizar = null;
    root.addEventListener("focusin", (e) => {
      if (e.target.matches && e.target.matches("[data-var-target]")) campoActivoPersonalizar = e.target;
    });
    if (el.personalizarVariables) {
      el.personalizarVariables.addEventListener("click", (e) => {
        const chip = e.target.closest("[data-var]");
        if (!chip) return;
        e.preventDefault();

        if (insertarVariableEnPrevia(chip.dataset.var)) return;

        const objetivo = campoActivoPersonalizar && root.contains(campoActivoPersonalizar) ? campoActivoPersonalizar : null;
        if (!objetivo) {
          toast("Haz clic primero en el texto del correo (en la previa) o en el campo donde quieres insertar la variable.", "warning");
          return;
        }
        const marcador = `{{${chip.dataset.var}}}`;
        const inicio = objetivo.selectionStart ?? objetivo.value.length;
        const fin = objetivo.selectionEnd ?? objetivo.value.length;
        objetivo.setRangeText(marcador, inicio, fin, "end");
        objetivo.focus();
        objetivo.dispatchEvent(new Event("input", { bubbles: true }));
      });
    }

    function pintarChipsVariablesPersonalizar() {
      if (!el.personalizarVariables) return;
      const todas = Object.assign({}, catalogo);
      state.variablesPersonalizadas.forEach((k) => {
        if (!todas[k]) todas[k] = { label: k, ejemplo: "" };
      });
      el.personalizarVariables.innerHTML = Object.keys(todas)
        .map((k) => `<button type="button" class="cp-chip cp-chip--var u-mono" data-var="${escapeHtml(k)}" title="${escapeHtml(todas[k].label)}">{{${escapeHtml(k)}}}</button>`)
        .join("");
    }

    // ---- 5 · Cuándo ----------------------------------------------------------

    function aplicarCuando() {
      el.programarCampo.hidden = state.cuando !== "programar";
      el.cuando.forEach((r) => {
        r.checked = r.value === state.cuando;
        r.closest(".correo-cuando__opcion")?.classList.toggle("is-active", r.value === state.cuando);
      });
      actualizarAccionPrincipal();
      actualizarPreviaMeta();
    }

    el.cuando.forEach((r) =>
      r.addEventListener("change", () => {
        state.cuando = r.value;
        if (state.cuando === "programar" && !state.programadoPara) {
          // Sugerencia: mañana a las 9:00, hora local del navegador.
          const d = new Date();
          d.setDate(d.getDate() + 1);
          d.setHours(9, 0, 0, 0);
          state.programadoPara = fechaLocal(d);
          el.programadoPara.value = state.programadoPara;
        }
        aplicarCuando();
      })
    );

    el.programadoPara.value = state.programadoPara;
    el.programadoPara.addEventListener("input", () => {
      state.programadoPara = el.programadoPara.value;
      limpiarError("programado_para");
      actualizarPreviaMeta();
    });

    function fechaLocal(d) {
      const p = (n) => String(n).padStart(2, "0");
      return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}T${p(d.getHours())}:${p(d.getMinutes())}`;
    }

    function actualizarAccionPrincipal() {
      const n = state.destinatarios.length;
      if (state.cuando === "programar") {
        el.accionPrincipal.dataset.accion = "programar";
        el.accionPrincipal.querySelector("i").className = "fa-solid fa-clock";
        el.accionPrincipalTexto.textContent = n ? `Programar envío a ${n}` : "Programar envío";
      } else {
        el.accionPrincipal.dataset.accion = "enviar";
        el.accionPrincipal.querySelector("i").className = "fa-solid fa-paper-plane";
        el.accionPrincipalTexto.textContent = n ? `Enviar a ${n}` : "Enviar ahora";
      }
    }

    // ---- Previa --------------------------------------------------------------

    function actualizarPreviaMeta() {
      const p = plantillaActual();
      el.previaPlantilla.textContent = p ? p.nombre : "Sin plantilla";
      const de = state.remitenteEmail || datos.remitente?.email || "";
      el.previaDe.textContent = state.remitenteNombre ? `${state.remitenteNombre} <${de}>` : de || "—";
      const emails = state.destinatarios.map((d) => d.email);
      el.previaPara.textContent = emails.length
        ? emails.slice(0, 3).join(", ") + (emails.length > 3 ? ` y ${emails.length - 3} más` : "")
        : "—";
      el.previaAsunto.textContent = state.asunto.trim() ? sustituirEjemplo(state.asunto) : "(sin asunto)";
      el.previaSalida.textContent =
        state.cuando === "programar"
          ? `Programada · ${state.programadoPara ? state.programadoPara.replace("T", " ") : "sin fecha"}`
          : "Inmediata al confirmar";
    }

    /** Variables para la previa: las del envío (o su ejemplo si están vacías) + ejemplos de persona. */
    function variablesPrevia() {
      const v = {};
      Object.keys(catalogo).forEach((k) => {
        v[k] = catalogo[k].ejemplo || "";
      });
      Object.keys(state.variables).forEach((k) => {
        if (String(state.variables[k] || "").trim()) v[k] = state.variables[k];
      });
      return v;
    }

    function sustituirEjemplo(texto) {
      const v = variablesPrevia();
      return String(texto).replace(/\{\{\s*([a-z_]+)\s*\}\}/gi, (m, k) => v[k.toLowerCase()] ?? "");
    }

    let previaSerie = 0;

    function actualizarPrevia() {
      actualizarPreviaMeta();
      const p = plantillaActual();
      if (!p) {
        el.previaFrame.srcdoc = "";
        el.previaEstado.hidden = false;
        el.previaEstado.textContent = "Elige una plantilla para ver la previa.";
        return;
      }
      const serie = ++previaSerie;
      el.previaEstado.hidden = false;
      el.previaEstado.textContent = "Generando previa…";

      fetch(urls.preview, {
        method: "POST",
        headers: { ...jsonHeaders(), Accept: "text/html, application/json" },
        body: JSON.stringify({
          plantilla_id: p.id,
          // state.bloques/marca/htmlPersonalizado reflejan la plantilla elegida
          // cuando no se está personalizando (sincronizarContenidoConPlantilla),
          // o el contenido propio del envío cuando sí.
          bloques: state.bloques || [],
          marca: state.marca || {},
          html_personalizado: state.htmlPersonalizado || null,
          asunto: state.asunto,
          variables: variablesPrevia(),
          // El editor en vivo (engancharEdicionPrevia) necesita que el backend
          // anote cada bloque con data-rp-bloque/data-rp-tipo; se pide siempre
          // desde este archivo, sin importar si se está personalizando (con la
          // previa de solo lectura los wrappers no estorban, solo no se usan).
          editor: true,
        }),
      })
        .then((res) => {
          const tipo = res.headers.get("content-type") || "";
          return res.text().then((texto) => {
            if (!res.ok) throw new Error("preview_failed");
            // La previa de plantillas puede devolver HTML directo o {html}.
            if (tipo.includes("application/json")) {
              try {
                const body = JSON.parse(texto);
                return body.html ?? "";
              } catch (e) {
                return texto;
              }
            }
            return texto;
          });
        })
        .then((html) => {
          if (serie !== previaSerie) return;
          el.previaFrame.srcdoc = html;
          el.previaEstado.hidden = true;
        })
        .catch(() => {
          if (serie !== previaSerie) return;
          el.previaEstado.hidden = false;
          el.previaEstado.textContent = "No se pudo generar la previa.";
        });
    }

    const programarPrevia = debounce(actualizarPrevia, 500);

    initPreviaAncho(root);

    // ---- Errores -------------------------------------------------------------

    function limpiarError(campo) {
      const span = root.querySelector(`[data-error-for="${campo}"]`);
      if (span) span.textContent = "";
    }

    function limpiarErrores() {
      root.querySelectorAll("[data-error-for]").forEach((s) => (s.textContent = ""));
      el.formError.hidden = true;
      el.formError.textContent = "";
    }

    /** Errores 422: "destinatarios.2.email" se pinta bajo "destinatarios", "variables.mes" bajo "variables". */
    function pintarErrores(body) {
      const errors = (body && body.errors) || {};
      const pintados = new Set();
      Object.keys(errors).forEach((clave) => {
        const raiz = clave.split(".")[0];
        const span = root.querySelector(`[data-error-for="${raiz}"]`);
        const msg = Array.isArray(errors[clave]) ? errors[clave][0] : String(errors[clave]);
        if (span && !pintados.has(raiz)) {
          span.textContent = msg;
          pintados.add(raiz);
        }
      });
      const general = (body && body.message) || "Revisa los campos marcados.";
      el.formError.textContent = Object.keys(errors).length ? "Revisa los campos marcados." : general;
      el.formError.hidden = false;
      root.querySelector("[data-error-for]:not(:empty)")?.closest(".correo-paso")?.scrollIntoView({ behavior: "smooth", block: "center" });
    }

    // ---- Guardar / enviar / programar ---------------------------------------

    function variablesActuales() {
      const variables = {};
      const p = plantillaActual();
      (p ? p.variables_envio || [] : []).forEach((k) => {
        variables[k] = state.variables[k] || "";
      });
      // Las personalizadas viajan siempre, sin importar qué declare la plantilla elegida.
      state.variablesPersonalizadas.forEach((k) => {
        variables[k] = state.variables[k] || "";
      });
      return variables;
    }

    function payload(accion) {
      return {
        plantilla_id: state.plantillaId,
        asunto: state.asunto,
        remitente_nombre: state.remitenteNombre || null,
        remitente_email: state.remitenteEmail || null,
        variables: variablesActuales(),
        destinatarios: state.destinatarios.map((d) => ({
          cliente_id: d.cliente_id || null,
          email: d.email,
          nombre: d.nombre || null,
          variables: d.variables || {},
        })),
        accion,
        programado_para: accion === "programar" ? state.programadoPara || null : null,
        personalizar: state.personalizar,
        bloques: state.bloques,
        marca: state.marca,
        html_personalizado: state.htmlPersonalizado,
      };
    }

    function bloquear(bloqueado) {
      form.querySelectorAll("button").forEach((b) => (b.disabled = bloqueado));
    }

    function guardar(accion) {
      limpiarErrores();
      if (!state.plantillaId) {
        pintarErrores({ errors: { plantilla_id: ["Elige una plantilla."] } });
        return;
      }
      const n = state.destinatarios.length;
      if (accion !== "borrador" && n === 0) {
        pintarErrores({ errors: { destinatarios: ["Agrega al menos un destinatario."] } });
        return;
      }
      if (accion === "programar" && !state.programadoPara) {
        pintarErrores({ errors: { programado_para: ["Indica la fecha y hora del envío."] } });
        return;
      }
      if (accion === "enviar" && !window.confirm(`¿Enviar ahora a ${n} ${n === 1 ? "destinatario" : "destinatarios"}? Los correos salen en este momento.`)) return;

      bloquear(true);
      const boton = accion === "borrador" ? root.querySelector('[data-accion="borrador"]') : el.accionPrincipal;
      const htmlOriginal = boton.innerHTML;
      if (accion === "enviar") boton.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Enviando…';

      const url = envio ? envio.update_url : urls.store;
      request(url, envio ? "PUT" : "POST", payload(accion))
        .then((data) => {
          toast(data.mensaje || "Guardado.", data.ok === false ? "warning" : "success");
          window.location.href = data.show_url || urls.index;
        })
        .catch((err) => {
          bloquear(false);
          boton.innerHTML = htmlOriginal;
          if (err.status === 422) pintarErrores(err.body);
          else {
            el.formError.textContent = mensajeDe(err, "No se pudo guardar el envío.");
            el.formError.hidden = false;
          }
        });
    }

    form.querySelectorAll("[data-accion]").forEach((b) =>
      b.addEventListener("click", () => guardar(b.dataset.accion))
    );

    form.addEventListener("submit", (e) => e.preventDefault());

    // ---- Prueba --------------------------------------------------------------

    root.querySelector("[data-accion-prueba]")?.addEventListener("click", (e) => {
      const btn = e.currentTarget;
      limpiarErrores();
      if (!state.plantillaId) {
        pintarErrores({ errors: { plantilla_id: ["Elige una plantilla para mandar la prueba."] } });
        return;
      }
      if (!state.asunto.trim()) {
        pintarErrores({ errors: { asunto: ["Escribe un asunto para mandar la prueba."] } });
        return;
      }
      const destino = datos.usuario_email || "tu correo";
      btn.disabled = true;
      const html = btn.innerHTML;
      btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Enviando prueba…';
      const p = payload("borrador");
      request(urls.prueba, "POST", {
        plantilla_id: p.plantilla_id,
        asunto: p.asunto,
        variables: p.variables,
        remitente_nombre: p.remitente_nombre,
        remitente_email: p.remitente_email,
        personalizar: p.personalizar,
        bloques: p.bloques,
        marca: p.marca,
        html_personalizado: p.html_personalizado,
      })
        .then((data) => toast(data.mensaje || `Prueba enviada a ${destino}.`, "success"))
        .catch((err) => {
          if (err.status === 422 && err.body && err.body.errors) pintarErrores(err.body);
          else toast(mensajeDe(err, "No se pudo enviar la prueba."), "error");
        })
        .finally(() => {
          btn.disabled = false;
          btn.innerHTML = html;
        });
    });

    // ---- Arranque ------------------------------------------------------------

    pintarPlantillas();
    pintarChips();
    pintarVariables();
    pintarVariablesPersonalizadas();
    pintarChipsVariablesPersonalizar();
    aplicarCuando();

    if (el.personalizarToggle) el.personalizarToggle.checked = state.personalizar;
    if (el.personalizarPanel) el.personalizarPanel.hidden = !state.personalizar;
    const htmlActivo = state.htmlPersonalizado.trim() !== "";
    if (el.personalizarHtmlToggle) el.personalizarHtmlToggle.checked = htmlActivo;
    if (el.personalizarHtmlPanel) el.personalizarHtmlPanel.hidden = !htmlActivo;
    // Un envío en edición con HTML propio ya guardado abre el disclosure de
    // una vez: si no, quedaría escondido justo el contenido que importa ver.
    if (el.detallesAvanzado && htmlActivo) el.detallesAvanzado.open = true;
    pintarPersonalizarPanel();

    // Un envío nuevo arranca con la primera plantilla activa elegida para que
    // la previa enseñe algo; en edición se respeta la guardada.
    if (!state.plantillaId && plantillas.length) {
      elegirPlantilla(plantillas[0].id);
    } else {
      // sincronizarContenidoConPlantilla() no toca nada si state.personalizar
      // ya es true (envío en edición con contenido propio guardado).
      sincronizarContenidoConPlantilla();
      pintarPersonalizarPanel();
      actualizarPrevia();
    }
  }

  document.addEventListener("shell:ready", () => {
    initIndex();
    initShow();
    initRedactar();
  });
})();
