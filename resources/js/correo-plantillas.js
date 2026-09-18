/**
 * Módulo Correo — Plantillas.
 *
 * Índice ([data-correo-plantillas-index]): los filtros son GET y los resuelve
 * el servidor (el buscador reenvía el formulario con debounce); modal "Nueva
 * plantilla" (POST + navegación al show_url, como propuestas), duplicar y
 * eliminar con confirm() nativo.
 *
 * Editor ([data-correo-plantilla-editor]): un solo objeto `estado` con la
 * plantilla completa (nombre, categoría, estado, asunto, bloques, marca y
 * html_personalizado). Los campos fijos los pinta Blade y llevan
 * [data-campo]/[data-marca]; la lista de bloques y la de redes las pinta el
 * JS desde `estado`, y solo se vuelven a pintar en cambios estructurales
 * (añadir, mover, duplicar, borrar): en el tecleo se actualiza `estado` por
 * data-attributes para no perder el foco.
 *
 * Cada cambio dispara dos cosas independientes:
 *   - la vista previa: POST al endpoint de preview (debounce 500ms) y srcdoc
 *     en el iframe; el HTML lo produce el servidor, nunca el navegador, para
 *     que la previa sea exactamente lo que se enviará;
 *   - el autosave: PUT con TODO el estado (debounce 800ms en input/change,
 *     inmediato en blur), con indicador Guardando…/Guardado/Error. Si llega
 *     otro cambio mientras se guarda, se reencola un guardado al terminar.
 */
