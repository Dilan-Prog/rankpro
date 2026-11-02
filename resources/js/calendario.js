/**
 * Módulo «Calendario» (admin): cuadrícula mensual, filtro por tipo, panel del
 * día y alta/edición/baja de eventos manuales. El mes inicial llega embebido; al cambiar
 * de mes se pide el rango de 42 días al servidor y se guarda en caché por mes.
 * Los eventos que no son manuales (reunión, tarea, proyecto, cobro, campañas de
 * Ads y SEO) son de solo lectura y enlazan a su módulo.
 *
 * Los textos libres entran al DOM con textContent, nunca con innerHTML.
 */
(function () {
  "use strict";

  const root = document.querySelector("[data-calendario]");
  if (!root) return;

  const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
  const datos = JSON.parse(root.querySelector("[data-cal-datos]").textContent);
  const HOY = root.dataset.hoy; // zona horaria de la app, no la del navegador
  const MESES = ["enero", "febrero", "marzo", "abril", "mayo", "junio", "julio", "agosto", "septiembre", "octubre", "noviembre", "diciembre"];
  const DIAS = ["Lun", "Mar", "Mié", "Jue", "Vie", "Sáb", "Dom"];
  const ETIQUETA_TIPO = Object.fromEntries(datos.tipos.map((t) => [t.valor, t.etiqueta]));

  const estado = {
    mes: root.dataset.mes,
    dia: HOY,
    ocultos: new Set(), // tipos apagados en el filtro
    cache: { [root.dataset.mes]: datos.eventos },
  };

  const cuadricula = root.querySelector("[data-cal-cuadricula]");
  const lista = root.querySelector("[data-cal-lista]");
  const form = root.querySelector("[data-cal-form]");
  const errorForm = root.querySelector("[data-cal-error]");
  const botonGuardar = root.querySelector("[data-cal-guardar]");
  const botonCancelar = root.querySelector("[data-cal-cancelar]");
  const tituloForm = root.querySelector("[data-cal-form-titulo]");
  let editando = null; // evento manual en edición (null = alta)

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

  const parsear = (iso) => { const [a, m, d] = iso.split("-").map(Number); return new Date(a, m - 1, d); };
  const aIso = (f) => `${f.getFullYear()}-${String(f.getMonth() + 1).padStart(2, "0")}-${String(f.getDate()).padStart(2, "0")}`;
  const sumarDias = (iso, n) => { const f = parsear(iso); f.setDate(f.getDate() + n); return aIso(f); };

  /** Primer día visible: el lunes de la semana del día 1 (la cuadrícula siempre tiene 42 celdas). */
  function inicioCuadricula(mes) {
    const f = parsear(`${mes}-01`);
    f.setDate(f.getDate() - ((f.getDay() + 6) % 7));
    return aIso(f);
  }

  async function api(url, metodo, cuerpo) {
    const r = await fetch(url, {
      method: metodo,
      headers: { "X-CSRF-TOKEN": csrf, "X-Requested-With": "XMLHttpRequest", Accept: "application/json", "Content-Type": "application/json" },
      body: cuerpo ? JSON.stringify(cuerpo) : undefined,
    });
    const json = await r.json().catch(() => ({}));
    if (!r.ok) throw new Error((json.errors ? Object.values(json.errors)[0][0] : json.message) || "No se pudo completar la acción.");
    return json;
  }

  async function cargarMes(mes) {
    if (estado.cache[mes]) return;
    const desde = inicioCuadricula(mes);
    try {
      const json = await api(`${root.dataset.urlEventos}?desde=${desde}&hasta=${sumarDias(desde, 41)}`, "GET");
      estado.cache[mes] = json.eventos;
    } catch (err) {
      window.AgencyOS?.toast(err.message, "error");
      estado.cache[mes] = [];
    }
  }

  /** Eventos del mes en pantalla que pasan el filtro de tipo. */
  const eventosVisibles = () => (estado.cache[estado.mes] || []).filter((e) => !estado.ocultos.has(e.tipo));
  const porHora = (a, b) => (a.hora || "").localeCompare(b.hora || "");

  function renderCuadricula() {
    const [anio, mes] = estado.mes.split("-").map(Number);
    root.querySelector("[data-cal-titulo]").textContent = `${MESES[mes - 1]} ${anio}`;

    const eventos = eventosVisibles();
    const inicio = inicioCuadricula(estado.mes);
    const nodos = DIAS.map((d) => el("div", { class: "cal-semana", text: d }));

    for (let i = 0; i < 42; i++) {
      const dia = sumarDias(inicio, i);
      const delDia = eventos.filter((e) => e.fecha === dia).sort(porHora);
      nodos.push(el(
        "button",
        {
          type: "button",
          class: `cal-dia ${dia.slice(0, 7) !== estado.mes ? "is-fuera" : ""} ${dia === HOY ? "is-hoy" : ""} ${dia === estado.dia ? "is-seleccionado" : ""}`,
          "aria-label": `${parsear(dia).getDate()} de ${MESES[parsear(dia).getMonth()]}, ${delDia.length} eventos`,
          onclick: () => { estado.dia = dia; if (!editando) form.elements.fecha.value = dia; renderCuadricula(); renderPanel(); },
        },
        el("span", { class: "cal-dia__n", text: String(parsear(dia).getDate()) }),
        delDia.slice(0, 3).map((e) => el("span", { class: "cal-evento", dataset: { tipo: e.tipo }, text: `${e.hora ? e.hora + " " : ""}${e.titulo}` })),
        delDia.length > 3 ? el("span", { class: "cal-dia__mas", text: `+${delDia.length - 3} más` }) : null
      ));
    }
    cuadricula.replaceChildren(...nodos);
  }

  function renderPanel() {
    const f = parsear(estado.dia);
    root.querySelector("[data-cal-dia-titulo]").textContent = `${DIAS[(f.getDay() + 6) % 7]} ${f.getDate()} de ${MESES[f.getMonth()]}`;

    // El panel usa el caché del mes en pantalla: un día de un mes vecino que se vea en la cuadrícula también está.
    const delDia = eventosVisibles().filter((e) => e.fecha === estado.dia).sort(porHora);
    if (!delDia.length) {
      lista.replaceChildren(el("li", { class: "cal-vacio", text: "Sin eventos este día." }));
      return;
    }

    lista.replaceChildren(...delDia.map((e) => {
      const titulo = e.url
        ? el("a", { href: e.url, class: "cal-item__titulo", text: e.titulo })
        : el("span", { class: "cal-item__titulo", text: e.titulo });
      return el(
        "li",
        { class: "cal-item", dataset: { tipo: e.tipo } },
        el("span", { class: "cal-item__hora", text: e.hora || "Todo el día" }),
        el("div", { class: "cal-item__cuerpo" },
          titulo,
          el("span", { class: "cal-item__meta", text: [ETIQUETA_TIPO[e.tipo], e.cliente].filter(Boolean).join(" · ") + (e.editable ? "" : " · desde su módulo") })),
        e.editable
          ? el("div", { class: "cal-item__acciones" },
            el("button", { type: "button", class: "btn btn--icon", "aria-label": `Editar ${e.titulo}`, onclick: () => empezarEdicion(e) }, el("i", { class: "fa-solid fa-pen" })),
            el("button", { type: "button", class: "btn btn--icon", "aria-label": `Eliminar ${e.titulo}`, onclick: () => borrar(e) }, el("i", { class: "fa-solid fa-trash" })))
          : null
      );
    }));
  }

  /** Carga el evento en el formulario del panel; guardar hace PUT en lugar de POST. */
  function empezarEdicion(evento) {
    editando = evento;
    errorForm.hidden = true;
    form.elements.titulo.value = evento.titulo;
    form.elements.fecha.value = evento.fecha;
    form.elements.hora.value = evento.hora || "";
    form.elements.cliente_id.value = evento.cliente_id || "";
    // Un evento «campaña» heredado no está entre las opciones manuales: se añade solo mientras se edita.
    const select = form.elements.tipo;
    if (![...select.options].some((o) => o.value === evento.tipo)) {
      select.append(el("option", { value: evento.tipo, text: ETIQUETA_TIPO[evento.tipo] || evento.tipo, dataset: { temporal: "1" } }));
    }
    select.value = evento.tipo;
    tituloForm.textContent = "Editar evento";
    botonGuardar.textContent = "Guardar cambios";
    botonCancelar.hidden = false;
    form.elements.titulo.focus();
  }

  function terminarEdicion() {
    editando = null;
    form.reset();
    form.querySelectorAll("option[data-temporal]").forEach((o) => o.remove());
    form.elements.fecha.value = estado.dia;
    tituloForm.textContent = "Agregar evento";
    botonGuardar.textContent = "Agregar al día";
    botonCancelar.hidden = true;
  }

  /** Quita el evento de todos los meses en caché (puede estar en dos por los días de relleno de la cuadrícula). */
  function quitarDeCache(id) {
    Object.keys(estado.cache).forEach((m) => { estado.cache[m] = estado.cache[m].filter((e) => e.id !== id); });
  }

  /** Mete el evento en la caché de cada mes cargado cuya cuadrícula incluye su fecha. */
  function ponerEnCache(evento) {
    Object.keys(estado.cache).forEach((m) => {
      const ini = inicioCuadricula(m);
      if (evento.fecha >= ini && evento.fecha <= sumarDias(ini, 41)) estado.cache[m].push(evento);
    });
  }

  async function borrar(evento) {
    if (!window.confirm(`¿Eliminar «${evento.titulo}»?`)) return;
    try {
      await api(`${root.dataset.urlBorrar}/${evento.evento_id}`, "DELETE");
      quitarDeCache(evento.id);
      if (editando && editando.id === evento.id) terminarEdicion();
      renderCuadricula();
      renderPanel();
    } catch (err) {
      window.AgencyOS?.toast(err.message, "error");
    }
  }

  async function irAMes(mes) {
    estado.mes = mes;
    // Al cambiar de mes, el día seleccionado salta al 1 (o a hoy si es el mes actual).
    estado.dia = mes === HOY.slice(0, 7) ? HOY : `${mes}-01`;
    await cargarMes(mes);
    renderCuadricula();
    renderPanel();
  }

  const desplazar = (delta) => {
    const [a, m] = estado.mes.split("-").map(Number);
    return aIso(new Date(a, m - 1 + delta, 1)).slice(0, 7);
  };

  root.querySelector("[data-cal-anterior]").addEventListener("click", () => irAMes(desplazar(-1)));
  root.querySelector("[data-cal-siguiente]").addEventListener("click", () => irAMes(desplazar(1)));
  root.querySelector("[data-cal-hoy]").addEventListener("click", () => irAMes(HOY.slice(0, 7)));

  root.querySelectorAll("[data-cal-filtro]").forEach((boton) => {
    boton.addEventListener("click", () => {
      const tipo = boton.dataset.calFiltro;
      const apagar = !estado.ocultos.has(tipo);
      if (apagar) estado.ocultos.add(tipo); else estado.ocultos.delete(tipo);
      boton.classList.toggle("is-activo", !apagar);
      boton.setAttribute("aria-pressed", String(!apagar));
      renderCuadricula();
      renderPanel();
    });
  });

  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    errorForm.hidden = true;
    const titulo = form.elements.titulo.value.trim();
    if (!titulo) {
      errorForm.textContent = "Escribe un título.";
      errorForm.hidden = false;
      return;
    }
    const fecha = form.elements.fecha.value;
    if (!fecha) {
      errorForm.textContent = "Elige una fecha.";
      errorForm.hidden = false;
      return;
    }
    const cuerpo = {
      titulo,
      tipo: form.elements.tipo.value,
      fecha,
      hora: form.elements.hora.value || null,
      cliente_id: form.elements.cliente_id.value || null,
    };
    try {
      const guardado = editando
        ? await api(`${root.dataset.urlEditar}/${editando.evento_id}`, "PUT", cuerpo)
        : await api(root.dataset.urlCrear, "POST", cuerpo);
      // Al editar puede cambiar la fecha (y con ella los meses en que aparece): se quita y se vuelve a poner.
      if (editando) quitarDeCache(editando.id);
      ponerEnCache(guardado);
      if (editando) terminarEdicion();
      else { form.reset(); form.elements.fecha.value = estado.dia; }
      renderCuadricula();
      renderPanel();
    } catch (err) {
      errorForm.textContent = err.message;
      errorForm.hidden = false;
    }
  });

  botonCancelar.addEventListener("click", terminarEdicion);

  form.elements.fecha.value = estado.dia;
  renderCuadricula();
  renderPanel();
})();
