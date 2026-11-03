/**
 * Módulo «Contenido»: explorador de carpetas/archivos, editor de documentos
 * Markdown y tabla editable (vista tabla y Kanban).
 *
 * Todo el DOM dinámico se construye con createElement + textContent: el
 * contenido de celdas, nombres y opciones lo escribe un usuario y jamás se
 * interpola como HTML.
 */
(function () {
  "use strict";

  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || "";

  // ---------- Utilidades ----------

  function toast(mensaje, tipo) {
    window.AgencyOS.toast(mensaje, tipo);
  }

  /** Petición JSON (o FormData si `cuerpo` lo es). Siempre resuelve { ok, status, datos }. */
  function api(url, metodo, cuerpo) {
    const headers = { Accept: "application/json", "X-CSRF-TOKEN": csrfToken, "X-Requested-With": "XMLHttpRequest" };
    let body;
    if (cuerpo instanceof FormData) {
      body = cuerpo;
    } else if (cuerpo !== undefined) {
      headers["Content-Type"] = "application/json";
      body = JSON.stringify(cuerpo);
    }
    return fetch(url, { method: metodo, headers, body })
      .then((res) => res.json().catch(() => ({})).then((datos) => ({ ok: res.ok, status: res.status, datos })))
      .catch(() => ({ ok: false, status: 0, datos: { message: "No se pudo conectar con el servidor." } }));
  }

  /** Primer mensaje de error legible de una respuesta de Laravel. */
  function mensajeError(res) {
    const errores = res.datos && res.datos.errors;
    if (errores) {
      const primero = Object.values(errores)[0];
      if (primero && primero[0]) return primero[0];
    }
    if (res.status === 413) return "El archivo o la tabla es demasiado grande.";
    return (res.datos && res.datos.message) || "No se pudo completar la acción.";
  }

  function el(etiqueta, atributos, hijos) {
    const nodo = document.createElement(etiqueta);
    Object.entries(atributos || {}).forEach(([clave, valor]) => {
      if (valor === null || valor === undefined || valor === false) return;
      if (clave === "class") nodo.className = valor;
      else if (clave === "text") nodo.textContent = valor;
      else nodo.setAttribute(clave, valor === true ? "" : valor);
    });
    (hijos || []).forEach((h) => nodo.appendChild(typeof h === "string" ? document.createTextNode(h) : h));
    return nodo;
  }

  function mostrarError(nodo, mensaje) {
    if (!nodo) return;
    nodo.textContent = mensaje || "";
    nodo.hidden = !mensaje;
  }

  function horaActual() {
    return new Date().toLocaleTimeString("es-MX", { hour: "2-digit", minute: "2-digit", second: "2-digit" });
  }

  // ---------- Nombre editable (cabecera de los editores) ----------

  function iniciarNombreEditable() {
    const campo = document.querySelector("[data-cm-nombre-editor]");
    if (!campo) return;
    let guardado = campo.value;

    campo.addEventListener("change", async () => {
      const nombre = campo.value.trim();
      if (!nombre) {
        campo.value = guardado;
        return;
      }
      const res = await api(campo.dataset.url, "PATCH", { nombre });
      if (res.ok) {
        guardado = nombre;
        campo.value = nombre;
        toast("Nombre actualizado");
      } else {
        campo.value = guardado;
        toast(mensajeError(res), "error");
      }
    });
  }

  // ======================================================================
  // EXPLORADOR
  // ======================================================================

  function iniciarExplorador(raiz) {
    const modalNombre = document.getElementById("cmNombreModal");
    const formNombre = modalNombre.querySelector("[data-cm-nombre-form]");
    const inputNombre = document.getElementById("cmNombreInput");
    const errorNombre = modalNombre.querySelector("[data-cm-nombre-error]");
    const tituloNombre = modalNombre.querySelector("[data-cm-nombre-titulo]");
    const modalMover = document.getElementById("cmMoverModal");
    const formMover = modalMover.querySelector("[data-cm-mover-form]");
    const selectMover = document.getElementById("cmMoverSelect");
    const errorMover = modalMover.querySelector("[data-cm-mover-error]");
    const inputArchivo = raiz.querySelector("[data-cm-input-archivo]");
    const parentId = raiz.dataset.parentId ? Number(raiz.dataset.parentId) : null;

    // Acción pendiente del modal de nombre: crear o renombrar.
    let accionNombre = null;
    let filaMover = null;

    const titulos = { carpeta: "Nueva carpeta", documento: "Nuevo documento", tabla: "Nueva tabla", pizarra: "Nueva pizarra" };

    raiz.querySelectorAll("[data-cm-crear]").forEach((boton) => {
      boton.addEventListener("click", () => {
        const tipo = boton.dataset.cmCrear;
        const plantilla = boton.dataset.plantilla || null;
        accionNombre = { modo: "crear", tipo, plantilla };
        tituloNombre.textContent = plantilla === "calendario" ? "Nuevo calendario de contenido" : titulos[tipo];
        inputNombre.value = plantilla === "calendario" ? "Calendario de contenido" : tipo === "pizarra" ? "Pizarra" : "";
        mostrarError(errorNombre, "");
        document.querySelectorAll("[data-dropdown-panel]").forEach((p) => (p.hidden = true));
        window.AgencyOS.openModal("cmNombreModal");
        inputNombre.focus();
        inputNombre.select();
      });
    });

    raiz.querySelectorAll("[data-cm-renombrar]").forEach((boton) => {
      boton.addEventListener("click", () => {
        const fila = boton.closest("[data-cm-fila]");
        accionNombre = { modo: "renombrar", url: fila.dataset.urlActualizar };
        tituloNombre.textContent = "Renombrar";
        inputNombre.value = fila.dataset.nombre;
        mostrarError(errorNombre, "");
        window.AgencyOS.openModal("cmNombreModal");
        inputNombre.focus();
        inputNombre.select();
      });
    });

    formNombre.addEventListener("submit", async (e) => {
      e.preventDefault();
      const nombre = inputNombre.value.trim();
      if (!nombre) {
        mostrarError(errorNombre, "Escribe un nombre.");
        return;
      }
      const boton = formNombre.querySelector("[data-cm-nombre-guardar]");
      boton.disabled = true;

      let res;
      if (accionNombre.modo === "crear") {
        res = await api(raiz.dataset.urlStore, "POST", {
          tipo: accionNombre.tipo,
          nombre,
          parent_id: parentId,
          plantilla: accionNombre.plantilla,
        });
      } else {
        res = await api(accionNombre.url, "PATCH", { nombre });
      }
      boton.disabled = false;

      if (!res.ok) {
        mostrarError(errorNombre, mensajeError(res));
        return;
      }
      // Documentos y tablas se abren para empezar a escribir; el resto recarga la lista.
      if (accionNombre.modo === "crear" && res.datos.tipo !== "carpeta") {
        window.location.href = res.datos.url;
      } else {
        window.location.reload();
      }
    });

    raiz.querySelectorAll("[data-cm-mover]").forEach((boton) => {
      boton.addEventListener("click", () => {
        filaMover = boton.closest("[data-cm-fila]");
        selectMover.value = filaMover.dataset.parentId || "";
        // La carpeta no se ofrece como destino de sí misma; el servidor valida además los descendientes.
        Array.from(selectMover.options).forEach((op) => {
          op.disabled = op.value !== "" && op.value === filaMover.dataset.id;
        });
        mostrarError(errorMover, "");
        window.AgencyOS.openModal("cmMoverModal");
      });
    });

    formMover.addEventListener("submit", async (e) => {
      e.preventDefault();
      const destino = selectMover.value ? Number(selectMover.value) : null;
      const res = await api(filaMover.dataset.urlActualizar, "PATCH", { parent_id: destino });
      if (!res.ok) {
        mostrarError(errorMover, mensajeError(res));
        return;
      }
      window.location.reload();
    });

    raiz.querySelectorAll("[data-cm-borrar]").forEach((boton) => {
      boton.addEventListener("click", async () => {
        const fila = boton.closest("[data-cm-fila]");
        const esCarpeta = fila.dataset.tipo === "carpeta";
        const aviso = esCarpeta
          ? `¿Eliminar la carpeta «${fila.dataset.nombre}» y TODO lo que contiene? No se puede deshacer.`
          : `¿Eliminar «${fila.dataset.nombre}»? No se puede deshacer.`;
        if (!window.confirm(aviso)) return;

        const res = await api(fila.dataset.urlBorrar, "DELETE");
        if (!res.ok) {
          toast(mensajeError(res), "error");
          return;
        }
        window.location.reload();
      });
    });

    // Subida de archivos: validación de tamaño en el cliente solo como cortesía; el servidor es quien manda.
    raiz.querySelector("[data-cm-subir]").addEventListener("click", () => {
      document.querySelectorAll("[data-dropdown-panel]").forEach((p) => (p.hidden = true));
      inputArchivo.click();
    });

    inputArchivo.addEventListener("change", async () => {
      const archivo = inputArchivo.files[0];
      if (!archivo) return;
      const maxMb = Number(raiz.dataset.maxMb);
      if (archivo.size > maxMb * 1024 * 1024) {
        toast(`El archivo supera el máximo de ${maxMb} MB.`, "error");
        inputArchivo.value = "";
        return;
      }
      const datos = new FormData();
      datos.append("archivo", archivo);
      if (parentId) datos.append("parent_id", String(parentId));

      toast("Subiendo archivo...");
      const res = await api(raiz.dataset.urlSubir, "POST", datos);
      inputArchivo.value = "";
      if (!res.ok) {
        toast(mensajeError(res), "error");
        return;
      }
      window.location.reload();
    });
  }

  // ======================================================================
  // DOCUMENTO MARKDOWN
  // ======================================================================

  function iniciarDocumento(raiz) {
    const texto = raiz.querySelector("[data-cm-markdown]");
    const previa = raiz.querySelector("[data-cm-previa]");
    const palabras = raiz.querySelector("[data-cm-palabras]");
    const estado = raiz.querySelector("[data-cm-estado]");
    const botonGuardar = raiz.querySelector("[data-cm-guardar]");
    const botonPrevia = raiz.querySelector("[data-cm-previa-actualizar]");
    let sucio = false;

    function contarPalabras(valor) {
      const limpio = valor.replace(/[#>*_`\-\[\]()!]/g, " ").trim();
      return limpio ? limpio.split(/\s+/).length : 0;
    }

    function marcar(cambios) {
      sucio = cambios;
      estado.textContent = cambios ? "Cambios sin guardar" : estado.textContent;
      estado.classList.toggle("is-sucio", cambios);
    }

    async function renderizar() {
      if (!texto.value.trim()) {
        previa.replaceChildren(el("p", { class: "cm-placeholder", text: "Escribe Markdown a la izquierda para ver el resultado aquí." }));
        return;
      }
      const res = await api(raiz.dataset.urlPrevia, "POST", { contenido: texto.value });
      if (!res.ok) {
        previa.replaceChildren(el("p", { class: "cm-placeholder", text: "No se pudo generar la vista previa." }));
        return;
      }
      // El HTML ya viene del renderizador seguro del servidor (html_input escapado).
      previa.innerHTML = res.datos.html;
      palabras.textContent = `${res.datos.palabras} palabras`;
    }

    async function guardar() {
      botonGuardar.disabled = true;
      estado.textContent = "Guardando...";
      const res = await api(raiz.dataset.urlGuardar, "PUT", { contenido: texto.value });
      botonGuardar.disabled = false;
      if (!res.ok) {
        estado.textContent = "No se pudo guardar";
        toast(mensajeError(res), "error");
        return;
      }
      sucio = false;
      estado.classList.remove("is-sucio");
      estado.textContent = `Guardado a las ${res.datos.guardado}`;
      toast("Documento guardado");
    }

    const renderizarDiferido = window.AgencyOS.debounce(renderizar, 800);

    texto.addEventListener("input", () => {
      palabras.textContent = `${contarPalabras(texto.value)} palabras`;
      marcar(true);
      renderizarDiferido();
    });
    botonPrevia.addEventListener("click", renderizar);
    botonGuardar.addEventListener("click", guardar);

    document.addEventListener("keydown", (e) => {
      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === "s") {
        e.preventDefault();
        guardar();
      }
    });
    window.addEventListener("beforeunload", (e) => {
      if (sucio) {
        e.preventDefault();
        e.returnValue = "";
      }
    });

    palabras.textContent = `${contarPalabras(texto.value)} palabras`;
    if (texto.value.trim()) renderizar();
  }

  // ======================================================================
  // TABLA EDITABLE
  // ======================================================================

  function iniciarTabla(raiz) {
    let estado;
    try {
      estado = JSON.parse(raiz.dataset.datos);
    } catch (e) {
      estado = { columnas: [], filas: [], vista: "tabla", columna_kanban: null };
    }
    estado.columnas = estado.columnas || [];
    estado.filas = estado.filas || [];
    estado.columnas_visibles = estado.columnas_visibles || [];

    let fuentes = [];
    let fuentesEtiquetas = {};
    try {
      fuentes = JSON.parse(raiz.dataset.fuentes || "[]");
      fuentesEtiquetas = JSON.parse(raiz.dataset.fuentesEtiquetas || "{}");
    } catch (e) {
      // sin fuentes: el menú «Llenar desde...» ni existe en la vista
    }
    const hoy = new Date();
    let mesVisible = { a: hoy.getFullYear(), m: hoy.getMonth() }; // m: 0-11

    const filtros = {}; // colId -> texto/valor del filtro
    let orden = { col: null, dir: 1 };
    let sucio = false;

    const panelTabla = raiz.querySelector("[data-cm-panel-tabla]");
    const panelKanban = raiz.querySelector("[data-cm-panel-kanban]");
    const sinColumnas = raiz.querySelector("[data-cm-sin-columnas]");
    const estadoTexto = raiz.querySelector("[data-cm-estado]");
    const botonGuardar = raiz.querySelector("[data-cm-guardar]");
    const selectorKanban = raiz.querySelector("[data-cm-kanban-selector]");
    const selectKanban = raiz.querySelector("[data-cm-kanban-columna]");
    const botonesVista = raiz.querySelectorAll("[data-cm-vista]");
    const panelCalendario = raiz.querySelector("[data-cm-panel-calendario]");
    const panelGaleria = raiz.querySelector("[data-cm-panel-galeria]");
    const selectorFecha = raiz.querySelector("[data-cm-fecha-selector]");
    const selectFecha = raiz.querySelector("[data-cm-fecha-columna]");
    const selectorTitulo = raiz.querySelector("[data-cm-titulo-selector]");
    const selectTitulo = raiz.querySelector("[data-cm-titulo-columna]");
    const detalleVisibles = raiz.querySelector("[data-cm-visibles]");
    const listaVisibles = raiz.querySelector("[data-cm-visibles-lista]");
    const avisoOrigen = raiz.querySelector("[data-cm-origen]");

    let cuerpoTabla = null;
    let filaArrastrada = null; // id de la fila que se arrastra en la vista tabla

    // ---------- Estado ----------

    function marcarSucio() {
      sucio = true;
      estadoTexto.textContent = "Cambios sin guardar";
      estadoTexto.classList.add("is-sucio");
    }

    function nuevoId(prefijo, existentes) {
      let n = existentes.length + 1;
      const usados = new Set(existentes.map((x) => x.id));
      while (usados.has(prefijo + n)) n += 1;
      return prefijo + n;
    }

    const columnaPorId = (id) => estado.columnas.find((c) => c.id === id);
    const columnasSeleccion = () => estado.columnas.filter((c) => c.tipo === "seleccion");
    const valorCelda = (fila, col) => (fila.celdas && fila.celdas[col.id]) || "";

    function fijarCelda(fila, col, valor) {
      fila.celdas = fila.celdas || {};
      fila.celdas[col.id] = valor;
      marcarSucio();
    }

    /** Garantiza que la columna de Kanban sea una selección existente. */
    function validarKanban() {
      const actual = columnaPorId(estado.columna_kanban);
      if (!actual || actual.tipo !== "seleccion") {
        const primera = columnasSeleccion()[0];
        estado.columna_kanban = primera ? primera.id : null;
      }
      if (estado.vista === "kanban" && !estado.columna_kanban) estado.vista = "tabla";
    }

    // ---------- Controles de celda ----------

    function crearControl(col, fila, alCambiar) {
      const valor = valorCelda(fila, col);
      let control;

      if (col.tipo === "seleccion") {
        control = el("select", { class: "select cm-celda" });
        control.appendChild(el("option", { value: "", text: "—" }));
        col.opciones.forEach((op) => control.appendChild(el("option", { value: op, text: op })));
        control.value = col.opciones.includes(valor) ? valor : "";
        control.addEventListener("change", () => {
          fijarCelda(fila, col, control.value);
          if (alCambiar) alCambiar();
        });
      } else {
        const tipoInput = col.tipo === "numero" ? "number" : col.tipo === "fecha" ? "date" : "text";
        control = el("input", { class: "input cm-celda", type: tipoInput, maxlength: col.tipo === "texto" ? "2000" : null, step: col.tipo === "numero" ? "any" : null });
        control.value = valor;
        control.addEventListener("input", () => {
          fijarCelda(fila, col, control.value);
          if (alCambiar) alCambiar();
        });
      }
      control.setAttribute("aria-label", col.nombre);
      return control;
    }

    // ---------- Filtro y orden ----------

    function coincideFiltro(fila, col) {
      const filtro = filtros[col.id];
      if (!filtro) return true;
      const valor = valorCelda(fila, col);

      if (col.tipo === "seleccion") {
        return filtro === "__vacio__" ? valor === "" : valor === filtro;
      }
      if (col.tipo === "numero") {
        const m = filtro.trim().match(/^(>=|<=|>|<|=)?\s*(-?\d+(?:[.,]\d+)?)$/);
        if (m) {
          if (valor === "") return false;
          const n = Number(valor);
          const ref = Number(m[2].replace(",", "."));
          switch (m[1]) {
            case ">": return n > ref;
            case ">=": return n >= ref;
            case "<": return n < ref;
            case "<=": return n <= ref;
            default: return n === ref;
          }
        }
      }
      return valor.toLowerCase().includes(filtro.trim().toLowerCase());
    }

    function filasVisibles() {
      let filas = estado.filas.filter((f) => estado.columnas.every((c) => coincideFiltro(f, c)));
      const col = columnaPorId(orden.col);
      if (col) {
        const clave = (f) => {
          const v = valorCelda(f, col);
          if (col.tipo === "numero") return v === "" ? null : Number(v);
          if (col.tipo === "seleccion") return v === "" ? null : col.opciones.indexOf(v);
          return v === "" ? null : v.toLowerCase();
        };
        filas = filas.slice().sort((a, b) => {
          const ka = clave(a);
          const kb = clave(b);
          if (ka === null && kb === null) return 0;
          if (ka === null) return 1; // vacíos siempre al final
          if (kb === null) return -1;
          const cmp = typeof ka === "number" ? ka - kb : String(ka).localeCompare(String(kb), "es");
          return cmp * orden.dir;
        });
      }
      return filas;
    }

    // ---------- Vista tabla ----------

    function renderTabla() {
      panelTabla.replaceChildren();
      if (!estado.columnas.length) return;

      const encabezado = el("tr");
      estado.columnas.forEach((col) => {
        const marca = orden.col === col.id ? (orden.dir === 1 ? " ▲" : " ▼") : "";
        const ordenar = el("button", { type: "button", class: "cm-th__orden", title: "Ordenar por esta columna", text: col.nombre + marca });
        ordenar.addEventListener("click", () => {
          if (orden.col === col.id && orden.dir === -1) orden = { col: null, dir: 1 };
          else if (orden.col === col.id) orden = { col: col.id, dir: -1 };
          else orden = { col: col.id, dir: 1 };
          renderTabla();
        });
        const editar = el("button", { type: "button", class: "cm-th__editar", title: "Editar columna", "aria-label": `Editar columna ${col.nombre}` }, [el("i", { class: "fa-solid fa-gear" })]);
        editar.addEventListener("click", () => abrirColumna(col));
        encabezado.appendChild(el("th", {}, [el("div", { class: "cm-th" }, [ordenar, editar])]));
      });
      encabezado.appendChild(el("th", { class: "cm-col-acciones", "aria-label": "Acciones" }));
      encabezado.insertBefore(el("th", { class: "cm-col-asa", "aria-label": "Reordenar" }), encabezado.firstChild);

      const filaFiltros = el("tr", { class: "cm-filtros" });
      estado.columnas.forEach((col) => {
        let control;
        if (col.tipo === "seleccion") {
          control = el("select", { class: "select cm-celda", "aria-label": `Filtrar ${col.nombre}` });
          control.appendChild(el("option", { value: "", text: "Todos" }));
          control.appendChild(el("option", { value: "__vacio__", text: "(vacío)" }));
          col.opciones.forEach((op) => control.appendChild(el("option", { value: op, text: op })));
          control.value = filtros[col.id] || "";
          control.addEventListener("change", () => {
            filtros[col.id] = control.value;
            renderCuerpo();
          });
        } else {
          control = el("input", {
            class: "input cm-celda",
            type: "search",
            placeholder: col.tipo === "numero" ? "Ej. >=10" : "Filtrar",
            "aria-label": `Filtrar ${col.nombre}`,
          });
          control.value = filtros[col.id] || "";
          control.addEventListener("input", () => {
            filtros[col.id] = control.value;
            renderCuerpo();
          });
        }
        filaFiltros.appendChild(el("th", {}, [control]));
      });
      filaFiltros.appendChild(el("th"));
      filaFiltros.insertBefore(el("th"), filaFiltros.firstChild);

      cuerpoTabla = el("tbody");
      const tabla = el("table", { class: "table cm-grid" }, [el("thead", {}, [encabezado, filaFiltros]), cuerpoTabla]);
      panelTabla.appendChild(el("div", { class: "table-wrap cm-grid-wrap" }, [tabla]));
      renderCuerpo();
    }

    function renderCuerpo() {
      if (!cuerpoTabla) return;
      cuerpoTabla.replaceChildren();
      const filas = filasVisibles();

      if (!filas.length) {
        const mensaje = estado.filas.length ? "Ninguna fila coincide con los filtros." : "Sin filas todavía. Usa «+ Fila» para agregar la primera.";
        cuerpoTabla.appendChild(el("tr", {}, [el("td", { class: "table__empty", colspan: String(estado.columnas.length + 2), text: mensaje })]));
        return;
      }

      filas.forEach((fila) => {
        const tr = el("tr", { "data-fila-id": fila.id });
        tr.appendChild(el("td", { class: "cm-col-asa" }, [crearAsa(fila, tr)]));
        estado.columnas.forEach((col) => tr.appendChild(el("td", {}, [crearControl(col, fila)])));
        const borrar = el("button", { type: "button", class: "btn btn--ghost btn--sm btn--icon", title: "Eliminar fila", "aria-label": "Eliminar fila" }, [el("i", { class: "fa-solid fa-trash" })]);
        borrar.addEventListener("click", () => {
          estado.filas = estado.filas.filter((f) => f.id !== fila.id);
          marcarSucio();
          renderCuerpo();
        });
        tr.appendChild(el("td", { class: "cm-col-acciones" }, [borrar]));
        cuerpoTabla.appendChild(tr);
      });
    }

    // ---------- Reordenar filas (arrastrar) ----------

    /** Mueve `fila` justo antes o después de `destino` dentro del orden COMPLETO (aunque haya filtros). */
    function moverFila(filaId, destinoId, despues) {
      if (filaId === destinoId) return false;
      const origen = estado.filas.findIndex((f) => f.id === filaId);
      if (origen < 0) return false;
      const [fila] = estado.filas.splice(origen, 1);
      let pos = estado.filas.findIndex((f) => f.id === destinoId);
      if (pos < 0) {
        estado.filas.splice(origen, 0, fila);
        return false;
      }
      if (despues) pos += 1;
      estado.filas.splice(pos, 0, fila);
      marcarSucio();
      return true;
    }

    /**
     * Asa de arrastre. Solo el asa es `draggable` (no la fila): si lo fuera, no se podría
     * seleccionar texto dentro de los campos. Con un orden por columna activo el orden
     * manual no se ve, así que se desactiva. Alt+↑/↓ hace lo mismo con teclado.
     */
    function crearAsa(fila, tr) {
      const bloqueado = Boolean(orden.col);
      const asa = el("button", {
        type: "button",
        class: "cm-asa",
        title: bloqueado ? "Quita el orden por columna para reordenar filas" : "Arrastra para reordenar (o Alt + flechas)",
        "aria-label": "Reordenar fila",
        draggable: bloqueado ? null : "true",
        disabled: bloqueado ? "" : null,
      }, [el("i", { class: "fa-solid fa-grip-vertical" })]);

      asa.addEventListener("dragstart", (e) => {
        e.dataTransfer.setData("text/plain", fila.id);
        e.dataTransfer.effectAllowed = "move";
        e.dataTransfer.setDragImage(tr, 0, 0);
        filaArrastrada = fila.id;
        tr.classList.add("is-arrastrando");
      });
      asa.addEventListener("dragend", () => {
        filaArrastrada = null;
        tr.classList.remove("is-arrastrando");
        cuerpoTabla.querySelectorAll(".is-destino-antes, .is-destino-despues").forEach((n) => n.classList.remove("is-destino-antes", "is-destino-despues"));
      });
      asa.addEventListener("keydown", (e) => {
        if (!e.altKey || (e.key !== "ArrowUp" && e.key !== "ArrowDown")) return;
        e.preventDefault();
        const visibles = filasVisibles();
        const i = visibles.findIndex((f) => f.id === fila.id);
        const vecina = visibles[i + (e.key === "ArrowUp" ? -1 : 1)];
        if (vecina && moverFila(fila.id, vecina.id, e.key === "ArrowDown")) {
          renderCuerpo();
          const nueva = cuerpoTabla.querySelector(`tr[data-fila-id="${fila.id}"] .cm-asa`);
          if (nueva) nueva.focus();
        }
      });

      tr.addEventListener("dragover", (e) => {
        if (!filaArrastrada || filaArrastrada === fila.id) return;
        e.preventDefault();
        e.dataTransfer.dropEffect = "move";
        const caja = tr.getBoundingClientRect();
        const despues = e.clientY > caja.top + caja.height / 2;
        tr.classList.toggle("is-destino-antes", !despues);
        tr.classList.toggle("is-destino-despues", despues);
      });
      tr.addEventListener("dragleave", () => tr.classList.remove("is-destino-antes", "is-destino-despues"));
      tr.addEventListener("drop", (e) => {
        if (!filaArrastrada) return;
        e.preventDefault();
        const caja = tr.getBoundingClientRect();
        const despues = e.clientY > caja.top + caja.height / 2;
        const id = filaArrastrada;
        filaArrastrada = null;
        if (moverFila(id, fila.id, despues)) renderCuerpo();
      });

      return asa;
    }

    // ---------- Vista Kanban ----------

    /** Columna cuyo valor sirve de título de la tarjeta: la primera que no es la agrupadora (mejor si no es una selección). */
    function columnaTitulo(colAgrupa) {
      return estado.columnas.find((c) => c.id !== colAgrupa.id && c.tipo !== "seleccion") || estado.columnas.find((c) => c.id !== colAgrupa.id) || null;
    }

    function renderKanban() {
      panelKanban.replaceChildren();
      const col = columnaPorId(estado.columna_kanban);

      if (!col) {
        panelKanban.appendChild(
          el("div", { class: "card empty-state" }, [
            el("div", { class: "empty-state__icon" }, [el("i", { class: "fa-solid fa-table-columns" })]),
            el("p", { class: "empty-state__text", text: "Para usar el Kanban agrega una columna de tipo Selección: sus opciones serán los carriles." }),
          ])
        );
        return;
      }

      const colTitulo = columnaTitulo(col);
      const carriles = col.opciones.map((op) => ({ valor: op, etiqueta: op })).concat([{ valor: "", etiqueta: "Sin valor" }]);
      const tablero = el("div", { class: "cm-kanban" });

      carriles.forEach((carril) => {
        const filas = estado.filas.filter((f) => (col.opciones.includes(valorCelda(f, col)) ? valorCelda(f, col) : "") === carril.valor);
        const cuerpo = el("div", { class: "cm-kanban__cuerpo" });

        filas.forEach((fila) => {
          const tarjeta = el("div", { class: "cm-tarjeta", draggable: "true", tabindex: "0", role: "button", "data-fila-id": fila.id }, [
            el("div", { class: "cm-tarjeta__titulo", text: (colTitulo && valorCelda(fila, colTitulo)) || "(sin título)" }),
          ]);
          estado.columnas
            .filter((c) => c.id !== col.id && c !== colTitulo && valorCelda(fila, c))
            .slice(0, 3)
            .forEach((c) => tarjeta.appendChild(el("div", { class: "cm-tarjeta__dato", text: `${c.nombre}: ${valorCelda(fila, c)}` })));

          tarjeta.addEventListener("dragstart", (e) => {
            e.dataTransfer.setData("text/plain", fila.id);
            e.dataTransfer.effectAllowed = "move";
            tarjeta.classList.add("is-arrastrando");
          });
          tarjeta.addEventListener("dragend", () => tarjeta.classList.remove("is-arrastrando"));
          tarjeta.addEventListener("click", () => abrirFila(fila));
          tarjeta.addEventListener("keydown", (e) => {
            if (e.key === "Enter" || e.key === " ") {
              e.preventDefault();
              abrirFila(fila);
            }
          });
          cuerpo.appendChild(tarjeta);
        });

        const agregar = el("button", { type: "button", class: "cm-kanban__agregar" }, [el("i", { class: "fa-solid fa-plus" }), " Agregar"]);
        agregar.addEventListener("click", () => {
          const fila = { id: nuevoId("f", estado.filas), celdas: carril.valor ? { [col.id]: carril.valor } : {} };
          estado.filas.push(fila);
          marcarSucio();
          renderKanban();
          abrirFila(fila);
        });

        const lane = el("section", { class: "cm-kanban__carril", "data-valor": carril.valor }, [
          el("header", { class: "cm-kanban__cabecera" }, [
            el("span", { class: "cm-kanban__nombre", text: carril.etiqueta }),
            el("span", { class: "cm-kanban__cuenta", text: String(filas.length) }),
          ]),
          cuerpo,
          agregar,
        ]);

        lane.addEventListener("dragover", (e) => {
          e.preventDefault();
          e.dataTransfer.dropEffect = "move";
          lane.classList.add("is-destino");
        });
        lane.addEventListener("dragleave", (e) => {
          if (!lane.contains(e.relatedTarget)) lane.classList.remove("is-destino");
        });
        lane.addEventListener("drop", (e) => {
          e.preventDefault();
          lane.classList.remove("is-destino");
          const fila = estado.filas.find((f) => f.id === e.dataTransfer.getData("text/plain"));
          if (!fila || valorCelda(fila, col) === carril.valor) return;
          fijarCelda(fila, col, carril.valor);
          renderKanban();
        });

        tablero.appendChild(lane);
      });

      panelKanban.appendChild(tablero);
    }

    // ---------- Vistas ----------

    // ---------- Vista Calendario ----------

    const MESES = ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];
    const DIAS_SEMANA = ["Lun", "Mar", "Mié", "Jue", "Vie", "Sáb", "Dom"];
    const dos = (n) => String(n).padStart(2, "0");
    const claveDia = (a, m, d) => `${a}-${dos(m + 1)}-${dos(d)}`;

    function fechaIsoValida(v) {
      if (!/^\d{4}-\d{2}-\d{2}$/.test(v)) return false;
      const d = new Date(`${v}T00:00:00`);
      return !Number.isNaN(d.getTime()) && claveDia(d.getFullYear(), d.getMonth(), d.getDate()) === v;
    }

    const columnasFecha = () => estado.columnas.filter((c) => c.tipo === "fecha");

    /** Garantiza que la columna del calendario sea una fecha existente. */
    function validarFecha() {
      const actual = columnaPorId(estado.columna_fecha);
      if (!actual || actual.tipo !== "fecha") {
        const primera = columnasFecha()[0];
        estado.columna_fecha = primera ? primera.id : null;
      }
    }

    /** Columna que da el título a cada tarjeta/evento: la elegida, o la primera de texto, o la primera que haya. */
    function columnaTituloPref() {
      return (
        columnaPorId(estado.columna_titulo) ||
        estado.columnas.find((c) => c.tipo === "texto") ||
        estado.columnas.find((c) => c.tipo !== "fecha") ||
        estado.columnas[0] ||
        null
      );
    }

    const tituloDeFila = (fila, colTitulo) => (colTitulo && valorCelda(fila, colTitulo)) || "(sin título)";

    function estadoVacio(icono, texto) {
      return el("div", { class: "card empty-state" }, [
        el("div", { class: "empty-state__icon" }, [el("i", { class: `fa-solid ${icono}` })]),
        el("p", { class: "empty-state__text", text: texto }),
      ]);
    }

    function nuevaFilaConFecha(colFecha, dia) {
      if (estado.filas.length >= 2000) {
        toast("Una tabla admite como máximo 2000 filas.", "error");
        return;
      }
      const fila = { id: nuevoId("f", estado.filas), celdas: { [colFecha.id]: dia } };
      estado.filas.push(fila);
      marcarSucio();
      renderCalendario();
      abrirFila(fila);
    }

    function renderCalendario() {
      panelCalendario.replaceChildren();
      const colFecha = columnaPorId(estado.columna_fecha);

      if (!colFecha) {
        panelCalendario.appendChild(estadoVacio("fa-calendar-days", "Para usar el calendario agrega una columna de tipo Fecha: cada fila aparecerá en su día."));
        return;
      }

      const colTitulo = columnaTituloPref();
      const porDia = new Map();
      const sinFecha = [];
      estado.filas.forEach((fila) => {
        const v = valorCelda(fila, colFecha);
        if (fechaIsoValida(v)) {
          if (!porDia.has(v)) porDia.set(v, []);
          porDia.get(v).push(fila);
        } else {
          sinFecha.push(fila);
        }
      });

      const { a, m } = mesVisible;
      const mover = (delta) => {
        const f = new Date(a, m + delta, 1);
        mesVisible = { a: f.getFullYear(), m: f.getMonth() };
        renderCalendario();
      };
      const botonNav = (icono, etiqueta, accion) => {
        const b = el("button", { type: "button", class: "btn btn--secondary btn--sm btn--icon", title: etiqueta, "aria-label": etiqueta }, [el("i", { class: `fa-solid ${icono}` })]);
        b.addEventListener("click", accion);
        return b;
      };
      const botonHoy = el("button", { type: "button", class: "btn btn--secondary btn--sm", text: "Hoy" });
      botonHoy.addEventListener("click", () => {
        const ahora = new Date();
        mesVisible = { a: ahora.getFullYear(), m: ahora.getMonth() };
        renderCalendario();
      });

      panelCalendario.appendChild(
        el("div", { class: "cm-cal__nav" }, [
          botonNav("fa-chevron-left", "Mes anterior", () => mover(-1)),
          el("strong", { class: "cm-cal__mes", text: `${MESES[m]} ${a}` }),
          botonNav("fa-chevron-right", "Mes siguiente", () => mover(1)),
          botonHoy,
        ])
      );

      const rejilla = el("div", { class: "cm-cal" });
      DIAS_SEMANA.forEach((d) => rejilla.appendChild(el("div", { class: "cm-cal__semana", text: d })));

      const desfase = (new Date(a, m, 1).getDay() + 6) % 7; // semana desde el lunes
      for (let i = 0; i < desfase; i += 1) rejilla.appendChild(el("div", { class: "cm-cal__dia cm-cal__dia--fuera" }));

      const claveHoy = claveDia(hoy.getFullYear(), hoy.getMonth(), hoy.getDate());
      const totalDias = new Date(a, m + 1, 0).getDate();

      for (let d = 1; d <= totalDias; d += 1) {
        const clave = claveDia(a, m, d);
        const filas = porDia.get(clave) || [];
        const agregar = el("button", { type: "button", class: "cm-cal__agregar", title: `Agregar fila el ${d} de ${MESES[m].toLowerCase()}`, "aria-label": `Agregar fila el ${clave}` }, [el("i", { class: "fa-solid fa-plus" })]);
        agregar.addEventListener("click", () => nuevaFilaConFecha(colFecha, clave));

        const celda = el("div", { class: `cm-cal__dia${clave === claveHoy ? " is-hoy" : ""}`, "data-dia": clave }, [
          el("div", { class: "cm-cal__cabecera" }, [el("span", { class: "cm-cal__numero", text: String(d) }), agregar]),
        ]);

        filas.forEach((fila) => {
          const evento = el("button", { type: "button", class: "cm-cal__evento", draggable: "true", title: tituloDeFila(fila, colTitulo), text: tituloDeFila(fila, colTitulo) });
          evento.addEventListener("click", () => abrirFila(fila));
          evento.addEventListener("dragstart", (e) => {
            e.dataTransfer.setData("text/plain", fila.id);
            e.dataTransfer.effectAllowed = "move";
          });
          celda.appendChild(evento);
        });

        // Soltar un evento en otro día cambia su fecha.
        celda.addEventListener("dragover", (e) => {
          e.preventDefault();
          e.dataTransfer.dropEffect = "move";
          celda.classList.add("is-destino");
        });
        celda.addEventListener("dragleave", (e) => {
          if (!celda.contains(e.relatedTarget)) celda.classList.remove("is-destino");
        });
        celda.addEventListener("drop", (e) => {
          e.preventDefault();
          celda.classList.remove("is-destino");
          const fila = estado.filas.find((f) => f.id === e.dataTransfer.getData("text/plain"));
          if (!fila || valorCelda(fila, colFecha) === clave) return;
          fijarCelda(fila, colFecha, clave);
          renderCalendario();
        });

        rejilla.appendChild(celda);
      }
      panelCalendario.appendChild(rejilla);

      if (sinFecha.length) {
        const lista = el("div", { class: "cm-cal__sin-fecha-lista" });
        sinFecha.forEach((fila) => {
          const chip = el("button", { type: "button", class: "cm-cal__evento", text: tituloDeFila(fila, colTitulo) });
          chip.addEventListener("click", () => abrirFila(fila));
          lista.appendChild(chip);
        });
        panelCalendario.appendChild(
          el("div", { class: "cm-cal__sin-fecha" }, [
            el("strong", { text: `Sin fecha en «${colFecha.nombre}» (${sinFecha.length})` }),
            el("span", { class: "field__hint", text: "Ábrelas y asígnales una fecha para verlas en el calendario." }),
            lista,
          ])
        );
      }
    }

    // ---------- Vista Galería ----------

    function columnasGaleria() {
      const ids = estado.columnas_visibles || [];
      return ids.length ? estado.columnas.filter((c) => ids.includes(c.id)) : estado.columnas;
    }

    function renderGaleria() {
      panelGaleria.replaceChildren();

      if (!estado.filas.length) {
        panelGaleria.appendChild(estadoVacio("fa-border-all", "Sin filas todavía. Usa «+ Fila» para agregar la primera."));
        return;
      }

      const colTitulo = columnaTituloPref();
      const visibles = columnasGaleria().filter((c) => c !== colTitulo);
      const rejilla = el("div", { class: "cm-galeria" });

      estado.filas.forEach((fila) => {
        const tarjeta = el("div", { class: "cm-ficha", tabindex: "0", role: "button", "data-fila-id": fila.id }, [
          el("div", { class: "cm-ficha__titulo", text: tituloDeFila(fila, colTitulo) }),
        ]);
        visibles.forEach((c) => {
          const v = valorCelda(fila, c);
          if (!v) return;
          tarjeta.appendChild(el("div", { class: "cm-ficha__dato" }, [el("span", { class: "cm-ficha__etiqueta", text: c.nombre }), el("span", { text: v })]));
        });
        tarjeta.addEventListener("click", () => abrirFila(fila));
        tarjeta.addEventListener("keydown", (e) => {
          if (e.key === "Enter" || e.key === " ") {
            e.preventDefault();
            abrirFila(fila);
          }
        });
        rejilla.appendChild(tarjeta);
      });

      panelGaleria.appendChild(rejilla);
    }

    /** Casillas de «Columnas visibles»: vacío en el estado = todas. */
    function renderVisibles() {
      listaVisibles.replaceChildren();
      const ids = estado.columnas_visibles || [];
      estado.columnas.forEach((c) => {
        const caja = el("input", { type: "checkbox" });
        caja.checked = !ids.length || ids.includes(c.id);
        caja.addEventListener("change", () => {
          const marcadas = Array.from(listaVisibles.querySelectorAll("input")).map((n, i) => (n.checked ? estado.columnas[i].id : null)).filter(Boolean);
          estado.columnas_visibles = marcadas.length === estado.columnas.length || !marcadas.length ? [] : marcadas;
          if (!marcadas.length) caja.checked = true; // al menos una: sin columnas la tarjeta no diría nada
          marcarSucio();
          renderGaleria();
        });
        listaVisibles.appendChild(el("label", { class: "cm-radio" }, [caja, ` ${c.nombre}`]));
      });
    }

    // ---------- Origen («foto» de una fuente real) ----------

    function formatoFechaHora(s) {
      const m = /^(\d{4})-(\d{2})-(\d{2}) (\d{2}:\d{2})$/.exec(s || "");
      return m ? `${m[3]}/${m[2]}/${m[1]} ${m[4]}` : s || "";
    }

    function renderOrigen() {
      const o = estado.origen;
      avisoOrigen.hidden = !o;
      if (!o) return;
      const nombre = fuentesEtiquetas[o.clave] || o.clave;
      avisoOrigen.querySelector("[data-cm-origen-texto]").textContent =
        `Foto de los datos del ${formatoFechaHora(o.fecha)} (${nombre}). No se actualiza sola: usa «Llenar desde...» para tomar una foto nueva.`;
    }

    // ---------- Vistas ----------

    function llenarSelector(select, columnas, valor, vacio) {
      select.replaceChildren();
      if (vacio) select.appendChild(el("option", { value: "", text: vacio }));
      columnas.forEach((c) => select.appendChild(el("option", { value: c.id, text: c.nombre })));
      select.value = valor || "";
    }

    function renderVista() {
      validarKanban();
      validarFecha();
      sinColumnas.hidden = estado.columnas.length > 0;
      botonesVista.forEach((b) => b.classList.toggle("is-activa", b.dataset.cmVista === estado.vista));

      const v = estado.vista;
      panelTabla.hidden = v !== "tabla";
      panelKanban.hidden = v !== "kanban";
      panelCalendario.hidden = v !== "calendario";
      panelGaleria.hidden = v !== "galeria";
      selectorKanban.hidden = v !== "kanban" || columnasSeleccion().length < 2;
      selectorFecha.hidden = v !== "calendario" || columnasFecha().length < 2;
      selectorTitulo.hidden = (v !== "calendario" && v !== "galeria") || estado.columnas.length < 2;
      detalleVisibles.hidden = v !== "galeria" || !estado.columnas.length;

      llenarSelector(selectKanban, columnasSeleccion(), estado.columna_kanban);
      llenarSelector(selectFecha, columnasFecha(), estado.columna_fecha);
      llenarSelector(selectTitulo, estado.columnas, estado.columna_titulo, "Automático");
      renderOrigen();

      if (v === "kanban") renderKanban();
      else if (v === "calendario") renderCalendario();
      else if (v === "galeria") {
        renderVisibles();
        renderGaleria();
      } else renderTabla();
    }
    botonesVista.forEach((boton) => {
      boton.addEventListener("click", () => {
        estado.vista = boton.dataset.cmVista;
        renderVista();
      });
    });
    selectKanban.addEventListener("change", () => {
      estado.columna_kanban = selectKanban.value;
      renderVista();
    });
    selectFecha.addEventListener("change", () => {
      estado.columna_fecha = selectFecha.value || null;
      marcarSucio();
      renderVista();
    });
    selectTitulo.addEventListener("change", () => {
      estado.columna_titulo = selectTitulo.value || null;
      marcarSucio();
      renderVista();
    });

    // ---------- Columnas ----------

    const modalColumna = document.getElementById("cmColumnaModal");
    const formColumna = modalColumna.querySelector("[data-cm-columna-form]");
    const inputColNombre = document.getElementById("cmColumnaNombre");
    const selectColTipo = document.getElementById("cmColumnaTipo");
    const areaColOpciones = document.getElementById("cmColumnaOpciones");
    const campoOpciones = modalColumna.querySelector("[data-cm-opciones-campo]");
    const errorColumna = modalColumna.querySelector("[data-cm-columna-error]");
    const botonColBorrar = modalColumna.querySelector("[data-cm-columna-borrar]");
    let columnaEditada = null;

    const avisoTipo = modalColumna.querySelector("[data-cm-tipo-aviso]");

    /** En una columna existente que no era selección, las opciones salen solas de los valores que ya tiene. */
    function actualizarCampoOpciones() {
      campoOpciones.hidden = selectColTipo.value !== "seleccion" || Boolean(columnaEditada && columnaEditada.tipo !== "seleccion");
    }
    selectColTipo.addEventListener("change", () => {
      actualizarCampoOpciones();
      avisoTipo.hidden = !columnaEditada || selectColTipo.value === columnaEditada.tipo;
    });

    function abrirColumna(col) {
      columnaEditada = col || null;
      modalColumna.querySelector("[data-cm-columna-titulo]").textContent = col ? "Editar columna" : "Nueva columna";
      inputColNombre.value = col ? col.nombre : "";
      selectColTipo.value = col ? col.tipo : "texto";
      areaColOpciones.value = col ? col.opciones.join("\n") : "";
      avisoTipo.hidden = true;
      actualizarCampoOpciones();
      botonColBorrar.hidden = !col;
      mostrarError(errorColumna, "");
      window.AgencyOS.openModal("cmColumnaModal");
      inputColNombre.focus();
    }

    raiz.querySelector("[data-cm-columna-nueva]").addEventListener("click", () => {
      if (estado.columnas.length >= 30) {
        toast("Una tabla admite como máximo 30 columnas.", "error");
        return;
      }
      abrirColumna(null);
    });

    formColumna.addEventListener("submit", async (e) => {
      e.preventDefault();
      const nombre = inputColNombre.value.trim();
      if (!nombre) {
        mostrarError(errorColumna, "Escribe un nombre para la columna.");
        return;
      }
      const tipo = selectColTipo.value;
      const cambiaTipo = Boolean(columnaEditada) && tipo !== columnaEditada.tipo;

      // Cambiar el tipo de una columna ya creada: el servidor convierte solo si TODAS las celdas encajan.
      if (cambiaTipo) {
        const id = columnaEditada.id;
        const res = await api(raiz.dataset.urlConvertir, "POST", { ...cargaTabla(), columna: id, tipo });
        if (!res.ok) {
          mostrarError(errorColumna, mensajeError(res));
          return;
        }
        estado = res.datos.datos;
        estado.columnas_visibles = estado.columnas_visibles || [];
        const convertida = columnaPorId(id);
        if (convertida) convertida.nombre = nombre;
        delete filtros[id];
        if (orden.col === id) orden = { col: null, dir: 1 };
        marcarSucio();
        window.AgencyOS.closeModal("cmColumnaModal");
        renderVista();
        toast("Columna convertida. Recuerda guardar la tabla.");
        return;
      }

      const opciones = tipo === "seleccion"
        ? Array.from(new Set(areaColOpciones.value.split("\n").map((o) => o.trim().slice(0, 80)).filter(Boolean)))
        : [];
      if (opciones.length > 40) {
        mostrarError(errorColumna, "Máximo 40 opciones por columna.");
        return;
      }
      if (tipo === "seleccion" && !opciones.length) {
        mostrarError(errorColumna, "Escribe al menos una opción.");
        return;
      }

      if (columnaEditada) {
        columnaEditada.nombre = nombre;
        if (columnaEditada.tipo === "seleccion") {
          columnaEditada.opciones = opciones;
          // Una opción eliminada deja sin valor las celdas que la usaban: el servidor rechazaría un valor huérfano.
          estado.filas.forEach((f) => {
            if (f.celdas && f.celdas[columnaEditada.id] && !opciones.includes(f.celdas[columnaEditada.id])) f.celdas[columnaEditada.id] = "";
          });
        }
      } else {
        estado.columnas.push({ id: nuevoId("c", estado.columnas), nombre, tipo, opciones });
      }

      marcarSucio();
      window.AgencyOS.closeModal("cmColumnaModal");
      renderVista();
    });

    botonColBorrar.addEventListener("click", () => {
      if (!columnaEditada) return;
      if (!window.confirm(`¿Eliminar la columna «${columnaEditada.nombre}»? Se perderán sus valores en todas las filas.`)) return;
      const id = columnaEditada.id;
      estado.columnas = estado.columnas.filter((c) => c.id !== id);
      estado.filas.forEach((f) => {
        if (f.celdas) delete f.celdas[id];
      });
      delete filtros[id];
      if (orden.col === id) orden = { col: null, dir: 1 };
      marcarSucio();
      window.AgencyOS.closeModal("cmColumnaModal");
      renderVista();
    });

    // ---------- Filas ----------

    raiz.querySelector("[data-cm-fila-nueva]").addEventListener("click", () => {
      if (!estado.columnas.length) {
        toast("Agrega primero una columna.", "warning");
        return;
      }
      if (estado.filas.length >= 2000) {
        toast("Una tabla admite como máximo 2000 filas.", "error");
        return;
      }
      const fila = { id: nuevoId("f", estado.filas), celdas: {} };
      estado.filas.push(fila);
      marcarSucio();
      if (estado.vista === "kanban") {
        renderKanban();
        abrirFila(fila);
        return;
      }
      // Con filtros activos la fila nueva podría quedar oculta: se limpian para que se vea.
      Object.keys(filtros).forEach((k) => delete filtros[k]);
      renderTabla();
      const campo = cuerpoTabla.querySelector(`tr[data-fila-id="${fila.id}"] input, tr[data-fila-id="${fila.id}"] select`);
      if (campo) {
        campo.scrollIntoView({ block: "nearest" });
        campo.focus();
      }
    });

    const modalFila = document.getElementById("cmFilaModal");
    const camposFila = modalFila.querySelector("[data-cm-fila-campos]");
    const formFila = modalFila.querySelector("[data-cm-fila-form]");
    let filaEditada = null;

    function abrirFila(fila) {
      filaEditada = fila;
      camposFila.replaceChildren();
      estado.columnas.forEach((col, i) => {
        const control = crearControl(col, fila);
        control.id = `cmFilaCampo${i}`;
        camposFila.appendChild(el("div", { class: "field", style: "margin-bottom: var(--space-3);" }, [el("label", { class: "field__label", for: control.id, text: col.nombre }), control]));
      });
      window.AgencyOS.openModal("cmFilaModal");
      const primero = camposFila.querySelector("input, select");
      if (primero) primero.focus();
    }

    formFila.addEventListener("submit", (e) => {
      e.preventDefault();
      window.AgencyOS.closeModal("cmFilaModal");
      renderVista();
    });
    modalFila.querySelector("[data-cm-fila-borrar]").addEventListener("click", () => {
      if (!filaEditada) return;
      estado.filas = estado.filas.filter((f) => f.id !== filaEditada.id);
      marcarSucio();
      window.AgencyOS.closeModal("cmFilaModal");
      renderVista();
    });
    // Cerrar el modal por la X o fuera también refleja lo editado en el tablero.
    new MutationObserver(() => {
      if (modalFila.hidden && filaEditada) {
        filaEditada = null;
        renderVista();
      }
    }).observe(modalFila, { attributes: true, attributeFilter: ["hidden"] });

    // ---------- Guardar ----------

    /** Lo que viaja al servidor: la tabla completa (el orden de `filas` es el orden persistente). */
    function cargaTabla() {
      return {
        columnas: estado.columnas,
        filas: estado.filas,
        vista: estado.vista,
        columna_kanban: estado.columna_kanban,
        columna_fecha: estado.columna_fecha || null,
        columna_titulo: estado.columna_titulo || null,
        columnas_visibles: estado.columnas_visibles || [],
        origen: estado.origen || null,
      };
    }

    async function guardar() {
      if (botonGuardar.disabled) return true;
      botonGuardar.disabled = true;
      estadoTexto.textContent = "Guardando...";
      const res = await api(raiz.dataset.urlGuardar, "PUT", cargaTabla());
      botonGuardar.disabled = false;

      if (!res.ok) {
        estadoTexto.textContent = "No se pudo guardar";
        toast(mensajeError(res), "error");
        return false;
      }
      sucio = false;
      estadoTexto.classList.remove("is-sucio");
      estadoTexto.textContent = `Guardado a las ${res.datos.guardado}`;
      toast("Tabla guardada");
      return true;
    }

    botonGuardar.addEventListener("click", guardar);
    document.addEventListener("keydown", (e) => {
      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === "s") {
        e.preventDefault();
        guardar();
      }
    });
    window.addEventListener("beforeunload", (e) => {
      if (sucio) {
        e.preventDefault();
        e.returnValue = "";
      }
    });

    // ---------- CSV ----------

    raiz.querySelector("[data-cm-exportar]").addEventListener("click", (e) => {
      if (sucio && !window.confirm("Hay cambios sin guardar: el CSV usará la última versión guardada. ¿Descargar de todos modos?")) {
        e.preventDefault();
      }
    });

    const modalImportar = document.getElementById("cmImportarModal");
    const formImportar = modalImportar.querySelector("[data-cm-importar-form]");
    const errorImportar = modalImportar.querySelector("[data-cm-importar-error]");
    const inputImportar = document.getElementById("cmImportarArchivo");

    raiz.querySelector("[data-cm-importar-abrir]").addEventListener("click", () => {
      inputImportar.value = "";
      mostrarError(errorImportar, "");
      window.AgencyOS.openModal("cmImportarModal");
    });

    formImportar.addEventListener("submit", async (e) => {
      e.preventDefault();
      const archivo = inputImportar.files[0];
      if (!archivo) {
        mostrarError(errorImportar, "Elige un archivo CSV.");
        return;
      }
      if (archivo.size > 2 * 1024 * 1024) {
        mostrarError(errorImportar, "El archivo supera los 2 MB.");
        return;
      }
      const modo = formImportar.querySelector('input[name="cmImportarModo"]:checked').value;
      if (modo === "reemplazar" && !window.confirm("Se reemplazarán todas las columnas y filas actuales. ¿Continuar?")) return;

      // La importación trabaja sobre lo guardado: primero se guardan los cambios pendientes.
      if (sucio && !(await guardar())) {
        mostrarError(errorImportar, "No se pudieron guardar los cambios pendientes antes de importar.");
        return;
      }

      const datos = new FormData();
      datos.append("archivo", archivo);
      datos.append("modo", modo);
      const res = await api(raiz.dataset.urlImportar, "POST", datos);
      if (!res.ok) {
        mostrarError(errorImportar, mensajeError(res));
        return;
      }

      estado = res.datos.datos;
      Object.keys(filtros).forEach((k) => delete filtros[k]);
      orden = { col: null, dir: 1 };
      sucio = false;
      estadoTexto.classList.remove("is-sucio");
      estadoTexto.textContent = `Importado a las ${horaActual()}`;
      window.AgencyOS.closeModal("cmImportarModal");
      renderVista();
      const ignoradas = res.datos.ignoradas ? ` (${res.datos.ignoradas} columna(s) del CSV no coincidían y se ignoraron)` : "";
      toast(`${res.datos.importadas} fila(s) importada(s)${ignoradas}`);
    });

    // ---------- Llenar desde... (foto de una fuente real) ----------

    const botonLlenar = raiz.querySelector("[data-cm-llenar-abrir]");
    if (botonLlenar) {
      const modalLlenar = document.getElementById("cmLlenarModal");
      const formLlenar = modalLlenar.querySelector("[data-cm-llenar-form]");
      const listaFuentes = modalLlenar.querySelector("[data-cm-llenar-fuentes]");
      const errorLlenar = modalLlenar.querySelector("[data-cm-llenar-error]");

      fuentes.forEach((f, i) => {
        const radio = el("input", { type: "radio", name: "cmLlenarFuente", value: f.clave });
        radio.checked = i === 0;
        listaFuentes.appendChild(
          el("label", { class: "cm-radio" }, [radio, el("span", {}, [el("strong", { text: ` ${f.etiqueta}` }), el("span", { class: "field__hint", text: ` — ${f.descripcion}` })])])
        );
      });

      botonLlenar.addEventListener("click", () => {
        mostrarError(errorLlenar, "");
        window.AgencyOS.openModal("cmLlenarModal");
      });

      formLlenar.addEventListener("submit", async (e) => {
        e.preventDefault();
        const marcada = formLlenar.querySelector('input[name="cmLlenarFuente"]:checked');
        if (!marcada) {
          mostrarError(errorLlenar, "Elige una fuente.");
          return;
        }
        const modo = formLlenar.querySelector('input[name="cmLlenarModo"]:checked').value;
        const nombre = fuentesEtiquetas[marcada.value] || marcada.value;
        const aviso = modo === "reemplazar"
          ? `Se reemplazarán todas las columnas y filas actuales por una foto de «${nombre}». ¿Continuar?`
          : `Se agregarán a la tabla las filas de «${nombre}» (foto de hoy, sin sincronización). ¿Continuar?`;
        if (!window.confirm(aviso)) return;

        // Llenar trabaja sobre lo guardado: primero se guardan los cambios pendientes.
        if (sucio && !(await guardar())) {
          mostrarError(errorLlenar, "No se pudieron guardar los cambios pendientes antes de llenar.");
          return;
        }

        const res = await api(raiz.dataset.urlLlenar, "POST", { fuente: marcada.value, modo });
        if (!res.ok) {
          mostrarError(errorLlenar, mensajeError(res));
          return;
        }

        estado = res.datos.datos;
        estado.columnas_visibles = estado.columnas_visibles || [];
        Object.keys(filtros).forEach((k) => delete filtros[k]);
        orden = { col: null, dir: 1 };
        sucio = false;
        estadoTexto.classList.remove("is-sucio");
        estadoTexto.textContent = `Llenado a las ${horaActual()}`;
        window.AgencyOS.closeModal("cmLlenarModal");
        renderVista();
        const omitidas = res.datos.omitidas ? ` (${res.datos.omitidas} no cupieron en el máximo de 2000 filas)` : "";
        toast(`${res.datos.importadas} fila(s) copiada(s)${omitidas}`);
      });
    }

    renderVista();
  }

  // ---------- Arranque ----------

  function init() {
    const explorador = document.querySelector("[data-cm-root]");
    if (explorador) iniciarExplorador(explorador);

    const documento = document.querySelector("[data-cm-documento]");
    if (documento) iniciarDocumento(documento);

    const tabla = document.querySelector("[data-cm-tabla]");
    if (tabla) iniciarTabla(tabla);

    iniciarNombreEditable();
  }

  document.addEventListener("shell:ready", init);
})();
