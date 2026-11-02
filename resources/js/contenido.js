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

    const titulos = { carpeta: "Nueva carpeta", documento: "Nuevo documento", tabla: "Nueva tabla" };

    raiz.querySelectorAll("[data-cm-crear]").forEach((boton) => {
      boton.addEventListener("click", () => {
        const tipo = boton.dataset.cmCrear;
        const plantilla = boton.dataset.plantilla || null;
        accionNombre = { modo: "crear", tipo, plantilla };
        tituloNombre.textContent = plantilla === "calendario" ? "Nuevo calendario de contenido" : titulos[tipo];
        inputNombre.value = plantilla === "calendario" ? "Calendario de contenido" : "";
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

    let cuerpoTabla = null;

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
        cuerpoTabla.appendChild(el("tr", {}, [el("td", { class: "table__empty", colspan: String(estado.columnas.length + 1), text: mensaje })]));
        return;
      }

      filas.forEach((fila) => {
        const tr = el("tr", { "data-fila-id": fila.id });
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

    function renderVista() {
      validarKanban();
      sinColumnas.hidden = estado.columnas.length > 0;
      botonesVista.forEach((b) => b.classList.toggle("is-activa", b.dataset.cmVista === estado.vista));

      const enKanban = estado.vista === "kanban";
      panelTabla.hidden = enKanban;
      panelKanban.hidden = !enKanban;
      selectorKanban.hidden = !enKanban || columnasSeleccion().length < 2;

      selectKanban.replaceChildren();
      columnasSeleccion().forEach((c) => selectKanban.appendChild(el("option", { value: c.id, text: c.nombre })));
      selectKanban.value = estado.columna_kanban || "";

      if (enKanban) renderKanban();
      else renderTabla();
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

    selectColTipo.addEventListener("change", () => {
      campoOpciones.hidden = selectColTipo.value !== "seleccion";
    });

    function abrirColumna(col) {
      columnaEditada = col || null;
      modalColumna.querySelector("[data-cm-columna-titulo]").textContent = col ? "Editar columna" : "Nueva columna";
      inputColNombre.value = col ? col.nombre : "";
      selectColTipo.value = col ? col.tipo : "texto";
      selectColTipo.disabled = Boolean(col);
      areaColOpciones.value = col ? col.opciones.join("\n") : "";
      campoOpciones.hidden = selectColTipo.value !== "seleccion";
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

    formColumna.addEventListener("submit", (e) => {
      e.preventDefault();
      const nombre = inputColNombre.value.trim();
      if (!nombre) {
        mostrarError(errorColumna, "Escribe un nombre para la columna.");
        return;
      }
      const tipo = selectColTipo.value;
      const opciones = tipo === "seleccion"
        ? Array.from(new Set(areaColOpciones.value.split("\n").map((o) => o.trim()).filter(Boolean)))
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

    async function guardar() {
      if (botonGuardar.disabled) return true;
      botonGuardar.disabled = true;
      estadoTexto.textContent = "Guardando...";
      const res = await api(raiz.dataset.urlGuardar, "PUT", {
        columnas: estado.columnas,
        filas: estado.filas,
        vista: estado.vista,
        columna_kanban: estado.columna_kanban,
      });
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
