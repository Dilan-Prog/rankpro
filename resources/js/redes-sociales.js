/**
 * Configuración > Redes sociales: agregar, quitar y reordenar filas. Guardar es un
 * formulario clásico; el orden de las filas en la página es el que se guarda.
 */
(function () {
  "use strict";

  const form = document.querySelector("[data-configuracion-redes]");
  if (!form) return;

  const lista = form.querySelector("[data-redes-lista]");
  const plantilla = form.querySelector("[data-redes-plantilla]");
  const agregar = form.querySelector("[data-redes-agregar]");
  const maximo = Number(form.dataset.maximo);
  let siguiente = Number(form.dataset.siguiente);

  const sincronizar = () => {
    agregar.disabled = lista.children.length >= maximo;
  };

  agregar.addEventListener("click", () => {
    lista.insertAdjacentHTML("beforeend", plantilla.innerHTML.replaceAll("__I__", String(siguiente++)));
    sincronizar();
    lista.lastElementChild.querySelector("input[type=radio]").focus();
  });

  lista.addEventListener("click", (e) => {
    const fila = e.target.closest("[data-red-fila]");
    if (!fila) return;

    if (e.target.closest("[data-red-quitar]")) {
      fila.remove();
      sincronizar();
    } else if (e.target.closest("[data-red-subir]")) {
      fila.previousElementSibling?.before(fila);
    } else if (e.target.closest("[data-red-bajar]")) {
      fila.nextElementSibling?.after(fila);
    }
  });

  // El ejemplo del enlace cambia con el icono elegido.
  lista.addEventListener("change", (e) => {
    if (e.target.matches("input[type=radio]")) {
      e.target.closest("[data-red-fila]").querySelector("[data-red-url]").placeholder = e.target.dataset.ejemplo;
    }
  });

  sincronizar();
})();
