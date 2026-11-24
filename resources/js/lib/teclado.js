/**
 * Navegación del panel con el teclado: flechas para moverse, Enter para abrir.
 *
 * Es un módulo común y no uno por pantalla porque las filas, tarjetas y modales se
 * crean y reemplazan por AJAX en casi todos los módulos: aquí se calcula en cada
 * pulsación qué hay a la vista y cuál es el elemento más cercano en esa dirección.
 *
 * Escucha en `document` al final del burbujeo y respeta `defaultPrevented`, así que un
 * módulo con teclas propias (pestañas, menús, paleta Ctrl+K) siempre manda sobre este.
 */

const NATIVOS =
  'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), summary, [contenteditable="true"], [tabindex]:not([tabindex="-1"]), [role="tab"]:not([disabled]), [role="menuitem"]:not([disabled])';
// Elementos que responden al clic pero que el navegador no enfoca por sí solo.
const CLICABLES = "tr.is-clickable, [data-editable-cell], [data-columna-nombre-display]";
const FILA = "tr.is-clickable";
const MODAL = ".modal-overlay";
const CAPA = `${MODAL}, .command-palette-overlay, [role="dialog"][aria-modal="true"]`;
const FLECHAS = { ArrowUp: "arriba", ArrowDown: "abajo", ArrowLeft: "izquierda", ArrowRight: "derecha" };
// Entradas donde las flechas verticales no tienen uso propio y pueden salir del campo.
const TEXTO_SIMPLE = ["text", "search", "email", "url", "tel", "password"];

let ultimo = null; // último elemento enfocado de la página
let ultimoContenido = null; // ídem, solo dentro del contenido (para volver desde el menú)
let selectAbierto = null;
let salioDe = null; // campo del que se salió con Escape: la siguiente flecha parte de él

function visible(el) {
  if (!el.isConnected || el.closest("[hidden], [inert]")) return false;
  // Lo que está en un <details> cerrado conserva medidas pero no admite foco.
  if (el.checkVisibility && !el.checkVisibility()) return false;
  const r = el.getBoundingClientRect();
  // Fuera por la izquierda = menú lateral oculto en móvil.
  if (!r.width || !r.height || r.right <= 0) return false;
  return getComputedStyle(el).visibility !== "hidden";
}

function prepararClicables(raiz) {
  raiz.querySelectorAll(CLICABLES).forEach((el) => {
    if (!el.hasAttribute("tabindex")) el.tabIndex = 0;
  });
}

function candidatos(raiz) {
  prepararClicables(raiz);
  return [...raiz.querySelectorAll(NATIVOS)].filter(visible);
}

/** Capa superior abierta (modal, diálogo o menú desplegable con el foco) o null. */
function capaAbierta() {
  const capas = [...document.querySelectorAll(CAPA)].filter(visible);
  if (capas.length) return capas[capas.length - 1];
  const panel = document.activeElement?.closest("[data-dropdown-panel]");
  return panel && visible(panel) ? panel : null;
}

function enfocar(el) {
  if (!el) return false;
  document.documentElement.classList.add("teclado-activo");
  el.focus({ preventScroll: true });
  el.scrollIntoView({ block: "nearest", inline: "nearest" });
  return true;
}

function enPantalla(el) {
  const r = el.getBoundingClientRect();
  return r.bottom > 0 && r.top < window.innerHeight;
}

/** Elemento más cercano a `desde` en la dirección pedida, por geometría. */
function masCercano(desde, lista, dir) {
  const a = desde.getBoundingClientRect();
  const vertical = dir === "arriba" || dir === "abajo";
  let mejor = null;
  let mejorPunto = Infinity;

  lista.forEach((el) => {
    if (el === desde || el.contains(desde) || desde.contains(el)) return;
    const b = el.getBoundingClientRect();
    const tolerancia = vertical ? Math.min(a.height, b.height) / 2 : Math.min(a.width, b.width) / 2;
    const avance = {
      abajo: b.top - a.bottom,
      arriba: a.top - b.bottom,
      derecha: b.left - a.right,
      izquierda: a.left - b.right,
    }[dir];
    if (avance < -tolerancia) return;

    const cruce = vertical
      ? Math.max(0, b.left - a.right, a.left - b.right)
      : Math.max(0, b.top - a.bottom, a.top - b.bottom);
    // En horizontal solo cuenta lo que está en la misma franja: si no, «izquierda» saltaría de renglón.
    if (!vertical && cruce > 0) return;
    const centro = vertical ? Math.abs(a.left - b.left) : Math.abs(a.top + a.height / 2 - b.top - b.height / 2);
    const punto = Math.max(0, avance) + cruce * 2 + centro * 0.04;
    if (punto < mejorPunto) {
      mejor = el;
      mejorPunto = punto;
    }
  });
  return mejor;
}

