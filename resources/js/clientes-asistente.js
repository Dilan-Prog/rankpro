/**
 * Asistente de alta de cliente (4 pasos, alta rápida) e importación de clientes por CSV.
 *
 * A diferencia del generador de Audiencia, el alta SÍ guarda: un solo POST multipart a admin.clientes.asistente.store
 * crea todo en una transacción. Cada "Siguiente" valida ese paso en el servidor (así un correo repetido se avisa en
 * el paso 2, no al final) y el MRR del paso 3 también lo calcula el servidor con la fuente única del sistema, para
 * que lo que se ve aquí sea lo que sumará el Dashboard. Todo texto del servidor entra con textContent.
 */
(function () {
  "use strict";

  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

  function dinero(n) {
    return "$" + Number(n || 0).toLocaleString("es-MX", { minimumFractionDigits: Number(n || 0) % 1 === 0 ? 0 : 2, maximumFractionDigits: 2 });
  }

  function el(tag, clase, texto) {
    const nodo = document.createElement(tag);
    if (clase) nodo.className = clase;
    if (texto != null) nodo.textContent = texto;
    return nodo;
  }

  function enviar(url, datos) {
    return fetch(url, {
      method: "POST",
      headers: { Accept: "application/json", "X-CSRF-TOKEN": csrfToken },
      body: datos,
    }).then((res) => res.json().catch(() => ({})).then((cuerpo) => ({ ok: res.ok, status: res.status, cuerpo })));
  }

  // ------------------------------------------------------------------ Asistente

  function iniciarAsistente() {
    const form = document.getElementById("altaClienteForm");
    const overlay = document.getElementById("altaClienteModal");
    const datosNodo = document.getElementById("asistenteDatos");
    if (!form || !overlay || !datosNodo) return;

    const datos = JSON.parse(datosNodo.textContent);
    const pasos = Array.from(form.querySelectorAll("[data-asistente-paso]"));
    const etiquetasPasos = Array.from(form.querySelectorAll("[data-asistente-etiqueta]"));
    const barra = form.querySelector("[data-asistente-barra]");
    const subtitulo = form.querySelector("[data-asistente-subtitulo]");
    const errorGeneral = form.querySelector("[data-asistente-error]");
    const btnAtras = form.querySelector("[data-asistente-atras]");
    const btnSiguiente = form.querySelector("[data-asistente-siguiente]");
    const btnRapida = form.querySelector("[data-asistente-alta-rapida]");
    const bloqueRapido = form.querySelector("[data-asistente-rapido]");
    const resultado = form.querySelector("[data-asistente-resultado]");
    const listaServicios = form.querySelector("[data-asistente-servicios]");
    const nombresPaso = ["Negocio", "Contacto y contrato", "Servicios", "Marca y conexiones"];

    const selGiro = form.elements.giro;
    const selPlantilla = form.elements.plantilla;

    let paso = 1;
    let modo = "completo";
    let ocupado = false;
    let plantillaTocada = false;
    let serviciosTocados = false;
    let plantillaCargada = null; // plantilla cuyos servicios están en pantalla
    let servicios = [];
    let creado = false;
    let secuenciaResumen = 0;
    let temporizadorResumen = null;

    const giroPorClave = (clave) => datos.giros.find((g) => g.clave === clave) || null;

    // ---- Presentación del paso

    function pintar() {
      const enResultado = !resultado.hidden;
      pasos.forEach((s, i) => { s.hidden = enResultado ? true : modo === "rapido" ? i !== 0 : i + 1 !== paso; });
      bloqueRapido.hidden = modo !== "rapido";
      // En alta rápida el paso 1 muestra solo lo básico (nombre comercial y contacto); lo demás se oculta.
      Array.from(pasos[0].children).forEach((n, idx) => {
        if (idx === 0 || n === bloqueRapido) return;
        n.hidden = modo === "rapido" || n.dataset.oculto === "1";
      });
      const total = pasos.length;
      const actual = enResultado ? total : modo === "rapido" ? 1 : paso;
      barra.style.width = ((enResultado ? total : actual) / total) * 100 + "%";
      etiquetasPasos.forEach((li, i) => {
        li.classList.toggle("is-activo", !enResultado && modo === "completo" && i + 1 === paso);
        li.classList.toggle("is-hecho", enResultado || (modo === "completo" && i + 1 < paso));
      });
      form.querySelector("[data-asistente-lista-pasos]").hidden = modo === "rapido";

      if (enResultado) {
        subtitulo.textContent = "Alta terminada";
        btnRapida.hidden = true;
        btnAtras.textContent = "Crear otro cliente";
        btnSiguiente.textContent = "Cerrar";
        return;
      }
      subtitulo.textContent = modo === "rapido" ? "Alta rápida · nombre comercial y contacto" : `Paso ${paso} de ${total} · ${nombresPaso[paso - 1]}`;
      btnRapida.hidden = !(modo === "completo" && paso === 1);
      btnAtras.textContent = modo === "rapido" ? "Volver al asistente" : paso === 1 ? "Cancelar" : "Atrás";
      btnSiguiente.textContent = modo === "rapido" ? "Crear cliente" : paso === total ? "Crear cliente" : "Siguiente";
      mostrarError("");
    }

    function enfocarPaso() {
      const seccion = modo === "rapido" ? bloqueRapido : pasos[paso - 1];
      const campo = seccion?.querySelector("input:not([type=hidden]):not([type=color]), select, textarea, button");
      campo?.focus();
    }

    function mostrarError(texto) {
      errorGeneral.textContent = texto || "";
      errorGeneral.hidden = !texto;
    }

    function limpiarErrores() {
      form.querySelectorAll("[data-error-for]").forEach((s) => { s.textContent = ""; });
      form.querySelectorAll("[aria-invalid]").forEach((n) => n.removeAttribute("aria-invalid"));
      mostrarError("");
    }

    // Paso al que pertenece cada campo, para llevar al usuario al primer error que devuelva el servidor.
    function pasoDeCampo(campo) {
      if (/^(nombre_comercial|giro|sitio_web|ciudad|plantilla|tema)$/.test(campo)) return 1;
      if (/^(contacto_nombre|telefono|email|fecha_|forma_pago|metodo_pago)/.test(campo)) return 2;
      if (/^servicios/.test(campo)) return 3;
      return 4;
    }

    function mostrarErrores(errores) {
      limpiarErrores();
      const generales = [];
      let primerPaso = 99;
      Object.keys(errores || {}).forEach((campo) => {
        const destino = modo === "rapido" && campo === "contacto_nombre" ? "contacto_nombre_rapido" : campo;
        const mensaje = errores[campo][0];
        const span = form.querySelector(`[data-error-for="${destino}"]`);
        if (span) {
          span.textContent = mensaje;
          form.elements[campo]?.setAttribute("aria-invalid", "true");
        } else {
          generales.push(mensaje);
        }
        primerPaso = Math.min(primerPaso, pasoDeCampo(campo));
      });
      if (generales.length) mostrarError(generales.join(" "));
      if (modo === "completo" && primerPaso !== 99 && primerPaso !== paso) {
        paso = primerPaso;
        pintar();
        generales.length && mostrarError(generales.join(" "));
      }
      form.querySelector('[aria-invalid="true"]')?.focus();
    }

    // ---- Paso 1: giro, plantilla y vista de lo que crea

    function pintarPlantilla() {
      const clave = selPlantilla.value;
      const giro = giroPorClave(clave);
      const campoTema = form.querySelector("[data-asistente-tema-campo]");
      const vista = form.querySelector("[data-asistente-vista-plantilla]");

      campoTema.dataset.oculto = giro ? "0" : "1";
      campoTema.hidden = !giro || modo === "rapido";
      vista.dataset.oculto = giro ? "0" : "1";
      vista.hidden = !giro || modo === "rapido";
      vista.replaceChildren();
      if (!giro) return;

      form.querySelector("[data-asistente-tema-etiqueta]").textContent = giro.tema.etiqueta;
      vista.appendChild(el("strong", null, `La plantilla «${giro.etiqueta}» preparará:`));
      const ul = el("ul", "asistente__lista");
      ul.appendChild(el("li", null, "Servicios (los editas en el paso 3): " + giro.servicios.map((s) => s.nombre).join(", ")));
      ul.appendChild(el("li", null, "Métricas sin meta: " + giro.metricas.join(", ")));
      ul.appendChild(el("li", null, "Keywords semilla: " + giro.keywords.join(" · ")));
      ul.appendChild(el("li", null, "Reportes mensuales sugeridos: " + giro.reportes.map((r) => (r === "ads" ? "Ads" : r === "seo" ? "SEO" : r)).join(" + ")));
      vista.appendChild(ul);
      vista.appendChild(el("p", "form-moderno__ayuda", "Es un punto de partida: sin precios ni metas; tú los defines."));
    }

    selGiro.addEventListener("change", () => {
      if (!plantillaTocada) selPlantilla.value = selGiro.value;
      pintarPlantilla();
    });
    selPlantilla.addEventListener("change", () => { plantillaTocada = true; pintarPlantilla(); });

    // ---- Paso 3: servicios editables y MRR en vivo

    function cargarServiciosDePlantilla() {
      const clave = selPlantilla.value;
      const nota = form.querySelector("[data-asistente-servicios-nota]");
      if (serviciosTocados) {
        nota.textContent = "Conservamos los servicios que ya editaste. Puedes añadir o quitar los que haga falta.";
        return;
      }
      if (plantillaCargada === clave && servicios.length) return;
      const giro = giroPorClave(clave);
      servicios = giro ? giro.servicios.map((s) => ({ tipo: s.tipo, nombre: s.nombre, tipo_cobro: s.tipo_cobro, precio_mensual: "" })) : [];
      plantillaCargada = clave;
      nota.textContent = giro
        ? `Sugeridos por la plantilla «${giro.etiqueta}», sin precio: captúralos aquí o después en Servicios. Quita los que no apliquen.`
        : "Sin plantilla: añade los servicios que contrataron (puedes dejarlo vacío y hacerlo después).";
      pintarServicios();
    }

    function opciones(select, lista, valor) {
      lista.forEach((o) => {
        const opt = el("option", null, o.etiqueta);
        opt.value = o.valor;
        opt.selected = o.valor === valor;
        select.appendChild(opt);
      });
    }

    function pintarServicios() {
      listaServicios.replaceChildren();
      servicios.forEach((s, i) => {
        const fila = el("div", "asistente__servicio");
        const tipo = el("select", "form-moderno__control form-moderno__control--select");
        tipo.setAttribute("aria-label", "Tipo de servicio");
        opciones(tipo, datos.tipos, s.tipo);
        tipo.addEventListener("change", () => { s.tipo = tipo.value; serviciosTocados = true; });

        const nombre = el("input", "form-moderno__control");
        nombre.type = "text";
        nombre.value = s.nombre;
        nombre.placeholder = "Nombre del servicio";
        nombre.setAttribute("aria-label", "Nombre del servicio");
        nombre.addEventListener("input", () => { s.nombre = nombre.value; serviciosTocados = true; });

        const cobro = el("select", "form-moderno__control form-moderno__control--select");
        cobro.setAttribute("aria-label", "Tipo de cobro");
        opciones(cobro, datos.cobros, s.tipo_cobro);

        const precio = el("input", "form-moderno__control");
        precio.type = "number";
        precio.min = "0";
        precio.step = "0.01";
        precio.placeholder = "Precio";
        precio.setAttribute("aria-label", "Precio");
        precio.value = s.precio_mensual;
        precio.hidden = s.tipo_cobro === "gratuito";
        precio.addEventListener("input", () => { s.precio_mensual = precio.value; serviciosTocados = true; pedirResumen(); });

        cobro.addEventListener("change", () => {
          s.tipo_cobro = cobro.value;
          serviciosTocados = true;
          precio.hidden = cobro.value === "gratuito";
          pedirResumen();
        });

        const quitar = el("button", "btn--icon");
        quitar.type = "button";
        quitar.title = "Quitar servicio";
        quitar.setAttribute("aria-label", "Quitar servicio");
        quitar.innerHTML = '<i class="fa-solid fa-xmark" aria-hidden="true"></i>';
        quitar.addEventListener("click", () => {
          servicios.splice(i, 1);
          serviciosTocados = true;
          pintarServicios();
        });

        fila.append(tipo, nombre, cobro, precio, quitar);
        listaServicios.appendChild(fila);
      });
      pedirResumen();
    }

    form.querySelector("[data-asistente-agregar-servicio]").addEventListener("click", () => {
      servicios.push({ tipo: "seo", nombre: "", tipo_cobro: "mensual", precio_mensual: "" });
      serviciosTocados = true;
      pintarServicios();
      listaServicios.querySelector(".asistente__servicio:last-child input")?.focus();
    });

    // El servidor calcula el MRR (fuente única); se ignoran respuestas viejas con una secuencia.
    function pedirResumen() {
      clearTimeout(temporizadorResumen);
      temporizadorResumen = setTimeout(() => {
        const secuencia = ++secuenciaResumen;
        const cuerpo = new FormData();
        servicios.forEach((s, i) => {
          cuerpo.append(`servicios[${i}][tipo_cobro]`, s.tipo_cobro);
          cuerpo.append(`servicios[${i}][precio_mensual]`, s.tipo_cobro === "gratuito" ? "" : s.precio_mensual);
        });
        enviar(form.dataset.urlServicios, cuerpo).then((r) => {
          if (secuencia !== secuenciaResumen || !r.ok) return;
          form.querySelector("[data-asistente-mrr]").textContent = dinero(r.cuerpo.mrr);
          form.querySelector("[data-asistente-unico]").textContent = dinero(r.cuerpo.unico);
          form.querySelector("[data-asistente-gratuitos]").textContent = String(r.cuerpo.gratuitos);
          const aviso = form.querySelector("[data-asistente-sin-precio]");
          aviso.hidden = !r.cuerpo.sin_precio;
          aviso.textContent = r.cuerpo.sin_precio
            ? `${r.cuerpo.sin_precio} servicio(s) sin precio: cuentan como $0 hasta que los completes en Servicios.`
            : "";
        });
      }, 200);
    }

    // ---- Construcción del envío

    function construirDatos(extra) {
      const fd = new FormData();
      fd.append("_token", csrfToken);
      ["nombre_comercial", "giro", "plantilla", "sitio_web", "ciudad", "tema", "contacto_nombre", "telefono", "email",
        "fecha_inicio_contrato", "fecha_renovacion_contrato", "forma_pago", "metodo_pago"].forEach((n) => {
        if (form.elements[n]) fd.append(n, form.elements[n].value);
      });
      if (modo === "rapido") fd.set("contacto_nombre", form.querySelector("#al_rapido_contacto").value);
      fd.append("modo", modo);

      servicios
        .filter((s) => s.nombre.trim() !== "" || String(s.precio_mensual).trim() !== "")
        .forEach((s, i) => {
          fd.append(`servicios[${i}][tipo]`, s.tipo);
          fd.append(`servicios[${i}][nombre]`, s.nombre);
          fd.append(`servicios[${i}][tipo_cobro]`, s.tipo_cobro);
          fd.append(`servicios[${i}][precio_mensual]`, s.tipo_cobro === "gratuito" ? "" : s.precio_mensual);
        });

      let c = 0;
      form.querySelectorAll("[data-asistente-color]").forEach((input) => {
        if (input.dataset.vacio === "1") return; // un color sin tocar no se manda
        fd.append(`colores[${c}][nombre]`, `Color ${Number(input.dataset.asistenteColor) + 1}`);
        fd.append(`colores[${c}][hex]`, input.value);
        c++;
      });
      form.querySelectorAll('input[name="tonos[]"]:checked').forEach((t) => fd.append("tonos[]", t.value));
      const logo = form.elements.logo?.files?.[0];
      if (logo) fd.append("logo", logo);
      fd.append("conexiones[gsc]", form.querySelector("[data-asistente-gsc-check]")?.checked ? "1" : "0");

      Object.entries(extra || {}).forEach(([k, v]) => fd.append(k, v));
      return fd;
    }

    form.querySelectorAll("[data-asistente-color]").forEach((input) => {
      input.addEventListener("input", () => { input.dataset.vacio = "0"; });
    });

    // ---- Navegación

    function ocupar(si) {
      ocupado = si;
      btnSiguiente.disabled = si;
      btnSiguiente.classList.toggle("is-cargando", si);
      btnAtras.disabled = si;
      btnRapida.disabled = si;
    }

    function siguiente() {
      if (ocupado) return;
      if (!resultado.hidden) { cerrar(); return; }
      limpiarErrores();

      if (modo === "rapido" || paso === pasos.length) {
        crear();
        return;
      }
      ocupar(true);
      enviar(form.dataset.urlValidar, construirDatos({ paso: String(paso) }))
        .then((r) => {
          if (r.status === 422) { mostrarErrores(r.cuerpo.errors); return; }
          if (!r.ok) { mostrarError("No se pudo validar este paso. Intenta de nuevo."); return; }
          paso += 1;
          if (paso === 3) cargarServiciosDePlantilla();
          pintar();
          enfocarPaso();
        })
        .catch(() => mostrarError("Sin conexión con el servidor. Intenta de nuevo."))
        .finally(() => ocupar(false));
    }

    function atras() {
      if (ocupado) return;
      if (!resultado.hidden) { reiniciar(); return; }
      if (modo === "rapido") { modo = "completo"; pintarPlantilla(); pintar(); enfocarPaso(); return; }
      if (paso === 1) { window.AgencyOS.closeModal("altaClienteModal"); return; }
      paso -= 1;
      pintar();
      enfocarPaso();
    }

    function crear() {
      ocupar(true);
      enviar(form.dataset.urlStore, construirDatos())
        .then((r) => {
          if (r.status === 422) { mostrarErrores(r.cuerpo.errors); return; }
          if (!r.ok) { mostrarError("No se pudo crear el cliente: no se guardó nada. Intenta de nuevo."); return; }
          creado = true;
          mostrarResultado(r.cuerpo);
        })
        .catch(() => mostrarError("Sin conexión con el servidor. No se guardó nada."))
        .finally(() => ocupar(false));
    }

    const TEXTO_GSC = {
      creada: "Search Console: propiedad vinculada con el sitio web del cliente.",
      sin_conexion_google: "Search Console: no se vinculó porque aún no hay conexión de Google configurada (Integraciones).",
      sin_sitio: "Search Console: no se vinculó porque el cliente no tiene sitio web.",
    };

    function mostrarResultado(r) {
      pasos.forEach((s) => { s.hidden = true; });
      bloqueRapido.hidden = true;
      resultado.hidden = false;
      form.querySelector("[data-asistente-resultado-titulo]").textContent = `Cliente creado: ${r.cliente.nombre_comercial}`;
      const ul = form.querySelector("[data-asistente-resultado-lista]");
      ul.replaceChildren();
      if (r.resumen) {
        ul.appendChild(el("li", null, `Servicios creados: ${r.resumen.servicios_creados} · MRR ${dinero(r.mrr)}`));
        ul.appendChild(el("li", null, `Métricas del catálogo (sin meta): ${r.resumen.metricas_creadas}`));
        const kw = `Keywords semilla: ${r.resumen.keywords_creadas}`;
        ul.appendChild(el("li", null, r.resumen.keywords_omitidas.length ? `${kw} (${r.resumen.keywords_omitidas.length} omitida(s) por falta de ciudad o tema)` : kw));
        if (TEXTO_GSC[r.conexiones.gsc]) ul.appendChild(el("li", null, TEXTO_GSC[r.conexiones.gsc]));
      } else {
        ul.appendChild(el("li", null, "Alta rápida: solo se guardó el nombre comercial y el contacto."));
      }
      const aviso = form.querySelector("[data-asistente-resultado-perfil]");
      aviso.hidden = r.perfil_completo;
      aviso.textContent = r.perfil_completo ? "" : `Perfil incompleto: falta ${r.faltantes.join(", ")}. Aparece en Pendientes de captura hasta completarlo.`;
      form.querySelector("[data-asistente-enlace-ficha]").href = r.url_ficha;
      form.querySelector("[data-asistente-enlace-tracking]").href = r.url_integraciones;
      pintar();
      resultado.focus();
    }

    function cerrar() {
      window.AgencyOS.closeModal("altaClienteModal");
    }

    function reiniciar() {
      form.reset();
      limpiarErrores();
      form.querySelectorAll("[data-asistente-color]").forEach((i) => { i.dataset.vacio = "1"; });
      resultado.hidden = true;
      paso = 1;
      modo = "completo";
      plantillaTocada = false;
      serviciosTocados = false;
      plantillaCargada = null;
      servicios = [];
      pintarServicios();
      pintarPlantilla();
      pintar();
      enfocarPaso();
    }

    btnSiguiente.addEventListener("click", siguiente);
    btnAtras.addEventListener("click", atras);
    btnRapida.addEventListener("click", () => {
      limpiarErrores();
      modo = "rapido";
      pintarPlantilla();
      pintar();
      form.querySelector("#al_rapido_contacto").focus();
    });
    form.addEventListener("submit", (e) => { e.preventDefault(); siguiente(); });

    // Teclado: Enter avanza (salvo en botones y selectores abiertos), Alt+flechas navegan.
    form.addEventListener("keydown", (e) => {
      if (e.key === "Enter" && e.target.matches("input:not([type=file]):not([type=checkbox]):not([type=color])")) {
        e.preventDefault();
        siguiente();
      } else if (e.altKey && e.key === "ArrowRight") {
        e.preventDefault();
        siguiente();
      } else if (e.altKey && e.key === "ArrowLeft") {
        e.preventDefault();
        if (resultado.hidden && (paso > 1 || modo === "rapido")) atras();
      }
    });

    document.querySelectorAll("[data-abrir-asistente]").forEach((btn) => {
      btn.addEventListener("click", () => {
        if (creado) reiniciar();
        creado = false;
        resultado.hidden = true;
        window.AgencyOS.openModal("altaClienteModal");
        pintarPlantilla();
        pintar();
        enfocarPaso();
      });
    });

    // La tabla del CRM se renderiza en el servidor: tras un alta se recarga al cerrar el asistente.
    new MutationObserver(() => {
      if (overlay.hidden && creado) window.location.reload();
    }).observe(overlay, { attributes: true, attributeFilter: ["hidden"] });

    pintarPlantilla();
  }

  // ------------------------------------------------------------------ Importación

  function iniciarImportacion() {
    const form = document.getElementById("importarClientesForm");
    const overlay = document.getElementById("importarClientesModal");
    if (!form || !overlay) return;

    const texto = form.elements.texto;
    const archivo = form.elements.archivo;
    const btnPrevia = form.querySelector("[data-importar-previa]");
    const btnConfirmar = form.querySelector("[data-importar-confirmar]");
    const error = form.querySelector("[data-importar-error]");
    const bloque = form.querySelector("[data-importar-resultado]");
    const resumen = form.querySelector("[data-importar-resumen]");
    const filas = form.querySelector("[data-importar-filas]");
    let importado = false;

    const ETIQUETA = { nuevo: "Nuevo", duplicado: "Duplicado", error: "Error" };
    const CLASE = { nuevo: "badge--success", duplicado: "badge--warning", error: "badge--danger" };

    function mostrarError(msg) {
      error.textContent = msg || "";
      error.hidden = !msg;
    }

    function cuerpo() {
      const fd = new FormData();
      fd.append("_token", csrfToken);
      if (archivo.files[0]) fd.append("archivo", archivo.files[0]);
      else fd.append("texto", texto.value);
      return fd;
    }

    function pintar(r) {
      bloque.hidden = false;
      const s = r.resumen;
      resumen.textContent = `${s.total} fila(s): ${s.nuevos} nuevo(s) · ${s.duplicados} duplicado(s) · ${s.errores} con error`
        + (s.incompletos ? ` · ${s.incompletos} se crearán con perfil incompleto` : "")
        + (r.creados != null ? ` — se crearon ${r.creados}.` : ".");
      filas.replaceChildren();
      r.filas.forEach((f) => {
        const tr = el("tr");
        tr.appendChild(el("td", null, String(f.fila)));
        tr.appendChild(el("td", null, f.datos.nombre_comercial || f.texto));
        const celdaEstado = el("td");
        celdaEstado.appendChild(el("span", `badge ${CLASE[f.estado]}`, ETIQUETA[f.estado]));
        tr.appendChild(celdaEstado);
        const detalle = f.motivos.length ? f.motivos.join(" ") : f.faltantes.length ? "Perfil incompleto: falta " + f.faltantes.join(", ") + "." : "";
        tr.appendChild(el("td", null, detalle));
        filas.appendChild(tr);
      });
    }

    function invalidarVistaPrevia() {
      btnConfirmar.disabled = true;
    }
    texto.addEventListener("input", invalidarVistaPrevia);
    archivo.addEventListener("change", invalidarVistaPrevia);

    function ejecutar(url, esImportar) {
      mostrarError("");
      btnPrevia.disabled = true;
      btnConfirmar.disabled = true;
      enviar(url, cuerpo())
        .then((r) => {
          if (r.cuerpo.filas) pintar(r.cuerpo);
          if (!r.ok) {
            mostrarError(r.cuerpo.message || "No se pudo procesar el archivo.");
            return;
          }
          if (esImportar) {
            importado = true;
            btnConfirmar.disabled = true;
          } else {
            btnConfirmar.disabled = r.cuerpo.resumen.nuevos === 0;
          }
        })
        .catch(() => mostrarError("Sin conexión con el servidor."))
        .finally(() => { btnPrevia.disabled = false; });
    }

    btnPrevia.addEventListener("click", () => ejecutar(form.dataset.urlVistaPrevia, false));
    btnConfirmar.addEventListener("click", () => ejecutar(form.dataset.urlImportar, true));

    document.querySelectorAll("[data-abrir-importar]").forEach((b) => b.addEventListener("click", () => window.AgencyOS.openModal("importarClientesModal")));
    new MutationObserver(() => {
      if (overlay.hidden && importado) window.location.reload();
    }).observe(overlay, { attributes: true, attributeFilter: ["hidden"] });
  }

  document.addEventListener("shell:ready", () => {
    iniciarAsistente();
    iniciarImportacion();
  });
})();
