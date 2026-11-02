/**
 * Módulo Identidad de marca (admin): paleta editable, vista previa de
 * tipografía con Google Fonts cargada solo aquí, vista previa del logo
 * elegido y aviso de cambios sin guardar. El guardado es un envío normal.
 */
(function () {
  "use strict";

  const root = document.querySelector("[data-identidad]");
  const form = root && root.querySelector("[data-identidad-form]");
  if (!form) return;

  const HEX = /^#[0-9A-Fa-f]{6}$/;

  // ---- Paleta ----
  const paleta = form.querySelector("[data-paleta]");
  const vacia = form.querySelector("[data-paleta-vacia]");
  const barra = form.querySelector("[data-paleta-barra]");
  const plantilla = form.querySelector("[data-color-plantilla]");

  // Los índices se renumeran para que el servidor reciba colores[0], colores[1]… sin huecos al quitar filas.
  function renumerar() {
    paleta.querySelectorAll("[data-color]").forEach((fila, i) => {
      fila.querySelector("[data-color-nombre]").name = `colores[${i}][nombre]`;
      fila.querySelector("[data-color-hex]").name = `colores[${i}][hex]`;
    });
    if (vacia) vacia.hidden = paleta.children.length > 0;
    pintarBarra();
    pintarPrevia();
  }

  function pintarBarra() {
    barra.replaceChildren();
    paleta.querySelectorAll("[data-color-hex]").forEach((input) => {
      if (!HEX.test(input.value)) return;
      const trozo = document.createElement("span");
      trozo.style.backgroundColor = input.value;
      barra.appendChild(trozo);
    });
    barra.hidden = barra.children.length === 0;
  }

  paleta.addEventListener("input", (e) => {
    const fila = e.target.closest("[data-color]");
    if (!fila) return;
    const selector = fila.querySelector("[data-color-selector]");
    const hex = fila.querySelector("[data-color-hex]");
    if (e.target === selector) {
      hex.value = selector.value.toUpperCase();
    } else if (e.target === hex) {
      // Solo se sincroniza el selector si el texto ya es un hex válido; el servidor valida igualmente.
      hex.classList.toggle("is-invalid", hex.value !== "" && !HEX.test(hex.value));
      if (HEX.test(hex.value)) selector.value = hex.value;
    }
    pintarBarra();
    pintarPrevia();
  });

  paleta.addEventListener("click", (e) => {
    const quitar = e.target.closest("[data-color-quitar]");
    if (!quitar) return;
    quitar.closest("[data-color]").remove();
    renumerar();
    marcarSucio();
  });

  form.querySelector("[data-color-agregar]").addEventListener("click", () => {
    paleta.appendChild(plantilla.content.cloneNode(true));
    renumerar();
    marcarSucio();
  });

  // ---- Tipografía ----
  const selTitulos = form.querySelector('[data-fuente="titulos"]');
  const selTexto = form.querySelector('[data-fuente="texto"]');
  const pTitulo = form.querySelector("[data-previa-titulo]");
  const pTexto = form.querySelector("[data-previa-texto]");
  const pBoton = form.querySelector("[data-previa-boton]");
  const slogan = form.querySelector("[data-identidad-slogan]");

  function cargarFuente(select) {
    const opcion = select.selectedOptions[0];
    if (!opcion || !opcion.dataset.url) return;
    const id = "identidad-fuente-" + opcion.value.replace(/\s+/g, "-");
    if (document.getElementById(id) || document.querySelector(`link[data-identidad-fuente="${opcion.value}"]`)) return;
    const link = document.createElement("link");
    link.id = id;
    link.rel = "stylesheet";
    link.href = opcion.dataset.url;
    document.head.appendChild(link);
  }

  function pintarPrevia() {
    const familia = (sel) => (sel.value ? `'${sel.value}', sans-serif` : "");
    pTitulo.style.fontFamily = familia(selTitulos);
    pTexto.style.fontFamily = familia(selTexto);
    pBoton.style.fontFamily = familia(selTexto);
    pTitulo.textContent = slogan.value.trim() || "Así se ve la marca";
    const primero = paleta.querySelector("[data-color-hex]");
    pBoton.style.backgroundColor = primero && HEX.test(primero.value) ? primero.value : "";
  }

  [selTitulos, selTexto].forEach((sel) => {
    sel.addEventListener("change", () => { cargarFuente(sel); pintarPrevia(); });
  });
  slogan.addEventListener("input", pintarPrevia);

  // ---- Logo: vista previa local antes de guardar ----
  const logoInput = form.querySelector("[data-identidad-logo-input]");
  const logoCaja = form.querySelector(".identidad__logo");
  logoInput.addEventListener("change", () => {
    const archivo = logoInput.files[0];
    if (!archivo) return;
    if (archivo.size > 2 * 1024 * 1024) {
      window.alert("El logo no puede pesar más de 2 MB.");
      logoInput.value = "";
      return;
    }
    let img = logoCaja.querySelector("img");
    if (!img) {
      logoCaja.replaceChildren();
      img = document.createElement("img");
      img.alt = "Vista previa del logo";
      logoCaja.appendChild(img);
    }
    img.src = URL.createObjectURL(archivo);
  });

  // ---- Quitar logo: borra el archivo del servidor, así que pide confirmación ----
  const formQuitarLogo = document.querySelector("[data-identidad-quitar-logo-form]");
  if (formQuitarLogo) {
    formQuitarLogo.addEventListener("submit", (e) => {
      if (!window.confirm("Se borrará el logo del servidor. ¿Continuar?")) {
        e.preventDefault();
        return;
      }
      // Es un envío aparte del formulario principal: no debe avisar de cambios sin guardar.
      sucio = false;
    });
  }

  // ---- Cambios sin guardar ----
  const aviso = form.querySelector("[data-identidad-sucio]");
  let sucio = false;
  function marcarSucio() {
    sucio = true;
    if (aviso) aviso.hidden = false;
  }
  form.addEventListener("input", marcarSucio);
  form.addEventListener("change", marcarSucio);
  form.addEventListener("submit", (e) => {
    const invalido = form.querySelector("[data-color-hex].is-invalid");
    if (invalido) {
      e.preventDefault();
      invalido.focus();
      return;
    }
    sucio = false;
    const boton = form.querySelector("[data-identidad-guardar]");
    boton.disabled = true;
    boton.textContent = "Guardando…";
  });
  window.addEventListener("beforeunload", (e) => {
    if (sucio) { e.preventDefault(); e.returnValue = ""; }
  });

  renumerar();
  sucio = false;
  if (aviso) aviso.hidden = true;
})();
