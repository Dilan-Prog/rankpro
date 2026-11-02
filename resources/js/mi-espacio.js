/**
 * Módulo «Mi espacio» (admin): cinco vistas de las tareas del usuario
 * (tarjetas, kanban, tabla, calendario, línea de tiempo) pintadas en cliente a
 * partir del JSON embebido en la página. Buscar, filtrar y cambiar de vista no
 * piden nada al servidor; crear, editar, borrar y mover sí (fetch JSON).
 *
 * Todo texto del usuario entra al DOM con textContent (nunca innerHTML): los
 * títulos y descripciones son libres.
 */
(function () {
  "use strict";

  const root = document.querySelector("[data-mi-espacio]");
  if (!root) return;

  const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
  const datos = JSON.parse(root.querySelector("[data-me-datos]").textContent);
  const HOY = root.dataset.hoy; // fecha «de hoy» según la zona horaria de la app, no la del navegador
  const URL_CREAR = root.dataset.urlCrear;
  const URL_TAREA = root.dataset.urlTarea;

  const ESTADOS = [
    { id: "pendiente", etiqueta: "Pendiente" },
    { id: "en_progreso", etiqueta: "En progreso" },
    { id: "en_revision", etiqueta: "En revisión" },
    { id: "completada", etiqueta: "Completada" },
  ];
  const PRIORIDADES = [
    { id: "alta", etiqueta: "Alta", badge: "badge--danger", peso: 0 },
    { id: "media", etiqueta: "Media", badge: "badge--warning", peso: 1 },
    { id: "baja", etiqueta: "Baja", badge: "badge--neutral", peso: 2 },
  ];
  const DIAS = ["Lun", "Mar", "Mié", "Jue", "Vie", "Sáb", "Dom"];
  const MESES = ["enero", "febrero", "marzo", "abril", "mayo", "junio", "julio", "agosto", "septiembre", "octubre", "noviembre", "diciembre"];

  const estado = {
    tareas: datos.tareas,
    vista: root.dataset.vista,
    q: "",
    prioridad: "",
    orden: { clave: null, dir: 1 },
    mes: HOY.slice(0, 7),
    lineaInicio: lunesDe(HOY),
  };

  const contenido = root.querySelector("[data-me-contenido]");

  // ---------- Utilidades ----------

  function el(tag, props, ...hijos) {
    const nodo = document.createElement(tag);
    Object.entries(props || {}).forEach(([k, v]) => {
      if (v === null || v === undefined || v === false) return;
      if (k === "class") nodo.className = v;
      else if (k === "text") nodo.textContent = v;
      else if (k.startsWith("on")) nodo.addEventListener(k.slice(2), v);
      else if (k === "dataset") Object.assign(nodo.dataset, v);
      else nodo.setAttribute(k, v === true ? "" : v);
    });
    hijos.flat().forEach((h) => { if (h) nodo.append(h); });
    return nodo;
  }

  function parsear(iso) {
    const [a, m, d] = iso.split("-").map(Number);
    return new Date(a, m - 1, d);
  }

  function aIso(fecha) {
    const p = (n) => String(n).padStart(2, "0");
    return `${fecha.getFullYear()}-${p(fecha.getMonth() + 1)}-${p(fecha.getDate())}`;
  }

  function sumarDias(iso, n) {
    const f = parsear(iso);
    f.setDate(f.getDate() + n);
    return aIso(f);
  }

  function diasEntre(a, b) {
    return Math.round((parsear(b) - parsear(a)) / 86400000);
  }

  function lunesDe(iso) {
    const f = parsear(iso);
    f.setDate(f.getDate() - ((f.getDay() + 6) % 7));
    return aIso(f);
  }

  function fechaCorta(iso) {
    if (!iso) return "—";
    const f = parsear(iso);
    return `${f.getDate()} ${MESES[f.getMonth()].slice(0, 3)}`;
  }

  const etiquetaEstado = (id) => ESTADOS.find((e) => e.id === id)?.etiqueta || id;
  const infoPrioridad = (id) => PRIORIDADES.find((p) => p.id === id) || PRIORIDADES[1];
  const atrasada = (t) => t.estado !== "completada" && !!t.fecha_limite && t.fecha_limite < HOY;

  function badgePrioridad(t) {
    const p = infoPrioridad(t.prioridad);
    return el("span", { class: `badge ${p.badge}`, text: p.etiqueta });
  }

  function badgeEstado(t) {
    const clase = { pendiente: "badge--neutral", en_progreso: "badge--info", en_revision: "badge--orange", completada: "badge--success" }[t.estado];
    return el("span", { class: `badge ${clase}`, text: etiquetaEstado(t.estado) });
  }

  async function api(url, metodo, cuerpo) {
    const respuesta = await fetch(url, {
      method: metodo,
      headers: {
        "X-CSRF-TOKEN": csrf,
        "X-Requested-With": "XMLHttpRequest",
        Accept: "application/json",
        "Content-Type": "application/json",
      },
      body: cuerpo ? JSON.stringify(cuerpo) : undefined,
    });
    const json = await respuesta.json().catch(() => ({}));
    if (!respuesta.ok) {
      const primero = json.errors ? Object.values(json.errors)[0][0] : json.message;
      throw new Error(primero || "No se pudo completar la acción.");
    }
    return json;
  }

  const avisar = (msg, tipo) => window.AgencyOS?.toast(msg, tipo);

  // ---------- Datos derivados ----------

  function visibles() {
    const q = estado.q.trim().toLowerCase();
    return estado.tareas.filter((t) => {
      if (estado.prioridad && t.prioridad !== estado.prioridad) return false;
      if (!q) return true;
      return `${t.titulo} ${t.cliente || ""} ${t.descripcion || ""}`.toLowerCase().includes(q);
    });
  }

  function resumen() {
    const total = estado.tareas.length;
    const hechas = estado.tareas.filter((t) => t.estado === "completada").length;
    const tarde = estado.tareas.filter(atrasada).length;
    let texto = `${total} ${total === 1 ? "tarea" : "tareas"} · ${hechas} ${hechas === 1 ? "hecha" : "hechas"}`;
    if (tarde) texto += ` · ${tarde} ${tarde === 1 ? "atrasada" : "atrasadas"}`;
    root.querySelector("[data-me-resumen]").textContent = texto;
  }

  function ordenarColumna(estadoId) {
    return estado.tareas.filter((t) => t.estado === estadoId).sort((a, b) => a.orden - b.orden || a.id - b.id);
  }

  // ---------- Vistas ----------

  function vacio(hayTareas) {
    return el(
      "div",
      { class: "card card--padded me-vacio" },
      el("p", { text: hayTareas ? "Ninguna tarea coincide con la búsqueda." : "Todavía no tienes tareas. Crea la primera para empezar." }),
      hayTareas ? null : el("button", { type: "button", class: "btn btn--primary", onclick: () => abrirModal(null) }, "Crear tarea")
    );
  }

  function tarjeta(t) {
    return el(
      "article",
      { class: `me-tarjeta ${atrasada(t) ? "is-atrasada" : ""} ${t.estado === "completada" ? "is-hecha" : ""}`, tabindex: "0", dataset: { id: t.id } },
      el("div", { class: "me-tarjeta__cabecera" }, badgePrioridad(t), badgeEstado(t)),
      el("h3", { class: "me-tarjeta__titulo", text: t.titulo }),
      t.descripcion ? el("p", { class: "me-tarjeta__desc", text: t.descripcion }) : null,
      el(
        "div",
        { class: "me-tarjeta__pie" },
        t.cliente ? el("span", { class: "me-chip", text: t.cliente }) : null,
        t.fecha_limite ? el("span", { class: `me-fecha ${atrasada(t) ? "is-atrasada" : ""}`, text: `Vence ${fechaCorta(t.fecha_limite)}` }) : null
      )
    );
  }

  function vistaTarjetas(lista) {
    return el("div", { class: "me-tarjetas" }, lista.map(tarjeta));
  }

  function vistaKanban(lista) {
    const ids = new Set(lista.map((t) => t.id));
    const columnas = ESTADOS.map((e) => {
      const tarjetas = ordenarColumna(e.id).filter((t) => ids.has(t.id));
      const col = el(
        "section",
        { class: "me-columna", dataset: { estado: e.id } },
        el("header", { class: "me-columna__cabecera" }, el("span", { text: e.etiqueta }), el("span", { class: "me-columna__n", text: String(tarjetas.length) })),
        el("div", { class: "me-columna__cuerpo" }, tarjetas.map((t) => {
          const nodo = tarjeta(t);
          nodo.draggable = true;
          nodo.setAttribute("aria-label", `${t.titulo}. Columna ${e.etiqueta}. Flechas para mover.`);
          return nodo;
        }))
      );
      return col;
    });
    const tablero = el("div", { class: "me-kanban" }, columnas);
    enlazarArrastre(tablero);
    return el("div", { class: "me-kanban-envoltorio" },
      el("p", { class: "field__hint", text: "Con el foco en una tarjeta: flechas izquierda y derecha la cambian de columna; arriba y abajo la reordenan. Enter la abre." }),
      tablero);
  }

  /** HTML5 nativo: la posición de soltado se decide por el punto medio vertical de cada tarjeta. */
  function enlazarArrastre(tablero) {
    let arrastrada = null;

    function siguiente(cuerpo, y) {
      return [...cuerpo.querySelectorAll(".me-tarjeta:not(.is-arrastrando)")].find((n) => {
        const r = n.getBoundingClientRect();
        return y < r.top + r.height / 2;
      }) || null;
    }

    tablero.addEventListener("dragstart", (e) => {
      const nodo = e.target.closest?.(".me-tarjeta");
      if (!nodo) return;
      arrastrada = nodo;
      nodo.classList.add("is-arrastrando");
      e.dataTransfer.effectAllowed = "move";
      e.dataTransfer.setData("text/plain", nodo.dataset.id);
    });

    tablero.addEventListener("dragend", () => {
      arrastrada?.classList.remove("is-arrastrando");
      tablero.querySelectorAll(".is-sobre").forEach((n) => n.classList.remove("is-sobre"));
      arrastrada = null;
    });

    tablero.addEventListener("dragover", (e) => {
      const cuerpo = e.target.closest?.(".me-columna__cuerpo");
      if (!cuerpo || !arrastrada) return;
      e.preventDefault();
      tablero.querySelectorAll(".is-sobre").forEach((n) => n.classList.remove("is-sobre"));
      cuerpo.classList.add("is-sobre");
      const antes = siguiente(cuerpo, e.clientY);
      // Se reubica el nodo en vivo: es la vista previa del orden resultante.
      if (antes) cuerpo.insertBefore(arrastrada, antes);
      else cuerpo.append(arrastrada);
    });

    tablero.addEventListener("drop", (e) => {
      const cuerpo = e.target.closest?.(".me-columna__cuerpo");
      if (!cuerpo || !arrastrada) return;
      e.preventDefault();
      const id = Number(arrastrada.dataset.id);
      const destino = cuerpo.closest(".me-columna").dataset.estado;
      // Vecinos visibles en el DOM; con filtros activos hay tarjetas ocultas entre ellos y mover() resuelve la posición contra la columna completa.
      const hermano = (n) => (n?.classList.contains("me-tarjeta") ? Number(n.dataset.id) : null);
      mover(id, destino, { antesDeId: hermano(arrastrada.nextElementSibling), despuesDeId: hermano(arrastrada.previousElementSibling) });
    });
  }

  /**
   * Coloca la tarea en la columna destino calculando la posición contra TODAS las
   * tareas de la columna (también las que un filtro oculta), igual que el servidor:
   * `orden` es un índice sobre la columna completa. Con filtros, lo que el usuario
   * ve junto a la tarjeta soltada son solo los vecinos visibles, así que se prefiere
   * quedar justo después del vecino visible anterior; si no hay, justo antes del
   * siguiente; y si la columna visible está vacía, al final.
   *
   * `ref`: { antesDeId, despuesDeId } (vecinos visibles) o { posicion } (índice exacto, teclado).
   */
  async function mover(id, destino, ref = {}) {
    const respaldo = estado.tareas.map((t) => ({ ...t }));
    const tarea = estado.tareas.find((t) => t.id === id);
    if (!tarea) return;
    const columna = ordenarColumna(destino).filter((t) => t.id !== id);
    const indiceDe = (otroId) => columna.findIndex((t) => t.id === otroId);

    let pos = columna.length;
    if (Number.isInteger(ref.posicion)) pos = Math.max(0, Math.min(ref.posicion, columna.length));
    else if (ref.despuesDeId != null && indiceDe(ref.despuesDeId) >= 0) pos = indiceDe(ref.despuesDeId) + 1;
    else if (ref.antesDeId != null && indiceDe(ref.antesDeId) >= 0) pos = indiceDe(ref.antesDeId);

    columna.splice(pos, 0, tarea);
    tarea.estado = destino;
    columna.forEach((t, i) => { t.orden = i; });
    render();
    enfocarTarjeta(id);

    try {
      await api(`${URL_TAREA}/${id}/mover`, "PATCH", { estado: destino, orden: pos });
    } catch (err) {
      estado.tareas = respaldo;
      render();
      enfocarTarjeta(id);
      avisar(err.message, "error");
    }
  }

  /** Tras repintar, el foco vuelve a la tarjeta movida para poder seguir con el teclado. */
  function enfocarTarjeta(id) {
    contenido.querySelector(`.me-tarjeta[data-id="${id}"]`)?.focus();
  }

  const anuncio = root.querySelector("[data-me-anuncio]");
  function anunciar(texto) {
    // Vaciar primero fuerza a los lectores a releer un mensaje idéntico al anterior.
    anuncio.textContent = "";
    setTimeout(() => { anuncio.textContent = texto; }, 30);
  }

  /** Flechas sobre una tarjeta del kanban. */
  function moverConTeclado(tecla, id) {
    const tarea = estado.tareas.find((t) => t.id === id);
    if (!tarea) return;

    if (tecla === "ArrowLeft" || tecla === "ArrowRight") {
      const i = ESTADOS.findIndex((e) => e.id === tarea.estado) + (tecla === "ArrowRight" ? 1 : -1);
      if (i < 0 || i >= ESTADOS.length) { anunciar(`${tarea.titulo} ya está en la ${i < 0 ? "primera" : "última"} columna.`); return; }
      const destino = ESTADOS[i];
      // Conserva el índice que tenía en su columna (acotado) para no saltar al final.
      const actual = ordenarColumna(tarea.estado).findIndex((t) => t.id === id);
      const total = ordenarColumna(destino.id).length;
      const posicion = Math.min(actual, total);
      anunciar(`${tarea.titulo} movida a ${destino.etiqueta}, posición ${posicion + 1} de ${total + 1}.`);
      mover(id, destino.id, { posicion });
      return;
    }

    // Arriba/abajo: se intercambia con el vecino VISIBLE; la posición real se calcula en la columna completa.
    const idsVisibles = new Set(visibles().map((t) => t.id));
    const completa = ordenarColumna(tarea.estado);
    const visiblesCol = completa.filter((t) => idsVisibles.has(t.id));
    const k = visiblesCol.findIndex((t) => t.id === id);
    const vecino = visiblesCol[k + (tecla === "ArrowDown" ? 1 : -1)];
    if (!vecino) { anunciar(`${tarea.titulo} ya está ${tecla === "ArrowDown" ? "al final" : "al inicio"} de la columna.`); return; }

    const sinEsta = completa.filter((t) => t.id !== id);
    const posicion = sinEsta.findIndex((t) => t.id === vecino.id) + (tecla === "ArrowDown" ? 1 : 0);
    anunciar(`${tarea.titulo} movida ${tecla === "ArrowDown" ? "hacia abajo" : "hacia arriba"}, posición ${posicion + 1} de ${completa.length}.`);
    mover(id, tarea.estado, { posicion });
  }

  function vistaTabla(lista) {
    const { clave, dir } = estado.orden;
    const filas = [...lista];
    if (clave) {
      const valor = (t) => {
        if (clave === "prioridad") return infoPrioridad(t.prioridad).peso;
        if (clave === "estado") return ESTADOS.findIndex((e) => e.id === t.estado);
        return (t[clave] || "").toString().toLowerCase();
      };
      // Las fechas vacías van siempre al final, sin importar el sentido.
      filas.sort((a, b) => {
        const va = valor(a), vb = valor(b);
        if (va === "" && vb !== "") return 1;
        if (vb === "" && va !== "") return -1;
        return (va < vb ? -1 : va > vb ? 1 : 0) * dir;
      });
    }

    const columnas = [
      ["titulo", "Tarea"], ["cliente", "Cliente"], ["estado", "Estado"], ["prioridad", "Prioridad"], ["fecha_inicio", "Inicio"], ["fecha_limite", "Vence"],
    ];
    const cabecera = el("tr", {}, columnas.map(([k, texto]) => {
      const activa = estado.orden.clave === k;
      return el("th", { "aria-sort": activa ? (dir === 1 ? "ascending" : "descending") : "none" },
        el("button", { type: "button", class: "me-orden", onclick: () => {
          estado.orden = { clave: k, dir: activa ? -dir : 1 };
          render();
        } }, texto, activa ? el("i", { class: `fa-solid ${dir === 1 ? "fa-arrow-up" : "fa-arrow-down"}` }) : null));
    }));

    const cuerpo = filas.map((t) => el(
      "tr",
      { class: atrasada(t) ? "is-atrasada" : "" },
      el("td", {}, el("button", { type: "button", class: "me-enlace", text: t.titulo, onclick: () => abrirModal(t) })),
      el("td", { text: t.cliente || "—" }),
      el("td", {}, selectorEnLinea(t, "estado", ESTADOS)),
      el("td", {}, selectorEnLinea(t, "prioridad", PRIORIDADES)),
      el("td", { text: fechaCorta(t.fecha_inicio) }),
      el("td", { class: atrasada(t) ? "me-fecha is-atrasada" : "", text: fechaCorta(t.fecha_limite) })
    ));

    return el("div", { class: "table-wrap" }, el("table", { class: "table" }, el("thead", {}, cabecera), el("tbody", {}, cuerpo)));
  }

  function selectorEnLinea(t, campo, opciones) {
    const select = el("select", { class: "select select--inline", "aria-label": campo === "estado" ? "Estado" : "Prioridad" },
      opciones.map((o) => el("option", { value: o.id, text: o.etiqueta, selected: t[campo] === o.id })));
    select.addEventListener("change", async () => {
      const anterior = t[campo];
      t[campo] = select.value;
      try {
        const nueva = await api(`${URL_TAREA}/${t.id}`, "PUT", { [campo]: select.value });
        Object.assign(t, nueva);
      } catch (err) {
        t[campo] = anterior;
        avisar(err.message, "error");
      }
      render();
    });
    return select;
  }

  function vistaCalendario(lista) {
    const [anio, mes] = estado.mes.split("-").map(Number);
    const primero = `${estado.mes}-01`;
    const inicio = lunesDe(primero);

    const titulo = el("strong", { class: "me-mes__titulo", text: `${MESES[mes - 1]} ${anio}` });
    const moverMes = (delta) => {
      const f = new Date(anio, mes - 1 + delta, 1);
      estado.mes = aIso(f).slice(0, 7);
      render();
    };
    const nav = el(
      "div",
      { class: "me-mes__nav" },
      el("button", { type: "button", class: "btn btn--icon", "aria-label": "Mes anterior", onclick: () => moverMes(-1) }, el("i", { class: "fa-solid fa-chevron-left" })),
      titulo,
      el("button", { type: "button", class: "btn btn--icon", "aria-label": "Mes siguiente", onclick: () => moverMes(1) }, el("i", { class: "fa-solid fa-chevron-right" })),
      el("button", { type: "button", class: "btn btn--sm", onclick: () => { estado.mes = HOY.slice(0, 7); render(); } }, "Hoy")
    );

    const celdas = [];
    for (let i = 0; i < 42; i++) {
      const dia = sumarDias(inicio, i);
      const delDia = lista.filter((t) => t.fecha_limite === dia);
      celdas.push(el(
        "div",
        { class: `me-dia ${dia.slice(0, 7) !== estado.mes ? "is-fuera" : ""} ${dia === HOY ? "is-hoy" : ""}`, onclick: (e) => {
          if (e.target === e.currentTarget || e.target.classList.contains("me-dia__n")) abrirModal(null, { fecha_limite: dia });
        } },
        el("span", { class: "me-dia__n", text: String(parsear(dia).getDate()) }),
        delDia.slice(0, 3).map((t) => el("button", { type: "button", class: `me-evento me-evento--${t.prioridad} ${t.estado === "completada" ? "is-hecha" : ""}`, title: t.titulo, text: t.titulo, onclick: () => abrirModal(t) })),
        delDia.length > 3 ? el("span", { class: "me-dia__mas", text: `+${delDia.length - 3} más` }) : null
      ));
    }

    return el(
      "div",
      { class: "card card--padded me-mes" },
      nav,
      el("div", { class: "me-mes__cuadricula" }, DIAS.map((d) => el("div", { class: "me-mes__dia-semana", text: d })), celdas)
    );
  }

  function vistaTimeline(lista) {
    const NUM = 14;
    const ini = estado.lineaInicio;
    const fin = sumarDias(ini, NUM - 1);

    const conFechas = lista
      .map((t) => ({ t, desde: t.fecha_inicio || t.fecha_limite, hasta: t.fecha_limite || t.fecha_inicio }))
      .filter((x) => x.desde && x.hasta);
    const sinFechas = lista.length - conFechas.length;
    const enRango = conFechas.filter((x) => x.hasta >= ini && x.desde <= fin);

    const moverSemana = (n) => { estado.lineaInicio = sumarDias(estado.lineaInicio, n * 7); render(); };
    const nav = el(
      "div",
      { class: "me-mes__nav" },
      el("button", { type: "button", class: "btn btn--icon", "aria-label": "Semana anterior", onclick: () => moverSemana(-1) }, el("i", { class: "fa-solid fa-chevron-left" })),
      el("strong", { class: "me-mes__titulo", text: `${fechaCorta(ini)} – ${fechaCorta(fin)}` }),
      el("button", { type: "button", class: "btn btn--icon", "aria-label": "Semana siguiente", onclick: () => moverSemana(1) }, el("i", { class: "fa-solid fa-chevron-right" })),
      el("button", { type: "button", class: "btn btn--sm", onclick: () => { estado.lineaInicio = lunesDe(HOY); render(); } }, "Hoy")
    );

    const cabecera = el("div", { class: "me-linea__fila me-linea__fila--cabecera" },
      el("div", { class: "me-linea__etiqueta" }),
      el("div", { class: "me-linea__pista" }, Array.from({ length: NUM }, (_, i) => {
        const d = sumarDias(ini, i);
        return el("div", { class: `me-linea__dia ${d === HOY ? "is-hoy" : ""}` }, el("span", { text: DIAS[(parsear(d).getDay() + 6) % 7] }), el("strong", { text: String(parsear(d).getDate()) }));
      })));

    const filas = enRango.map(({ t, desde, hasta }) => {
      const a = Math.max(0, diasEntre(ini, desde));
      const b = Math.min(NUM - 1, diasEntre(ini, hasta));
      return el("div", { class: "me-linea__fila" },
        el("button", { type: "button", class: "me-linea__etiqueta me-enlace", title: t.titulo, text: t.titulo, onclick: () => abrirModal(t) }),
        el("div", { class: "me-linea__pista" },
          el("button", {
            type: "button",
            class: `me-barra-tarea me-barra-tarea--${t.prioridad} ${t.estado === "completada" ? "is-hecha" : ""} ${atrasada(t) ? "is-atrasada" : ""}`,
            style: `grid-column: ${a + 1} / ${b + 2}`,
            title: `${t.titulo} (${fechaCorta(desde)} – ${fechaCorta(hasta)})`,
            onclick: () => abrirModal(t),
          }, el("span", { text: etiquetaEstado(t.estado) }))));
    });

    return el(
      "div",
      { class: "card card--padded me-linea" },
      nav,
      el("div", { class: "me-linea__tabla" }, cabecera, filas.length ? filas : el("p", { class: "me-linea__vacio", text: "No hay tareas con fechas en estas dos semanas." })),
      sinFechas ? el("p", { class: "field__hint", text: `${sinFechas} ${sinFechas === 1 ? "tarea sin fechas no aparece" : "tareas sin fechas no aparecen"} aquí.` }) : null
    );
  }

  const VISTAS = { tarjetas: vistaTarjetas, kanban: vistaKanban, tabla: vistaTabla, calendario: vistaCalendario, timeline: vistaTimeline };

  function render() {
    resumen();
    contenido.replaceChildren();
    const lista = visibles();
    // Calendario y línea de tiempo se muestran aunque no haya resultados: la cuadrícula en sí es la información.
    const requiereLista = ["tarjetas", "tabla"].includes(estado.vista);
    if (requiereLista && lista.length === 0) {
      contenido.append(vacio(estado.tareas.length > 0));
      return;
    }
    contenido.append(VISTAS[estado.vista](lista));
  }

  // ---------- Modal ----------

  const form = root.querySelector("[data-me-form]");
  const tituloModal = root.querySelector("[data-me-modal-titulo]");
  const botonBorrar = root.querySelector("[data-me-borrar]");
  const errorForm = root.querySelector("[data-me-error]");
  let editando = null;

  function abrirModal(tarea, base) {
    editando = tarea;
    form.reset();
    errorForm.hidden = true;
    const valores = tarea || { estado: "pendiente", prioridad: "media", fecha_inicio: base?.fecha_limite || HOY, fecha_limite: base?.fecha_limite || "", ...base };
    ["titulo", "descripcion", "prioridad", "estado", "fecha_inicio", "fecha_limite"].forEach((c) => { form.elements[c].value = valores[c] || ""; });
    form.elements.cliente_id.value = valores.cliente_id || "";
    tituloModal.textContent = tarea ? "Editar tarea" : "Nueva tarea";
    botonBorrar.hidden = !tarea;
    window.AgencyOS.openModal("tareaModal");
    form.elements.titulo.focus();
  }

  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    const v = (n) => form.elements[n].value.trim() || null;
    const cuerpo = {
      titulo: form.elements.titulo.value.trim(),
      descripcion: v("descripcion"),
      prioridad: form.elements.prioridad.value,
      estado: form.elements.estado.value,
      fecha_inicio: v("fecha_inicio"),
      fecha_limite: v("fecha_limite"),
      cliente_id: v("cliente_id"),
    };
    if (!cuerpo.titulo) {
      errorForm.textContent = "Escribe un título.";
      errorForm.hidden = false;
      return;
    }
    try {
      if (editando) {
        const nueva = await api(`${URL_TAREA}/${editando.id}`, "PUT", cuerpo);
        Object.assign(editando, nueva);
      } else {
        estado.tareas.push(await api(URL_CREAR, "POST", cuerpo));
      }
      window.AgencyOS.closeModal("tareaModal");
      render();
    } catch (err) {
      errorForm.textContent = err.message;
      errorForm.hidden = false;
    }
  });

  /** Toast con botón «Deshacer» (el de AgencyOS no admite acciones). Dura unos segundos. */
  function toastDeshacer(mensaje, alDeshacer) {
    let region = document.querySelector(".toast-region");
    if (!region) {
      region = el("div", { class: "toast-region" });
      document.body.append(region);
    }
    const nodo = el("div", { class: "toast", role: "status" },
      el("i", { class: "fa-solid fa-circle-check", style: "color:var(--text-success)" }),
      el("span", { text: mensaje }),
      el("button", { type: "button", class: "btn btn--sm", onclick: () => { clearTimeout(timer); nodo.remove(); alDeshacer(); } }, "Deshacer"));
    const timer = setTimeout(() => nodo.remove(), 8000);
    region.append(nodo);
  }

  async function restaurar(id) {
    try {
      const tarea = await api(`${URL_TAREA}/${id}/restaurar`, "PATCH");
      estado.tareas.push(tarea);
      render();
      avisar("Tarea restaurada.", "success");
    } catch (err) {
      avisar(err.message, "error");
    }
  }

  botonBorrar.addEventListener("click", async () => {
    // Sin confirm(): el borrado es suave y el toast permite deshacerlo.
    if (!editando) return;
    const borrada = editando;
    try {
      await api(`${URL_TAREA}/${borrada.id}`, "DELETE");
      estado.tareas = estado.tareas.filter((t) => t.id !== borrada.id);
      window.AgencyOS.closeModal("tareaModal");
      render();
      toastDeshacer("Tarea eliminada", () => restaurar(borrada.id));
    } catch (err) {
      errorForm.textContent = err.message;
      errorForm.hidden = false;
    }
  });

  // ---------- Controles ----------

  root.querySelector("[data-me-nueva]").addEventListener("click", () => abrirModal(null));
  root.querySelector("[data-me-buscar]").addEventListener("input", (e) => { estado.q = e.target.value; render(); });
  root.querySelector("[data-me-prioridad]").addEventListener("change", (e) => { estado.prioridad = e.target.value; render(); });

  root.querySelectorAll("[data-me-vista]").forEach((a) => {
    a.addEventListener("click", (e) => {
      e.preventDefault();
      estado.vista = a.dataset.meVista;
      root.querySelectorAll("[data-me-vista]").forEach((x) => x.classList.toggle("is-active", x === a));
      history.replaceState(null, "", a.href);
      render();
    });
  });

  // Delegación: las tarjetas se recrean en cada render. En el kanban el clic también abre la edición.
  contenido.addEventListener("click", (e) => {
    const nodo = e.target.closest(".me-tarjeta");
    if (!nodo) return;
    const t = estado.tareas.find((x) => x.id === Number(nodo.dataset.id));
    if (t) abrirModal(t);
  });
  contenido.addEventListener("keydown", (e) => {
    if (!e.target.classList?.contains("me-tarjeta")) return;
    const id = Number(e.target.dataset.id);
    if (e.key === "Enter") {
      const t = estado.tareas.find((x) => x.id === id);
      if (t) abrirModal(t);
      return;
    }
    // Solo en el kanban: en las otras vistas las flechas siguen desplazando la página.
    if (estado.vista === "kanban" && ["ArrowLeft", "ArrowRight", "ArrowUp", "ArrowDown"].includes(e.key) && !e.altKey && !e.ctrlKey && !e.metaKey) {
      e.preventDefault();
      moverConTeclado(e.key, id);
    }
  });

  render();
})();