function lineal(desde, lista, paso) {
  return lista[lista.indexOf(desde) + paso] || null;
}

/** Filas clicables: arriba/abajo recorre filas; derecha entra a sus botones y la izquierda vuelve. */
function moverEnFila(actual, dir) {
  const fila = actual.closest(FILA);
  if (!fila) return null;
  const internos = candidatos(fila);

  if (dir === "derecha") return actual === fila ? internos[0] : lineal(actual, internos, 1) || actual;
  if (dir === "izquierda") return actual === fila ? null : lineal(actual, internos, -1) || fila;

  const filas = [...fila.parentElement.querySelectorAll(FILA)].filter(visible);
  const otra = lineal(fila, filas, dir === "abajo" ? 1 : -1);
  if (!otra || actual === fila) return otra;
  // Conserva la columna: del botón «Editar» de una fila al «Editar» de la siguiente.
  return candidatos(otra)[internos.indexOf(actual)] || otra;
}

function primeroDelContenido() {
  const main = document.querySelector(".app-shell__content");
  if (!main) return null;
  if (ultimoContenido && visible(ultimoContenido)) return ultimoContenido;
  const lista = candidatos(main);
  return lista.find(enPantalla) || lista[0] || null;
}

function enlaceActivoDelMenu() {
  const menu = document.getElementById("sidebar");
  if (!menu) return null;
  const activo = menu.querySelector(".sidebar__link.is-active");
  return activo && visible(activo) ? activo : candidatos(menu)[0] || null;
}

/** Destino de una flecha, o null para dejar que el navegador haga lo suyo (desplazar la página). */
function destino(dir) {
  const capa = capaAbierta();
  let actual = document.activeElement;
  if (!actual || actual === document.body || !visible(actual)) actual = null;

  if (capa) {
    const lista = candidatos(capa);
    if (!actual || !capa.contains(actual) || !lista.includes(actual)) return lista[0] || null;
    const enFila = moverEnFila(actual, dir);
    if (enFila) return enFila;
    const cerca = masCercano(actual, lista, dir);
    if (cerca || dir === "izquierda" || dir === "derecha") return cerca;
    // Con columnas desiguales la geometría puede no hallar nada: sigue el orden del documento.
    return lineal(actual, lista, dir === "abajo" ? 1 : -1);
  }

  // Sin esto, la flecha volvería al mismo campo (fecha, texto largo) y no habría salida.
  if (!actual && salioDe && visible(salioDe)) actual = salioDe;
  salioDe = null;

  if (!actual) {
    // Tras un clic en una zona sin foco se retoma donde se iba.
    if (ultimo && visible(ultimo) && enPantalla(ultimo)) return ultimo;
    return dir === "izquierda" ? enlaceActivoDelMenu() : primeroDelContenido();
  }

  const menu = actual.closest("#sidebar");
  if (menu) {
    if (dir === "derecha") return primeroDelContenido();
    if (dir === "izquierda") return null;
    return lineal(actual, candidatos(menu), dir === "abajo" ? 1 : -1);
  }

  const barra = actual.closest(".topbar");
  if (barra) {
    if (dir === "abajo") return primeroDelContenido();
    if (dir === "arriba") return null;
    return lineal(actual, candidatos(barra), dir === "derecha" ? 1 : -1) || (dir === "izquierda" ? enlaceActivoDelMenu() : null);
  }

  const enFila = moverEnFila(actual, dir);
  if (enFila) return enFila;

  const main = document.querySelector(".app-shell__content");
  if (!main || !main.contains(actual)) return null;
  // Los botones internos de otra fila no compiten: se llega a la fila y de ahí a ellos.
  const lista = candidatos(main).filter((el) => el.matches(FILA) || !el.closest(FILA));
  const origen = actual.closest(FILA) || actual;
  const cerca = masCercano(origen, lista, dir);
  if (cerca) {
    const r = cerca.getBoundingClientRect();
    const lejos = Math.max(r.top - window.innerHeight, -r.bottom);
    // Zonas largas de solo lectura: se desplaza la página en vez de saltársela.
    // A mano y no nativo: con el foco en un campo, la flecha no desplaza nada.
    if (lejos <= window.innerHeight * 0.5) return cerca;
    const antes = window.scrollY;
    window.scrollBy(0, dir === "abajo" ? 160 : -160);
    return window.scrollY === antes ? cerca : origen;
  }
  // Sin nada alineado se sigue el orden del documento, siempre que quede hacia ese lado.
  const vecino = lineal(origen, lista, dir === "abajo" || dir === "derecha" ? 1 : -1);
  if (vecino) {
    const a = origen.getBoundingClientRect();
    const b = vecino.getBoundingClientRect();
    if (dir === "derecha" ? b.left >= a.right : dir === "izquierda" ? b.right <= a.left : dir === "abajo" ? b.top >= a.top : b.top <= a.top) return vecino;
  }
  if (dir === "izquierda") return enlaceActivoDelMenu();
  if (dir === "arriba" && window.scrollY < 10) return document.querySelector(".topbar .command-palette-trigger");
  return null;
}

