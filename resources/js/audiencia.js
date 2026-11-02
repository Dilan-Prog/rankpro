/**
 * Módulo Audiencia (admin): abre el modal único en modo crear o editar y
 * confirma la eliminación. Los datos de cada tarjeta viajan en data-* del
 * botón Editar; crear, editar, borrar y reordenar son envíos normales.
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

  function abrir(datos) {
    form.reset();
    if (datos) {
      form.action = datos.url;
      metodo.disabled = false;
      titulo.textContent = "Editar buyer persona";
      campos.forEach((c) => { form.elements[c].value = datos[c] || ""; });
      botonEliminar.hidden = false;
      formEliminar.action = datos.url;
    } else {
      form.action = form.dataset.urlCrear;
      metodo.disabled = true;
      titulo.textContent = "Nueva buyer persona";
      botonEliminar.hidden = true;
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

  // Evita doble envío al guardar.
  form.addEventListener("submit", () => {
    const boton = form.querySelector('button[type="submit"]');
    boton.disabled = true;
  });
})();
