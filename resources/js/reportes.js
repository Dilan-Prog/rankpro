/**
 * Módulo Reportes — listado y editor de secciones.
 *
 * El editor no reconstruye el JSON leyendo el DOM: cada bloque de sección
 * llega con su `contenido` serializado en data-contenido, ese objeto se
 * mantiene en memoria, los handlers lo mutan y el autosave envía el objeto
 * completo. Así el repintado tras un pegado masivo (que devuelve el contenido
 * ya normalizado por el backend) usa exactamente el mismo código que el render
 * inicial, y las estructuras anidadas —valores por serie, valores por campo de
 * ficha— no dependen de que el DOM las represente fielmente.
 *
 * La forma de cada tipo de sección es la documentada en
 * App\Support\Reportes\EsquemaSeccion. Como el contenido almacenado puede venir
 * de la forma anterior (filas de tabla como mapa plano, valores de ficha como
 * escalar), todo contenido pasa por `normalizarContenido` antes de pintarse.
 */
(function () {
  "use strict";

  const { toast, debounce, openModal, closeModal } = window.AgencyOS;
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

  // ---------- Constantes del contrato (EsquemaSeccion) ----------

  const FORMATOS = [
    ["texto", "Texto"],
    ["numero", "Número"],
    ["decimal", "Decimal"],
    ["porcentaje", "Porcentaje"],
    ["moneda", "Moneda"],
    ["fecha", "Fecha"],
    ["nivel", "Nivel (ALTA/MEDIA/BAJA)"],
  ];

  const ALINEACIONES = [
    ["izquierda", "Izquierda"],
    ["centro", "Centro"],
    ["derecha", "Derecha"],
  ];

  const TOTALES = [
    ["ninguno", "Sin total"],
    ["suma", "Suma"],
    ["promedio", "Promedio"],
    ["ponderado", "Ponderado"],
    ["ctr", "CTR"],
  ];

  const SEVERIDADES = [
    ["critico", "Crítico"],
    ["alto", "Alto"],
    ["medio", "Medio"],
    ["informativo", "Informativo"],
  ];

  const DIRECCIONES = [
    ["positiva", "Positiva"],
    ["negativa", "Negativa"],
    ["neutral", "Neutral"],
  ];

  const PRIORIDADES = [
    ["p0", "P0"],
    ["p1", "P1"],
    ["p2", "P2"],
    ["p3", "P3"],
  ];

  const ESTADOS_FICHA = [
    ["ok", "OK"],
    ["revisar", "Revisar"],
    ["critico", "Crítico"],
    ["nd", "Sin dato"],
  ];

  const EJES = [
    ["izq", "Eje izquierdo"],
    ["der", "Eje derecho"],
  ];

  const PUNTUACIONES = [1, 2, 3, 4, 5].map((n) => [String(n), String(n)]);

  /** Claves de configuración cuyo renombrado arrastra los datos ya capturados. */
  const LISTAS_CONFIG = { serie: "series", tabla: "columnas", ficha: "campos" };

  /** Listas de datos que dependen de la lista de configuración de cada tipo. */
  const LISTAS_DEPENDIENTES = { tabla: ["filas", "filas_excluidas"], serie: ["filas"], ficha: ["registros"] };

  // ---------- Helpers ----------

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

  function request(url, method, payload, opciones) {
    return fetch(url, {
      method,
      headers: jsonHeaders(),
      signal: opciones && opciones.signal,
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

  /** `<option>`s a partir de pares [valor, etiqueta], marcando el seleccionado. */
  function options(pares, seleccionado, vacio) {
    const head = vacio ? `<option value="">${escapeHtml(vacio)}</option>` : "";
    return (
      head +
      pares
        .map(
          ([valor, etiqueta]) =>
            `<option value="${escapeHtml(valor)}"${String(seleccionado) === String(valor) ? " selected" : ""}>${escapeHtml(etiqueta)}</option>`
        )
        .join("")
    );
  }

  function url(plantilla, id) {
    return String(plantilla || "").replace("__ID__", id);
  }

  /**
   * Debounce con `flush`: el autosave del editor necesita poder vaciarse a
   * mano antes de cualquier navegación, o el último cambio tecleado dentro de
   * la ventana de espera se pierde sin avisar.
   */
  function autosave(fn, espera) {
    let timer = null;
    let pendiente = false;

    const api = function () {
      pendiente = true;
      clearTimeout(timer);
      timer = setTimeout(() => {
        timer = null;
        if (!pendiente) return;
        pendiente = false;
        fn();
      }, espera);
    };

    api.pendiente = () => pendiente;
    api.flush = () => {
      if (!pendiente) return Promise.resolve(true);
      clearTimeout(timer);
      timer = null;
      pendiente = false;
      return Promise.resolve(fn());
    };

    return api;
  }

  // ---------- Listado ----------

  function initReporteFiltros() {
    const search = document.getElementById("reporteSearch");
    const areaFilter = document.getElementById("reporteAreaFilter");
    const estadoFilter = document.getElementById("reporteEstadoFilter");
    if (!search || !areaFilter || !estadoFilter) return;

    const noResults = document.getElementById("reporteNoResults");

    function applyFilters() {
      // Se vuelven a consultar en cada pasada: las filas se pueden eliminar
      // por AJAX después de haber cableado el listener.
      const rows = document.querySelectorAll("[data-reporte-row]");
      const term = search.value.trim().toLowerCase();
      const area = areaFilter.value;
      const estado = estadoFilter.value;
      let visible = 0;

      rows.forEach((row) => {
        const show =
          (!term || (row.dataset.search || "").includes(term)) &&
          (area === "all" || row.dataset.area === area) &&
          (estado === "all" || row.dataset.estado === estado);
        row.style.display = show ? "" : "none";
        if (show) visible++;
      });

      if (noResults) noResults.hidden = visible !== 0 || rows.length === 0;
    }

    search.addEventListener("input", debounce(applyFilters, 150));
    areaFilter.addEventListener("change", applyFilters);
    estadoFilter.addEventListener("change", applyFilters);
  }

  function initReporteForm() {
    const form = document.getElementById("reporteForm");
    if (!form) return;

    document.querySelectorAll("[data-open-reporte-modal]").forEach((btn) => {
      btn.addEventListener("click", () => {
        form.reset();
        form.querySelectorAll("[data-error-for]").forEach((el) => (el.textContent = ""));
        openModal("reporteModal");
      });
    });

    form.addEventListener("submit", (e) => {
      e.preventDefault();
      form.querySelectorAll("[data-error-for]").forEach((el) => (el.textContent = ""));

      const payload = {};
      new FormData(form).forEach((value, key) => {
        if (key !== "_token") payload[key] = value;
      });

      const submit = form.querySelector('button[type="submit"]');
      if (submit) submit.disabled = true;

      request(form.dataset.storeAction, "POST", payload)
        .then((data) => {
          // El reporte nace con su plantilla de secciones sembrada: lo útil es
          // llevar al usuario directo al editor, no repintar una fila.
          window.location.href = data.show_url;
        })
        .catch((error) => {
          if (submit) submit.disabled = false;
          const errores = error.body && error.body.errors;
          if (errores) {
            Object.keys(errores).forEach((campo) => {
              const slot = form.querySelector(`[data-error-for="${campo}"]`);
              if (slot) slot.textContent = errores[campo][0];
            });
            return;
          }
          toast("No se pudo crear el reporte.", "error");
        });
    });
  }

  function initReporteDelete() {
    const tabla = document.querySelector("[data-reportes-tabla]");
    if (!tabla) return;

    // La plantilla de la ruta la inyecta Blade, como el resto del módulo.
    const destroyUrl = tabla.dataset.reporteDestroyUrl;

    tabla.addEventListener("click", (e) => {
      const btn = e.target.closest("[data-delete-reporte]");
      if (!btn) return;
      const row = btn.closest("[data-reporte-row]");
      if (!window.confirm("Se eliminará el reporte y todas sus secciones. ¿Continuar?")) return;

      request(url(destroyUrl, btn.dataset.deleteReporte), "DELETE")
        .then(() => {
          row.remove();
          toast("Reporte eliminado.", "success");
          const empty = document.querySelector("[data-reportes-empty]");
          if (!document.querySelector("[data-reporte-row]")) {
            tabla.hidden = true;
            if (empty) empty.hidden = false;
          }
        })
        .catch(() => toast("No se pudo eliminar el reporte.", "error"));
    });
  }

  // ---------- Editor: estado en memoria por sección ----------

  const estados = new WeakMap();
  /** Bloques con estado vivo, para poder vaciar el autosave antes de navegar. */
  const bloques = new Set();

  /** Plantillas de ruta del editor; las inyecta Blade en el contenedor raíz. */
  const rutas = { update: "", destroy: "", reordenar: "", pegar: "", store: "" };

  function parseJson(texto, porDefecto) {
    try {
      const valor = JSON.parse(texto || "null");
      return valor == null ? porDefecto : valor;
    } catch (err) {
      return porDefecto;
    }
  }

  function estado(bloque) {
    let st = estados.get(bloque);
    if (!st) {
      const aviso = parseJson(bloque.dataset.aviso, null);
      st = {
        id: bloque.dataset.seccionId,
        tipo: bloque.dataset.seccionTipo,
        contenido: parseJson(bloque.dataset.contenido, {}) || {},
        titulo: bloque.querySelector("[data-seccion-titulo]")?.value || "",
        rotulo: bloque.querySelector("[data-seccion-rotulo]")?.value || "",
        aviso: aviso && typeof aviso === "object" ? { texto: aviso.texto || "", fecha: aviso.fecha || "" } : { texto: "", fecha: "" },
        visible: !!bloque.querySelector("[data-seccion-visible]")?.checked,
        guardar: null,
        // Secuencia del autosave: una respuesta con `seq` viejo se descarta,
        // para que dos PUT solapados no dejen el estado antiguo en pantalla.
        seq: 0,
        abort: null,
        ultimoError: null,
      };
      normalizarContenido(st);
      estados.set(bloque, st);
      bloques.add(bloque);
    }
    return st;
  }

  function lista(st, clave) {
    if (!Array.isArray(st.contenido[clave])) st.contenido[clave] = [];
    return st.contenido[clave];
  }

  function mapa(st, clave) {
    const valor = st.contenido[clave];
    if (!valor || typeof valor !== "object" || Array.isArray(valor)) st.contenido[clave] = {};
    return st.contenido[clave];
  }

  // ---------- Editor: normalización al contrato vigente ----------

  /** Una fila de tabla es {valores:{}, destacada, motivo}; antes era un mapa plano. */
  function normalizarFilaTabla(fila) {
    if (!fila || typeof fila !== "object" || Array.isArray(fila)) return { valores: {}, destacada: false, motivo: "" };

    if (fila.valores && typeof fila.valores === "object" && !Array.isArray(fila.valores)) {
      return { valores: fila.valores, destacada: !!fila.destacada, motivo: fila.motivo || "" };
    }

    const valores = {};
    Object.keys(fila).forEach((clave) => {
      if (clave !== "valores" && clave !== "destacada" && clave !== "motivo") valores[clave] = fila[clave];
    });

    return { valores, destacada: !!fila.destacada, motivo: fila.motivo || "" };
  }

  /** Una celda de ficha es {valor, estado}; antes era el valor a secas. */
  function normalizarCeldaFicha(celda) {
    if (celda && typeof celda === "object" && !Array.isArray(celda)) {
      return { valor: celda.valor == null ? "" : celda.valor, estado: celda.estado || null };
    }
    return { valor: celda == null ? "" : celda, estado: null };
  }

  /**
   * Deja el contenido en la forma que documenta EsquemaSeccion, venga de donde
   * venga (una sección vieja en base de datos, o la respuesta de un pegado).
   */
  function normalizarContenido(st) {
    const c = st.contenido;

    if (st.tipo === "tabla") {
      ["filas", "filas_excluidas"].forEach((clave) => {
        c[clave] = (Array.isArray(c[clave]) ? c[clave] : []).map(normalizarFilaTabla);
      });
      mapa(st, "totales");
      return;
    }

    if (st.tipo === "serie") {
      (Array.isArray(c.filas) ? c.filas : []).forEach((fila) => {
        if (!fila.valores || typeof fila.valores !== "object" || Array.isArray(fila.valores)) fila.valores = {};
      });
      return;
    }

    if (st.tipo === "ficha") {
      if (!Array.isArray(c.dimensiones)) c.dimensiones = [];
      (Array.isArray(c.registros) ? c.registros : []).forEach((registro) => {
        const origen = registro.valores && typeof registro.valores === "object" && !Array.isArray(registro.valores) ? registro.valores : {};
        const valores = {};
        Object.keys(origen).forEach((clave) => {
          valores[clave] = normalizarCeldaFicha(origen[clave]);
        });
        registro.valores = valores;
      });
    }
  }

  // ---------- Editor: renderizado de filas ----------

  function celdaBorrar() {
    return `<button type="button" class="btn--icon rep-row__del" data-del-fila title="Eliminar fila"><i class="fa-solid fa-xmark"></i></button>`;
  }

  function inputTexto(campo, valor, placeholder, extra) {
    return `<input class="input" type="text" data-campo="${escapeHtml(campo)}" value="${escapeHtml(valor)}" placeholder="${escapeHtml(placeholder || "")}"${extra || ""}>`;
  }

  function campoConEtiqueta(etiqueta, control, ancho) {
    return `<label class="rep-row__campo${ancho ? " rep-row__campo--" + ancho : ""}"><span class="rep-row__label">${escapeHtml(etiqueta)}</span>${control}</label>`;
  }

  function checkCampo(etiqueta, campo, marcado, titulo) {
    return `<label class="rep-row__check" title="${escapeHtml(titulo || "")}"><input type="checkbox" data-campo-check="${escapeHtml(campo)}"${marcado ? " checked" : ""}><span>${escapeHtml(etiqueta)}</span></label>`;
  }

  /**
   * Escribe en el objeto el valor por defecto que el `<select>` va a pintar.
   * Si solo viviera en el render, el usuario vería «Medio» seleccionado y el
   * backend respondería 422 por una clave que nunca llegó a existir.
   */
  function porDefecto(objeto, campo, valor) {
    if (objeto[campo] == null || objeto[campo] === "") objeto[campo] = valor;
    return objeto[campo];
  }

  /** Filas de las listas que se pintan como tarjetas apiladas (`.rep-rows`). */
  const FILAS = {
    "kpis:items": (item) =>
      campoConEtiqueta("Label", inputTexto("label", item.label, "Clics orgánicos"), "ancho") +
      campoConEtiqueta("Valor", inputTexto("valor", item.valor, "12.480")) +
      campoConEtiqueta("Formato", `<select class="select" data-campo="formato">${options(FORMATOS, porDefecto(item, "formato", "texto"))}</select>`) +
      campoConEtiqueta("Comparativo", inputTexto("comparativo", item.comparativo, "+18,2%")) +
      campoConEtiqueta("Dirección", `<select class="select" data-campo="direccion">${options(DIRECCIONES, porDefecto(item, "direccion", "neutral"))}</select>`) +
      campoConEtiqueta("Detalle", inputTexto("detalle", item.detalle, "vs. periodo anterior"), "ancho") +
      campoConEtiqueta("Desactualizado", inputTexto("desactualizado", item.desactualizado, "dato a 31 ago")) +
      checkCampo("Destacado", "destacado", !!item.destacado, "Se imprime con más peso que el resto") +
      celdaBorrar(),

    "hallazgos:items": (item) =>
      campoConEtiqueta(
        "Hallazgo",
        `<textarea class="textarea rep-row__textarea" rows="2" data-campo="titulo" placeholder="Qué se detectó">${escapeHtml(item.titulo)}</textarea>`,
        "ancho"
      ) +
      campoConEtiqueta("Severidad", `<select class="select" data-campo="severidad">${options(SEVERIDADES, porDefecto(item, "severidad", "medio"))}</select>`) +
      campoConEtiqueta("Evidencia", inputTexto("evidencia", item.evidencia, "URL, captura o consulta"), "ancho") +
      celdaBorrar(),

    "serie:series": (item) =>
      campoConEtiqueta("Clave", inputTexto("clave", item.clave, "clics", ` data-prev="${escapeHtml(item.clave)}"`)) +
      campoConEtiqueta("Título", inputTexto("titulo", item.titulo, "Clics"), "ancho") +
      campoConEtiqueta("Eje", `<select class="select" data-campo="eje">${options(EJES, porDefecto(item, "eje", "izq"))}</select>`) +
      campoConEtiqueta("Color", inputTexto("color", item.color, "#2563EB")) +
      celdaBorrar(),

    "tabla:columnas": (item, i, st) =>
      campoConEtiqueta("Clave", inputTexto("clave", item.clave, "consulta", ` data-prev="${escapeHtml(item.clave)}"`)) +
      campoConEtiqueta("Título", inputTexto("titulo", item.titulo, "Consulta"), "ancho") +
      campoConEtiqueta("Tipo", `<select class="select" data-campo="tipo">${options(FORMATOS, porDefecto(item, "tipo", "texto"))}</select>`) +
      campoConEtiqueta("Alineación", `<select class="select" data-campo="alineacion">${options(ALINEACIONES, porDefecto(item, "alineacion", "izquierda"))}</select>`) +
      campoConEtiqueta(
        "Total",
        `<select class="select" data-total-columna>${options(TOTALES, (st.contenido.totales || {})[item.clave] || "ninguno")}</select>`
      ) +
      celdaBorrar(),

    "ficha:dimensiones": (item) =>
      campoConEtiqueta("Clave", inputTexto("clave", item.clave, "indexacion", ` data-prev="${escapeHtml(item.clave)}"`)) +
      campoConEtiqueta("Título", inputTexto("titulo", item.titulo, "Indexación"), "ancho") +
      celdaBorrar(),

    "ficha:campos": (item, i, st) =>
      campoConEtiqueta("Clave", inputTexto("clave", item.clave, "title", ` data-prev="${escapeHtml(item.clave)}"`)) +
      campoConEtiqueta("Título", inputTexto("titulo", item.titulo, "Title"), "ancho") +
      campoConEtiqueta(
        "Dimensión",
        `<select class="select" data-campo="dimension">${options(
          (st.contenido.dimensiones || []).filter((d) => d.clave).map((d) => [d.clave, d.titulo || d.clave]),
          item.dimension || "",
          "— Sin dimensión —"
        )}</select>`
      ) +
      celdaBorrar(),

    "plan:acciones": (item) => {
      const score = calcularScore(item);
      return (
        campoConEtiqueta("Prioridad", `<select class="select" data-campo="prioridad">${options(PRIORIDADES, porDefecto(item, "prioridad", "p2"))}</select>`) +
        campoConEtiqueta(
          "Acción",
          `<textarea class="textarea rep-row__textarea" rows="2" data-campo="accion" placeholder="Qué hay que hacer">${escapeHtml(item.accion)}</textarea>`,
          "ancho"
        ) +
        campoConEtiqueta("Evidencia", inputTexto("evidencia", item.evidencia, "En qué dato se apoya"), "ancho") +
        campoConEtiqueta("Área", inputTexto("area", item.area, "Contenido")) +
        campoConEtiqueta("Impacto", `<select class="select" data-campo="impacto">${options(PUNTUACIONES, porDefecto(item, "impacto", 3))}</select>`) +
        campoConEtiqueta("Esfuerzo", `<select class="select" data-campo="esfuerzo">${options(PUNTUACIONES, porDefecto(item, "esfuerzo", 3))}</select>`) +
        campoConEtiqueta("Score", `<output class="rep-row__score u-mono" data-score>${score}</output>`) +
        campoConEtiqueta("KPI", inputTexto("kpi", item.kpi, "Clics orgánicos"), "ancho") +
        celdaBorrar()
      );
    },

    "texto:bloques": (item) =>
      campoConEtiqueta("Título", inputTexto("titulo", item.titulo, "Fuentes"), "ancho") +
      campoConEtiqueta(
        "Cuerpo",
        `<textarea class="textarea rep-row__textarea" rows="3" data-campo="cuerpo" placeholder="Contenido del bloque">${escapeHtml(item.cuerpo)}</textarea>`,
        "ancho"
      ) +
      celdaBorrar(),
  };

  function calcularScore(accion) {
    const impacto = Number(accion.impacto);
    const esfuerzo = Number(accion.esfuerzo);
    if (!impacto || !esfuerzo) return "—";
    return (impacto / esfuerzo).toFixed(2);
  }

  /** Columnas de una rejilla: las claves de la lista de configuración del tipo. */
  function columnasDe(st) {
    // Una columna sin clave todavía no existe para los datos: pintarla dejaría
    // celdas escribiendo bajo la clave "".
    if (st.tipo === "tabla") {
      return (st.contenido.columnas || []).filter((c) => c.clave).map((c) => ({ clave: c.clave, titulo: c.titulo || c.clave, anidado: true }));
    }
    if (st.tipo === "serie") {
      const series = (st.contenido.series || []).filter((s) => s.clave);
      if (!series.length) return [];
      return [{ clave: "x", titulo: st.contenido.etiqueta_x || "X", anidado: false }].concat(
        series.map((s) => ({ clave: s.clave, titulo: s.titulo || s.clave, anidado: true }))
      );
    }
    return [];
  }

  /** ¿La lista de datos `clave` necesita columnas configuradas para tener sentido? */
  function dependeDeColumnas(st, clave) {
    return (LISTAS_DEPENDIENTES[st.tipo] || []).indexOf(clave) !== -1;
  }

  function hayColumnas(st) {
    if (st.tipo === "tabla") return (st.contenido.columnas || []).some((c) => c.clave);
    if (st.tipo === "serie") return (st.contenido.series || []).some((s) => s.clave);
    if (st.tipo === "ficha") return (st.contenido.campos || []).some((c) => c.clave);
    return true;
  }

  function pintarLista(bloque, st, clave) {
    const contenedor = bloque.querySelector(`[data-lista="${clave}"]`);
    if (!contenedor) return;

    const filas = lista(st, clave);
    const conteo = bloque.querySelector(`[data-conteo="${clave}"]`);
    if (conteo) conteo.textContent = filas.length ? `(${filas.length})` : "";

    const esRejilla = contenedor.tagName === "TBODY";
    const renderer = FILAS[`${st.tipo}:${clave}`];

    if (!filas.length) {
      const mensaje = escapeHtml(contenedor.dataset.vacio || "Sin filas.");
      contenedor.innerHTML = esRejilla
        ? `<tr class="table__empty"><td colspan="99">${mensaje}</td></tr>`
        : `<p class="rep-rows__vacio">${mensaje}</p>`;
      pintarCabecera(bloque, st, clave);
      actualizarBotones(bloque, st);
      return;
    }

    contenedor.innerHTML = filas
      .map((fila, i) => {
        if (renderer) {
          return `<div class="rep-row" data-fila data-index="${i}">${renderer(fila, i, st)}</div>`;
        }
        if (st.tipo === "ficha" && clave === "registros") return fichaHtml(fila, i, st);
        return `<tr data-fila data-index="${i}"${fila.destacada ? ' class="is-destacada"' : ""}>${celdasRejilla(fila, st, clave)}<td class="rep-grid__acciones">${celdaBorrar()}</td></tr>`;
      })
      .join("");

    pintarCabecera(bloque, st, clave);
    actualizarBotones(bloque, st);
  }

  function celdasRejilla(fila, st, clave) {
    let html = columnasDe(st)
      .map((col) => {
        const valor = col.anidado ? (fila.valores || {})[col.clave] : fila[col.clave];
        const attr = col.anidado ? `data-campo-valor="${escapeHtml(col.clave)}"` : `data-campo="${escapeHtml(col.clave)}"`;
        return `<td><input class="input rep-grid__input" type="text" ${attr} value="${escapeHtml(valor)}"></td>`;
      })
      .join("");

    // Metadatos de fila: viven en la raíz del registro, no entre los valores.
    if (st.tipo === "tabla") {
      if (clave === "filas") {
        html += `<td class="rep-grid__check"><input type="checkbox" data-campo-check="destacada"${fila.destacada ? " checked" : ""} title="Destacar la fila en el entregable"></td>`;
      }
      html += `<td><input class="input rep-grid__input" type="text" data-campo="motivo" value="${escapeHtml(fila.motivo)}" placeholder="Por qué"></td>`;
    }

    return html;
  }

  function pintarCabecera(bloque, st, clave) {
    const cabecera = bloque.querySelector(`[data-cabecera="${clave}"]`);
    if (!cabecera) return;

    const cols = columnasDe(st);
    if (!cols.length) {
      cabecera.innerHTML = `<th>Configura primero las ${st.tipo === "serie" ? "series" : "columnas"}.</th>`;
      return;
    }

    let html = cols.map((c) => `<th>${escapeHtml(c.titulo)}</th>`).join("");
    if (st.tipo === "tabla") {
      if (clave === "filas") html += "<th>Destacada</th>";
      html += "<th>Motivo</th>";
    }
    cabecera.innerHTML = html + "<th></th>";
  }

  /** Campos de una ficha agrupados por dimensión, si el usuario definió alguna. */
  function gruposFicha(st) {
    const campos = (st.contenido.campos || []).filter((c) => c.clave);
    const dimensiones = (st.contenido.dimensiones || []).filter((d) => d.clave);
    if (!dimensiones.length) return [{ titulo: "", campos }];

    const grupos = dimensiones.map((d) => ({ titulo: d.titulo || d.clave, campos: campos.filter((c) => c.dimension === d.clave) }));
    const sueltos = campos.filter((c) => !dimensiones.some((d) => d.clave === c.dimension));
    if (sueltos.length) grupos.push({ titulo: "Sin dimensión", campos: sueltos });

    return grupos.filter((g) => g.campos.length);
  }

  function fichaHtml(registro, i, st) {
    const grupos = gruposFicha(st)
      .map((grupo) => {
        const campos = grupo.campos
          .map((campo) => {
            const celda = normalizarCeldaFicha((registro.valores || {})[campo.clave]);
            registro.valores = registro.valores || {};
            registro.valores[campo.clave] = celda;
            return (
              `<div class="rep-ficha__campo"><span class="rep-row__label">${escapeHtml(campo.titulo || campo.clave)}</span>` +
              `<div class="rep-ficha__valor">` +
              `<input class="input" type="text" data-campo-valor="${escapeHtml(campo.clave)}" value="${escapeHtml(celda.valor)}">` +
              `<select class="select rep-ficha__semaforo" data-campo-estado="${escapeHtml(campo.clave)}" title="Semáforo del valor">${options(ESTADOS_FICHA, celda.estado || "", "—")}</select>` +
              `</div></div>`
            );
          })
          .join("");

        return grupo.titulo
          ? `<div class="rep-ficha__grupo"><p class="rep-ficha__grupo-titulo">${escapeHtml(grupo.titulo)}</p><div class="rep-ficha__campos">${campos}</div></div>`
          : `<div class="rep-ficha__campos">${campos}</div>`;
      })
      .join("");

    return `<article class="rep-ficha" data-fila data-index="${i}">
      <header class="rep-ficha__head">
        ${inputTexto("titulo", registro.titulo, "https://ejemplo.com/pagina")}
        <select class="select rep-ficha__estado" data-campo="estado">${options(ESTADOS_FICHA, porDefecto(registro, "estado", "ok"))}</select>
        ${celdaBorrar()}
      </header>
      ${grupos || '<p class="rep-rows__vacio">Sin campos configurados.</p>'}
      <label class="rep-ficha__veredicto"><span class="rep-row__label">Veredicto</span>
        ${inputTexto("veredicto", registro.veredicto, "Qué conclusión deja esta página")}
      </label>
    </article>`;
  }

  /**
   * Sin columnas configuradas, una fila de datos solo puede mostrar su botón de
   * borrar: se deshabilita el alta (y el pegado) hasta que haya columnas.
   */
  function actualizarBotones(bloque, st) {
    const listo = hayColumnas(st);

    bloque.querySelectorAll("[data-add-fila]").forEach((btn) => {
      if (!dependeDeColumnas(st, btn.dataset.addFila)) return;
      btn.disabled = !listo;
      btn.title = listo ? "" : "Configura primero las columnas de la sección.";
    });

    // El botón de pegado vive en la cabecera de la sección, fuera del cuerpo.
    bloque.querySelectorAll("[data-abrir-pegado]").forEach((btn) => {
      btn.disabled = !listo;
      btn.title = listo ? "Pegar desde Excel" : "Configura primero las columnas de la sección.";
    });
  }

  /** Repinta un bloque de sección entero desde su `contenido` en memoria. */
  function pintarSeccion(bloque) {
    const st = estado(bloque);

    bloque.querySelectorAll("[data-campo-raiz]").forEach((campo) => {
      const valor = st.contenido[campo.dataset.campoRaiz];
      campo.value = valor == null ? "" : valor;
    });

    bloque.querySelectorAll("[data-lista]").forEach((contenedor) => {
      pintarLista(bloque, st, contenedor.dataset.lista);
    });

    pintarAviso(bloque, st);
    actualizarBotones(bloque, st);
  }

  function pintarAviso(bloque, st) {
    const chip = bloque.querySelector("[data-aviso-chip]");
    const texto = bloque.querySelector("[data-aviso-texto]");
    const fecha = bloque.querySelector("[data-aviso-fecha]");

    if (texto) texto.value = st.aviso.texto || "";
    if (fecha) fecha.value = st.aviso.fecha || "";
    if (chip) {
      chip.hidden = !st.aviso.texto;
      chip.textContent = st.aviso.texto ? `Dato desactualizado${st.aviso.fecha ? " · " + st.aviso.fecha : ""}` : "";
    }
  }

  // ---------- Editor: guardado ----------

  function marcarGuardado(bloque, texto, esError) {
    const nota = bloque.querySelector("[data-guardado]");
    if (!nota) return;
    nota.textContent = texto;
    nota.classList.toggle("is-error", !!esError);
    nota.classList.add("is-visible");
    clearTimeout(nota._t);
    if (!esError) nota._t = setTimeout(() => nota.classList.remove("is-visible"), 1500);
  }

  /**
   * Aviso persistente en la cabecera: sobrevive al plegado de la sección, que es
   * justo cuando un 422 repetido pasaba inadvertido.
   */
  function marcarAvisoSeccion(bloque, mensaje) {
    const slot = bloque.querySelector("[data-seccion-error]");
    bloque.classList.toggle("is-invalida", !!mensaje);
    if (!slot) return;
    slot.textContent = mensaje || "";
    slot.hidden = !mensaje;
  }

  /** Limpia las marcas de campo inválido de un repintado anterior. */
  function limpiarErroresCampo(bloque) {
    bloque.querySelectorAll(".is-invalid").forEach((el) => {
      el.classList.remove("is-invalid");
      el.removeAttribute("aria-invalid");
      el.removeAttribute("title");
    });
    bloque.querySelectorAll(".rep-row--error").forEach((el) => el.classList.remove("rep-row--error"));
  }

  /**
   * Pinta en rojo la fila y el campo culpables de un 422. El backend devuelve
   * las claves con la ruta completa dentro del contenido
   * (`contenido.acciones.3.accion`, `contenido.registros.0.valores.title.valor`).
   */
  function marcarErroresCampo(bloque, errores) {
    limpiarErroresCampo(bloque);

    Object.keys(errores || {}).forEach((ruta) => {
      const mensaje = [].concat(errores[ruta])[0] || "";
      const partes = ruta.split(".");

      if (partes[0] !== "contenido") {
        const suelto = buscar(bloque, `[data-seccion-${partes[0]}]`);
        if (suelto) marcarCampo(suelto, mensaje);
        return;
      }

      const contenedor = bloque.querySelector(`[data-lista="${partes[1]}"]`);
      if (!contenedor) return;

      const fila = contenedor.querySelector(`[data-fila][data-index="${partes[2]}"]`);
      if (!fila) return;

      fila.classList.add("rep-row--error");

      // `contenido.registros.0.valores.title.valor` apunta a la celda `title`:
      // el nombre de la columna está un nivel más adentro que el campo.
      const campo = partes[3] === "valores" && partes[4] ? partes[4] : partes[3];
      const objetivo =
        (campo && buscar(fila, `[data-campo="${campo}"], [data-campo-valor="${campo}"], [data-campo-check="${campo}"]`)) ||
        fila.querySelector(".input, .select, .textarea");
      if (objetivo) marcarCampo(objetivo, mensaje);
    });
  }

  /**
   * `querySelector` tolerante: las claves de columna las teclea el usuario y
   * pueden llevar comillas que romperían el selector.
   */
  function buscar(raiz, selector) {
    try {
      return raiz.querySelector(selector);
    } catch (err) {
      return null;
    }
  }

  function marcarCampo(el, mensaje) {
    el.classList.add("is-invalid");
    el.setAttribute("aria-invalid", "true");
    if (mensaje) el.setAttribute("title", mensaje);
  }

  function payloadSeccion(st) {
    const aviso = st.aviso && st.aviso.texto ? { texto: st.aviso.texto, fecha: st.aviso.fecha || null } : null;
    return {
      titulo: st.titulo,
      rotulo: st.rotulo || null,
      visible: st.visible,
      aviso,
      contenido: st.contenido,
    };
  }

  /**
   * Guarda la sección. Resuelve a `true` si el servidor aceptó el estado, a
   * `false` si lo rechazó o si la petición quedó obsoleta: quien necesite el
   * contenido almacenado al día (el pegado masivo) puede esperarlo.
   */
  function guardar(bloque) {
    const st = estado(bloque);

    // Secuencia + AbortController: dos PUT solapados podían llegar
    // desordenados y dejar en base de datos el estado más viejo.
    st.seq += 1;
    const seq = st.seq;
    if (st.abort) st.abort.abort();
    st.abort = new AbortController();

    marcarGuardado(bloque, "Guardando…");

    return request(url(rutas.update, st.id), "PUT", payloadSeccion(st), { signal: st.abort.signal })
      .then(() => {
        if (seq !== st.seq) return false;
        st.ultimoError = null;
        marcarGuardado(bloque, "Guardado ✓");
        marcarAvisoSeccion(bloque, "");
        limpiarErroresCampo(bloque);
        return true;
      })
      .catch((error) => {
        if (error.name === "AbortError" || seq !== st.seq) return false;

        // 422 no es una caída: es una fila a medio capturar (los campos
        // obligatorios de cada tipo los fija EsquemaSeccion). Se avisa sin
        // ruido de error de red y se deja seguir escribiendo.
        if (error.status === 422) {
          const errores = (error.body && error.body.errors) || {};
          const primero = Object.keys(errores)[0];
          const mensaje = primero ? [].concat(errores[primero])[0] : "Hay campos obligatorios sin completar.";

          marcarGuardado(bloque, "Sin guardar: faltan datos", true);
          marcarAvisoSeccion(bloque, "Sin guardar — " + mensaje);
          marcarErroresCampo(bloque, errores);

          // Un 422 persistente se repite cada 800 ms: el toast solo sale
          // cuando el motivo cambia; el aviso de la cabecera queda fijo.
          const firma = primero + "|" + mensaje;
          if (st.ultimoError !== firma) {
            st.ultimoError = firma;
            toast(mensaje, "warning");
          }
          return false;
        }

        st.ultimoError = null;
        marcarGuardado(bloque, "No se pudo guardar", true);
        marcarAvisoSeccion(bloque, "No se pudo guardar la sección.");
        toast("No se pudo guardar la sección.", "error");
        return false;
      });
  }

  function programarGuardado(bloque) {
    const st = estado(bloque);
    if (!st.guardar) st.guardar = autosave(() => guardar(bloque), 800);
    st.guardar();
  }

  function hayPendientes() {
    return Array.from(bloques).some((bloque) => {
      const st = estados.get(bloque);
      return st && st.guardar && st.guardar.pendiente();
    });
  }

  /** Vacía el autosave de todas las secciones y espera a que el PUT termine. */
  function vaciarPendientes() {
    const promesas = Array.from(bloques).map((bloque) => {
      const st = estados.get(bloque);
      return st && st.guardar ? st.guardar.flush() : Promise.resolve(true);
    });
    return Promise.all(promesas);
  }

  // ---------- Editor: renombrado de claves de configuración ----------

  /** Claves ya usadas por el resto de filas de la lista de configuración. */
  function clavesOcupadas(st, claveLista, exceptoIndice) {
    return lista(st, claveLista)
      .map((item, i) => (i === exceptoIndice ? null : item.clave))
      .filter((clave) => clave != null && clave !== "");
  }

  /**
   * Renombrar la clave de una columna/serie/campo debe arrastrar los datos ya
   * capturados; si no, el usuario ve la rejilla vaciarse al corregir un typo.
   * La unicidad se comprueba antes de mutar: pisar la clave de otra columna
   * borraba sus datos en todas las filas (y ahora además es un 422 `distinct`).
   */
  function renombrarClave(st, anterior, nueva) {
    if (!anterior || anterior === nueva) return;

    const mover = (obj) => {
      if (!obj || !Object.prototype.hasOwnProperty.call(obj, anterior)) return;
      obj[nueva] = obj[anterior];
      delete obj[anterior];
    };

    if (st.tipo === "tabla") {
      (st.contenido.filas || []).forEach((fila) => mover(fila.valores));
      (st.contenido.filas_excluidas || []).forEach((fila) => mover(fila.valores));
      mover(mapa(st, "totales"));
    } else if (st.tipo === "serie") {
      (st.contenido.filas || []).forEach((fila) => mover(fila.valores));
    } else if (st.tipo === "ficha") {
      (st.contenido.registros || []).forEach((registro) => mover(registro.valores));
    }
  }

  /**
   * Las dimensiones no son columnas: no hay valores capturados bajo su clave,
   * solo los campos que la referencian.
   */
  function renombrarDimension(st, anterior, nueva) {
    if (!anterior || anterior === nueva) return;
    (st.contenido.campos || []).forEach((campo) => {
      if (campo.dimension === anterior) campo.dimension = nueva;
    });
  }

  function camposEnDimension(st, clave) {
    return (st.contenido.campos || []).filter((campo) => campo.dimension === clave).length;
  }

  function olvidarDimension(st, clave) {
    (st.contenido.campos || []).forEach((campo) => {
      if (campo.dimension === clave) campo.dimension = "";
    });
  }

  /** Cuántos valores capturados se perderían al borrar la clave `clave`. */
  function valoresBajoClave(st, clave) {
    if (!clave) return 0;
    let total = 0;
    const cuenta = (obj) => {
      if (obj && Object.prototype.hasOwnProperty.call(obj, clave)) {
        const valor = obj[clave];
        const vacio = valor == null || valor === "" || (typeof valor === "object" && (valor.valor == null || valor.valor === ""));
        if (!vacio) total += 1;
      }
    };

    if (st.tipo === "tabla") {
      (st.contenido.filas || []).forEach((fila) => cuenta(fila.valores));
      (st.contenido.filas_excluidas || []).forEach((fila) => cuenta(fila.valores));
      cuenta(st.contenido.totales);
    } else if (st.tipo === "serie") {
      (st.contenido.filas || []).forEach((fila) => cuenta(fila.valores));
    } else if (st.tipo === "ficha") {
      (st.contenido.registros || []).forEach((registro) => cuenta(registro.valores));
    }

    return total;
  }

  /** Borra la clave de todas las estructuras dependientes: sin huérfanos que resuciten. */
  function olvidarClave(st, clave) {
    if (!clave) return;
    const borrar = (obj) => {
      if (obj) delete obj[clave];
    };

    if (st.tipo === "tabla") {
      (st.contenido.filas || []).forEach((fila) => borrar(fila.valores));
      (st.contenido.filas_excluidas || []).forEach((fila) => borrar(fila.valores));
      borrar(st.contenido.totales);
    } else if (st.tipo === "serie") {
      (st.contenido.filas || []).forEach((fila) => borrar(fila.valores));
    } else if (st.tipo === "ficha") {
      (st.contenido.registros || []).forEach((registro) => borrar(registro.valores));
    }
  }

  /** Repinta solo las rejillas de datos, dejando intacta la lista de configuración. */
  function repintarDependientes(bloque, st) {
    (LISTAS_DEPENDIENTES[st.tipo] || []).forEach((clave) => pintarLista(bloque, st, clave));
  }

  // ---------- Editor: filas nuevas ----------

  function filaNueva(st, clave) {
    const plantillas = {
      "kpis:items": () => ({
        label: "",
        valor: "",
        formato: "texto",
        comparativo: "",
        direccion: "neutral",
        detalle: "",
        destacado: false,
        desactualizado: "",
      }),
      "hallazgos:items": () => ({ titulo: "", severidad: "medio", evidencia: "" }),
      "serie:series": () => ({ clave: "", titulo: "", eje: "izq", color: "" }),
      "serie:filas": () => ({ x: "", valores: {} }),
      "tabla:columnas": () => ({ clave: "", titulo: "", tipo: "texto", alineacion: "izquierda" }),
      "tabla:filas": () => filaTablaVacia(st),
      "tabla:filas_excluidas": () => filaTablaVacia(st),
      "ficha:dimensiones": () => ({ clave: "", titulo: "" }),
      "ficha:campos": () => ({ clave: "", titulo: "", dimension: "" }),
      "ficha:registros": () => ({ titulo: "", valores: {}, veredicto: "", estado: "ok" }),
      "plan:acciones": () => ({ prioridad: "p2", accion: "", evidencia: "", area: "", impacto: 3, esfuerzo: 3, kpi: "" }),
      "texto:bloques": () => ({ titulo: "", cuerpo: "" }),
    };
    const fabrica = plantillas[`${st.tipo}:${clave}`];
    return fabrica ? fabrica() : {};
  }

  function filaTablaVacia(st) {
    const valores = {};
    (st.contenido.columnas || []).forEach((col) => {
      if (col.clave) valores[col.clave] = "";
    });
    return { valores, destacada: false, motivo: "" };
  }

  // ---------- Editor: cableado ----------

  function initEditor() {
    const root = document.querySelector("[data-reporte-editor]");
    if (!root) return;

    rutas.update = root.dataset.seccionUpdateUrl;
    rutas.destroy = root.dataset.seccionDestroyUrl;
    rutas.reordenar = root.dataset.reordenarUrl;
    rutas.pegar = root.dataset.seccionPegarUrl;
    rutas.store = root.dataset.seccionStoreUrl;

    root.querySelectorAll("[data-seccion]").forEach(pintarSeccion);

    function onCampo(e) {
      const el = e.target;
      const bloque = el.closest("[data-seccion]");
      if (!bloque) return;

      // Un `<select>` y un checkbox emiten `input` Y `change`: sin este filtro,
      // el switch «Visible» mandaba dos PUT por clic.
      const esDiscreto = el.matches("select") || (el.tagName === "INPUT" && el.type === "checkbox");
      const esClave = el.matches('[data-campo="clave"]');
      if (esDiscreto ? e.type !== "change" : e.type === "change" && !esClave) return;

      const st = estado(bloque);

      if (el.matches("[data-seccion-titulo]")) {
        st.titulo = el.value;
        programarGuardado(bloque);
        return;
      }

      if (el.matches("[data-seccion-rotulo]")) {
        st.rotulo = el.value;
        programarGuardado(bloque);
        return;
      }

      if (el.matches("[data-aviso-texto]") || el.matches("[data-aviso-fecha]")) {
        st.aviso[el.matches("[data-aviso-texto]") ? "texto" : "fecha"] = el.value;
        pintarAviso(bloque, st);
        programarGuardado(bloque);
        return;
      }

      if (el.matches("[data-seccion-visible]")) {
        st.visible = el.checked;
        guardar(bloque);
        return;
      }

      if (el.matches("[data-campo-raiz]")) {
        st.contenido[el.dataset.campoRaiz] = el.value;
        // La etiqueta del eje X es la cabecera de la primera columna de la rejilla.
        if (el.dataset.campoRaiz === "etiqueta_x") pintarCabecera(bloque, st, "filas");
        programarGuardado(bloque);
        return;
      }

      const fila = el.closest("[data-fila]");
      const contenedor = el.closest("[data-lista]");
      if (!fila || !contenedor) return;

      const claveLista = contenedor.dataset.lista;
      const registro = lista(st, claveLista)[Number(fila.dataset.index)];
      if (!registro) return;

      if (el.matches("[data-total-columna]")) {
        const totales = mapa(st, "totales");
        if (el.value === "ninguno") delete totales[registro.clave];
        else totales[registro.clave] = el.value;
        programarGuardado(bloque);
        return;
      }

      if (el.matches("[data-campo-valor]")) {
        if (!registro.valores || typeof registro.valores !== "object") registro.valores = {};
        const clave = el.dataset.campoValor;
        if (st.tipo === "ficha") {
          const celda = normalizarCeldaFicha(registro.valores[clave]);
          celda.valor = el.value;
          registro.valores[clave] = celda;
        } else {
          registro.valores[clave] = el.value;
        }
        programarGuardado(bloque);
        return;
      }

      if (el.matches("[data-campo-estado]")) {
        const clave = el.dataset.campoEstado;
        const celda = normalizarCeldaFicha((registro.valores || {})[clave]);
        // `null` y no `""`: la regla del esquema es `nullable|in:ok,revisar,...`
        // y una cadena vacía sería un valor fuera de la lista.
        celda.estado = el.value || null;
        registro.valores = registro.valores || {};
        registro.valores[clave] = celda;
        programarGuardado(bloque);
        return;
      }

      if (el.matches("[data-campo-check]")) {
        registro[el.dataset.campoCheck] = el.checked;
        if (el.dataset.campoCheck === "destacada") fila.classList.toggle("is-destacada", el.checked);
        programarGuardado(bloque);
        return;
      }

      if (!el.matches("[data-campo]")) return;
      const campo = el.dataset.campo;

      const esListaConfig = claveLista === LISTAS_CONFIG[st.tipo];
      const esDimension = st.tipo === "ficha" && claveLista === "dimensiones";

      // El renombrado de una clave de configuración se procesa en `change`
      // (al salir del campo): en `input` cada pulsación sería una clave nueva
      // y arrastraría los datos letra a letra.
      if (esClave && (esListaConfig || esDimension)) {
        if (e.type !== "change") return;
        const anterior = el.dataset.prev || "";
        const nueva = el.value.trim();

        // Sin clave los valores quedan huérfanos y el camino de vuelta desde
        // "" ya no encuentra qué renombrar: se revierte y se avisa.
        if (nueva === "") {
          el.value = anterior;
          marcarCampo(el, "La clave no puede quedar vacía.");
          toast("La clave no puede quedar vacía.", "warning");
          return;
        }

        if (clavesOcupadas(st, claveLista, Number(fila.dataset.index)).indexOf(nueva) !== -1) {
          marcarCampo(el, "Ya hay otra columna con esta clave.");
          toast(`La clave «${nueva}» ya está en uso en esta sección.`, "warning");
          return;
        }

        el.classList.remove("is-invalid");
        el.removeAttribute("aria-invalid");
        el.removeAttribute("title");

        if (esDimension) renombrarDimension(st, anterior, nueva);
        else renombrarClave(st, anterior, nueva);

        registro.clave = nueva;
        el.dataset.prev = nueva;
        el.value = nueva;

        if (esDimension) pintarLista(bloque, st, "campos");
        repintarDependientes(bloque, st);
        programarGuardado(bloque);
        return;
      }

      registro[campo] = campo === "impacto" || campo === "esfuerzo" ? Number(el.value) : el.value;

      if (st.tipo === "plan" && (campo === "impacto" || campo === "esfuerzo")) {
        const score = fila.querySelector("[data-score]");
        if (score) score.textContent = calcularScore(registro);
      }

      // El título de una columna/serie/campo es la cabecera de la rejilla; la
      // dimensión reagrupa los campos dentro de cada ficha.
      if ((campo === "titulo" || campo === "dimension") && (esListaConfig || esDimension)) {
        const dependiente = st.tipo === "ficha" ? "registros" : "filas";
        pintarCabecera(bloque, st, dependiente);
        if (st.tipo === "tabla") pintarCabecera(bloque, st, "filas_excluidas");
        if (st.tipo === "ficha") pintarLista(bloque, st, "registros");
        if (esDimension) pintarLista(bloque, st, "campos");
      }

      programarGuardado(bloque);
    }

    root.addEventListener("input", onCampo);
    root.addEventListener("change", onCampo);

    root.addEventListener("click", (e) => {
      const bloque = e.target.closest("[data-seccion]");
      if (!bloque) return;
      const st = estado(bloque);

      const toggle = e.target.closest("[data-toggle-seccion]");
      if (toggle) {
        const body = bloque.querySelector("[data-seccion-body]");
        const abierto = toggle.getAttribute("aria-expanded") === "true";
        toggle.setAttribute("aria-expanded", abierto ? "false" : "true");
        bloque.classList.toggle("is-plegada", abierto);
        if (body) body.hidden = abierto;
        return;
      }

      const avisoToggle = e.target.closest("[data-toggle-aviso]");
      if (avisoToggle) {
        const panel = bloque.querySelector("[data-aviso-panel]");
        if (!panel) return;
        panel.hidden = !panel.hidden;
        avisoToggle.setAttribute("aria-expanded", panel.hidden ? "false" : "true");
        if (!panel.hidden) panel.querySelector("[data-aviso-texto]")?.focus();
        return;
      }

      const avisoLimpiar = e.target.closest("[data-limpiar-aviso]");
      if (avisoLimpiar) {
        st.aviso = { texto: "", fecha: "" };
        pintarAviso(bloque, st);
        programarGuardado(bloque);
        return;
      }

      const add = e.target.closest("[data-add-fila]");
      if (add) {
        if (add.disabled) return;
        const clave = add.dataset.addFila;
        if (dependeDeColumnas(st, clave) && !hayColumnas(st)) {
          toast("Configura primero las columnas de la sección.", "warning");
          return;
        }
        lista(st, clave).push(filaNueva(st, clave));
        pintarLista(bloque, st, clave);
        if (clave === LISTAS_CONFIG[st.tipo]) repintarDependientes(bloque, st);
        if (st.tipo === "ficha" && clave === "dimensiones") pintarLista(bloque, st, "campos");
        // No se guarda aquí: una fila recién añadida está vacía y los campos
        // obligatorios del tipo la rechazarían. Guarda el primer tecleo.
        const primerCampo = bloque.querySelector(`[data-lista="${clave}"] [data-fila]:last-child .input, [data-lista="${clave}"] [data-fila]:last-child .textarea`);
        if (primerCampo) primerCampo.focus();
        return;
      }

      const del = e.target.closest("[data-del-fila]");
      if (del) {
        const fila = del.closest("[data-fila]");
        const contenedor = del.closest("[data-lista]");
        if (!fila || !contenedor) return;
        const clave = contenedor.dataset.lista;
        const indice = Number(fila.dataset.index);
        const esConfig = clave === LISTAS_CONFIG[st.tipo];
        const esDimension = st.tipo === "ficha" && clave === "dimensiones";
        const registro = lista(st, clave)[indice];

        // Borrar una columna dejaba sus datos huérfanos en filas, filas
        // excluidas y totales: resucitaban al crear otra columna igual.
        if (esConfig && registro && registro.clave) {
          const huerfanos = valoresBajoClave(st, registro.clave);
          const aviso = huerfanos
            ? `Se eliminará «${registro.clave}» y se perderán ${huerfanos} valor${huerfanos === 1 ? "" : "es"} ya capturado${huerfanos === 1 ? "" : "s"}. ¿Continuar?`
            : `Se eliminará la clave «${registro.clave}». ¿Continuar?`;
          if (!window.confirm(aviso)) return;
          olvidarClave(st, registro.clave);
        }

        if (esDimension && registro && registro.clave) {
          const afectados = camposEnDimension(st, registro.clave);
          const aviso = afectados
            ? `Se eliminará la dimensión «${registro.clave}»: ${afectados} campo${afectados === 1 ? "" : "s"} quedará${afectados === 1 ? "" : "n"} sin agrupar. ¿Continuar?`
            : `Se eliminará la dimensión «${registro.clave}». ¿Continuar?`;
          if (!window.confirm(aviso)) return;
          olvidarDimension(st, registro.clave);
        }

        lista(st, clave).splice(indice, 1);
        pintarLista(bloque, st, clave);
        if (esConfig) repintarDependientes(bloque, st);
        if (esDimension) {
          pintarLista(bloque, st, "campos");
          pintarLista(bloque, st, "registros");
        }
        guardar(bloque);
        return;
      }

      const mover = e.target.closest("[data-mover]");
      if (mover) {
        moverSeccion(bloque, mover.dataset.mover);
        return;
      }

      const eliminar = e.target.closest("[data-eliminar-seccion]");
      if (eliminar) {
        if (!window.confirm("Se eliminará esta sección y su contenido. ¿Continuar?")) return;
        request(url(rutas.destroy, st.id), "DELETE")
          .then(() => {
            bloques.delete(bloque);
            bloque.remove();
            toast("Sección eliminada.", "success");
            actualizarVacio();
          })
          .catch(() => toast("No se pudo eliminar la sección.", "error"));
        return;
      }

      const pegar = e.target.closest("[data-abrir-pegado]");
      if (pegar && !pegar.disabled) abrirPegado(bloque, pegar);
    });

    /**
     * Mueve una sección y persiste el orden. Los botones quedan bloqueados
     * mientras dura la petición (un doble clic mandaba dos POST) y el DOM
     * vuelve a su sitio si el servidor la rechaza.
     */
    let reordenando = false;

    function moverSeccion(bloque, direccion) {
      if (reordenando) return;

      const hermano = direccion === "arriba" ? bloque.previousElementSibling : bloque.nextElementSibling;
      if (!hermano || !hermano.matches("[data-seccion]")) return;

      const anclaAnterior = bloque.previousElementSibling;
      const padre = bloque.parentNode;

      if (direccion === "arriba") hermano.before(bloque);
      else hermano.after(bloque);

      const botones = Array.from(document.querySelectorAll("[data-mover]"));
      botones.forEach((btn) => (btn.disabled = true));
      reordenando = true;

      const orden = Array.from(document.querySelectorAll("[data-seccion]")).map((s) => Number(s.dataset.seccionId));

      request(rutas.reordenar, "POST", { orden })
        .then(() => toast("Orden actualizado.", "success"))
        .catch(() => {
          // Rollback: el DOM no puede quedar mostrando un orden que el
          // servidor no tiene.
          if (anclaAnterior && anclaAnterior.parentNode === padre) anclaAnterior.after(bloque);
          else padre.prepend(bloque);
          toast("No se pudo reordenar las secciones.", "error");
        })
        .finally(() => {
          reordenando = false;
          botones.forEach((btn) => (btn.disabled = false));
        });
    }

    function actualizarVacio() {
      const empty = document.querySelector("[data-secciones-empty]");
      if (empty) empty.hidden = !!document.querySelector("[data-seccion]");
    }

    initSeccionForm();
    initPegado();
    initNavegacionSegura();
  }

  // ---------- Editor: no perder el último cambio al navegar ----------

  /**
   * El autosave espera 800 ms: previsualizar, generar un entregable, volver al
   * listado o añadir una sección (que recarga) dentro de esa ventana descartaba
   * el último cambio sin avisar. Toda navegación propia del editor vacía antes
   * la cola; para lo demás queda el `beforeunload`.
   */
  function initNavegacionSegura() {
    document.addEventListener("click", (e) => {
      const enlace = e.target.closest("a[data-nav-flush]");
      if (!enlace || !hayPendientes()) return;
      e.preventDefault();
      const destino = enlace.href;
      const nuevaPestana = enlace.target === "_blank";
      vaciarPendientes().then(() => {
        if (nuevaPestana) window.open(destino, "_blank", "noopener");
        else window.location.href = destino;
      });
    });

    // En burbuja y respetando `defaultPrevented`: el `data-confirm` de global.js
    // escucha en el mismo punto y su cancelación tiene que mandar.
    document.addEventListener("submit", (e) => {
      const form = e.target.closest("form[data-nav-flush]");
      if (!form || e.defaultPrevented || form.dataset.flushed === "1" || !hayPendientes()) return;
      e.preventDefault();
      vaciarPendientes().then(() => {
        form.dataset.flushed = "1";
        form.submit();
      });
    });

    window.addEventListener("beforeunload", (e) => {
      if (!hayPendientes()) return;
      e.preventDefault();
      e.returnValue = "";
      return "";
    });
  }

  // ---------- Editor: añadir sección ----------

  function initSeccionForm() {
    const form = document.getElementById("seccionForm");
    if (!form) return;

    document.querySelectorAll("[data-open-seccion-modal]").forEach((btn) => {
      btn.addEventListener("click", () => {
        form.reset();
        form.querySelectorAll("[data-error-for]").forEach((el) => (el.textContent = ""));
        openModal("seccionModal");
      });
    });

    form.addEventListener("submit", (e) => {
      e.preventDefault();
      const payload = {
        tipo: form.querySelector("[data-seccion-tipo-select]").value,
        titulo: form.querySelector("#sec_titulo").value,
      };

      const submit = form.querySelector('button[type="submit"]');
      if (submit) submit.disabled = true;

      // Se recarga la página: hay que vaciar antes el autosave de las demás
      // secciones o el último cambio tecleado se va con la recarga.
      vaciarPendientes()
        .then(() => request(rutas.store, "POST", payload))
        .then(() => {
          // La sección nueva trae su propio editor Blade: recargar es más
          // barato y más fiable que replicar los siete editores en JS.
          window.location.reload();
        })
        .catch((error) => {
          if (submit) submit.disabled = false;
          const errores = error.body && error.body.errors;
          if (errores) {
            Object.keys(errores).forEach((campo) => {
              const slot = form.querySelector(`[data-error-for="${campo}"]`);
              if (slot) slot.textContent = errores[campo][0];
            });
            return;
          }
          toast("No se pudo añadir la sección.", "error");
        });
    });
  }

  // ---------- Editor: pegado desde hoja de cálculo ----------

  let bloqueEnPegado = null;

  /** Orden de columnas que espera el backend, derivado del propio contenido. */
  function columnasPegado(st) {
    if (st.tipo === "tabla") return (st.contenido.columnas || []).map((c) => c.clave);
    if (st.tipo === "serie") return ["x"].concat((st.contenido.series || []).map((s) => s.clave));
    if (st.tipo === "ficha") return ["titulo"].concat((st.contenido.campos || []).map((c) => c.clave), ["veredicto"]);
    if (st.tipo === "plan") return ["prioridad", "accion", "evidencia", "area", "impacto", "esfuerzo", "kpi"];
    return [];
  }

  /**
   * El servidor mapea el texto pegado contra el contenido ALMACENADO, no contra
   * el que hay en memoria: sin forzar un guardado antes de abrir el modal, el
   * usuario podía pegar contra columnas que el backend todavía no conocía.
   */
  function abrirPegado(bloque, boton) {
    const st = estado(bloque);
    if (boton) boton.disabled = true;

    // Se vacía el debounce y se ESPERA al PUT: recién después el contenido
    // almacenado coincide con el que anuncia el modal.
    Promise.resolve(st.guardar ? st.guardar.flush() : true)
      .then(() => guardar(bloque))
      .then((ok) => {
        if (!ok) {
          toast("No se abrió el pegado: la sección tiene cambios sin guardar que el servidor rechazó. Corrígelos primero.", "warning");
          return;
        }

        bloqueEnPegado = bloque;

        const ayuda = document.querySelector("[data-pegar-columnas]");
        if (ayuda) {
          const cols = columnasPegado(st);
          ayuda.textContent = cols.length ? cols.join("  ·  ") : "Configura primero las columnas de la sección.";
        }

        const errores = document.querySelector("[data-pegar-errores]");
        if (errores) {
          errores.innerHTML = "";
          errores.hidden = true;
        }

        const textarea = document.querySelector("[data-pegar-textarea]");
        if (textarea) textarea.value = "";

        openModal("pegarModal");
      })
      .finally(() => {
        if (boton) boton.disabled = !hayColumnas(st);
      });
  }

  function initPegado() {
    const form = document.getElementById("pegarForm");
    if (!form) return;

    form.addEventListener("submit", (e) => {
      e.preventDefault();
      if (!bloqueEnPegado) return;

      const bloque = bloqueEnPegado;
      const st = estado(bloque);
      const textarea = form.querySelector("[data-pegar-textarea]");
      const boton = form.querySelector("[data-pegar-importar]");
      const errores = form.querySelector("[data-pegar-errores]");

      if (!textarea.value.trim()) {
        toast("Pega al menos una fila.", "warning");
        return;
      }

      boton.disabled = true;
      request(url(rutas.pegar, st.id), "POST", { texto: textarea.value })
        .then((data) => aplicarPegado(bloque, st, textarea, errores, data))
        .catch((error) => {
          // El endpoint responde 422 cuando NO se creó ninguna fila, pero el
          // cuerpo sigue trayendo el desglose fila a fila: ese caso no es un
          // fallo de red, es el resultado que hay que mostrar en el modal.
          const body = error.body || {};
          if (Array.isArray(body.errores) || body.creadas != null) {
            aplicarPegado(bloque, st, textarea, errores, body);
            return;
          }
          toast(body.message || "No se pudo importar el texto pegado.", "error");
        })
        .finally(() => {
          boton.disabled = false;
        });
    });
  }

  /**
   * Éxito total, parcial o nulo se resuelven igual: si se creó algo se repinta
   * la sección con el contenido que devuelve el backend, y si hubo rechazos el
   * modal se queda abierto mostrándolos fila a fila.
   */
  function aplicarPegado(bloque, st, textarea, errores, data) {
    const creadas = Number(data.creadas || 0);
    const fallos = Array.isArray(data.errores) ? data.errores : [];

    if (creadas > 0 && data.seccion && data.seccion.contenido) {
      st.contenido = data.seccion.contenido;
      normalizarContenido(st);
      bloque.dataset.contenido = JSON.stringify(st.contenido);
      if (data.seccion.titulo != null) st.titulo = data.seccion.titulo;
      pintarSeccion(bloque);
      toast(`${creadas} fila${creadas === 1 ? "" : "s"} importada${creadas === 1 ? "" : "s"}.`, "success");
    }

    if (fallos.length) {
      // Solo quedan en el textarea las líneas rechazadas: si se dejaran las
      // cinco, corregir la mala y reimportar duplicaba las cuatro buenas,
      // porque el backend siempre suma filas.
      const rechazadas = fallos.map((fallo) => (typeof fallo === "string" ? "" : fallo.texto || "")).filter((t) => t !== "");
      if (creadas > 0 && rechazadas.length === fallos.length) textarea.value = rechazadas.join("\n");
      pintarErroresPegado(errores, fallos, creadas > 0 && rechazadas.length === fallos.length);
      return;
    }

    errores.hidden = true;
    errores.innerHTML = "";
    textarea.value = "";
    if (creadas === 0) toast(data.message || "No se importó ninguna fila.", "warning");
    closeModal("pegarModal");
  }

  /** Un rechazo por fila: número, texto original y qué falló en ella. */
  function pintarErroresPegado(contenedor, fallos, soloRechazadas) {
    if (!contenedor) return;

    const nota = soloRechazadas
      ? `<p class="rep-pegar__errores-nota">Las filas importadas ya se quitaron del cuadro de texto: corrige lo que queda y vuelve a importar.</p>`
      : "";

    contenedor.innerHTML =
      `<p class="rep-pegar__errores-titulo"><i class="fa-solid fa-triangle-exclamation"></i> ${fallos.length} fila${fallos.length === 1 ? "" : "s"} sin importar</p>` +
      nota +
      fallos
        .map((fallo) => {
          if (typeof fallo === "string") return `<div class="rep-pegar__error"><p class="rep-pegar__error-msg">${escapeHtml(fallo)}</p></div>`;
          const numero = fallo.fila != null ? fallo.fila : fallo.linea;
          const mensajes = [].concat(fallo.errores || fallo.mensajes || fallo.mensaje || []);
          return `<div class="rep-pegar__error">
            <p class="rep-pegar__error-head"><span class="rep-pegar__error-num">Fila ${escapeHtml(numero == null ? "?" : numero)}</span>
              <code class="rep-pegar__error-texto">${escapeHtml(fallo.texto || "")}</code></p>
            <ul class="rep-pegar__error-lista">${mensajes.map((m) => `<li>${escapeHtml(m)}</li>`).join("")}</ul>
          </div>`;
        })
        .join("");

    contenedor.hidden = false;
  }

  document.addEventListener("shell:ready", () => {
    initReporteFiltros();
    initReporteForm();
    initReporteDelete();
    initEditor();
  });
})();
