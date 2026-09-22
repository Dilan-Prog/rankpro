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
      personalizarBloques: root.querySelector("[data-personalizar-bloques]"),
      personalizarBloquesVacio: root.querySelector("[data-personalizar-bloques-vacio]"),
      personalizarPaleta: root.querySelector("[data-personalizar-paleta]"),
      personalizarRedes: root.querySelector("[data-personalizar-redes]"),
      personalizarColores: root.querySelector("[data-personalizar-colores]"),
      personalizarColorLibre: root.querySelector("[data-personalizar-color-libre]"),
      personalizarColorHex: root.querySelector("[data-personalizar-color-hex]"),
      personalizarLogoUrlCampo: root.querySelector("[data-personalizar-logo-url-campo]"),
      personalizarSeccionesBloques: Array.from(root.querySelectorAll("[data-personalizar-seccion-bloques]")),
      variablesPersonalizadasLista: root.querySelector("[data-variables-personalizadas-lista]"),
      variableNuevaClave: root.querySelector("[data-variable-nueva-clave]"),
      variableNuevaValor: root.querySelector("[data-variable-nueva-valor]"),
      variableAgregar: root.querySelector("[data-variable-agregar]"),
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

    // ---- Personalizar contenido (bloques, marca, HTML propio) ----------------
    // Mismo motor que el editor de Plantillas (resources/js/correo-plantillas.js):
    // aquí no hay autosave por campo, solo se mantiene `state.bloques/marca/
    // htmlPersonalizado` al día y se dispara la previa; el guardado real va en
    // el payload de guardar()/prueba junto con todo lo demás.

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
    const MAX_KPI = 4;
    const MIN_KPI = 2;
    const MAX_ITEMS = 12;

    function campoBloqueHtml(i, campo, valor, opciones) {
      const o = opciones || {};
      const extra = o.mono ? " u-mono" : "";
      const attrs = `data-personalizar-bloque="${i}" data-personalizar-campo-bloque="${campo}" data-var-target${o.placeholder ? ` placeholder="${escapeHtml(o.placeholder)}"` : ""}${o.label ? ` aria-label="${escapeHtml(o.label)}"` : ""}`;
      if (o.textarea) return `<textarea class="textarea${extra}" rows="${o.rows || 4}" ${attrs}>${escapeHtml(valor)}</textarea>`;
      return `<input class="input${extra}" type="text" value="${escapeHtml(valor)}" ${attrs}>`;
    }

    function cuerpoBloquePersonalizado(b, i) {
      switch (b.tipo) {
        case "heading":
          return `
            ${campoBloqueHtml(i, "texto", b.texto, { label: "Texto del encabezado" })}
            <div class="cp-segmento cp-segmento--inline" role="group" aria-label="Alineación">
              <button type="button" class="cp-segmento__btn ${b.alineacion !== "centro" ? "is-active" : ""}" data-personalizar-bloque="${i}" data-personalizar-alineacion="izquierda">Izquierda</button>
              <button type="button" class="cp-segmento__btn ${b.alineacion === "centro" ? "is-active" : ""}" data-personalizar-bloque="${i}" data-personalizar-alineacion="centro">Centrado</button>
            </div>`;
        case "text":
          return campoBloqueHtml(i, "texto", b.texto, { textarea: true, rows: 4, label: "Texto del párrafo", placeholder: "Cada salto de línea es un párrafo." });
        case "footer":
          return campoBloqueHtml(i, "texto", b.texto, { textarea: true, rows: 3, label: "Texto del pie" });
        case "list": {
          const items = Array.isArray(b.items) ? b.items : [];
          const filas = items
            .map(
              (it, k) => `
              <div class="cp-item">
                <input class="input" type="text" value="${escapeHtml(it)}" data-personalizar-bloque="${i}" data-personalizar-item="${k}" data-var-target aria-label="Punto ${k + 1}">
                <button type="button" class="btn--icon cp-accion-danger" data-personalizar-bloque="${i}" data-personalizar-item-quitar="${k}" title="Quitar punto"><i class="fa-solid fa-xmark"></i></button>
              </div>`
            )
            .join("");
          return `${filas}${items.length < MAX_ITEMS ? `<button type="button" class="cp-link" data-personalizar-bloque="${i}" data-personalizar-item-agregar>+ Añadir punto</button>` : ""}`;
        }
        case "kpi": {
          const items = Array.isArray(b.items) ? b.items : [];
          const filas = items
            .map(
              (it, k) => `
              <div class="cp-item cp-item--kpi">
                <input class="input" type="text" placeholder="Etiqueta" value="${escapeHtml(it && it.label)}" data-personalizar-bloque="${i}" data-personalizar-item="${k}" data-personalizar-sub="label" data-var-target aria-label="Etiqueta ${k + 1}">
                <input class="input u-mono" type="text" placeholder="Valor" value="${escapeHtml(it && it.valor)}" data-personalizar-bloque="${i}" data-personalizar-item="${k}" data-personalizar-sub="valor" data-var-target aria-label="Valor ${k + 1}">
                <button type="button" class="btn--icon cp-accion-danger" data-personalizar-bloque="${i}" data-personalizar-item-quitar="${k}" title="Quitar cifra" ${items.length <= MIN_KPI ? "disabled" : ""}><i class="fa-solid fa-xmark"></i></button>
              </div>`
            )
            .join("");
          return `${filas}${items.length < MAX_KPI ? `<button type="button" class="cp-link" data-personalizar-bloque="${i}" data-personalizar-item-agregar>+ Añadir cifra</button>` : `<p class="field__hint">Máximo ${MAX_KPI} cifras.</p>`}`;
        }
        case "button":
          return `
            <div class="field"><span class="field__label">Texto del botón</span>${campoBloqueHtml(i, "texto", b.texto, { label: "Texto del botón" })}</div>
            <div class="field"><span class="field__label">Enlace</span>${campoBloqueHtml(i, "url", b.url, { mono: true, label: "Enlace del botón", placeholder: "https://… o {{enlace_reporte}}" })}</div>`;
        case "image":
          return `
            <div class="field"><span class="field__label">URL de la imagen</span>${campoBloqueHtml(i, "url", b.url, { mono: true, label: "URL de la imagen", placeholder: "https://…" })}</div>
            <div class="field"><span class="field__label">Texto alternativo</span>${campoBloqueHtml(i, "alt", b.alt, { label: "Texto alternativo" })}</div>`;
        case "divider":
          return '<p class="field__hint">Línea divisoria de 1px, sin opciones.</p>';
        default:
          return `<p class="field__hint">Tipo de bloque desconocido: ${escapeHtml(b.tipo)}.</p>`;
      }
    }

    function pintarBloquesPersonalizados() {
      if (!el.personalizarBloques) return;
      const total = state.bloques.length;
      el.personalizarBloques.innerHTML = state.bloques
        .map((b, i) => {
          const label = (catalogoBloques[b.tipo] && catalogoBloques[b.tipo].label) || b.tipo;
          return `
          <div class="card cp-bloque" data-personalizar-bloque-card="${i}">
            <div class="cp-bloque__cabecera">
              <div class="cp-bloque__titulo">
                <i class="fa-solid ${ICONOS_BLOQUE[b.tipo] || "fa-square"} cp-bloque__icono"></i>
                <span>${escapeHtml(label)}</span>
                <span class="u-mono cp-muted">#${i + 1}</span>
              </div>
              <div class="cp-bloque__acciones">
                <button type="button" class="btn--icon" data-personalizar-bloque="${i}" data-personalizar-mover="-1" title="Subir" ${i === 0 ? "disabled" : ""}><i class="fa-solid fa-arrow-up"></i></button>
                <button type="button" class="btn--icon" data-personalizar-bloque="${i}" data-personalizar-mover="1" title="Bajar" ${i === total - 1 ? "disabled" : ""}><i class="fa-solid fa-arrow-down"></i></button>
                <button type="button" class="btn--icon" data-personalizar-bloque="${i}" data-personalizar-duplicar-bloque title="Duplicar bloque"><i class="fa-solid fa-copy"></i></button>
                <button type="button" class="btn--icon cp-accion-danger" data-personalizar-bloque="${i}" data-personalizar-quitar-bloque title="Eliminar bloque"><i class="fa-solid fa-trash"></i></button>
              </div>
            </div>
            <div class="cp-bloque__cuerpo">${cuerpoBloquePersonalizado(b, i)}</div>
          </div>`;
        })
        .join("");
      if (el.personalizarBloquesVacio) el.personalizarBloquesVacio.hidden = total !== 0;
    }

    function pintarPaletaPersonalizada() {
      if (!el.personalizarPaleta) return;
      el.personalizarPaleta.innerHTML = tiposBloque
        .map((tipo) => {
          const label = (catalogoBloques[tipo] && catalogoBloques[tipo].label) || tipo;
          return `<button type="button" class="cp-paleta__btn" data-personalizar-agregar="${escapeHtml(tipo)}"><i class="fa-solid ${ICONOS_BLOQUE[tipo] || "fa-square"}"></i>${escapeHtml(label)}</button>`;
        })
        .join("");
    }

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
    }

    function pintarPersonalizarPanel() {
      pintarBloquesPersonalizados();
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

      const agregar = t.closest("[data-personalizar-agregar]");
      if (agregar) {
        if (state.bloques.length >= 40) {
          toast("Un correo admite como máximo 40 bloques.", "warning");
          return;
        }
        const nuevo = nuevoBloquePersonalizado(agregar.dataset.personalizarAgregar);
        const ultimo = state.bloques[state.bloques.length - 1];
        if (nuevo.tipo !== "footer" && ultimo && ultimo.tipo === "footer") {
          state.bloques.splice(state.bloques.length - 1, 0, nuevo);
        } else {
          state.bloques.push(nuevo);
        }
        pintarBloquesPersonalizados();
        const idx = state.bloques.indexOf(nuevo);
        const card = el.personalizarBloques.querySelector(`[data-personalizar-bloque-card="${idx}"]`);
        if (card) {
          card.scrollIntoView({ behavior: "smooth", block: "center" });
          const primerCampo = card.querySelector("input, textarea");
          if (primerCampo) primerCampo.focus();
        }
        programarPrevia();
        return;
      }

      const conBloque = t.closest("[data-personalizar-bloque]");
      if (!conBloque || conBloque.dataset.personalizarBloque === undefined) return;
      const i = Number(conBloque.dataset.personalizarBloque);
      const b = state.bloques[i];
      if (!b) return;

      if (conBloque.hasAttribute("data-personalizar-mover")) {
        const dir = Number(conBloque.dataset.personalizarMover);
        const j = i + dir;
        if (j < 0 || j >= state.bloques.length) return;
        const copia = state.bloques.slice();
        [copia[i], copia[j]] = [copia[j], copia[i]];
        state.bloques = copia;
        pintarBloquesPersonalizados();
        programarPrevia();
        return;
      }
      if (conBloque.hasAttribute("data-personalizar-duplicar-bloque")) {
        if (state.bloques.length >= 40) return;
        state.bloques.splice(i + 1, 0, clonar(b));
        pintarBloquesPersonalizados();
        programarPrevia();
        return;
      }
      if (conBloque.hasAttribute("data-personalizar-quitar-bloque")) {
        state.bloques.splice(i, 1);
        pintarBloquesPersonalizados();
        programarPrevia();
        return;
      }
      if (conBloque.dataset.personalizarAlineacion) {
        b.alineacion = conBloque.dataset.personalizarAlineacion;
        conBloque.parentElement.querySelectorAll("[data-personalizar-alineacion]").forEach((x) => x.classList.toggle("is-active", x === conBloque));
        programarPrevia();
        return;
      }
      if (conBloque.hasAttribute("data-personalizar-item-agregar")) {
        if (!Array.isArray(b.items)) b.items = [];
        if (b.tipo === "kpi") {
          if (b.items.length >= MAX_KPI) return;
          b.items.push({ label: "Métrica", valor: "0" });
        } else {
          if (b.items.length >= MAX_ITEMS) return;
          b.items.push("Nuevo punto");
        }
        pintarBloquesPersonalizados();
        programarPrevia();
        return;
      }
      if (conBloque.hasAttribute("data-personalizar-item-quitar")) {
        if (!Array.isArray(b.items)) return;
        if (b.tipo === "kpi" && b.items.length <= MIN_KPI) return;
        b.items.splice(Number(conBloque.dataset.personalizarItemQuitar), 1);
        pintarBloquesPersonalizados();
        programarPrevia();
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

      if (t.dataset && t.dataset.personalizarBloque !== undefined) {
        const b = state.bloques[Number(t.dataset.personalizarBloque)];
        if (!b) return;
        if (t.dataset.personalizarCampoBloque) {
          b[t.dataset.personalizarCampoBloque] = t.value;
        } else if (t.dataset.personalizarItem !== undefined) {
          if (!Array.isArray(b.items)) b.items = [];
          const k = Number(t.dataset.personalizarItem);
          if (t.dataset.personalizarSub) {
            if (!b.items[k] || typeof b.items[k] !== "object") b.items[k] = { label: "", valor: "" };
            b.items[k][t.dataset.personalizarSub] = t.value;
          } else {
            b.items[k] = t.value;
          }
        }
        programarPrevia();
      }
    });

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

    // Insertar variable en el campo activo (bloques personalizados o HTML propio)
    let campoActivoPersonalizar = null;
    root.addEventListener("focusin", (e) => {
      if (e.target.matches && e.target.matches("[data-var-target]")) campoActivoPersonalizar = e.target;
    });
    if (el.personalizarVariables) {
      el.personalizarVariables.addEventListener("click", (e) => {
        const chip = e.target.closest("[data-var]");
        if (!chip) return;
        e.preventDefault();
        const objetivo = campoActivoPersonalizar && root.contains(campoActivoPersonalizar) ? campoActivoPersonalizar : null;
        if (!objetivo) {
          toast("Haz clic primero en el campo donde quieres insertar la variable.", "warning");
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
    pintarPaletaPersonalizada();
    aplicarCuando();

    if (el.personalizarToggle) el.personalizarToggle.checked = state.personalizar;
    if (el.personalizarPanel) el.personalizarPanel.hidden = !state.personalizar;
    const htmlActivo = state.htmlPersonalizado.trim() !== "";
    if (el.personalizarHtmlToggle) el.personalizarHtmlToggle.checked = htmlActivo;
    if (el.personalizarHtmlPanel) el.personalizarHtmlPanel.hidden = !htmlActivo;
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