(function () {
  "use strict";

  const { toast, debounce, openModal } = window.AgencyOS;
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

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

  function primerError(error, fallback) {
    const errores = error && error.body && error.body.errors;
    if (errores) {
      const clave = Object.keys(errores)[0];
      if (clave && errores[clave] && errores[clave][0]) return errores[clave][0];
    }
    return (error && error.body && error.body.message) || fallback;
  }

  function clonar(valor) {
    return JSON.parse(JSON.stringify(valor));
  }

  // ==========================================================================
  // Índice
  // ==========================================================================

  function initIndex(root) {
    const form = root.querySelector("[data-plantillas-filtros]");
    if (form) {
      const search = form.querySelector("[data-plantillas-search]");
      const estado = form.querySelector("[data-plantillas-estado]");
      if (search) search.addEventListener("input", debounce(() => form.requestSubmit(), 450));
      if (estado) estado.addEventListener("change", () => form.requestSubmit());
    }

    initIndexModal(root);
    initIndexAcciones(root);
  }

  function initIndexModal(root) {
    const form = root.querySelector("#plantillaForm");
    if (!form) return;

    root.querySelectorAll("[data-open-plantilla-modal]").forEach((btn) => {
      btn.addEventListener("click", () => {
        form.reset();
        form.querySelectorAll("[data-error-for]").forEach((el) => (el.textContent = ""));
        openModal("plantillaModal");
        const nombre = form.querySelector("#pf_nombre");
        if (nombre) setTimeout(() => nombre.focus(), 50);
      });
    });

    form.addEventListener("submit", (e) => {
      e.preventDefault();
      form.querySelectorAll("[data-error-for]").forEach((el) => (el.textContent = ""));

      const payload = {
        nombre: form.querySelector("#pf_nombre").value.trim(),
        categoria: form.querySelector("#pf_categoria").value,
        asunto: form.querySelector("#pf_asunto").value.trim() || null,
      };

      const submitBtn = form.querySelector("[data-plantilla-submit]");
      if (submitBtn) submitBtn.disabled = true;

      request(form.dataset.storeAction, "POST", payload)
        .then((data) => {
          window.location.href = data.show_url;
        })
        .catch((error) => {
          if (submitBtn) submitBtn.disabled = false;
          const errores = error.body && error.body.errors;
          if (errores) {
            Object.keys(errores).forEach((campo) => {
              const slot = form.querySelector(`[data-error-for="${campo}"]`);
              if (slot) slot.textContent = errores[campo][0];
            });
            return;
          }
          toast("No se pudo crear la plantilla.", "error");
        });
    });
  }

  function initIndexAcciones(root) {
    const tabla = root.querySelector("[data-plantillas-tabla]");
    if (!tabla) return;

    tabla.addEventListener("click", (e) => {
      const borrar = e.target.closest("[data-delete-plantilla]");
      if (borrar) {
        const nombre = borrar.dataset.nombre || "esta plantilla";
        if (!window.confirm(`¿Eliminar «${nombre}»? Los envíos ya hechos conservan su historial.`)) return;
        const row = borrar.closest("[data-plantilla-row]");
        borrar.disabled = true;
        request(urlTemplate(root.dataset.destroyUrl, borrar.dataset.deletePlantilla), "DELETE")
          .then(() => {
            if (row) row.remove();
            toast("Plantilla eliminada.", "success");
            if (!tabla.querySelector("[data-plantilla-row]")) {
              tabla.hidden = true;
              const vacio = root.querySelector("[data-plantillas-empty]");
              if (vacio) vacio.hidden = false;
            }
          })
          .catch(() => {
            borrar.disabled = false;
            toast("No se pudo eliminar la plantilla.", "error");
          });
        return;
      }

      const duplicar = e.target.closest("[data-duplicar-plantilla]");
      if (duplicar) {
        duplicar.disabled = true;
        request(urlTemplate(root.dataset.duplicarUrl, duplicar.dataset.duplicarPlantilla), "POST")
          .then((data) => {
            // La copia nace lista para editarse: directo al editor.
            window.location.href = data.show_url;
          })
          .catch(() => {
            duplicar.disabled = false;
            toast("No se pudo duplicar la plantilla.", "error");
          });
      }
    });
  }

  // ==========================================================================
  // Editor
  // ==========================================================================

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

  function initEditor(root) {
    const datosEl = document.getElementById("plantilla-data");
    if (!datosEl) return;

    let datos;
    try {
      datos = JSON.parse(datosEl.textContent || "{}");
    } catch (e) {
      toast("No se pudo cargar la plantilla en el editor.", "error");
      return;
    }

    const estado = datos.plantilla;
    const catalogo = datos.catalogoBloques || {};
    const tipos = datos.tiposBloque || Object.keys(catalogo);
    const variables = datos.variables || {};
    const urls = datos.urls || {};

    if (!Array.isArray(estado.bloques)) estado.bloques = [];
    if (!estado.marca || typeof estado.marca !== "object") estado.marca = {};
    if (!Array.isArray(estado.marca.redes)) estado.marca.redes = [];
    estado.html_personalizado = String(estado.html_personalizado || "");

    const el = {
      nota: root.querySelector("[data-autosave-note]"),
      variables: root.querySelector("[data-variables]"),
      bloques: root.querySelector("[data-bloques]"),
      bloquesVacio: root.querySelector("[data-bloques-vacio]"),
      paleta: root.querySelector("[data-paleta]"),
      redes: root.querySelector("[data-redes]"),
      resumenBloques: root.querySelector("[data-resumen-bloques]"),
      resumenHtml: root.querySelector("[data-resumen-html]"),
      htmlToggle: root.querySelector("[data-html-toggle]"),
      htmlPanel: root.querySelector("[data-html-panel]"),
      htmlAviso: root.querySelector("[data-html-aviso]"),
      htmlCodigo: root.querySelector('[data-campo="html_personalizado"]'),
      htmlLongitud: root.querySelector("[data-html-longitud]"),
      logoUrlCampo: root.querySelector("[data-logo-url-campo]"),
      colorLibre: root.querySelector("[data-color-libre]"),
      colorHex: root.querySelector("[data-color-hex]"),
      previewAsunto: root.querySelector("[data-preview-asunto]"),
      previewMarco: root.querySelector("[data-preview-marco]"),
      previewIframe: root.querySelector("[data-preview-iframe]"),
      previewEstado: root.querySelector("[data-preview-estado]"),
      seccionesBloques: Array.from(root.querySelectorAll("[data-seccion-bloques]")),
    };

    // ----- Pintado -----------------------------------------------------------

    function pintarVariables() {
      if (!el.variables) return;
      el.variables.innerHTML = Object.keys(variables)
        .map((clave) => {
          const v = variables[clave];
          return `<button type="button" class="cp-chip cp-chip--var u-mono" data-var="${escapeHtml(clave)}" title="${escapeHtml(v.label)} · ej. ${escapeHtml(v.ejemplo)}">{{${escapeHtml(clave)}}}</button>`;
        })
        .join("");
    }

    function pintarPaleta() {
      if (!el.paleta) return;
      el.paleta.innerHTML = tipos
        .map((tipo) => {
          const label = (catalogo[tipo] && catalogo[tipo].label) || tipo;
          return `<button type="button" class="cp-paleta__btn" data-agregar="${escapeHtml(tipo)}"><i class="fa-solid ${ICONOS_BLOQUE[tipo] || "fa-square"}"></i>${escapeHtml(label)}</button>`;
        })
        .join("");
    }

    function pintarRedes() {
      if (!el.redes) return;
      const redes = estado.marca.redes;
      if (!redes.length) {
        el.redes.innerHTML = '<p class="field__hint">Sin enlaces en el pie.</p>';
        return;
      }
      el.redes.innerHTML = redes
        .map(
          (r, i) => `
          <div class="cp-red">
            <input class="input" type="text" placeholder="Nombre" value="${escapeHtml(r.nombre)}" data-red="${i}" data-red-campo="nombre" maxlength="40" aria-label="Nombre del enlace">
            <input class="input u-mono" type="text" placeholder="https://…" value="${escapeHtml(r.url)}" data-red="${i}" data-red-campo="url" aria-label="URL del enlace">
            <button type="button" class="btn--icon cp-accion-danger" data-red-quitar="${i}" title="Quitar"><i class="fa-solid fa-xmark"></i></button>
          </div>`
        )
        .join("");
    }

    function campoTexto(i, campo, valor, opciones) {
      const o = opciones || {};
      const extra = o.mono ? " u-mono" : "";
      const attrs = `data-bloque="${i}" data-campo-bloque="${campo}" data-var-target${o.placeholder ? ` placeholder="${escapeHtml(o.placeholder)}"` : ""}${o.label ? ` aria-label="${escapeHtml(o.label)}"` : ""}`;
      if (o.textarea) {
        return `<textarea class="textarea${extra}" rows="${o.rows || 4}" ${attrs}>${escapeHtml(valor)}</textarea>`;
      }
      return `<input class="input${extra}" type="text" value="${escapeHtml(valor)}" ${attrs}>`;
    }

    function cuerpoBloque(b, i) {
      switch (b.tipo) {
        case "heading":
          return `
            ${campoTexto(i, "texto", b.texto, { label: "Texto del encabezado" })}
            <div class="cp-segmento cp-segmento--inline" role="group" aria-label="Alineación">
              <button type="button" class="cp-segmento__btn ${b.alineacion !== "centro" ? "is-active" : ""}" data-bloque="${i}" data-alineacion="izquierda">Izquierda</button>
              <button type="button" class="cp-segmento__btn ${b.alineacion === "centro" ? "is-active" : ""}" data-bloque="${i}" data-alineacion="centro">Centrado</button>
            </div>`;
        case "text":
          return campoTexto(i, "texto", b.texto, { textarea: true, rows: 4, label: "Texto del párrafo", placeholder: "Cada salto de línea es un párrafo." });
        case "footer":
          return campoTexto(i, "texto", b.texto, { textarea: true, rows: 3, label: "Texto del pie" });
        case "list": {
          const items = Array.isArray(b.items) ? b.items : [];
          const filas = items
            .map(
              (it, k) => `
              <div class="cp-item">
                <input class="input" type="text" value="${escapeHtml(it)}" data-bloque="${i}" data-item="${k}" data-var-target aria-label="Punto ${k + 1}">
                <button type="button" class="btn--icon cp-accion-danger" data-bloque="${i}" data-item-quitar="${k}" title="Quitar punto"><i class="fa-solid fa-xmark"></i></button>
              </div>`
            )
            .join("");
          return `${filas}${items.length < MAX_ITEMS ? `<button type="button" class="cp-link" data-bloque="${i}" data-item-agregar>+ Añadir punto</button>` : ""}`;
        }
        case "kpi": {
          const items = Array.isArray(b.items) ? b.items : [];
          const filas = items
            .map(
              (it, k) => `
              <div class="cp-item cp-item--kpi">
                <input class="input" type="text" placeholder="Etiqueta" value="${escapeHtml(it && it.label)}" data-bloque="${i}" data-item="${k}" data-sub="label" data-var-target aria-label="Etiqueta ${k + 1}">
                <input class="input u-mono" type="text" placeholder="Valor" value="${escapeHtml(it && it.valor)}" data-bloque="${i}" data-item="${k}" data-sub="valor" data-var-target aria-label="Valor ${k + 1}">
                <button type="button" class="btn--icon cp-accion-danger" data-bloque="${i}" data-item-quitar="${k}" title="Quitar cifra" ${items.length <= MIN_KPI ? "disabled" : ""}><i class="fa-solid fa-xmark"></i></button>
              </div>`
            )
            .join("");
          return `${filas}${items.length < MAX_KPI ? `<button type="button" class="cp-link" data-bloque="${i}" data-item-agregar>+ Añadir cifra</button>` : `<p class="field__hint">Máximo ${MAX_KPI} cifras.</p>`}`;
        }
        case "button":
          return `
            <div class="field"><span class="field__label">Texto del botón</span>${campoTexto(i, "texto", b.texto, { label: "Texto del botón" })}</div>
            <div class="field"><span class="field__label">Enlace</span>${campoTexto(i, "url", b.url, { mono: true, label: "Enlace del botón", placeholder: "https://… o {{enlace_reporte}}" })}</div>`;
        case "image":
          return `
            <div class="field"><span class="field__label">URL de la imagen</span>${campoTexto(i, "url", b.url, { mono: true, label: "URL de la imagen", placeholder: "https://…" })}</div>
            <div class="field"><span class="field__label">Texto alternativo</span>${campoTexto(i, "alt", b.alt, { label: "Texto alternativo" })}</div>`;
        case "divider":
          return '<p class="field__hint">Línea divisoria de 1px, sin opciones.</p>';
        default:
          return `<p class="field__hint">Tipo de bloque desconocido: ${escapeHtml(b.tipo)}.</p>`;
      }
    }

    function pintarBloques() {
      if (!el.bloques) return;
      const total = estado.bloques.length;
      el.bloques.innerHTML = estado.bloques
        .map((b, i) => {
          const label = (catalogo[b.tipo] && catalogo[b.tipo].label) || b.tipo;
          return `
          <div class="card cp-bloque" data-bloque-card="${i}">
            <div class="cp-bloque__cabecera">
              <div class="cp-bloque__titulo">
                <span class="cp-bloque__asa" draggable="true" title="Arrastra para reordenar"><i class="fa-solid fa-grip-vertical"></i></span>
                <i class="fa-solid ${ICONOS_BLOQUE[b.tipo] || "fa-square"} cp-bloque__icono"></i>
                <span>${escapeHtml(label)}</span>
                <span class="u-mono cp-muted">#${i + 1}</span>
              </div>
              <div class="cp-bloque__acciones">
                <button type="button" class="btn--icon" data-bloque="${i}" data-mover="-1" title="Subir" ${i === 0 ? "disabled" : ""}><i class="fa-solid fa-arrow-up"></i></button>
                <button type="button" class="btn--icon" data-bloque="${i}" data-mover="1" title="Bajar" ${i === total - 1 ? "disabled" : ""}><i class="fa-solid fa-arrow-down"></i></button>
                <button type="button" class="btn--icon" data-bloque="${i}" data-duplicar-bloque title="Duplicar bloque"><i class="fa-solid fa-copy"></i></button>
                <button type="button" class="btn--icon cp-accion-danger" data-bloque="${i}" data-quitar-bloque title="Eliminar bloque"><i class="fa-solid fa-trash"></i></button>
              </div>
            </div>
            <div class="cp-bloque__cuerpo">${cuerpoBloque(b, i)}</div>
          </div>`;
        })
        .join("");

      if (el.bloquesVacio) el.bloquesVacio.hidden = total !== 0;
      if (el.resumenBloques) el.resumenBloques.textContent = `${total} ${total === 1 ? "bloque" : "bloques"}`;
    }

    function reflejarHtmlLibre() {
      const activo = estado.html_personalizado.trim() !== "";
      if (el.htmlAviso) el.htmlAviso.hidden = !activo;
      if (el.resumenHtml) el.resumenHtml.hidden = !activo;
      if (el.htmlLongitud) el.htmlLongitud.textContent = `${estado.html_personalizado.length.toLocaleString("es-MX")} caracteres`;
      el.seccionesBloques.forEach((s) => s.classList.toggle("is-atenuado", activo));
    }

    function reflejarColor() {
      const actual = String(estado.marca.color || "").toUpperCase();
      root.querySelectorAll("[data-color]").forEach((btn) => {
        btn.classList.toggle("is-active", btn.dataset.color.toUpperCase() === actual);
      });
      if (el.colorLibre && /^#[0-9A-F]{6}$/.test(actual)) el.colorLibre.value = actual.toLowerCase();
      if (el.colorHex) el.colorHex.textContent = actual;
    }

    function reflejarLogo() {
      if (el.logoUrlCampo) el.logoUrlCampo.hidden = estado.marca.logo !== "imagen";
    }

    // ----- Vista previa ------------------------------------------------------

    let dispositivo = "escritorio";
    let previewEnCurso = false;
    let previewPendiente = false;

    function mostrarEstadoPreview(texto) {
      if (!el.previewEstado) return;
      el.previewEstado.hidden = !texto;
      el.previewEstado.textContent = texto || "";
    }

    function pedirPreview() {
      if (previewEnCurso) {
        previewPendiente = true;
        return;
      }
      previewEnCurso = true;
      request(urls.preview, "POST", {
        bloques: estado.bloques,
        marca: estado.marca,
        html_personalizado: estado.html_personalizado,
        variables: {},
      })
        .then((data) => {
          if (el.previewIframe) el.previewIframe.srcdoc = data.html || "";
          mostrarEstadoPreview("");
        })
        .catch((error) => {
          mostrarEstadoPreview(primerError(error, "No se pudo generar la vista previa."));
        })
        .finally(() => {
          previewEnCurso = false;
          if (previewPendiente) {
            previewPendiente = false;
            pedirPreview();
          }
        });
    }

    const previewDebounced = debounce(pedirPreview, 500);

    // El iframe crece con el correo: así el lienzo hace scroll como un
    // cliente de correo y no aparece un segundo scroll dentro del marco.
    function ajustarAltoIframe() {
      const iframe = el.previewIframe;
      if (!iframe) return;
      try {
        const doc = iframe.contentDocument;
        const alto = doc && doc.documentElement ? Math.max(doc.documentElement.scrollHeight, doc.body ? doc.body.scrollHeight : 0) : 0;
        iframe.style.height = `${Math.max(alto + 2, 320)}px`;
      } catch (err) {
        iframe.style.height = "800px";
      }
    }

    if (el.previewIframe) el.previewIframe.addEventListener("load", ajustarAltoIframe);

    function aplicarDispositivo() {
      if (el.previewMarco) el.previewMarco.style.width = dispositivo === "movil" ? "375px" : "600px";
      // El ancho cambia el reflujo del correo: se recalcula el alto tras pintar.
      setTimeout(ajustarAltoIframe, 250);
      root.querySelectorAll("[data-dispositivo]").forEach((btn) => {
        btn.classList.toggle("is-active", btn.dataset.dispositivo === dispositivo);
      });
    }

    // ----- Autosave ----------------------------------------------------------

    let sucio = false;
    let guardando = false;
    let reguardar = false;

    function nota(texto, clase) {
      if (!el.nota) return;
      el.nota.textContent = texto;
      el.nota.className = `cp-guardado${clase ? ` ${clase}` : ""}`;
    }

    function payloadCompleto() {
      return {
        nombre: estado.nombre,
        categoria: estado.categoria,
        estado: estado.estado,
        asunto: estado.asunto,
        bloques: estado.bloques,
        marca: estado.marca,
        html_personalizado: estado.html_personalizado,
      };
    }

    function guardar() {
      if (!sucio) return Promise.resolve();
      if (guardando) {
        reguardar = true;
        return Promise.resolve();
      }
      guardando = true;
      sucio = false;
      nota("Guardando…", "is-saving");

      return request(urls.update, "PUT", payloadCompleto())
        .then(() => {
          if (!sucio) nota("Guardado", "is-ok");
        })
        .catch((error) => {
          sucio = true;
          nota("Error al guardar", "is-error");
          toast(primerError(error, "No se pudo guardar la plantilla."), "error");
        })
        .finally(() => {
          guardando = false;
          if (reguardar) {
            reguardar = false;
            guardar();
          }
        });
    }

    const guardarDebounced = debounce(guardar, 800);

    function marcarCambio(opciones) {
      sucio = true;
      nota("Sin guardar", "is-dirty");
      guardarDebounced();
      if (!opciones || opciones.preview !== false) previewDebounced();
    }

    // ----- Lectura de cambios de campos ------------------------------------

    function aplicarCambioDeCampo(target) {
      // Campos fijos del registro
      if (target.dataset.campo) {
        const campo = target.dataset.campo;
        estado[campo] = target.value;
        if (campo === "asunto" && el.previewAsunto) el.previewAsunto.textContent = target.value;
        if (campo === "html_personalizado") {
          reflejarHtmlLibre();
          marcarCambio();
          return;
        }
        if (campo === "nombre") document.title = `${target.value || "Plantilla"} — RankPro`;
        // Nombre/categoría/estado/asunto no cambian el HTML: no hace falta previa.
        marcarCambio({ preview: false });
        return;
      }

      // Marca
      if (target.dataset.marca) {
        estado.marca[target.dataset.marca] = target.value;
        if (target.dataset.marca === "logo") reflejarLogo();
        marcarCambio();
        return;
      }

      if (target.hasAttribute("data-color-libre")) {
        estado.marca.color = String(target.value).toUpperCase();
        reflejarColor();
        marcarCambio();
        return;
      }

      // Redes del pie
      if (target.dataset.red !== undefined) {
        const r = estado.marca.redes[Number(target.dataset.red)];
        if (r) r[target.dataset.redCampo] = target.value;
        marcarCambio();
        return;
      }

      // Bloques
      if (target.dataset.bloque !== undefined) {
        const b = estado.bloques[Number(target.dataset.bloque)];
        if (!b) return;
        if (target.dataset.campoBloque) {
          b[target.dataset.campoBloque] = target.value;
        } else if (target.dataset.item !== undefined) {
          if (!Array.isArray(b.items)) b.items = [];
          const k = Number(target.dataset.item);
          if (target.dataset.sub) {
            if (!b.items[k] || typeof b.items[k] !== "object") b.items[k] = { label: "", valor: "" };
            b.items[k][target.dataset.sub] = target.value;
          } else {
            b.items[k] = target.value;
          }
        }
        marcarCambio();
      }
    }

    // ----- Variables: inserción en el campo activo ---------------------------

    let campoActivo = null;

    function insertarVariable(clave) {
      const marcador = `{{${clave}}}`;
      const objetivo = campoActivo && root.contains(campoActivo) ? campoActivo : null;
      if (!objetivo) {
        toast("Haz clic primero en el campo donde quieres insertar la variable.", "warning");
        return;
      }
      const inicio = objetivo.selectionStart ?? objetivo.value.length;
      const fin = objetivo.selectionEnd ?? objetivo.value.length;
      objetivo.setRangeText(marcador, inicio, fin, "end");
      objetivo.focus();
      objetivo.dispatchEvent(new Event("input", { bubbles: true }));
    }

    // ----- Acciones estructurales -------------------------------------------

    function nuevoBloque(tipo) {
      const defecto = (catalogo[tipo] && catalogo[tipo].defecto) || {};
      return Object.assign({ tipo }, clonar(defecto));
    }

    function moverBloque(i, dir) {
      const j = i + dir;
      if (i < 0 || j < 0 || j >= estado.bloques.length) return;
      const copia = estado.bloques.slice();
      [copia[i], copia[j]] = [copia[j], copia[i]];
      estado.bloques = copia;
      pintarBloques();
      marcarCambio();
    }

    function manejarClick(e) {
      const t = e.target;

      const chip = t.closest("[data-var]");
      if (chip) {
        e.preventDefault();
        insertarVariable(chip.dataset.var);
        return;
      }

      const color = t.closest("[data-color]");
      if (color) {
        estado.marca.color = color.dataset.color.toUpperCase();
        reflejarColor();
        marcarCambio();
        return;
      }

      if (t.closest("[data-red-agregar]")) {
        if (estado.marca.redes.length >= 8) {
          toast("Máximo 8 enlaces en el pie.", "warning");
          return;
        }
        estado.marca.redes.push({ nombre: "", url: "" });
        pintarRedes();
        const ultimo = el.redes && el.redes.querySelector(`[data-red="${estado.marca.redes.length - 1}"]`);
        if (ultimo) ultimo.focus();
        marcarCambio();
        return;
      }

      const quitarRed = t.closest("[data-red-quitar]");
      if (quitarRed) {
        estado.marca.redes.splice(Number(quitarRed.dataset.redQuitar), 1);
        pintarRedes();
        marcarCambio();
        return;
      }

      const agregar = t.closest("[data-agregar]");
      if (agregar) {
        if (estado.bloques.length >= 40) {
          toast("Una plantilla admite como máximo 40 bloques.", "warning");
          return;
        }
        // El pie va al final: un bloque nuevo se inserta antes de él si existe.
        const nuevo = nuevoBloque(agregar.dataset.agregar);
        const ultimo = estado.bloques[estado.bloques.length - 1];
        if (nuevo.tipo !== "footer" && ultimo && ultimo.tipo === "footer") {
          estado.bloques.splice(estado.bloques.length - 1, 0, nuevo);
        } else {
          estado.bloques.push(nuevo);
        }
        pintarBloques();
        const idx = estado.bloques.indexOf(nuevo);
        const card = el.bloques.querySelector(`[data-bloque-card="${idx}"]`);
        if (card) {
          card.scrollIntoView({ behavior: "smooth", block: "center" });
          const primerCampo = card.querySelector("input, textarea");
          if (primerCampo) primerCampo.focus();
        }
        marcarCambio();
        return;
      }

      const dispositivoBtn = t.closest("[data-dispositivo]");
      if (dispositivoBtn) {
        dispositivo = dispositivoBtn.dataset.dispositivo;
        aplicarDispositivo();
        return;
      }

      if (t.closest("[data-html-descartar]")) {
        if (estado.html_personalizado.trim() !== "" && !window.confirm("¿Descartar el HTML propio? Los bloques volverán a controlar el correo.")) return;
        estado.html_personalizado = "";
        if (el.htmlCodigo) el.htmlCodigo.value = "";
        if (el.htmlToggle) el.htmlToggle.checked = false;
        if (el.htmlPanel) el.htmlPanel.hidden = true;
        reflejarHtmlLibre();
        marcarCambio();
        return;
      }

      if (t.closest("[data-duplicar]")) {
        const btn = t.closest("[data-duplicar]");
        btn.disabled = true;
        guardar()
          .then(() => request(urls.duplicar, "POST"))
          .then((data) => {
            window.location.href = data.show_url;
          })
          .catch(() => {
            btn.disabled = false;
            toast("No se pudo duplicar la plantilla.", "error");
          });
        return;
      }

      // Acciones sobre un bloque concreto
      const conBloque = t.closest("[data-bloque]");
      if (!conBloque || conBloque.dataset.bloque === undefined) return;
      const i = Number(conBloque.dataset.bloque);
      const b = estado.bloques[i];
      if (!b) return;

      if (conBloque.hasAttribute("data-mover")) {
        moverBloque(i, Number(conBloque.dataset.mover));
        return;
      }
      if (conBloque.hasAttribute("data-duplicar-bloque")) {
        if (estado.bloques.length >= 40) {
          toast("Una plantilla admite como máximo 40 bloques.", "warning");
          return;
        }
        estado.bloques.splice(i + 1, 0, clonar(b));
        pintarBloques();
        marcarCambio();
        return;
      }
      if (conBloque.hasAttribute("data-quitar-bloque")) {
        estado.bloques.splice(i, 1);
        pintarBloques();
        marcarCambio();
        return;
      }
      if (conBloque.dataset.alineacion) {
        b.alineacion = conBloque.dataset.alineacion;
        conBloque.parentElement.querySelectorAll("[data-alineacion]").forEach((x) => x.classList.toggle("is-active", x === conBloque));
        marcarCambio();
        return;
      }
      if (conBloque.hasAttribute("data-item-agregar")) {
        if (!Array.isArray(b.items)) b.items = [];
        if (b.tipo === "kpi") {
          if (b.items.length >= MAX_KPI) return;
          b.items.push({ label: "Métrica", valor: "0" });
        } else {
          if (b.items.length >= MAX_ITEMS) return;
          b.items.push("Nuevo punto");
        }
        pintarBloques();
        const nuevoCampo = el.bloques.querySelector(`[data-bloque="${i}"][data-item="${b.items.length - 1}"]`);
        if (nuevoCampo) {
          nuevoCampo.focus();
          nuevoCampo.select();
        }
        marcarCambio();
        return;
      }
      if (conBloque.hasAttribute("data-item-quitar")) {
        if (!Array.isArray(b.items)) return;
        if (b.tipo === "kpi" && b.items.length <= MIN_KPI) return;
        b.items.splice(Number(conBloque.dataset.itemQuitar), 1);
        pintarBloques();
        marcarCambio();
      }
    }

    // ----- Drag & drop de bloques (opcional; los botones subir/bajar siempre funcionan) -----

    let arrastrando = null;

    function initArrastre() {
      if (!el.bloques) return;
      el.bloques.addEventListener("dragstart", (e) => {
        // Solo el asa es draggable: si lo fuera la tarjeta entera, seleccionar
        // texto dentro de un input arrastraría el bloque (y Firefox bloquea la
        // selección dentro de elementos draggable).
        const asa = e.target.closest && e.target.closest(".cp-bloque__asa");
        const card = asa && asa.closest("[data-bloque-card]");
        if (!card) {
          e.preventDefault();
          return;
        }
        arrastrando = Number(card.dataset.bloqueCard);
        card.classList.add("is-arrastrando");
        e.dataTransfer.effectAllowed = "move";
        try {
          e.dataTransfer.setData("text/plain", String(arrastrando));
        } catch (err) {
          /* IE/Edge viejo */
        }
      });
      el.bloques.addEventListener("dragover", (e) => {
        if (arrastrando === null) return;
        e.preventDefault();
        const card = e.target.closest && e.target.closest("[data-bloque-card]");
        el.bloques.querySelectorAll(".is-destino").forEach((c) => c.classList.remove("is-destino"));
        if (card) card.classList.add("is-destino");
      });
      el.bloques.addEventListener("drop", (e) => {
        if (arrastrando === null) return;
        e.preventDefault();
        const card = e.target.closest && e.target.closest("[data-bloque-card]");
        const destino = card ? Number(card.dataset.bloqueCard) : estado.bloques.length - 1;
        const origen = arrastrando;
        arrastrando = null;
        if (destino === origen) {
          pintarBloques();
          return;
        }
        const [movido] = estado.bloques.splice(origen, 1);
        estado.bloques.splice(destino, 0, movido);
        pintarBloques();
        marcarCambio();
      });
      el.bloques.addEventListener("dragend", () => {
        arrastrando = null;
        el.bloques.querySelectorAll(".is-arrastrando, .is-destino").forEach((c) => c.classList.remove("is-arrastrando", "is-destino"));
      });
    }

    // ----- Cableado ----------------------------------------------------------

    root.addEventListener("input", (e) => {
      const t = e.target;
      if (!(t instanceof HTMLInputElement || t instanceof HTMLTextAreaElement || t instanceof HTMLSelectElement)) return;
      if (t.hasAttribute("data-html-toggle")) return;
      aplicarCambioDeCampo(t);
    });

    root.addEventListener("change", (e) => {
      const t = e.target;
      if (t.hasAttribute("data-html-toggle")) {
        const activar = t.checked;
        if (!activar && estado.html_personalizado.trim() !== "") {
          if (!window.confirm("¿Descartar el HTML propio? Los bloques volverán a controlar el correo.")) {
            t.checked = true;
            return;
          }
          estado.html_personalizado = "";
          if (el.htmlCodigo) el.htmlCodigo.value = "";
          reflejarHtmlLibre();
          marcarCambio();
        }
        if (el.htmlPanel) el.htmlPanel.hidden = !activar;
        if (activar && el.htmlCodigo) el.htmlCodigo.focus();
        return;
      }
      // Los <select> no disparan input en todos los navegadores: se cubre aquí.
      if (t instanceof HTMLSelectElement) aplicarCambioDeCampo(t);
    });

    root.addEventListener("focusin", (e) => {
      if (e.target.matches && e.target.matches("[data-var-target]")) campoActivo = e.target;
    });

    root.addEventListener("focusout", (e) => {
      const t = e.target;
      if (t instanceof HTMLInputElement || t instanceof HTMLTextAreaElement || t instanceof HTMLSelectElement) {
        if (sucio) guardar();
      }
    });

    root.addEventListener("click", manejarClick);

    // Volver: se guarda antes de navegar para no perder el último tecleo.
    const volver = root.querySelector(".cp-bar__volver");
    if (volver) {
      volver.addEventListener("click", (e) => {
        if (!sucio && !guardando) return;
        e.preventDefault();
        guardar().then(() => {
          window.location.href = volver.href;
        });
      });
    }

    window.addEventListener("beforeunload", (e) => {
      if (!sucio && !guardando) return;
      e.preventDefault();
      e.returnValue = "";
    });

    // Estado inicial
    pintarVariables();
    pintarPaleta();
    pintarRedes();
    pintarBloques();
    reflejarHtmlLibre();
    reflejarColor();
    reflejarLogo();
    aplicarDispositivo();
    initArrastre();
    nota("Guardado", "is-ok");
    pedirPreview();
  }

  document.addEventListener("shell:ready", () => {
    const indice = document.querySelector("[data-correo-plantillas-index]");
    if (indice) initIndex(indice);

    const editor = document.querySelector("[data-correo-plantilla-editor]");
    if (editor) initEditor(editor);
  });
})();
