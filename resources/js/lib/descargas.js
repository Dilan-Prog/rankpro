/**
 * Descargas con progreso para formularios y enlaces marcados con `data-descarga`.
 *
 * Por qué existe: la descarga nativa (navegación) no da ninguna señal mientras el
 * servidor genera el PDF, ni avisa si falla (un 500 dejaba la pestaña "colgada" o
 * mostraba una página de error). Aquí se hace con fetch, se valida la respuesta y
 * cualquier fallo queda como mensaje PERSISTENTE junto al botón (un toast de 3,5 s
 * se pierde, y ya hubo un fallo que nadie vio). Sin JS el formulario/enlace sigue
 * funcionando como siempre (mejora progresiva).
 *
 * Atributos opcionales:
 *   data-descarga-confirmar="..."  confirmación nativa antes de empezar
 *   data-descarga-recargar         recarga la página tras una descarga correcta
 *                                  (el servidor cambió estado/folio/lista)
 *   data-descarga-aviso="#sel"     dónde pintar el mensaje de error
 *   data-descarga-tipo="a/b,c/d"   tipos MIME aceptados (por defecto PDF, hojas de cálculo y CSV)
 */

export const TIPOS_POR_DEFECTO = [
  "application/pdf",
  "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
  "application/vnd.ms-excel",
  "text/csv",
  "application/csv",
  "application/octet-stream",
];

export const TIEMPO_MAXIMO_MS = 120000;
const CLAVE_OK = "agencyos-descarga-ok";

/** Tipo MIME sin parámetros (`text/csv; charset=UTF-8` -> `text/csv`). */
export function tipoBase(contentType) {
  return String(contentType || "").split(";")[0].trim().toLowerCase();
}

export function tipoEsperado(contentType, permitidos = TIPOS_POR_DEFECTO) {
  const tipo = tipoBase(contentType);
  return tipo !== "" && permitidos.includes(tipo);
}

/** Nombre de archivo de un `Content-Disposition` (admite `filename*=UTF-8''` y `filename=`). */
export function nombreDesdeDisposicion(cabecera, porDefecto = "descarga") {
  if (!cabecera) return porDefecto;

  const ext = /filename\*\s*=\s*(?:UTF-8|utf-8)''([^;]+)/i.exec(cabecera);
  if (ext) {
    try {
      return decodeURIComponent(ext[1].trim());
    } catch (err) {
      /* codificación rota: se prueba con filename= */
    }
  }

  const simple = /filename\s*=\s*"?([^";]+)"?/i.exec(cabecera);
  return simple && simple[1].trim() ? simple[1].trim() : porDefecto;
}

/** Mensaje claro para el usuario según lo que contestó (o no) el servidor. */
export function mensajeDeError({ status = 0, contentType = "", cuerpo = "", red = false, tiempoAgotado = false } = {}) {
  if (tiempoAgotado) return "La generación tardó demasiado y se canceló. Inténtalo de nuevo; si se repite, avisa a soporte.";
  if (red) return "No se pudo conectar con el servidor. Revisa tu conexión e inténtalo de nuevo.";

  if (status === 419) return "La sesión caducó. Recarga la página e inténtalo de nuevo.";
  if (status === 401) return "Tu sesión terminó. Vuelve a iniciar sesión.";
  if (status === 403) return "No tienes permiso para descargar este archivo.";
  if (status === 404) return "El archivo o el registro ya no existe.";

  // Laravel responde JSON con `message` en validaciones y errores con el header AJAX.
  if (tipoBase(contentType) === "application/json" && cuerpo) {
    try {
      const json = JSON.parse(cuerpo);
      if (json && typeof json.message === "string" && json.message.trim() && status < 500) return json.message.trim();
    } catch (err) {
      /* no era JSON válido */
    }
  }

  if (status >= 500) return "El servidor falló al generar el archivo (error " + status + "). Inténtalo de nuevo; si se repite, avisa a soporte.";
  if (status >= 400) return "No se pudo generar el archivo (error " + status + ").";

  if (tipoBase(contentType) === "text/html") {
    return "El servidor devolvió una página en lugar del archivo. Es probable que la sesión haya caducado: recarga la página.";
  }

  return "El servidor no devolvió un archivo válido. Inténtalo de nuevo.";
}

function csrf() {
  return document.querySelector('meta[name="csrf-token"]')?.content || "";
}

function guardarArchivo(blob, nombre) {
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = nombre;
  a.style.display = "none";
  document.body.appendChild(a);
  a.click();
  a.remove();
  setTimeout(() => URL.revokeObjectURL(url), 10000);
}

function contenedorDeAviso(origen) {
  const selector = origen.dataset.descargaAviso;
  if (selector) {
    const destino = document.querySelector(selector);
    if (destino) return destino;
  }

  // Sin destino explícito: justo debajo de la cabecera de página (donde están
  // los botones de exportar) o, si no hay, debajo del propio control.
  const cabecera = origen.closest(".page-header");
  const ancla = cabecera || origen;
  let caja = ancla.nextElementSibling;
  if (!caja || !caja.matches("[data-descarga-mensajes]")) {
    caja = document.createElement("div");
    caja.setAttribute("data-descarga-mensajes", "");
    ancla.insertAdjacentElement("afterend", caja);
  }
  return caja;
}

function limpiarError(origen) {
  if (origen._avisoDescarga) origen._avisoDescarga.remove();
  origen._avisoDescarga = null;
}