/** true si el campo necesita esa flecha para sí (cursor, valor, lista de sugerencias). */
function campoUsaFlecha(el, dir) {
  if (el.isContentEditable || (el.tagName === "TEXTAREA" && !el.readOnly)) return true;
  if (el.tagName === "SELECT") return el.multiple || el.size > 1 || selectAbierto === el;
  if (el.tagName !== "INPUT") return false;
  if (["checkbox", "button", "submit", "reset", "file", "image", "color"].includes(el.type)) return false;
  // En un número, arriba/abajo cambiaría el valor sin querer al recorrer el formulario.
  if (el.type === "number") return dir === "izquierda" || dir === "derecha";
  if (!TEXTO_SIMPLE.includes(el.type)) return true;
  if (dir === "izquierda" || dir === "derecha") return true;
  return !!el.list || el.getAttribute("aria-expanded") === "true";
}

function escribiendo(el) {
  return !!el.closest?.('textarea, select, [contenteditable="true"], input:not([type="checkbox"]):not([type="radio"]):not([type="button"]):not([type="submit"])') || el.isContentEditable;
}

function abrirSelect(select) {
  try {
    select.showPicker();
    selectAbierto = select;
  } catch (e) {
    /* navegador sin showPicker(): Espacio o Alt+Abajo siguen abriendo la lista */
  }
}

function alPulsar(e) {
  if (e.defaultPrevented || e.isComposing || e.ctrlKey || e.metaKey || e.altKey) return;
  const el = e.target;
  if (!(el instanceof Element)) return;

  const dir = FLECHAS[e.key];
  if (dir) {
    if (e.shiftKey || campoUsaFlecha(el, dir)) return;
    // La paleta tiene su propia lista; el lienzo de la pizarra no es una rejilla de botones.
    if (el.closest(".command-palette-overlay")) return;
    const proximo = destino(dir);
    if (proximo) {
      e.preventDefault();
      if (proximo !== document.activeElement) enfocar(proximo);
    } else if (el.tagName === "SELECT") {
      // Cerrado, la flecha cambiaría el valor (y el de cliente activo se envía solo).
      e.preventDefault();
    }
    return;
  }

  if (e.key === "Enter" && !e.shiftKey) {
    if (el.tagName === "SELECT") {
      if (selectAbierto === el) {
        selectAbierto = null;
        return;
      }
      e.preventDefault();
      abrirSelect(el);
    } else if (el.matches(CLICABLES) || el.matches('input[type="checkbox"], input[type="radio"]')) {
      e.preventDefault();
      el.click();
    }
    return;
  }

  if (e.key === "Escape") {
    // En un modal, Escape ya lo cierra global.js; fuera, saca del campo para volver a las flechas.
    if (escribiendo(el) && !el.closest(CAPA) && el.isConnected) {
      el.blur();
      salioDe = el;
    }
    return;
  }

  if (escribiendo(el) || capaAbierta()) return;
  if (e.key === "/") {
    const main = document.querySelector(".app-shell__content");
    const buscador = main && [...main.querySelectorAll('input[type="search"], input[placeholder*="uscar"]')].find(visible);
    e.preventDefault();
    if (buscador) enfocar(buscador);
    else document.querySelector("[data-command-palette-trigger]")?.click();
  } else if (e.key === "?") {
    e.preventDefault();
    window.AgencyOS.openModal("atajosModal");
  } else if (e.key.toLowerCase() === "m" && !e.shiftKey) {
    if (enfocar(enlaceActivoDelMenu())) e.preventDefault();
  }
}

