/**
 * Módulo «Contenido» → Pizarra: lienzo libre con notas, textos, formas y flechas.
 *
 * Lienzo = un div con transform (translate + scale) donde viven los elementos
 * y un SVG para las flechas. TODO el texto de la persona entra al DOM con
 * textContent / .value, nunca como marcado HTML: es contenido escrito por usuarios.
 * Se guarda la pizarra completa (PUT); deshacer/rehacer son instantáneas del
 * arreglo de elementos, sin tocar la vista (pan y zoom no se deshacen).
 */
(function () {
  "use strict";

  const raiz = document.querySelector("[data-pz]");
  if (!raiz) return;

  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || "";
  const NS_SVG = "http://www.w3.org/2000/svg";

  // Mismos límites que App\Support\Contenido\PizarraContenido (el servidor es quien manda).
  const MAX_ELEMENTOS = 500;
  const MAX_TEXTO = 2000;
  const COORD = 20000;
  const TAM_MIN = 20;
  const TAM_MAX = 4000;
  const ZOOM_MIN = 0.2;
  const ZOOM_MAX = 3;

  const PASTELES = ["#FDE68A", "#A7F3D0", "#FBCFE8", "#BFDBFE", "#FED7AA"];
  const FUERTES = ["#64748B", "#0F9D6E", "#DB2777", "#2563EB", "#EA580C"];

  const TAMANOS = {
    nota: [160, 110],
    texto: [180, 36],
    rectangulo: [160, 100],
    elipse: [160, 100],
  };
  const COLOR_INICIAL = { nota: PASTELES[0], texto: FUERTES[0], rectangulo: FUERTES[3], elipse: FUERTES[1], flecha: FUERTES[0] };
  const PLACEHOLDER = { nota: "Escribe…", texto: "Texto", rectangulo: "", elipse: "" };

  const lienzo = raiz.querySelector("[data-pz-lienzo]");
  const mundo = raiz.querySelector("[data-pz-mundo]");
  const capaFlechas = raiz.querySelector("[data-pz-flechas]");
  const vacio = raiz.querySelector("[data-pz-vacio]");
  const estadoGuardado = document.querySelector("[data-cm-estado]");
  const btnGuardar = raiz.querySelector("[data-pz-guardar]");
  const btnDeshacer = raiz.querySelector("[data-pz-deshacer]");
  const btnRehacer = raiz.querySelector("[data-pz-rehacer]");
  const btnBorrar = raiz.querySelector("[data-pz-borrar]");
  const zonaColores = raiz.querySelector("[data-pz-colores]");
  const textoZoom = raiz.querySelector("[data-pz-zoom-texto]");

  // ---------- Utilidades ----------

  function toast(mensaje, tipo) {
    if (window.AgencyOS && window.AgencyOS.toast) window.AgencyOS.toast(mensaje, tipo);
  }

  function api(url, metodo, cuerpo) {
    return fetch(url, {
      method: metodo,
      headers: { Accept: "application/json", "Content-Type": "application/json", "X-CSRF-TOKEN": csrfToken, "X-Requested-With": "XMLHttpRequest" },
      body: JSON.stringify(cuerpo),
    })
      .then((res) => res.json().catch(() => ({})).then((datos) => ({ ok: res.ok, status: res.status, datos })))
      .catch(() => ({ ok: false, status: 0, datos: { message: "No se pudo conectar con el servidor." } }));
  }

  function mensajeError(res) {
    const errores = res.datos && res.datos.errors;
    if (errores) {
      const primero = Object.values(errores)[0];
      if (primero && primero[0]) return primero[0];
    }
    if (res.status === 413) return "La pizarra es demasiado grande para guardarse.";
    return (res.datos && res.datos.message) || "No se pudo guardar la pizarra.";
  }

  const limitar = (v, min, max) => Math.min(max, Math.max(min, v));
  const redondear = (v) => Math.round(v * 10) / 10;
  // Punto libre de una flecha: se acota a ±COORD como el resto de coordenadas; el servidor rechaza TODO el guardado si uno se pasa.
  const puntoLibre = (p) => ({ x: redondear(limitar(p.x, -COORD, COORD)), y: redondear(limitar(p.y, -COORD, COORD)) });
  const esEditable = (nodo) => nodo && /^(input|textarea|select)$/i.test(nodo.tagName);

  function nuevoId() {
    return "e" + Date.now().toString(36) + Math.random().toString(36).slice(2, 7);
  }

  // ---------- Estado ----------

  let inicial = { elementos: [], vista: { x: 0, y: 0, zoom: 1 } };
  try {
    inicial = JSON.parse(raiz.dataset.datos) || inicial;
  } catch (e) {
    /* pizarra ilegible: se abre en blanco, sin romper la pantalla */
  }

  let elementos = Array.isArray(inicial.elementos) ? inicial.elementos : [];
  const vista = Object.assign({ x: 0, y: 0, zoom: 1 }, inicial.vista || {});
  let seleccion = null;
  let herramienta = "seleccionar";
  let arrastre = null;
  let edicion = null; // { id, area }
  let ultimoClic = { id: null, t: 0 };

  const porId = (id) => elementos.find((e) => e.id === id) || null;

  // ---------- Historial (deshacer / rehacer) ----------

  let base = JSON.stringify(elementos);
  const pilaDeshacer = [];
  let pilaRehacer = [];

  function actualizarBotones() {
    btnDeshacer.disabled = pilaDeshacer.length === 0;
    btnRehacer.disabled = pilaRehacer.length === 0;
    btnBorrar.disabled = !seleccion;
  }

  /** Cierra un cambio: si hubo diferencias, las guarda como paso del historial y programa el guardado. */
  function confirmar() {
    const ahora = JSON.stringify(elementos);
    if (ahora !== base) {
      pilaDeshacer.push(base);
      if (pilaDeshacer.length > 100) pilaDeshacer.shift();
      pilaRehacer = [];
      base = ahora;
      marcarSucio();
    }
    actualizarBotones();
  }

  function restaurar(instantanea) {
    terminarEdicion(false);
    elementos = JSON.parse(instantanea);
    base = instantanea;
    if (seleccion && !porId(seleccion)) seleccion = null;
    pintarTodo();
    marcarSucio();
  }

  function deshacer() {
    if (!pilaDeshacer.length) return;
    pilaRehacer.push(base);
    restaurar(pilaDeshacer.pop());
  }

  function rehacer() {
    if (!pilaRehacer.length) return;
    pilaDeshacer.push(base);
    restaurar(pilaRehacer.pop());
  }

  // ---------- Guardado ----------

  let sucio = false;
  let guardando = false;
  let temporizador = null;

  function ponerEstado(texto, sucioVisual) {
    if (!estadoGuardado) return;
    estadoGuardado.textContent = texto;
    estadoGuardado.classList.toggle("is-sucio", !!sucioVisual);
  }

  function marcarSucio() {
    sucio = true;
    ponerEstado("Cambios sin guardar", true);
    clearTimeout(temporizador);
    temporizador = setTimeout(guardar, 2000);
  }

  async function guardar() {
    clearTimeout(temporizador);
    if (guardando) {
      temporizador = setTimeout(guardar, 800);
      return;
    }
    if (!sucio) {
      ponerEstado("Sin cambios", false);
      return;
    }
    terminarEdicion(true);
    const enviado = JSON.stringify(elementos);
    guardando = true;
    btnGuardar.disabled = true;
    ponerEstado("Guardando…", true);

    const res = await api(raiz.dataset.urlGuardar, "PUT", {
      elementos,
      vista: { x: redondear(limitar(vista.x, -100000, 100000)), y: redondear(limitar(vista.y, -100000, 100000)), zoom: Math.round(vista.zoom * 100) / 100 },
    });

    guardando = false;
    btnGuardar.disabled = false;
    if (!res.ok) {
      ponerEstado("No se pudo guardar", true);
      toast(mensajeError(res), "error");
      return;
    }
    // Si hubo ediciones mientras se guardaba, siguen pendientes.
    if (JSON.stringify(elementos) === enviado) {
      sucio = false;
      ponerEstado("Guardado " + (res.datos.guardado || ""), false);
    } else {
      marcarSucio();
    }
  }

  window.addEventListener("beforeunload", (e) => {
    if (sucio) {
      e.preventDefault();
      e.returnValue = "";
    }
  });

  // ---------- Geometría ----------

  function aMundo(ev) {
    const r = lienzo.getBoundingClientRect();
    return { x: (ev.clientX - r.left - vista.x) / vista.zoom, y: (ev.clientY - r.top - vista.y) / vista.zoom };
  }

  const centro = (e) => ({ x: e.x + e.ancho / 2, y: e.y + e.alto / 2 });

  /** Punto donde la recta centro→`hacia` sale del contorno del elemento (rectángulo o elipse). */
  function borde(e, hacia) {
    const c = centro(e);
    const dx = hacia.x - c.x;
    const dy = hacia.y - c.y;
    if (dx === 0 && dy === 0) return c;
    let t;
    if (e.tipo === "elipse") {
      t = 1 / Math.sqrt(Math.pow(dx / (e.ancho / 2), 2) + Math.pow(dy / (e.alto / 2), 2));
    } else {
      t = Math.min(e.ancho / 2 / (Math.abs(dx) || 1e-9), e.alto / 2 / (Math.abs(dy) || 1e-9));
    }
    t = Math.min(t, 1);
    return { x: c.x + dx * t, y: c.y + dy * t };
  }

  function extremos(f) {
    const ea = typeof f.desde === "string" ? porId(f.desde) : null;
    const eb = typeof f.hasta === "string" ? porId(f.hasta) : null;
    const ca = ea ? centro(ea) : f.desde && typeof f.desde === "object" ? f.desde : { x: 0, y: 0 };
    const cb = eb ? centro(eb) : f.hasta && typeof f.hasta === "object" ? f.hasta : { x: 0, y: 0 };
    return { a: ea ? borde(ea, cb) : ca, b: eb ? borde(eb, ca) : cb };
  }

  // ---------- Pintado ----------

  const nodos = new Map(); // id → nodo de figura
  const nodosFlecha = new Map(); // id → <g> de flecha

  function aplicarVista() {
    mundo.style.transform = "translate(" + vista.x + "px," + vista.y + "px) scale(" + vista.zoom + ")";
    lienzo.style.backgroundPosition = vista.x + "px " + vista.y + "px";
    lienzo.style.backgroundSize = 24 * vista.zoom + "px " + 24 * vista.zoom + "px";
    textoZoom.textContent = Math.round(vista.zoom * 100) + "%";
  }

  function pintarFigura(e) {
    let nodo = nodos.get(e.id);
    if (!nodo) {
      nodo = document.createElement("div");
      nodo.className = "pz-el pz-el--" + e.tipo;
      nodo.dataset.pzId = e.id;
      const texto = document.createElement("div");
      texto.className = "pz-el__texto";
      nodo.appendChild(texto);
      mundo.appendChild(nodo);
      nodos.set(e.id, nodo);
    }
    nodo.style.left = e.x + "px";
    nodo.style.top = e.y + "px";
    nodo.style.width = e.ancho + "px";
    nodo.style.height = e.alto + "px";

    if (e.tipo === "nota") {
      nodo.style.background = e.color;
    } else if (e.tipo === "texto") {
      nodo.style.color = e.color;
    } else {
      nodo.style.borderColor = e.color;
      nodo.style.background = "color-mix(in srgb, " + e.color + " 18%, transparent)";
    }

    const texto = nodo.querySelector(".pz-el__texto");
    texto.textContent = e.texto;
    texto.dataset.vacio = PLACEHOLDER[e.tipo] || "";

    nodo.classList.toggle("is-sel", seleccion === e.id);
    nodo.classList.toggle("is-editando", !!edicion && edicion.id === e.id);
    const asa = nodo.querySelector(".pz-el__asa");
    if (seleccion === e.id && !edicion) {
      if (!asa) {
        const nueva = document.createElement("div");
        nueva.className = "pz-el__asa";
        nueva.dataset.pzAsa = "1";
        nodo.appendChild(nueva);
      }
    } else if (asa) {
      asa.remove();
    }
  }

  function crearGrupoFlecha() {
    const g = document.createElementNS(NS_SVG, "g");
    g.setAttribute("class", "pz-flecha");
    const zona = document.createElementNS(NS_SVG, "line");
    zona.setAttribute("class", "pz-flecha__zona");
    const linea = document.createElementNS(NS_SVG, "line");
    linea.setAttribute("class", "pz-flecha__linea");
    g.appendChild(zona);
    g.appendChild(linea);
    capaFlechas.appendChild(g);
    return g;
  }

  function pintarGeometriaFlecha(g, f) {
    const { a, b } = extremos(f);
    g.querySelectorAll("line").forEach((l) => {
      l.setAttribute("x1", a.x);
      l.setAttribute("y1", a.y);
      l.setAttribute("x2", b.x);
      l.setAttribute("y2", b.y);
    });
    g.querySelector(".pz-flecha__linea").setAttribute("stroke", f.color);
  }

  function pintarFlecha(f) {
    let g = nodosFlecha.get(f.id);
    if (!g) {
      g = crearGrupoFlecha();
      g.dataset.pzId = f.id;
      nodosFlecha.set(f.id, g);
    }
    pintarGeometriaFlecha(g, f);
    g.classList.toggle("is-sel", seleccion === f.id);
  }

  /** Redibuja solo las flechas (al mover una figura cambian sus extremos, pero no hace falta tocar el resto). */
  function pintarFlechas() {
    elementos.forEach((e) => {
      if (e.tipo === "flecha") pintarFlecha(e);
    });
  }

  function pintarTodo() {
    const vivos = new Set(elementos.map((e) => e.id));
    nodos.forEach((nodo, id) => {
      if (!vivos.has(id)) {
        nodo.remove();
        nodos.delete(id);
      }
    });
    nodosFlecha.forEach((g, id) => {
      if (!vivos.has(id)) {
        g.remove();
        nodosFlecha.delete(id);
      }
    });
    elementos.forEach((e) => (e.tipo === "flecha" ? pintarFlecha(e) : pintarFigura(e)));
    vacio.hidden = elementos.length > 0;
    pintarColores();
    actualizarBotones();
  }

  // ---------- Colores del elemento seleccionado ----------

  function pintarColores() {
    const e = seleccion ? porId(seleccion) : null;
    zonaColores.textContent = "";
    zonaColores.hidden = !e;
    if (!e) return;
    (e.tipo === "nota" ? PASTELES : FUERTES).forEach((c) => {
      const b = document.createElement("button");
      b.type = "button";
      b.className = "pz-color" + (String(e.color).toUpperCase() === c ? " is-activo" : "");
      b.style.background = c;
      b.title = "Color " + c;
      b.setAttribute("aria-label", "Color " + c);
      b.addEventListener("click", () => {
        e.color = c;
        if (e.tipo === "flecha") pintarFlecha(e);
        else pintarFigura(e);
        pintarColores();
        confirmar();
      });
      zonaColores.appendChild(b);
    });
  }

  function seleccionar(id) {
    seleccion = id;
    pintarTodo();
  }

  // ---------- Edición de texto ----------

  function iniciarEdicion(id) {
    const e = porId(id);
    const nodo = nodos.get(id);
    if (!e || !nodo || e.tipo === "flecha") return;
    terminarEdicion(true);
    const area = document.createElement("textarea");
    area.className = "pz-el__editor";
    area.maxLength = MAX_TEXTO;
    area.value = e.texto;
    area.setAttribute("aria-label", "Texto del elemento");
    edicion = { id, area };
    nodo.appendChild(area);
    pintarFigura(e);
    area.focus();
    area.setSelectionRange(area.value.length, area.value.length);
    area.addEventListener("blur", () => terminarEdicion(true));
    area.addEventListener("keydown", (ev) => {
      if (ev.key === "Escape" || (ev.key === "Enter" && (ev.ctrlKey || ev.metaKey))) {
        ev.preventDefault();
        terminarEdicion(true);
        lienzo.focus({ preventScroll: true });
      }
    });
  }

  function terminarEdicion(conservar) {
    if (!edicion) return;
    const { id, area } = edicion;
    edicion = null;
    const e = porId(id);
    if (e && conservar) e.texto = area.value.slice(0, MAX_TEXTO);
    area.remove();
    if (e) pintarFigura(e);
    confirmar();
  }

  // ---------- Altas y bajas ----------

  function crearFigura(tipo, p) {
    if (elementos.length >= MAX_ELEMENTOS) {
      toast("Una pizarra admite hasta " + MAX_ELEMENTOS + " elementos.", "error");
      return null;
    }
    const [ancho, alto] = TAMANOS[tipo];
    const e = {
      id: nuevoId(),
      tipo,
      x: redondear(limitar(p.x - ancho / 2, -COORD, COORD)),
      y: redondear(limitar(p.y - alto / 2, -COORD, COORD)),
      ancho,
      alto,
      texto: "",
      color: COLOR_INICIAL[tipo],
      desde: null,
      hasta: null,
    };
    elementos.push(e);
    return e;
  }

  function crearFlecha(desde, hasta) {
    if (elementos.length >= MAX_ELEMENTOS) {
      toast("Una pizarra admite hasta " + MAX_ELEMENTOS + " elementos.", "error");
      return null;
    }
    const f = { id: nuevoId(), tipo: "flecha", x: 0, y: 0, ancho: 0, alto: 0, texto: "", color: COLOR_INICIAL.flecha, desde, hasta };
    elementos.push(f);
    return f;
  }

  /** Borra el elemento y las flechas que salían de él o llegaban a él (quedarían apuntando a nada). */
  function borrarSeleccion() {
    if (!seleccion) return;
    const id = seleccion;
    terminarEdicion(false);
    elementos = elementos.filter((e) => e.id !== id && e.desde !== id && e.hasta !== id);
    seleccion = null;
    pintarTodo();
    confirmar();
  }

  function elegirHerramienta(nombre) {
    herramienta = nombre;
    raiz.querySelectorAll("[data-pz-herramienta]").forEach((b) => b.classList.toggle("is-activa", b.dataset.pzHerramienta === nombre));
    lienzo.classList.toggle("is-crear", nombre !== "seleccionar");
  }

  // ---------- Puntero ----------

  const destinoDe = (ev) => (ev.target.closest ? ev.target.closest("[data-pz-id]") : null);

  lienzo.addEventListener("pointerdown", (ev) => {
    if (ev.button !== 0 || (edicion && ev.target === edicion.area)) return;
    terminarEdicion(true);
    lienzo.focus({ preventScroll: true });

    const p = aMundo(ev);
    const diana = destinoDe(ev);
    const idDiana = diana ? diana.dataset.pzId : null;
    const elDiana = idDiana ? porId(idDiana) : null;

    if (herramienta === "flecha") {
      const desde = elDiana && elDiana.tipo !== "flecha" ? elDiana.id : puntoLibre(p);
      arrastre = { tipo: "flecha", desde, g: crearGrupoFlecha() };
      arrastre.borrador = { desde, hasta: { x: p.x, y: p.y }, color: COLOR_INICIAL.flecha };
      pintarGeometriaFlecha(arrastre.g, arrastre.borrador);
      lienzo.setPointerCapture(ev.pointerId);
      return;
    }

    if (herramienta !== "seleccionar") {
      const e = crearFigura(herramienta, p);
      elegirHerramienta("seleccionar");
      if (e) {
        seleccion = e.id;
        pintarTodo();
        confirmar();
        if (e.tipo === "nota" || e.tipo === "texto") iniciarEdicion(e.id);
      }
      return;
    }

    if (ev.target.dataset && ev.target.dataset.pzAsa && elDiana) {
      arrastre = { tipo: "redim", id: elDiana.id, sx: ev.clientX, sy: ev.clientY, ow: elDiana.ancho, oh: elDiana.alto };
      lienzo.setPointerCapture(ev.pointerId);
      return;
    }

    if (elDiana) {
      const ahora = Date.now();
      const doble = ultimoClic.id === elDiana.id && ahora - ultimoClic.t < 400 && elDiana.tipo !== "flecha";
      ultimoClic = { id: elDiana.id, t: ahora };
      seleccionar(elDiana.id);
      if (doble) {
        iniciarEdicion(elDiana.id);
        return;
      }
      if (elDiana.tipo !== "flecha") {
        arrastre = { tipo: "mover", id: elDiana.id, sx: ev.clientX, sy: ev.clientY, ox: elDiana.x, oy: elDiana.y };
        lienzo.setPointerCapture(ev.pointerId);
      }
      return;
    }

    if (seleccion) seleccionar(null);
    arrastre = { tipo: "pan", sx: ev.clientX, sy: ev.clientY, ox: vista.x, oy: vista.y };
    lienzo.classList.add("is-pan");
    lienzo.setPointerCapture(ev.pointerId);
  });

  lienzo.addEventListener("pointermove", (ev) => {
    if (!arrastre) return;
    const dx = ev.clientX - arrastre.sx;
    const dy = ev.clientY - arrastre.sy;

    if (arrastre.tipo === "pan") {
      vista.x = arrastre.ox + dx;
      vista.y = arrastre.oy + dy;
      aplicarVista();
    } else if (arrastre.tipo === "mover") {
      const e = porId(arrastre.id);
      if (!e) return;
      e.x = redondear(limitar(arrastre.ox + dx / vista.zoom, -COORD, COORD));
      e.y = redondear(limitar(arrastre.oy + dy / vista.zoom, -COORD, COORD));
      pintarFigura(e);
      pintarFlechas();
    } else if (arrastre.tipo === "redim") {
      const e = porId(arrastre.id);
      if (!e) return;
      e.ancho = redondear(limitar(arrastre.ow + dx / vista.zoom, TAM_MIN, TAM_MAX));
      e.alto = redondear(limitar(arrastre.oh + dy / vista.zoom, TAM_MIN, TAM_MAX));
      pintarFigura(e);
      pintarFlechas();
    } else if (arrastre.tipo === "flecha") {
      const p = aMundo(ev);
      arrastre.borrador.hasta = { x: p.x, y: p.y };
      pintarGeometriaFlecha(arrastre.g, arrastre.borrador);
    }
  });

  function soltar(ev) {
    if (!arrastre) return;
    const a = arrastre;
    arrastre = null;
    lienzo.classList.remove("is-pan");

    if (a.tipo === "flecha") {
      a.g.remove();
      const bajo = ev.type === "pointerup" ? document.elementFromPoint(ev.clientX, ev.clientY) : null;
      const diana = bajo && bajo.closest ? bajo.closest("[data-pz-id]") : null;
      const elHasta = diana ? porId(diana.dataset.pzId) : null;
      const p = aMundo(ev);
      const hasta = elHasta && elHasta.tipo !== "flecha" ? elHasta.id : puntoLibre(p);
      const mismo = typeof hasta === "string" && hasta === a.desde;
      const corta = typeof hasta !== "string" && typeof a.desde !== "string" && Math.hypot(hasta.x - a.desde.x, hasta.y - a.desde.y) < 12;
      if (ev.type === "pointerup" && !mismo && !corta) {
        const f = crearFlecha(a.desde, hasta);
        if (f) seleccion = f.id;
      }
      elegirHerramienta("seleccionar");
      pintarTodo();
    }
    confirmar();
  }

  lienzo.addEventListener("pointerup", soltar);
  lienzo.addEventListener("pointercancel", soltar);

  // ---------- Zoom ----------

  function zoomEn(factor, cx, cy) {
    const nuevo = limitar(vista.zoom * factor, ZOOM_MIN, ZOOM_MAX);
    const k = nuevo / vista.zoom;
    vista.x = cx - (cx - vista.x) * k;
    vista.y = cy - (cy - vista.y) * k;
    vista.zoom = nuevo;
    aplicarVista();
  }

  lienzo.addEventListener(
    "wheel",
    (ev) => {
      ev.preventDefault();
      const r = lienzo.getBoundingClientRect();
      zoomEn(ev.deltaY < 0 ? 1.1 : 1 / 1.1, ev.clientX - r.left, ev.clientY - r.top);
    },
    { passive: false }
  );

  raiz.querySelectorAll("[data-pz-zoom]").forEach((b) => {
    b.addEventListener("click", () => {
      const r = lienzo.getBoundingClientRect();
      const cx = r.width / 2;
      const cy = r.height / 2;
      if (b.dataset.pzZoom === "mas") zoomEn(1.25, cx, cy);
      else if (b.dataset.pzZoom === "menos") zoomEn(1 / 1.25, cx, cy);
      else zoomEn(1 / vista.zoom, cx, cy);
    });
  });

  // ---------- Barra y teclado ----------

  raiz.querySelectorAll("[data-pz-herramienta]").forEach((b) => b.addEventListener("click", () => elegirHerramienta(b.dataset.pzHerramienta)));
  btnDeshacer.addEventListener("click", deshacer);
  btnRehacer.addEventListener("click", rehacer);
  btnBorrar.addEventListener("click", borrarSeleccion);
  btnGuardar.addEventListener("click", guardar);

  document.addEventListener("keydown", (ev) => {
    if (esEditable(ev.target)) return;
    const ctrl = ev.ctrlKey || ev.metaKey;
    const tecla = ev.key.toLowerCase();

    if (ctrl && tecla === "z") {
      ev.preventDefault();
      if (ev.shiftKey) rehacer();
      else deshacer();
    } else if (ctrl && tecla === "y") {
      ev.preventDefault();
      rehacer();
    } else if (ctrl && tecla === "s") {
      ev.preventDefault();
      guardar();
    } else if (!ctrl && (ev.key === "Delete" || ev.key === "Backspace") && seleccion) {
      ev.preventDefault();
      borrarSeleccion();
    } else if (ev.key === "Escape") {
      elegirHerramienta("seleccionar");
      if (seleccion) seleccionar(null);
    }
  });

  // ---------- Nombre editable (esta pantalla no carga contenido.js) ----------

  const campoNombre = document.querySelector("[data-cm-nombre-editor]");
  if (campoNombre) {
    let nombreGuardado = campoNombre.value;
    campoNombre.addEventListener("change", async () => {
      const nombre = campoNombre.value.trim();
      if (!nombre) {
        campoNombre.value = nombreGuardado;
        return;
      }
      const res = await api(campoNombre.dataset.url, "PATCH", { nombre });
      if (res.ok) {
        nombreGuardado = nombre;
        campoNombre.value = nombre;
        toast("Nombre actualizado");
      } else {
        campoNombre.value = nombreGuardado;
        toast(mensajeError(res), "error");
      }
    });
  }

  // ---------- Arranque ----------

  aplicarVista();
  pintarTodo();
  ponerEstado("Sin cambios", false);
})();