function mostrarError(origen, mensaje) {
  const caja = contenedorDeAviso(origen);
  limpiarError(origen);

  const aviso = document.createElement("div");
  aviso.className = "form-status form-status--error";
  aviso.setAttribute("role", "alert");
  aviso.setAttribute("data-descarga-error", "");

  const icono = document.createElement("i");
  icono.className = "fa-solid fa-triangle-exclamation";
  icono.style.marginTop = "2px";
  const texto = document.createElement("span");
  texto.textContent = mensaje;
  const cerrar = document.createElement("button");
  cerrar.type = "button";
  cerrar.className = "btn--icon";
  cerrar.title = "Cerrar aviso";
  cerrar.style.marginLeft = "auto";
  cerrar.innerHTML = '<i class="fa-solid fa-xmark"></i>';
  cerrar.addEventListener("click", () => aviso.remove());

  aviso.append(icono, texto, cerrar);
  caja.appendChild(aviso);
  origen._avisoDescarga = aviso;
}

/** Deja el botón en estado "generando"; devuelve la función que lo restaura. */
function ocuparBoton(origen, boton) {
  const html = boton.innerHTML;
  const eraDeshabilitado = boton.disabled;

  boton.disabled = true;
  boton.setAttribute("aria-busy", "true");
  boton.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Generando…';
  if (origen === boton) {
    boton.setAttribute("aria-disabled", "true");
    boton.style.pointerEvents = "none";
  }

  return () => {
    boton.innerHTML = html;
    boton.disabled = eraDeshabilitado;
    boton.removeAttribute("aria-busy");
    if (origen === boton) {
      boton.removeAttribute("aria-disabled");
      boton.style.pointerEvents = "";
    }
  };
}

/**
 * Ejecuta la descarga de un formulario o enlace. Devuelve `true` si el archivo
 * se bajó, `false` si falló (el mensaje ya quedó en pantalla).
 */
export async function descargar(origen) {
  if (origen._descargando) return false;

  const confirmar = origen.dataset.descargaConfirmar;
  if (confirmar && !window.confirm(confirmar)) return false;

  const esFormulario = origen.tagName === "FORM";
  const boton = esFormulario ? origen.querySelector('button[type="submit"], button:not([type])') : origen;
  if (!boton) return false;

  origen._descargando = true;
  limpiarError(origen);
  const restaurar = ocuparBoton(origen, boton);
  const control = new AbortController();
  let agotado = false;
  const reloj = setTimeout(() => {
    agotado = true;
    control.abort();
  }, TIEMPO_MAXIMO_MS);

  try {
    // Otros módulos (el autosave del editor de reportes) vacían lo pendiente antes de salir.
    const esperas = [];
    document.dispatchEvent(new CustomEvent("descarga:antes", { detail: { esperar: esperas } }));
    await Promise.all(esperas);

    const permitidos = origen.dataset.descargaTipo
      ? origen.dataset.descargaTipo.split(",").map((t) => t.trim().toLowerCase())
      : TIPOS_POR_DEFECTO;

    const opciones = {
      signal: control.signal,
      credentials: "same-origin",
      headers: { "X-Requested-With": "XMLHttpRequest", Accept: permitidos.join(", ") + ", application/json;q=0.5" },
    };
    let destino;

    if (esFormulario) {
      opciones.method = (origen.getAttribute("method") || "GET").toUpperCase();
      destino = origen.getAttribute("action") || window.location.href;
      const token = csrf();
      if (token) opciones.headers["X-CSRF-TOKEN"] = token;
      if (opciones.method === "GET") {
        const url = new URL(destino, window.location.href);
        new URLSearchParams(new FormData(origen)).forEach((v, k) => url.searchParams.set(k, v));
        destino = url.toString();
      } else {
        opciones.body = new FormData(origen);
      }
    } else {
      opciones.method = "GET";
      destino = origen.getAttribute("href");
    }

    const respuesta = await fetch(destino, opciones);
    const contentType = respuesta.headers.get("Content-Type") || "";

    if (!respuesta.ok || !tipoEsperado(contentType, permitidos)) {
      const cuerpo = tipoBase(contentType) === "application/json" ? await respuesta.text().catch(() => "") : "";
      mostrarError(origen, mensajeDeError({ status: respuesta.ok ? 0 : respuesta.status, contentType, cuerpo }));
      restaurar();
      return false;
    }

    const blob = await respuesta.blob();
    const nombre = nombreDesdeDisposicion(respuesta.headers.get("Content-Disposition"));
    guardarArchivo(blob, nombre);
    restaurar();

    if (origen.hasAttribute("data-descarga-recargar")) {
      try {
        sessionStorage.setItem(CLAVE_OK, nombre);
      } catch (err) {
        /* sin almacenamiento: la recarga ocurre igual, solo sin el aviso de éxito */
      }
      // Un respiro para que el navegador arranque la descarga antes de recargar.
      setTimeout(() => window.location.reload(), 400);
    }
    return true;
  } catch (err) {
    mostrarError(origen, mensajeDeError({ red: !agotado, tiempoAgotado: agotado }));
    restaurar();
    return false;
  } finally {
    clearTimeout(reloj);
    origen._descargando = false;
  }
}

/** Engancha los controles `data-descarga` (también los añadidos después de la carga). */
export function iniciarDescargas() {
  document.addEventListener("submit", (e) => {
    const form = e.target.closest ? e.target.closest("form[data-descarga]") : null;
    if (!form || e.defaultPrevented) return;
    e.preventDefault();
    descargar(form);
  });

  document.addEventListener("click", (e) => {
    const enlace = e.target.closest ? e.target.closest("a[data-descarga]") : null;
    if (!enlace || e.defaultPrevented || e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey) return;
    e.preventDefault();
    descargar(enlace);
  });

  try {
    const nombre = sessionStorage.getItem(CLAVE_OK);
    if (nombre) {
      sessionStorage.removeItem(CLAVE_OK);
      window.AgencyOS?.toast("Descarga lista: " + nombre);
    }
  } catch (err) {
    /* sin almacenamiento */
  }
}