/** Lleva el foco al abrir modales y menús, y lo devuelve al cerrarlos. */
function vigilarCapas() {
  const origenes = new WeakMap();

  new MutationObserver((cambios) => {
    cambios.forEach(({ target: capa, oldValue }) => {
      const esModal = capa.matches(MODAL);
      // Keywords gestiona el foco de sus propios modales.
      if ((!esModal && !capa.matches("[data-dropdown-panel]")) || capa.closest(".kwx")) return;
      const estabaOculta = oldValue !== null;
      if (estabaOculta === capa.hidden) return;

      if (!capa.hidden) {
        const activo = document.activeElement;
        origenes.set(capa, activo && activo !== document.body && !capa.contains(activo) ? activo : ultimo);
        if (!esModal && !document.documentElement.classList.contains("teclado-activo")) return;
        // Un fotograma después: algunos módulos rellenan y enfocan su campo al abrir.
        requestAnimationFrame(() => {
          if (capa.hidden || capa.contains(document.activeElement)) return;
          if (!esModal) {
            enfocar(candidatos(capa)[0]);
            return;
          }
          const dialogo = capa.querySelector(".modal") || capa;
          // Sin formulario (fichas, confirmaciones) se enfoca el diálogo: Enter no debe borrar por accidente.
          const campo = [...dialogo.querySelectorAll('input:not([type="hidden"]):not([disabled]):not([readonly]), select:not([disabled]), textarea:not([disabled])')].find(visible);
          if (!campo && !dialogo.hasAttribute("tabindex")) dialogo.tabIndex = -1;
          (campo || dialogo).focus({ preventScroll: true });
        });
        return;
      }

      const origen = origenes.get(capa);
      origenes.delete(capa);
      const activo = document.activeElement;
      // Si el usuario ya puso el foco en otro sitio (clic fuera) no se le quita.
      if (origen && visible(origen) && (!activo || activo === document.body || capa.contains(activo))) {
        origen.focus({ preventScroll: true });
      }
    });
  }).observe(document.body, { attributes: true, attributeFilter: ["hidden"], attributeOldValue: true, subtree: true });
}

/** El Tab no debe escapar del modal hacia la página que queda detrás. */
function encerrarTab(e) {
  if (e.key !== "Tab" || e.defaultPrevented) return;
  const capa = capaAbierta();
  if (!capa || !capa.matches(CAPA)) return;
  const lista = candidatos(capa);
  if (!lista.length) return;
  const activo = document.activeElement;
  const dentro = capa.contains(activo);
  if (e.shiftKey && (!dentro || activo === lista[0] || !lista.includes(activo))) {
    e.preventDefault();
    lista[lista.length - 1].focus();
  } else if (!e.shiftKey && (!dentro || activo === lista[lista.length - 1])) {
    e.preventDefault();
    lista[0].focus();
  }
}

export function iniciarTeclado() {
  document.querySelector("[data-atajos-abrir]")?.addEventListener("click", () => window.AgencyOS.openModal("atajosModal"));
  prepararClicables(document);
  vigilarCapas();

  document.addEventListener("keydown", alPulsar);
  document.addEventListener("keydown", encerrarTab);
  document.addEventListener(
    "keydown",
    (e) => {
      if (FLECHAS[e.key] || e.key === "Tab" || e.key === "Enter") document.documentElement.classList.add("teclado-activo");
      // Escape con la lista abierta solo cierra la lista, no el modal que la contiene.
      if (e.key === "Escape" && selectAbierto && selectAbierto === e.target) {
        selectAbierto = null;
        e.stopImmediatePropagation();
      } else if (e.target.tagName === "SELECT" && (e.key === " " || (e.altKey && e.key === "ArrowDown"))) {
        selectAbierto = e.target;
      }
    },
    true
  );
  document.addEventListener(
    "pointerdown",
    (e) => {
      document.documentElement.classList.remove("teclado-activo");
      if (e.target.tagName === "SELECT") selectAbierto = e.target;
    },
    true
  );
  document.addEventListener("change", (e) => { if (e.target === selectAbierto) selectAbierto = null; }, true);
  document.addEventListener("focusout", (e) => { if (e.target === selectAbierto) selectAbierto = null; });
  document.addEventListener("focusin", (e) => {
    if (e.target.closest(`${CAPA}, [data-dropdown-panel]`)) return;
    ultimo = e.target;
    if (e.target.closest(".app-shell__content")) ultimoContenido = e.target;
  });
}
