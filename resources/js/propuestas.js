/**
 * Módulo Propuestas — Propuesta de Continuidad SEO.
 *
 * Índice: filtros client-side (como reportes.js), modal "Nueva Propuesta"
 * (fetch + navegación al show_url, igual que reportes/ads/seo), borrado con
 * window.confirm() nativo.
 *
 * Editor (show.blade.php): 5 pestañas, cada una con UN payload JSON por
 * sección — mismo patrón que initFasePanel() en seo.js (debounce 800ms en
 * input/change + guardado inmediato en blur) — adaptado para también leer
 * listas de filas repetibles (arreglos de objetos y de strings sueltos).
 * En vez de escribir una función de alta/baja por tabla (habría ~10 casi
 * idénticas), hay UN solo motor genérico de "row-list" que:
 *   - agrega una fila clonando la última fila existente y vaciando sus
 *     valores (todo row-list siempre tiene al menos una fila sembrada por
 *     Plantilla::vacio(), así que siempre hay algo que clonar);
 *   - lee cada fila vía [data-field] para armar el arreglo del payload;
 *   - respeta un mínimo (no deja bajar de data-row-list-min) y opcionalmente
 *     un máximo (data-row-list-max, usado solo por estadisticas_destacadas).
 */
(function () {
  "use strict";

  const { toast, debounce, openModal, closeModal } = window.AgencyOS;
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

  /** Como request() de reportes.js: rechaza con err.status/err.body en cualquier respuesta no-2xx. */
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

  // ==========================================================================
  // Índice
  // ==========================================================================

  function initPropuestaFiltros() {
    const search = document.getElementById("propuestaSearch");
    const clienteFilter = document.getElementById("propuestaClienteFilter");
    const estadoFilter = document.getElementById("propuestaEstadoFilter");
    if (!search || !clienteFilter || !estadoFilter) return;

    const noResults = document.getElementById("propuestaNoResults");

    function applyFilters() {
      const rows = document.querySelectorAll("[data-propuesta-row]");
      const term = search.value.trim().toLowerCase();
      const cliente = clienteFilter.value;
      const estado = estadoFilter.value;
      let visible = 0;

      rows.forEach((row) => {
        const show =
          (!term || (row.dataset.search || "").includes(term)) &&
          (cliente === "all" || row.dataset.cliente === cliente) &&
          (estado === "all" || row.dataset.estado === estado);
        row.style.display = show ? "" : "none";
        if (show) visible++;
      });

      if (noResults) noResults.hidden = visible !== 0 || rows.length === 0;
    }

    search.addEventListener("input", debounce(applyFilters, 150));
    clienteFilter.addEventListener("change", applyFilters);
    estadoFilter.addEventListener("change", applyFilters);
  }

  /**
   * campanasPorCliente llega serializado por PropuestaController::index()
   * como objeto {cliente_id: [{id, cliente_id, nombre}, ...]} — mismo patrón
   * que el banco de keywords embebido en seo/show.blade.php + seo.js
   * (getKeywordBank()): un solo JSON parseado una vez, filtrado en JS, sin
   * ida y vuelta al servidor por cliente elegido.
   */
  function initPropuestaModal() {
    const form = document.getElementById("propuestaForm");
    if (!form) return;

    let campanasPorCliente = {};
    try {
      campanasPorCliente = JSON.parse(form.dataset.campanasPorCliente || "{}");
    } catch (e) {
      campanasPorCliente = {};
    }

    const clienteSelect = document.getElementById("pf_cliente_id");
    const campanaSelect = document.getElementById("pf_seo_campana_id");

    function rebuildCampanas() {
      const clienteId = clienteSelect.value;
      if (!clienteId) {
        campanaSelect.disabled = true;
        campanaSelect.innerHTML = '<option value="">Elige primero un cliente</option>';
        return;
      }
      campanaSelect.disabled = false;
      const campanas = campanasPorCliente[clienteId] || [];
      const options = ['<option value="">— Sin campaña —</option>'].concat(
        campanas.map((c) => `<option value="${c.id}">${escapeHtml(c.nombre)}</option>`)
      );
      campanaSelect.innerHTML = options.join("");
    }

    clienteSelect.addEventListener("change", rebuildCampanas);

    document.querySelectorAll("[data-open-propuesta-modal]").forEach((btn) => {
      btn.addEventListener("click", () => {
        form.reset();
        form.querySelectorAll("[data-error-for]").forEach((el) => (el.textContent = ""));
        rebuildCampanas();
        openModal("propuestaModal");
      });
    });

    form.addEventListener("submit", (e) => {
      e.preventDefault();
      form.querySelectorAll("[data-error-for]").forEach((el) => (el.textContent = ""));

      const payload = {
        cliente_id: clienteSelect.value || null,
        seo_campana_id: campanaSelect.value || null,
        titulo: form.querySelector("#pf_titulo").value,
      };

      const submitBtn = form.querySelector("[data-propuesta-submit]");
      if (submitBtn) submitBtn.disabled = true;

      request(form.dataset.storeAction, "POST", payload)
        .then((data) => {
          // Nace con la plantilla de las 5 secciones sembrada: directo al editor.
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
          toast("No se pudo crear la propuesta.", "error");
        });
    });
  }

  function initPropuestaDelete() {
    const tabla = document.querySelector("[data-propuestas-tabla]");
    if (!tabla) return;

    const destroyUrl = tabla.dataset.propuestaDestroyUrl;

    tabla.addEventListener("click", (e) => {
      const btn = e.target.closest("[data-delete-propuesta]");
      if (!btn) return;
      const row = btn.closest("[data-propuesta-row]");
      if (!window.confirm("¿Eliminar esta propuesta? Esta acción no se puede deshacer.")) return;

      request(urlTemplate(destroyUrl, btn.dataset.deletePropuesta), "DELETE")
        .then(() => {
          row.remove();
          toast("Propuesta eliminada.", "success");
          if (!document.querySelector("[data-propuesta-row]")) {
            tabla.hidden = true;
            const empty = document.querySelector("[data-propuestas-empty]");
            if (empty) empty.hidden = false;
          }
        })
        .catch(() => toast("No se pudo eliminar la propuesta.", "error"));
    });
  }

  // ==========================================================================
  // Editor — motor genérico de listas de filas repetibles
  // ==========================================================================

  function rowListMin(container) {
    return parseInt(container.dataset.rowListMin || "0", 10) || 0;
  }

  function rowListMax(container) {
    const raw = container.dataset.rowListMax;
    if (!raw) return null;
    const max = parseInt(raw, 10);
    return Number.isNaN(max) ? null : max;
  }

  function rowsOf(container) {
    return Array.from(container.querySelectorAll(":scope > [data-row]"));
  }

  /** Clona la última fila (siempre hay al menos una: Plantilla::vacio() siembra todas las listas) y vacía sus valores. */
  function cloneEmptyRow(container) {
    const rows = rowsOf(container);
    const last = rows[rows.length - 1];
    if (!last) return null;
    const clone = last.cloneNode(true);
    clone.querySelectorAll("input, textarea").forEach((el) => {
      el.value = "";
    });
    clone.querySelectorAll("select").forEach((el) => {
      el.selectedIndex = 0;
    });
    return clone;
  }

  /** Ganchos cosméticos por lista: renumerar "BLOQUE N" y las letras A/B/C tras agregar/quitar filas. */
  const ROW_LIST_HOOKS = {
    estadisticas_destacadas: (rows) => {
      rows.forEach((row, i) => {
        const badge = row.querySelector("[data-row-badge]");
        if (badge) badge.textContent = "BLOQUE " + (i + 1);
      });
    },
    opciones_renegociacion: (rows) => {
      rows.forEach((row, i) => {
        const letter = row.querySelector(".row-card__letter");
        if (letter) letter.textContent = String.fromCharCode(65 + i);
      });
    },
  };

  function updateRowListButtons(container) {
    const rows = rowsOf(container);
    const min = rowListMin(container);
    const max = rowListMax(container);
    const name = container.dataset.rowList;

    const addBtn = document.querySelector(`[data-row-add="${name}"]`);
    if (addBtn) addBtn.disabled = max !== null && rows.length >= max;

    rows.forEach((row) => {
      const removeBtn = row.querySelector("[data-row-remove]");
      if (removeBtn) removeBtn.disabled = rows.length <= min;
    });

    const hook = ROW_LIST_HOOKS[name];
    if (hook) hook(rows);
  }

  function initRowLists(root) {
    root.querySelectorAll("[data-row-list]").forEach(updateRowListButtons);

    root.addEventListener("click", (e) => {
      const addBtn = e.target.closest("[data-row-add]");
      if (addBtn) {
        if (addBtn.disabled) return;
        const name = addBtn.dataset.rowAdd;
        const container = root.querySelector(`[data-row-list="${name}"]`);
        if (!container) return;

        const clone = cloneEmptyRow(container);
        if (!clone) return;
        container.appendChild(clone);
        updateRowListButtons(container);

        const firstField = clone.querySelector("input, textarea");
        if (firstField) firstField.focus();

        savePanel(addBtn.closest("[data-tab-panel]"));
        return;
      }

      const removeBtn = e.target.closest("[data-row-remove]");
      if (removeBtn) {
        if (removeBtn.disabled) return;
        const row = removeBtn.closest("[data-row]");
        const container = removeBtn.closest("[data-row-list]");
        if (!row || !container) return;

        const rows = rowsOf(container);
        if (rows.length <= rowListMin(container)) {
          toast(`Debe quedar al menos ${rowListMin(container)} fila.`, "warning");
          return;
        }

        row.remove();
        updateRowListButtons(container);
        savePanel(removeBtn.closest("[data-tab-panel]"));
      }
    });
  }

  // ==========================================================================
  // Editor — colección del payload de una pestaña
  // ==========================================================================

  /** Convierte "periodo_comparacion[label_1]" en payload.periodo_comparacion.label_1 (una sola profundidad de corchetes, la única que usan las pestañas). */
  function setPath(payload, name, value) {
    const match = /^([^[\]]+)((?:\[[^[\]]*\])*)$/.exec(name || "");
    if (!match) return;
    const root = match[1];
    const keys = Array.from(match[2].matchAll(/\[([^[\]]*)\]/g)).map((m) => m[1]);

    if (!keys.length) {
      payload[root] = value;
      return;
    }

    if (typeof payload[root] !== "object" || payload[root] === null || Array.isArray(payload[root])) {
      payload[root] = {};
    }
    let target = payload[root];
    keys.forEach((key, i) => {
      if (i === keys.length - 1) {
        target[key] = value;
      } else {
        if (typeof target[key] !== "object" || target[key] === null) target[key] = {};
        target = target[key];
      }
    });
  }

  function collectPanelPayload(panel) {
    const payload = {};

    panel.querySelectorAll("[data-autosave]").forEach((field) => {
      if (field.closest("[data-row]")) return; // los campos de fila se leen abajo, vía [data-field]
      setPath(payload, field.name || field.id, field.value);
    });

    panel.querySelectorAll("[data-row-list]").forEach((container) => {
      const key = container.dataset.rowList;
      const type = container.dataset.rowListType || "objects";

      payload[key] = rowsOf(container).map((row) => {
        if (type === "strings") {
          const field = row.querySelector("[data-field]");
          return field ? field.value : "";
        }
        const obj = {};
        row.querySelectorAll("[data-field]").forEach((f) => {
          obj[f.dataset.field] = f.value;
        });
        return obj;
      });
    });

    return payload;
  }

  // ==========================================================================
  // Editor — autoguardado por pestaña (debounce 800ms + guardado en blur)
  // ==========================================================================

  const panelState = new WeakMap();

  function stateOf(panel) {
    let st = panelState.get(panel);
    if (!st) {
      st = { timer: null, saving: null };
      panelState.set(panel, st);
    }
    return st;
  }

  function markSaving() {
    const note = document.querySelector("[data-autosave-note]");
    if (note) {
      note.textContent = "Guardando…";
      note.classList.remove("is-error");
    }
  }

  function markSaved(isError) {
    const note = document.querySelector("[data-autosave-note]");
    if (!note) return;
    note.textContent = isError ? "No se pudo guardar" : "Guardado ✓";
    note.classList.toggle("is-error", !!isError);
    clearTimeout(note._t);
    if (!isError) note._t = setTimeout(() => (note.textContent = ""), 1600);
  }

  function savePanel(panel) {
    if (!panel) return Promise.resolve();
    const st = stateOf(panel);
    clearTimeout(st.timer);
    st.timer = null;

    markSaving();
    st.saving = request(panel.dataset.autosaveUrl, "PATCH", collectPanelPayload(panel))
      .then((data) => {
        markSaved(false);
        return data;
      })
      .catch(() => {
        markSaved(true);
        toast("No se pudo guardar la sección.", "error");
      });
    return st.saving;
  }

  function scheduleSave(panel) {
    if (!panel) return;
    const st = stateOf(panel);
    clearTimeout(st.timer);
    st.timer = setTimeout(() => savePanel(panel), 800);
  }

  function initAutosavePanels(root) {
    const panels = Array.from(root.querySelectorAll("[data-tab-panel]"));

    panels.forEach((panel) => {
      panel.addEventListener("input", (e) => {
        if (e.target.matches("[data-autosave], [data-field]")) scheduleSave(panel);
      });
      panel.addEventListener("change", (e) => {
        if (!e.target.matches("[data-autosave], [data-field]")) return;
        if (e.target.tagName === "SELECT" || e.target.type === "checkbox") scheduleSave(panel);
      });
      // blur no burbujea: se captura en fase de captura sobre el panel completo.
      panel.addEventListener(
        "blur",
        (e) => {
          if (e.target && e.target.matches && e.target.matches("[data-autosave], [data-field]")) {
            savePanel(panel);
          }
        },
        true
      );
    });

    return panels;
  }

  /** Vacía cualquier guardado pendiente antes de navegar a Vista previa o Generar PDF (data-nav-flush). */
  function flushAllPanels(root) {
    const promises = [];
    root.querySelectorAll("[data-tab-panel]").forEach((panel) => {
      const st = stateOf(panel);
      if (st.timer) promises.push(savePanel(panel));
      else if (st.saving) promises.push(st.saving);
    });
    return Promise.all(promises);
  }

  function initNavFlush(root) {
    document.querySelectorAll("[data-nav-flush]").forEach((el) => {
      if (el.tagName === "A") {
        el.addEventListener("click", (e) => {
          e.preventDefault();
          const href = el.href;
          flushAllPanels(root).finally(() => {
            window.location.href = href;
          });
        });
      } else if (el.tagName === "FORM") {
        el.addEventListener("submit", (e) => {
          e.preventDefault();
          flushAllPanels(root).finally(() => el.submit());
        });
      }
    });
  }

  // ==========================================================================
  // Editor — pestañas, estado, subtotales, sugerir keywords
  // ==========================================================================

  function initPropuestaTabs() {
    const tabs = document.querySelectorAll("#propuestaTabs .tabs__item");
    if (!tabs.length) return;

    tabs.forEach((tab) => {
      tab.addEventListener("click", () => {
        tabs.forEach((t) => t.classList.remove("is-active"));
        tab.classList.add("is-active");
        document.querySelectorAll("[data-tab-panel]").forEach((panel) => {
          panel.hidden = panel.dataset.tabPanel !== tab.dataset.tab;
        });
      });
    });
  }

  // Mismo mapeo de colores que components/badge.blade.php para 'borrador'/'enviada'/'aprobada'/'rechazada'.
  const ESTADO_BADGE = {
    borrador: ["Borrador", "badge--neutral"],
    enviada: ["Enviada", "badge--info"],
    aprobada: ["Aprobada", "badge--success"],
    rechazada: ["Rechazada", "badge--danger"],
  };

  function initEstadoSelect(root) {
    const select = document.getElementById("propuestaEstado");
    const badge = document.querySelector("[data-estado-badge]");
    if (!select) return;

    select.addEventListener("change", () => {
      const previous = select.dataset.previous || select.value;
      request(root.dataset.estadoUrl, "PUT", { estado: select.value })
        .then((data) => {
          select.dataset.previous = data.estado;
          const [label, cls] = ESTADO_BADGE[data.estado] || [data.estado_label, "badge--neutral"];
          if (badge) {
            badge.className = "badge " + cls;
            badge.textContent = label;
          }
          toast("Estado actualizado.", "success");
        })
        .catch(() => {
          select.value = previous;
          toast("No se pudo actualizar el estado.", "error");
        });
    });
    select.dataset.previous = select.value;
  }

  function initSubtotales(root) {
    const precioInput = document.getElementById("rf_precio_mensual");
    const horasInput = document.getElementById("rf_horas_mensuales");
    const tarifaInput = document.getElementById("rf_tarifa_hora");
    const desgloseOut = document.querySelector("[data-desglose-output]");
    const precioEcho = document.querySelector("[data-precio-echo]");
    const horasEcho = document.querySelector("[data-horas-echo]");
    const tarifaEcho = document.querySelector("[data-tarifa-echo]");
    const subtotalOut = document.querySelector("[data-subtotal-output]");
    if (!precioInput && !horasInput && !tarifaInput) return;

    function money(value) {
      if (value === null || value === undefined || value === "" || Number.isNaN(Number(value))) return null;
      return "$" + Number(value).toLocaleString("es-MX", { maximumFractionDigits: 2 });
    }

    function recompute() {
      const precio = precioInput ? precioInput.value : "";
      const horas = horasInput ? horasInput.value : "";
      const tarifa = tarifaInput ? tarifaInput.value : "";

      if (precioEcho) precioEcho.textContent = money(precio) ?? "—";
      if (horasEcho) horasEcho.textContent = horas !== "" ? horas : "—";
      if (tarifaEcho) tarifaEcho.textContent = money(tarifa) ?? "—";

      const horasNum = Number(horas);
      const tarifaNum = Number(tarifa);
      if (horas !== "" && tarifa !== "" && !Number.isNaN(horasNum) && !Number.isNaN(tarifaNum)) {
        const subtotal = money(horasNum * tarifaNum);
        if (desgloseOut) desgloseOut.textContent = `${horas} hrs × ${money(tarifaNum)} = ${subtotal}`;
        if (subtotalOut) subtotalOut.textContent = subtotal;
      } else {
        if (desgloseOut) desgloseOut.textContent = "—";
        if (subtotalOut) subtotalOut.textContent = "—";
      }
    }

    [precioInput, horasInput, tarifaInput].forEach((el) => {
      if (el) el.addEventListener("input", recompute);
    });
    recompute();
  }

  function tablaConsultasRowHtml(fila) {
    return `<tr data-row>
      <td><input class="input" type="text" data-field="consulta" value="${escapeHtml(fila.consulta)}"></td>
      <td><input class="input" type="text" data-field="posicion" value="${escapeHtml(fila.posicion)}"></td>
      <td><input class="input" type="text" data-field="impresiones" value="${escapeHtml(fila.impresiones)}"></td>
      <td><input class="input" type="text" data-field="clics" value="${escapeHtml(fila.clics)}"></td>
      <td><input class="input" type="text" data-field="oportunidad" value="${escapeHtml(fila.oportunidad)}"></td>
      <td><button type="button" class="btn--icon" data-row-remove title="Eliminar"><i class="fa-solid fa-trash"></i></button></td>
    </tr>`;
  }

  /** Llama al endpoint real (Propuesta::sugerirConsultasDesdeBanco()) — nunca un arreglo local fijo. */
  function initSugerirKeywords(root) {
    const btn = document.querySelector("[data-sugerir-keywords]");
    if (!btn) return;

    btn.addEventListener("click", () => {
      if (btn.disabled) return;
      const originalHtml = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Sugiriendo…';

      request(root.dataset.sugerirUrl, "POST", {})
        .then((data) => {
          const container = document.querySelector('[data-tab-panel="situacion"] [data-row-list="tabla_consultas"]');
          const filas = (data.situacion_actual && data.situacion_actual.tabla_consultas) || [];
          if (container && filas.length) {
            container.innerHTML = filas.map(tablaConsultasRowHtml).join("");
            updateRowListButtons(container);
          }
          toast(`${data.agregadas} consulta${data.agregadas === 1 ? "" : "s"} agregada${data.agregadas === 1 ? "" : "s"} desde el banco de keywords.`, "success");
        })
        .catch((error) => {
          const message = (error.body && error.body.message) || "No se pudo sugerir consultas.";
          toast(message, "error");
        })
        .finally(() => {
          btn.disabled = false;
          btn.innerHTML = originalHtml;
        });
    });
  }

  document.addEventListener("shell:ready", () => {
    initPropuestaFiltros();
    initPropuestaModal();
    initPropuestaDelete();

    const root = document.querySelector("[data-propuesta-root]");
    if (!root) return;

    initRowLists(root);
    initAutosavePanels(root);
    initPropuestaTabs();
    initEstadoSelect(root);
    initSubtotales(root);
    initSugerirKeywords(root);
    initNavFlush(root);
  });
})();
