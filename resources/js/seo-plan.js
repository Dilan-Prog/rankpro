// Módulo «seo-plan»: pestañas, vistas kanban/lista, filtros, arrastrar y soltar,
// modales de actividad y clúster, bitácora y asignación de keywords.
(() => {
  "use strict";

  const root = document.querySelector("[data-seo-plan]");
  if (!root) return;

  const token = document.querySelector('meta[name="csrf-token"]')?.content;
  const configEl = root.querySelector("[data-spl-config]");
  const config = configEl ? JSON.parse(configEl.textContent) : {};
  const { toast } = window.AgencyOS || {};

  const avisar = (mensaje, tipo) => (toast ? toast(mensaje, tipo) : window.alert(mensaje));
  const url = (plantilla, reemplazos) =>
    Object.entries(reemplazos).reduce((u, [k, v]) => u.replace(k, v), plantilla);

  async function peticion(destino, metodo, cuerpo) {
    const respuesta = await fetch(destino, {
      method: metodo,
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
        "X-CSRF-TOKEN": token,
        "X-Requested-With": "XMLHttpRequest",
      },
      body: cuerpo ? JSON.stringify(cuerpo) : undefined,
    });
    if (!respuesta.ok) {
      let mensaje = "No se pudo completar la acción.";
      try {
        const json = await respuesta.json();
        mensaje = json.message || mensaje;
      } catch (e) { /* respuesta sin JSON */ }
      throw new Error(mensaje);
    }
    return respuesta.json();
  }

  // ---------- Pestañas (recuerdan la última abierta; sin storage disponible no pasa nada) ----------
  const pestanas = [...root.querySelectorAll("[data-spl-tab]")];
  const paneles = [...root.querySelectorAll("[data-spl-panel]")];

  function activarPestana(nombre, enfocar) {
    pestanas.forEach((t) => {
      const activa = t.dataset.splTab === nombre;
      t.classList.toggle("is-active", activa);
      t.setAttribute("aria-selected", activa ? "true" : "false");
      t.tabIndex = activa ? 0 : -1;
      if (activa && enfocar) t.focus();
    });
    paneles.forEach((p) => { p.hidden = p.dataset.splPanel !== nombre; });
    try { sessionStorage.setItem("seoPlanPestana", nombre); } catch (e) { /* ignorar */ }
  }

  pestanas.forEach((t, i) => {
    t.addEventListener("click", () => activarPestana(t.dataset.splTab));
    t.addEventListener("keydown", (e) => {
      if (e.key !== "ArrowRight" && e.key !== "ArrowLeft") return;
      e.preventDefault();
      const siguiente = pestanas[(i + (e.key === "ArrowRight" ? 1 : -1) + pestanas.length) % pestanas.length];
      activarPestana(siguiente.dataset.splTab, true);
    });
  });

  try {
    const guardada = sessionStorage.getItem("seoPlanPestana");
    if (guardada && pestanas.some((t) => t.dataset.splTab === guardada)) activarPestana(guardada);
  } catch (e) { /* ignorar */ }

  // ---------- Vistas: kanban / lista / línea de tiempo / calendario ----------
  const kanban = root.querySelector("[data-spl-kanban]");
  const lista = root.querySelector("[data-spl-lista]");
  const panelesVista = [...root.querySelectorAll("[data-spl-vista-panel]")];
  const botonesVista = [...root.querySelectorAll("[data-spl-vista]")];
  // La vista elegida es una preferencia de cada persona, no un dato: va en localStorage y sin él se queda el kanban.
  const CLAVE_VISTA = "seoPlanVista";

  // Calendario: el servidor pinta todos los meses del rango y aquí solo se alterna cuál se ve.
  const cal = root.querySelector("[data-spl-cal]");
  const mesesCal = cal ? [...cal.querySelectorAll("[data-spl-cal-mes]")] : [];
  const calHoy = cal ? Number(cal.dataset.indiceHoy) || 0 : 0;
  let calIndice = calHoy;

  function mostrarMes(i) {
    if (!mesesCal.length) return;
    calIndice = Math.max(0, Math.min(mesesCal.length - 1, i));
    mesesCal.forEach((m, k) => { m.hidden = k !== calIndice; });
    cal.querySelector("[data-spl-cal-titulo]").textContent = mesesCal[calIndice].dataset.etiqueta;
    cal.querySelector("[data-spl-cal-prev]").disabled = calIndice === 0;
    cal.querySelector("[data-spl-cal-sig]").disabled = calIndice === mesesCal.length - 1;
  }

  cal?.querySelector("[data-spl-cal-prev]").addEventListener("click", () => mostrarMes(calIndice - 1));
  cal?.querySelector("[data-spl-cal-sig]").addEventListener("click", () => mostrarMes(calIndice + 1));
  cal?.querySelector("[data-spl-cal-hoy]").addEventListener("click", () => mostrarMes(calHoy));

  function activarVista(nombre) {
    panelesVista.forEach((p) => { p.hidden = p.dataset.splVistaPanel !== nombre; });
    botonesVista.forEach((b) => {
      const activo = b.dataset.splVista === nombre;
      b.classList.toggle("is-active", activo);
      b.setAttribute("aria-pressed", activo ? "true" : "false");
    });
    if (nombre === "calendario") mostrarMes(calIndice);
    try { localStorage.setItem(CLAVE_VISTA, nombre); } catch (e) { /* sin storage: no se recuerda */ }
  }

  botonesVista.forEach((boton) => boton.addEventListener("click", () => activarVista(boton.dataset.splVista)));

  try {
    const vistaGuardada = localStorage.getItem(CLAVE_VISTA);
    if (vistaGuardada && botonesVista.some((b) => b.dataset.splVista === vistaGuardada)) activarVista(vistaGuardada);
  } catch (e) { /* ignorar */ }

  // Tras mover una tarjeta, la línea de tiempo y el calendario (pintados en servidor) se alinean sin recargar.
  function sincronizarVistas(id, estado, actividad) {
    root.querySelectorAll(`[data-spl-vista-item][data-id="${id}"]`).forEach((el) => { el.dataset.estado = estado; });
    root.querySelectorAll("[data-spl-abrir]").forEach((b) => {
      try { if (JSON.parse(b.dataset.actividad).id === Number(id)) b.dataset.actividad = actividad; } catch (e) { /* ignorar */ }
    });
  }

  // ---------- Filtros (en el navegador: son pocas actividades por cliente) ----------
  const filtros = [...root.querySelectorAll("[data-spl-filtro]")];
  const botonLimpiar = root.querySelector("[data-spl-limpiar]");

  function aplicarFiltros() {
    const activos = filtros.filter((f) => f.value !== "");
    root.querySelectorAll("[data-spl-item], [data-spl-fila], [data-spl-vista-item]").forEach((el) => {
      el.hidden = !activos.every((f) => el.dataset[f.dataset.splFiltro] === f.value);
    });
    if (botonLimpiar) botonLimpiar.hidden = activos.length === 0;
    actualizarColumnas();
    if (lista) {
      const visibles = lista.querySelectorAll("[data-spl-fila]:not([hidden])").length;
      const vacia = lista.querySelector("[data-spl-lista-vacia]");
      if (vacia) vacia.hidden = visibles > 0;
    }
  }

  function actualizarColumnas() {
    root.querySelectorAll("[data-spl-col]").forEach((col) => {
      const visibles = col.querySelectorAll("[data-spl-item]:not([hidden])").length;
      const total = col.querySelectorAll("[data-spl-item]").length;
      const contador = col.querySelector("[data-spl-count]");
      if (contador) contador.textContent = String(total);
      const vacia = col.querySelector("[data-spl-vacia]");
      if (vacia) vacia.hidden = visibles > 0;
    });
  }

  filtros.forEach((f) => f.addEventListener("change", aplicarFiltros));
  botonLimpiar?.addEventListener("click", () => {
    filtros.forEach((f) => { f.value = ""; });
    aplicarFiltros();
  });

  // ---------- Arrastrar y soltar (HTML5) ----------
  let arrastrada = null;

  function despuesDe(cuerpo, y) {
    // La tarjeta que queda justo debajo del cursor; null = al final.
    const otras = [...cuerpo.querySelectorAll("[data-spl-item]:not(.is-arrastrando):not([hidden])")];
    return otras.find((t) => {
      const caja = t.getBoundingClientRect();
      return y < caja.top + caja.height / 2;
    }) || null;
  }

  async function guardarMovimiento(tarjeta, columna) {
    // Posición entre TODAS las tarjetas de la columna (incluidas las ocultas por filtro): así el orden en servidor coincide.
    const hermanas = [...columna.querySelectorAll("[data-spl-item]")];
    const posicion = hermanas.indexOf(tarjeta);
    const estado = columna.dataset.splCol;
    try {
      await peticion(url(kanban.dataset.urlMover, { __ID__: tarjeta.dataset.id }), "PATCH", { estado, posicion });
      tarjeta.dataset.estado = estado;
      const datos = JSON.parse(tarjeta.dataset.actividad);
      datos.estado = estado;
      tarjeta.dataset.actividad = JSON.stringify(datos);
      const fila = root.querySelector(`[data-spl-fila][data-id="${tarjeta.dataset.id}"]`);
      if (fila) {
        fila.dataset.estado = estado;
        fila.dataset.actividad = tarjeta.dataset.actividad;
      }
      sincronizarVistas(tarjeta.dataset.id, estado, tarjeta.dataset.actividad);
    } catch (e) {
      avisar(e.message, "error");
      window.location.reload();
    }
  }

  if (kanban) {
    kanban.addEventListener("dragstart", (e) => {
      const tarjeta = e.target.closest("[data-spl-item]");
      if (!tarjeta) return;
      arrastrada = tarjeta;
      tarjeta.classList.add("is-arrastrando");
      e.dataTransfer.effectAllowed = "move";
      e.dataTransfer.setData("text/plain", tarjeta.dataset.id);
    });

    kanban.addEventListener("dragend", () => {
      arrastrada?.classList.remove("is-arrastrando");
      kanban.querySelectorAll(".is-sobre").forEach((c) => c.classList.remove("is-sobre"));
      arrastrada = null;
    });

    kanban.addEventListener("dragover", (e) => {
      const cuerpo = e.target.closest("[data-spl-col-body]");
      if (!cuerpo || !arrastrada) return;
      e.preventDefault();
      kanban.querySelectorAll(".is-sobre").forEach((c) => c.classList.remove("is-sobre"));
      cuerpo.closest("[data-spl-col]").classList.add("is-sobre");
    });

    kanban.addEventListener("drop", (e) => {
      const cuerpo = e.target.closest("[data-spl-col-body]");
      if (!cuerpo || !arrastrada) return;
      e.preventDefault();
      const tarjeta = arrastrada;
      const referencia = despuesDe(cuerpo, e.clientY);
      cuerpo.insertBefore(tarjeta, referencia || cuerpo.querySelector("[data-spl-vacia]"));
      tarjeta.classList.remove("is-arrastrando");
      kanban.querySelectorAll(".is-sobre").forEach((c) => c.classList.remove("is-sobre"));
      actualizarColumnas();
      guardarMovimiento(tarjeta, cuerpo.closest("[data-spl-col]"));
      arrastrada = null;
    });

    // Teclado: Enter abre el detalle; flechas izquierda/derecha cambian de columna.
    kanban.addEventListener("keydown", (e) => {
      const tarjeta = e.target.closest("[data-spl-item]");
      if (!tarjeta || e.target !== tarjeta) return;
      if (e.key === "Enter") { e.preventDefault(); abrirActividad(tarjeta); return; }
      if (e.key !== "ArrowRight" && e.key !== "ArrowLeft") return;
      e.preventDefault();
      const columnas = [...kanban.querySelectorAll("[data-spl-col]")];
      const actual = columnas.indexOf(tarjeta.closest("[data-spl-col]"));
      const destino = columnas[actual + (e.key === "ArrowRight" ? 1 : -1)];
      if (!destino) return;
      const cuerpo = destino.querySelector("[data-spl-col-body]");
      cuerpo.insertBefore(tarjeta, cuerpo.querySelector("[data-spl-vacia]"));
      actualizarColumnas();
      guardarMovimiento(tarjeta, destino).then(() => tarjeta.focus());
    });
  }

  // ---------- Modal de actividad ----------
  const formActividad = root.querySelector("[data-spl-form-actividad]");
  const detalle = root.querySelector("[data-spl-detalle]");
  const formEliminar = root.querySelector("[data-spl-form-eliminar]");
  const listaNotas = root.querySelector("[data-spl-notas]");
  const textoNota = root.querySelector("[data-spl-nota-texto]");
  let actividadAbierta = null;

  function pintarNota(n) {
    const caja = document.createElement("div");
    caja.className = "spl-nota";
    caja.dataset.id = n.id;
    const texto = document.createElement("p");
    texto.textContent = n.nota;
    const pie = document.createElement("span");
    pie.className = "field__hint";
    pie.textContent = `${n.autor || "—"} · ${n.fecha}`;
    const borrar = document.createElement("button");
    borrar.type = "button";
    borrar.className = "btn--icon";
    borrar.title = "Eliminar nota";
    borrar.setAttribute("aria-label", "Eliminar nota");
    borrar.innerHTML = '<i class="fa-solid fa-trash"></i>';
    borrar.addEventListener("click", async () => {
      if (!window.confirm("¿Eliminar esta nota?")) return;
      try {
        await peticion(url(config.urlNotaEliminar, { __ID__: actividadAbierta, __NOTA__: n.id }), "DELETE");
        caja.remove();
      } catch (e) { avisar(e.message, "error"); }
    });
    caja.append(texto, pie, borrar);
    return caja;
  }

  async function cargarNotas(id) {
    listaNotas.textContent = "";
    try {
      const notas = await peticion(url(config.urlNotas, { __ID__: id }), "GET");
      if (!notas.length) {
        const vacia = document.createElement("p");
        vacia.className = "field__hint";
        vacia.dataset.splNotasVacia = "";
        vacia.textContent = "Sin notas todavía.";
        listaNotas.append(vacia);
      }
      notas.forEach((n) => listaNotas.append(pintarNota(n)));
    } catch (e) { avisar(e.message, "error"); }
  }

  function abrirNueva() {
    actividadAbierta = null;
    formActividad.reset();
    formActividad.action = formActividad.dataset.urlCrear;
    formActividad.querySelector("[data-spl-metodo]").disabled = true;
    root.querySelector("[data-spl-modal-titulo]").textContent = "Nueva actividad";
    formActividad.querySelector("[data-spl-guardar]").textContent = "Crear actividad";
    formActividad.querySelector("[data-spl-campana-campo]").hidden = false;
    formActividad.elements.seo_campana_id.disabled = false;
    formActividad.elements.estado.value = "pendiente";
    detalle.hidden = true;
    window.AgencyOS.openModal("splActividadModal");
  }

  function abrirActividad(elemento) {
    const d = JSON.parse(elemento.dataset.actividad);
    actividadAbierta = d.id;
    formActividad.reset();
    formActividad.action = url(config.urlActualizar, { __ID__: d.id });
    formActividad.querySelector("[data-spl-metodo]").disabled = false;
    root.querySelector("[data-spl-modal-titulo]").textContent = "Actividad";
    formActividad.querySelector("[data-spl-guardar]").textContent = "Guardar cambios";
    // La campaña no se cambia al editar: la actividad ya pertenece a ella.
    formActividad.querySelector("[data-spl-campana-campo]").hidden = true;
    formActividad.elements.seo_campana_id.disabled = true;
    const el = formActividad.elements;
    el.accion.value = d.accion || "";
    el.tipo.value = d.tipo || el.tipo.options[0].value;
    el.estado.value = d.estado;
    el.cluster_id.value = d.cluster_id || "";
    el.responsable_id.value = d.responsable_id || "";
    el.fecha_limite.value = d.fecha_limite || "";
    el.url_pagina.value = d.url_pagina || "";
    el.medicion_antes.value = d.medicion_antes || "";
    el.medicion_despues.value = d.medicion_despues || "";
    formEliminar.action = url(config.urlEliminar, { __ID__: d.id });
    detalle.hidden = false;
    textoNota.value = "";
    cargarNotas(d.id);
    window.AgencyOS.openModal("splActividadModal");
  }

  root.querySelector("[data-spl-actividad-nueva]")?.addEventListener("click", abrirNueva);

  root.addEventListener("click", (e) => {
    const elemento = e.target.closest("[data-spl-item], [data-spl-fila], [data-spl-abrir]");
    if (elemento) abrirActividad(elemento);
  });

  async function agregarNota() {
    const nota = textoNota.value.trim();
    if (!nota || actividadAbierta === null) return;
    try {
      const creada = await peticion(url(config.urlNotas, { __ID__: actividadAbierta }), "POST", { nota });
      listaNotas.querySelector("[data-spl-notas-vacia]")?.remove();
      listaNotas.prepend(pintarNota(creada));
      textoNota.value = "";
    } catch (e) { avisar(e.message, "error"); }
  }

  root.querySelector("[data-spl-nota-agregar]")?.addEventListener("click", agregarNota);
  textoNota?.addEventListener("keydown", (e) => {
    if (e.key === "Enter") { e.preventDefault(); agregarNota(); }
  });

  // ---------- Modal de clúster ----------
  const formCluster = root.querySelector("[data-spl-form-cluster]");

  root.querySelector("[data-spl-cluster-nuevo]")?.addEventListener("click", () => {
    formCluster.reset();
    formCluster.action = formCluster.dataset.urlCrear;
    formCluster.querySelector("[data-spl-cluster-metodo]").disabled = true;
    root.querySelector("[data-spl-cluster-titulo]").textContent = "Nuevo clúster";
    window.AgencyOS.openModal("splClusterModal");
  });

  root.querySelectorAll("[data-spl-cluster-editar]").forEach((boton) => {
    boton.addEventListener("click", () => {
      const d = JSON.parse(boton.dataset.cluster);
      formCluster.reset();
      formCluster.action = url(formCluster.dataset.urlActualizar, { __ID__: d.id });
      formCluster.querySelector("[data-spl-cluster-metodo]").disabled = false;
      root.querySelector("[data-spl-cluster-titulo]").textContent = "Editar clúster";
      formCluster.elements.nombre.value = d.nombre;
      formCluster.elements.url.value = d.url || "";
      window.AgencyOS.openModal("splClusterModal");
    });
  });

  // ---------- Asignar keyword a clúster ----------
  root.querySelectorAll("[data-spl-asignar]").forEach((select) => {
    select.addEventListener("change", async () => {
      try {
        await peticion(url(config.urlAsignar, { __ID__: select.dataset.keyword }), "PATCH", { cluster_id: select.value || null });
        // Recarga para que la keyword aparezca bajo su clúster nuevo y los conteos cuadren.
        window.location.reload();
      } catch (e) { avisar(e.message, "error"); }
    });
  });

  root.querySelectorAll("form[data-confirmar]").forEach((form) => {
    form.addEventListener("submit", (e) => {
      if (!window.confirm(form.dataset.confirmar)) e.preventDefault();
    });
  });
})();
