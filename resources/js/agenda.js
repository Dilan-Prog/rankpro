/**
 * Módulo Agendar reunión (admin): pestañas (Agenda semanal / Citas /
 * Disponibilidad), navegación de la vista semanal, buscador de la tabla de
 * citas y el cajón de detalle de una reunión (un <x-modal> reutilizado como
 * panel de solo lectura + botón Cancelar).
 *
 * Todos los datos vienen ya renderizados por el servidor en
 * [data-agenda-panel][data-reuniones] (JSON) — nada de esto pide algo nuevo
 * al backend. El botón "Cancelar" del cajón es el mismo <form> POST de
 * siempre (ver [data-agenda-forms-cancelar] en el blade): este script solo
 * lo mueve dentro del modal, no cambia su lógica.
 *
 * OJO: el root de este módulo es [data-agenda-panel], que envuelve TODA la
 * pantalla (incluida la tabla de citas y el modal) — si algún día se agrega
 * un data-* nuevo, debe colgar de ese contenedor más externo o
 * root.querySelector no lo va a encontrar.
 */
(function () {
  "use strict";

  const NOMBRES_DIA = ["Domingo", "Lunes", "Martes", "Miércoles", "Jueves", "Viernes", "Sábado"];
  const NOMBRES_DIA_CORTO = ["DOM", "LUN", "MAR", "MIÉ", "JUE", "VIE", "SÁB"];

  function ymd(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, "0");
    const d = String(date.getDate()).padStart(2, "0");
    return `${y}-${m}-${d}`;
  }

  function addDays(date, n) {
    const copy = new Date(date);
    copy.setDate(copy.getDate() + n);
    return copy;
  }

  /** Lunes de la semana que contiene `date` (getDay(): 0=domingo..6=sábado). */
  function lunesDeLaSemana(date) {
    const dow = date.getDay();
    const offset = dow === 0 ? -6 : 1 - dow;
    const lunes = addDays(date, offset);
    lunes.setHours(0, 0, 0, 0);
    return lunes;
  }

  // ---------- Pestañas ----------

  function initTabs(root) {
    const tabs = root.querySelectorAll("[data-agenda-tabs] [data-agenda-tab]");
    if (!tabs.length) return;

    tabs.forEach((tab) => {
      tab.addEventListener("click", () => {
        const nombre = tab.dataset.agendaTab;

        tabs.forEach((t) => t.classList.toggle("is-active", t === tab));
        root.querySelectorAll("[data-agenda-panel-tab]").forEach((panel) => {
          panel.hidden = panel.dataset.agendaPanelTab !== nombre;
        });
      });
    });
  }

  // ---------- Vista de semana ----------

  function initSemana(root, reuniones) {
    const grid = root.querySelector("[data-agenda-semana-grid]");
    const label = root.querySelector("[data-agenda-semana-label]");
    if (!grid || !label) return;

    const hoy = new Date();
    hoy.setHours(0, 0, 0, 0);
    let inicioSemana = lunesDeLaSemana(hoy);

    function render() {
      const dias = Array.from({ length: 7 }, (_, i) => addDays(inicioSemana, i));
      const finSemana = dias[6];

      label.textContent = `${dias[0].getDate()} ${NOMBRES_DIA[dias[0].getDay()].slice(0, 3)} – ${finSemana.getDate()} ${NOMBRES_DIA[finSemana.getDay()].slice(0, 3)}`;

      grid.innerHTML = "";
      dias.forEach((dia) => {
        const clave = ymd(dia);
        const esHoy = clave === ymd(hoy);
        const delDia = reuniones
          .filter((r) => r.fecha === clave)
          .sort((a, b) => a.horaInicio.localeCompare(b.horaInicio));

        const col = document.createElement("div");
        col.className = "agenda-dia" + (esHoy ? " agenda-dia--hoy" : "");

        const cuerpoHtml = delDia.length
          ? delDia
              .map(
                (r) => `
                  <button type="button" class="agenda-bloque agenda-bloque--${r.estado}" data-agenda-ver="${r.id}">
                    <span class="agenda-bloque__hora">${r.horaCorta}</span>
                    <span class="agenda-bloque__nombre">${escapeHtml(r.nombre)}</span>
                  </button>`
              )
              .join("")
          : '<p class="agenda-dia__vacio">Sin reuniones</p>';

        col.innerHTML = `
          <div class="agenda-dia__header">
            <div class="agenda-dia__nombre">${NOMBRES_DIA_CORTO[dia.getDay()]}</div>
            <div class="agenda-dia__numero">${dia.getDate()}</div>
          </div>
          <div class="agenda-dia__cuerpo">${cuerpoHtml}</div>`;

        grid.appendChild(col);
      });
    }

    root.querySelector("[data-agenda-semana-hoy]")?.addEventListener("click", () => {
      inicioSemana = lunesDeLaSemana(hoy);
      render();
    });
    root.querySelector("[data-agenda-semana-prev]")?.addEventListener("click", () => {
      inicioSemana = addDays(inicioSemana, -7);
      render();
    });
    root.querySelector("[data-agenda-semana-next]")?.addEventListener("click", () => {
      inicioSemana = addDays(inicioSemana, 7);
      render();
    });

    render();
  }

  function escapeHtml(value) {
    return String(value == null ? "" : value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  // ---------- Buscador + filtro de estado de la pestaña Citas ----------

  function initBuscadorCitas(root) {
    const input = root.querySelector("#agendaCitasBuscar");
    const selectEstado = root.querySelector("#agendaCitasEstado");
    if (!input && !selectEstado) return;

    const sinResultados = root.querySelector("#agendaCitasSinResultados");

    function aplicarFiltro() {
      const texto = (input?.value || "").trim().toLowerCase();
      const estado = selectEstado?.value || "";
      const filas = root.querySelectorAll("[data-agenda-cita-row]");
      let visibles = 0;

      filas.forEach((fila) => {
        const coincideTexto = !texto || (fila.dataset.agendaBuscar || "").includes(texto);
        const coincideEstado = !estado || fila.dataset.agendaEstado === estado;
        const coincide = coincideTexto && coincideEstado;
        fila.style.display = coincide ? "" : "none";
        if (coincide) visibles++;
      });

      if (sinResultados) sinResultados.hidden = visibles !== 0;
    }

    input?.addEventListener("input", aplicarFiltro);
    selectEstado?.addEventListener("change", aplicarFiltro);
  }

  // ---------- Cajón de detalle ----------

  /**
   * Mueve dentro de `contenedor` el elemento marcado `[atributo="id"]` que
   * vive en alguno de los `[data-agenda-forms-*]` ocultos del blade — nunca
   * lo recrea, es el mismo <form> real con su CSRF y su action. Antes de
   * traer el nuevo, regresa a su holder lo que haya quedado de una apertura
   * anterior: un innerHTML="" aquí lo destruiría (no solo lo sacaría del
   * cajón) y esa reunión se quedaría sin ese control para siempre la
   * próxima vez que se abra. Devuelve el elemento movido (o null si no
   * había ninguno que mover, p. ej. una reunión ya cancelada).
   */
  function moverAlCajon(root, contenedor, holder, atributo, id) {
    Array.from(contenedor.children).forEach((hijo) => {
      if (holder && hijo.matches(`[${atributo}]`)) {
        holder.appendChild(hijo);
      } else {
        hijo.remove();
      }
    });
    if (id == null) return null;
    const elemento = root.querySelector(`[${atributo}="${id}"]`);
    if (elemento) contenedor.appendChild(elemento);
    return elemento;
  }

  function initDetalle(root, reuniones) {
    const porId = new Map(reuniones.map((r) => [String(r.id), r]));

    function abrir(id) {
      const r = porId.get(String(id));
      if (!r || !window.AgencyOS) return;

      root.querySelector("#agendaDetalleNombre").textContent = r.nombre;
      root.querySelector("#agendaDetalleBadge").innerHTML = `<span class="badge ${r.estadoClase}">${escapeHtml(r.estadoLabel)}</span>`;
      root.querySelector("#agendaDetalleFecha").textContent = `${r.fechaLarga} · ${r.horaInicio} – ${r.horaFin}`;
      root.querySelector("#agendaDetalleCorreo").textContent = r.email;
      root.querySelector("#agendaDetalleTelefono").textContent = r.telefono || "—";
      root.querySelector("#agendaDetalleCliente").textContent = r.cliente || "— Sin cliente vinculado —";

      const notasWrap = root.querySelector("#agendaDetalleNotasWrap");
      if (r.notas) {
        notasWrap.hidden = false;
        root.querySelector("#agendaDetalleNotas").textContent = r.notas;
      } else {
        notasWrap.hidden = true;
      }

      const idSiActiva = r.cancelada ? null : r.id;

      moverAlCajon(
        root,
        root.querySelector("#agendaDetalleAcciones"),
        root.querySelector("[data-agenda-forms-cancelar]"),
        "data-agenda-form-cancelar",
        idSiActiva
      );

      const reagendarWrap = root.querySelector("#agendaDetalleReagendarWrap");
      const formReagendar = moverAlCajon(
        root,
        root.querySelector("#agendaDetalleReagendar"),
        root.querySelector("[data-agenda-forms-reagendar]"),
        "data-agenda-form-reagendar",
        idSiActiva
      );
      reagendarWrap.hidden = !formReagendar;

      const estadosWrap = root.querySelector("#agendaDetalleEstadosWrap");
      const divEstados = moverAlCajon(
        root,
        root.querySelector("#agendaDetalleEstados"),
        root.querySelector("[data-agenda-forms-estado]"),
        "data-agenda-form-estado",
        idSiActiva
      );
      estadosWrap.hidden = !divEstados;

      window.AgencyOS.openModal("agendaDetalleModal");
    }

    root.addEventListener("click", (e) => {
      const trigger = e.target.closest("[data-agenda-ver]");
      if (!trigger) return;
      abrir(trigger.dataset.agendaVer);
    });
  }

  // ---------- Nueva cita (modal) ----------

  function initNuevaCita(root) {
    const btn = root.querySelector("[data-agenda-nueva-cita]");
    const fecha = root.querySelector("#agenda_nueva_fecha");
    const hora = root.querySelector("#agenda_nueva_hora");
    const iniciaEn = root.querySelector("#agenda_nueva_inicia_en");
    if (!btn) return;

    btn.addEventListener("click", () => {
      if (window.AgencyOS) window.AgencyOS.openModal("agendaNuevaCitaModal");
    });

    // El backend espera un único campo "Y-m-d H:i" (mismo formato que la
    // página pública) — se arma justo antes de enviar, a partir de los dos
    // inputs nativos de fecha/hora que sí son cómodos de llenar a mano.
    const form = iniciaEn?.closest("form");
    form?.addEventListener("submit", () => {
      if (fecha?.value && hora?.value) {
        iniciaEn.value = `${fecha.value} ${hora.value}`;
      }
    });
  }

  document.addEventListener("shell:ready", () => {
    const root = document.querySelector("[data-agenda-panel]");
    if (!root) return;

    let reuniones = [];
    try {
      reuniones = JSON.parse(root.dataset.reuniones || "[]");
    } catch (err) {
      reuniones = [];
    }

    initTabs(root);
    initSemana(root, reuniones);
    initBuscadorCitas(root);
    initDetalle(root, reuniones);
    initNuevaCita(root);
  });
})();
