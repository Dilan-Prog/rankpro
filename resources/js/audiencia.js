/**
 * Módulo Audiencia (admin): abre el modal único en modo crear o editar y
 * confirma la eliminación. Los datos de cada tarjeta viajan en data-* del
 * botón Editar; crear, editar, borrar y reordenar son envíos normales.
 *
 * El asistente de 4 preguntas pide 3 borradores al servidor (plantilla de
 * texto, sin IA) y los muestra; elegir uno solo PRECARGA el formulario de
 * crear: nada se guarda hasta que el usuario pulse Guardar.
 */
(function () {
  "use strict";

  const root = document.querySelector("[data-audiencia]");
  if (!root) return;

  const form = document.querySelector("[data-persona-form]");
  if (!form) return;

  const titulo = document.querySelector("[data-persona-modal-titulo]");
  const metodo = form.querySelector("[data-persona-method]");
  const botonEliminar = form.querySelector("[data-persona-eliminar]");
  const formEliminar = document.querySelector("[data-persona-form-eliminar]");
  const campos = ["nombre", "perfil", "dolores", "mensaje", "canales"];

  const avisoBorrador = document.querySelector("[data-persona-aviso-borrador]");

  // `esBorrador` precarga el formulario en modo CREAR con un texto generado
  // (sin url ni botón Eliminar: todavía no existe en la base de datos).
  function abrir(datos, esBorrador) {
    form.reset();
    if (avisoBorrador) avisoBorrador.hidden = !esBorrador;
    form.action = form.dataset.urlCrear;
    metodo.disabled = true;
    botonEliminar.hidden = true;
    titulo.textContent = esBorrador ? "Borrador de buyer persona" : "Nueva buyer persona";
    if (datos) {
      campos.forEach((c) => { form.elements[c].value = datos[c] || ""; });
    }
    if (datos && !esBorrador) {
      form.action = datos.url;
      metodo.disabled = false;
      titulo.textContent = "Editar buyer persona";
      botonEliminar.hidden = false;
      formEliminar.action = datos.url;
    }
    window.AgencyOS.openModal("personaModal");
    form.elements.nombre.focus();
  }

  root.querySelectorAll("[data-persona-nueva]").forEach((btn) => btn.addEventListener("click", () => abrir(null)));
  root.querySelectorAll("[data-persona-editar]").forEach((btn) => btn.addEventListener("click", () => abrir(btn.dataset)));

  // Borrar es destructivo: se pide confirmación antes de enviar.
  botonEliminar.addEventListener("click", () => {
    const nombre = form.elements.nombre.value || "esta buyer persona";
    if (window.confirm(`Se eliminará «${nombre}». ¿Continuar?`)) formEliminar.submit();
  });

  // ---- Asistente de 4 preguntas ----
  const gen = document.querySelector("[data-generador-form]");
  if (gen) {
    const pasos = Array.from(gen.querySelectorAll("[data-generador-paso]"));
    const num = gen.querySelector("[data-generador-num]");
    const barra = gen.querySelector("[data-generador-barra]");
    const error = gen.querySelector("[data-generador-error]");
    const btnAtras = gen.querySelector("[data-generador-atras]");
    const btnSiguiente = gen.querySelector("[data-generador-siguiente]");
    const resultado = document.querySelector("[data-generador-resultado]");
    const lista = document.querySelector("[data-generador-lista]");
    const urlGenerar = root.querySelector("[data-generador-abrir]").dataset.url;
    let paso = 0;
    let enviando = false;

    function mostrarError(texto) {
      error.textContent = texto || "";
      error.hidden = !texto;
    }

    function pintarPaso() {
      pasos.forEach((p, i) => p.classList.toggle("is-activo", i === paso));
      num.textContent = "Pregunta " + (paso + 1) + " de " + pasos.length;
      barra.style.width = ((paso + 1) / pasos.length) * 100 + "%";
      btnAtras.textContent = paso === 0 ? "Cancelar" : "Atrás";
      btnSiguiente.textContent = paso === pasos.length - 1 ? "Armar borradores" : "Siguiente";
      mostrarError("");
      const campo = pasos[paso].querySelector("input, textarea");
      if (campo) campo.focus();
    }

    function reiniciar() {
      gen.reset();
      paso = 0;
      gen.hidden = false;
      resultado.hidden = true;
      lista.replaceChildren();
      pintarPaso();
    }

    // Todo el texto generado entra con textContent: nunca se interpreta como HTML.
    function tarjetaBorrador(b) {
      const art = document.createElement("article");
      art.className = "audiencia__borrador";
      const h = document.createElement("h3");
      h.className = "audiencia__nombre";
      h.textContent = b.nombre;
      const perfil = document.createElement("p");
      perfil.className = "audiencia__texto audiencia__texto--suave";
      perfil.textContent = b.perfil;
      const ul = document.createElement("ul");
      ul.className = "audiencia__dolores";
      b.dolores.split("\n").filter(Boolean).forEach((d) => {
        const li = document.createElement("li");
        li.textContent = d;
        ul.appendChild(li);
      });
      const mensaje = document.createElement("p");
      mensaje.className = "audiencia__texto";
      mensaje.textContent = b.mensaje;
      const usar = document.createElement("button");
      usar.type = "button";
      usar.className = "btn btn--primary btn--sm";
      usar.textContent = "Usar este borrador";
      usar.addEventListener("click", () => {
        window.AgencyOS.closeModal("generadorModal");
        abrir({ nombre: b.nombre, perfil: b.perfil, dolores: b.dolores, mensaje: b.mensaje, canales: b.canales }, true);
      });
      art.append(h, perfil, ul, mensaje, usar);
      return art;
    }

    async function generar() {
      if (enviando) return;
      enviando = true;
      btnSiguiente.disabled = true;
      mostrarError("");
      try {
        const resp = await fetch(urlGenerar, {
          method: "POST",
          headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
          body: new FormData(gen),
        });
        const json = await resp.json().catch(() => ({}));
        if (!resp.ok) {
          const detalle = json.errors ? Object.values(json.errors)[0][0] : json.message;
          mostrarError(detalle || "No se pudieron armar los borradores. Inténtalo de nuevo.");
          return;
        }
        lista.replaceChildren(...json.personas.map(tarjetaBorrador));
        gen.hidden = true;
        resultado.hidden = false;
      } catch (e) {
        mostrarError("No se pudo conectar con el servidor. Inténtalo de nuevo.");
      } finally {
        enviando = false;
        btnSiguiente.disabled = false;
      }
    }

    btnSiguiente.addEventListener("click", () => {
      const campo = pasos[paso].querySelector("input, textarea");
      if (!campo.value.trim()) {
        mostrarError("Responde esta pregunta para continuar.");
        campo.focus();
        return;
      }
      if (paso === pasos.length - 1) { generar(); return; }
      paso += 1;
      pintarPaso();
    });

    btnAtras.addEventListener("click", () => {
      if (paso === 0) { window.AgencyOS.closeModal("generadorModal"); return; }
      paso -= 1;
      pintarPaso();
    });

    // Enter avanza en los campos de una línea (en los textarea inserta salto).
    gen.addEventListener("keydown", (e) => {
      if (e.key === "Enter" && e.target.tagName === "INPUT") {
        e.preventDefault();
        btnSiguiente.click();
      }
    });

    resultado.querySelector("[data-generador-reiniciar]").addEventListener("click", reiniciar);

    root.querySelectorAll("[data-generador-abrir]").forEach((btn) => btn.addEventListener("click", () => {
      reiniciar();
      window.AgencyOS.openModal("generadorModal");
      pintarPaso();
    }));
  }

  // Evita doble envío al guardar.
  form.addEventListener("submit", () => {
    const boton = form.querySelector('button[type="submit"]');
    boton.disabled = true;
  });
})();
