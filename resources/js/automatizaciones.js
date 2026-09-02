/**
 * Automatizaciones module — mismo patrón que seo.js: fases con
 * autosave/checklist, más CRUD de flujos vía fetch() sin recargar la
 * página. El motor de automatización (n8n) vive fuera del sistema; esto
 * solo lleva el registro del proceso de venta/entrega y las estadísticas.
 */
(function () {
  "use strict";

  const { toast } = window.AgencyOS;
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

  const BADGE_MAP = {
    activo: ["Activo", "badge--success"],
    pausado: ["Pausado", "badge--warning"],
    inactivo: ["Inactivo", "badge--neutral"],
  };

  const TIPO_LABEL = {
    whatsapp: "WhatsApp",
    crm: "CRM",
    email: "Email",
    notificaciones: "Notificaciones",
    otro: "Otro",
  };

  const COMPLEJIDAD_LABEL = {
    basico: "Básico",
    intermedio: "Intermedio",
    avanzado: "Avanzado",
  };

  function escapeHtml(value) {
    return String(value == null ? "" : value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  function badge(status) {
    const [label, cls] = BADGE_MAP[status] || [status, "badge--neutral"];
    return `<span class="badge ${cls}">${label}</span>`;
  }

  function jsonHeaders() {
    return {
      "Content-Type": "application/json",
      Accept: "application/json",
      "X-CSRF-TOKEN": csrfToken,
    };
  }

  function request(url, method, payload) {
    return fetch(url, { method, headers: jsonHeaders(), body: JSON.stringify(payload || {}) }).then((res) => {
      if (!res.ok) throw new Error("request_failed");
      return res.json();
    });
  }

  /** Filtra el select de "Servicio" del formulario de creación al cliente elegido (todas las opciones ya están renderizadas con data-cliente, ocultas por JS). */
  function initServicioCascade() {
    const clienteSelect = document.getElementById("cliente_id");
    const servicioSelect = document.getElementById("servicio_id");
    if (!clienteSelect || !servicioSelect) return;

    const options = Array.from(servicioSelect.options).filter((o) => o.dataset.cliente);

    function update() {
      const clienteId = clienteSelect.value;
      options.forEach((option) => {
        option.hidden = option.dataset.cliente !== clienteId;
      });
      if (servicioSelect.value && !options.find((o) => o.value === servicioSelect.value && !o.hidden)) {
        servicioSelect.value = "";
      }
    }

    clienteSelect.addEventListener("change", update);
    update();
  }

  // ---------- Panel de fase: checklist + autosave de campos + estado del botón Aprobar ----------
  function initFasePanel() {
    const panel = document.querySelector("[data-fase-panel]");
    const form = document.getElementById("faseForm");
    if (!panel || !form) return;

    const actionUrl = form.dataset.faseAction;
    const aprobarBtn = document.querySelector("[data-aprobar-btn]");
    const autosaveNote = document.querySelector("[data-autosave-note]");
    const checklistInputs = Array.from(panel.querySelectorAll("[data-checklist-item]"));

    function showSaved() {
      if (!autosaveNote) return;
      autosaveNote.textContent = "Guardado ✓";
      autosaveNote.classList.add("is-visible");
      clearTimeout(showSaved._t);
      showSaved._t = setTimeout(() => autosaveNote.classList.remove("is-visible"), 1500);
    }

    function recomputeLocalCompleteness() {
      const allChecked = checklistInputs.length > 0 && checklistInputs.every((i) => i.checked);
      if (aprobarBtn) aprobarBtn.disabled = !allChecked;
      return allChecked;
    }

    function collectPayload() {
      const payload = {};

      panel.querySelectorAll("[data-autosave]").forEach((field) => {
        payload[field.name || field.id] = field.value;
      });
      panel.querySelectorAll("[data-autosave-toggle]").forEach((field) => {
        payload[field.name] = field.checked;
      });

      const checklist = {};
      checklistInputs.forEach((input) => {
        checklist[input.dataset.checklistItem] = input.checked;
      });
      payload.checklist = checklist;

      return payload;
    }

    function save(silent) {
      return request(actionUrl, "POST", collectPayload())
        .then((data) => {
          if (!silent) showSaved();
          if (typeof data.completo === "boolean" && aprobarBtn) aprobarBtn.disabled = !data.completo;
        })
        .catch(() => toast("No se pudo guardar el avance de la fase.", "error"));
    }

    checklistInputs.forEach((input) => {
      input.addEventListener("change", () => {
        recomputeLocalCompleteness();
        save(true);
      });
    });

    let debounceTimer;
    panel.querySelectorAll("[data-autosave]").forEach((field) => {
      const liveEvent = field.tagName === "TEXTAREA" || field.type === "text" || field.type === "number" ? "input" : "change";
      field.addEventListener(liveEvent, () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => save(true), 800);
      });
      field.addEventListener("blur", () => {
        clearTimeout(debounceTimer);
        save(true);
      });
    });

    panel.querySelectorAll("[data-autosave-toggle]").forEach((field) => {
      field.addEventListener("change", () => save(true));
    });

    form.addEventListener("submit", (e) => {
      e.preventDefault();
      save(false);
    });
  }

  function toggleEmptyState(emptyAttr, tableAttr, rowsContainer) {
    const empty = document.querySelector(`[${emptyAttr}]`);
    const table = document.querySelector(`[${tableAttr}]`);
    const hasRows = rowsContainer.children.length > 0;
    if (empty) empty.hidden = hasRows;
    if (table) table.hidden = !hasRows;
  }

  // ---------- Flujos automatizados ----------
  function initFlujos() {
    const form = document.getElementById("flujoForm");
    const rowsBody = document.querySelector("[data-flujos-rows]");
    if (!form || !rowsBody) return;

    const modalTitle = document.querySelector("[data-flujo-modal-title]");
    const submitLabel = document.querySelector("[data-flujo-submit-label]");

    function rowHtml(fl) {
      const integraciones = (fl.integraciones || []).join(", ");
      return `<tr data-flujo-id="${fl.id}" data-flujo-nombre="${escapeHtml(fl.nombre)}" data-flujo-tipo="${fl.tipo}" data-flujo-complejidad="${fl.complejidad}" data-flujo-integraciones="${escapeHtml(integraciones)}" data-flujo-horas-ahorradas-mes="${fl.horas_ahorradas_mes ?? ""}" data-flujo-mensajes-gestionados-mes="${fl.mensajes_gestionados_mes ?? ""}" data-flujo-estado="${fl.estado}" data-flujo-fecha-implementado="${fl.fecha_implementado ? String(fl.fecha_implementado).slice(0, 10) : ""}" data-flujo-notas="${escapeHtml(fl.notas || "")}">
        <td>${escapeHtml(fl.nombre)}</td>
        <td><span style="font-size:var(--text-xs); color:var(--color-muted-foreground);">${TIPO_LABEL[fl.tipo] || fl.tipo}</span></td>
        <td><span style="font-size:var(--text-xs); color:var(--color-muted-foreground);">${COMPLEJIDAD_LABEL[fl.complejidad] || fl.complejidad}</span></td>
        <td><span style="font-size:var(--text-xs); color:var(--color-muted-foreground);">${escapeHtml(integraciones) || "—"}</span></td>
        <td class="u-mono">${fl.horas_ahorradas_mes ?? "—"}</td>
        <td class="u-mono">${fl.mensajes_gestionados_mes != null ? Number(fl.mensajes_gestionados_mes).toLocaleString("es-MX") : "—"}</td>
        <td>${badge(fl.estado)}</td>
        <td><div style="display:flex; gap:4px;">
          <button type="button" class="btn--icon" title="Editar" data-edit-flujo="${fl.id}"><i class="fa-solid fa-pen"></i></button>
          <button type="button" class="btn--icon" title="Eliminar" style="color:var(--text-danger);" data-delete-flujo="${fl.id}"><i class="fa-solid fa-trash"></i></button>
        </div></td>
      </tr>`;
    }

    function resetForm() {
      form.reset();
      delete form.dataset.editingId;
      if (modalTitle) modalTitle.textContent = "Agregar Flujo";
      if (submitLabel) submitLabel.textContent = "Agregar";
    }

    document.querySelector('[onclick*="flujoModal"]')?.addEventListener("click", resetForm);

    form.addEventListener("submit", (e) => {
      e.preventDefault();
      const editingId = form.dataset.editingId;
      const url = editingId ? form.dataset.updateActionTemplate.replace("__ID__", editingId) : form.dataset.storeAction;
      const method = editingId ? "PUT" : "POST";
      const payload = Object.fromEntries(new FormData(form).entries());

      // El input es un solo texto separado por comas — el backend espera un array.
      const integracionesTexto = payload.integraciones_texto || "";
      delete payload.integraciones_texto;
      payload.integraciones = integracionesTexto
        .split(",")
        .map((s) => s.trim())
        .filter(Boolean);

      request(url, method, payload)
        .then((flujo) => {
          const existing = rowsBody.querySelector(`[data-flujo-id="${flujo.id}"]`);
          if (existing) existing.outerHTML = rowHtml(flujo);
          else rowsBody.insertAdjacentHTML("beforeend", rowHtml(flujo));

          toggleEmptyState("data-flujos-empty", "data-flujos-table", rowsBody);
          resetForm();
          window.AgencyOS.closeModal("flujoModal");
          toast(editingId ? "Flujo actualizado." : "Flujo agregado.", "success");
        })
        .catch(() => toast("No se pudo guardar el flujo.", "error"));
    });

    rowsBody.addEventListener("click", (e) => {
      const editBtn = e.target.closest("[data-edit-flujo]");
      const deleteBtn = e.target.closest("[data-delete-flujo]");

      if (editBtn) {
        const row = editBtn.closest("tr");
        form.dataset.editingId = row.dataset.flujoId;
        form.querySelector("#fl_nombre").value = row.dataset.flujoNombre || "";
        form.querySelector("#fl_tipo").value = row.dataset.flujoTipo;
        form.querySelector("#fl_complejidad").value = row.dataset.flujoComplejidad;
        form.querySelector("#fl_integraciones").value = row.dataset.flujoIntegraciones || "";
        form.querySelector("#fl_horas").value = row.dataset.flujoHorasAhorradasMes || "";
        form.querySelector("#fl_mensajes").value = row.dataset.flujoMensajesGestionadosMes || "";
        form.querySelector("#fl_estado").value = row.dataset.flujoEstado;
        form.querySelector("#fl_fecha").value = row.dataset.flujoFechaImplementado || "";
        form.querySelector("#fl_notas").value = row.dataset.flujoNotas || "";
        if (modalTitle) modalTitle.textContent = "Editar Flujo";
        if (submitLabel) submitLabel.textContent = "Guardar Cambios";
        window.AgencyOS.openModal("flujoModal");
      }

      if (deleteBtn) {
        if (!window.confirm("¿Eliminar este flujo?")) return;
        const id = deleteBtn.dataset.deleteFlujo;
        request(`/admin/automatizaciones/flujos/${id}`, "DELETE")
          .then(() => {
            rowsBody.querySelector(`[data-flujo-id="${id}"]`)?.remove();
            toggleEmptyState("data-flujos-empty", "data-flujos-table", rowsBody);
            toast("Flujo eliminado.", "success");
          })
          .catch(() => toast("No se pudo eliminar el flujo.", "error"));
      }
    });
  }

  document.addEventListener("shell:ready", () => {
    initServicioCascade();
    initFasePanel();
    initFlujos();
  });
})();
