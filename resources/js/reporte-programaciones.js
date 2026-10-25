/**
 * Reportes programados: modal de alta/edición y acciones de fila.
 *
 * Toda acción recarga la página al terminar (éxito o fallo): el resultado vive
 * en la base y se pinta desde el servidor, así nunca depende de un toast que
 * desaparece a los pocos segundos.
 */
(function () {
  "use strict";

  const { toast, openModal } = window.AgencyOS;
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

  const tabla = document.querySelector("[data-programaciones-tabla]");
  const form = document.getElementById("programacionForm");
  if (!tabla || !form) return;

  const urlBase = tabla.dataset.urlBase;
  let editandoId = null;

  function request(url, method, payload) {
    return fetch(url, {
      method,
      headers: { "Content-Type": "application/json", Accept: "application/json", "X-CSRF-TOKEN": csrfToken },
      body: method === "GET" ? undefined : JSON.stringify(payload || {}),
    }).then((res) =>
      res
        .json()
        .catch(() => ({}))
        .then((body) => {
          if (!res.ok) {
            const error = new Error(body.message || "request_failed");
            error.body = body;
            throw error;
          }
          return body;
        })
    );
  }

  function limpiarErrores() {
    form.querySelectorAll("[data-error-for]").forEach((el) => (el.textContent = ""));
  }

  function abrirModal(datos) {
    editandoId = datos ? datos.id : null;
    form.reset();
    limpiarErrores();
    document.querySelector("[data-programacion-titulo]").textContent = datos ? "Editar programación" : "Nueva programación";

    if (datos) {
      form.elements.cliente_id.value = datos.cliente_id;
      form.elements.area.value = datos.area;
      form.elements.plantilla_id.value = datos.plantilla_id || "";
      form.elements.asunto.value = datos.asunto;
      form.elements.destinatarios.value = (datos.destinatarios || []).join("\n");
      form.elements.dia_mes.value = datos.dia_mes;
      form.elements.hora.value = datos.hora;
    }

    openModal("programacionModal");
  }

  document.querySelector("[data-programacion-nueva]").addEventListener("click", () => abrirModal(null));

  form.addEventListener("submit", (e) => {
    e.preventDefault();
    limpiarErrores();

    const payload = {};
    new FormData(form).forEach((value, key) => {
      if (key !== "_token") payload[key] = value;
    });

    const submit = form.querySelector('button[type="submit"]');
    submit.disabled = true;

    const url = editandoId ? `${urlBase}/${editandoId}` : form.dataset.storeAction;

    request(url, editandoId ? "PUT" : "POST", payload)
      .then(() => window.location.reload())
      .catch((error) => {
        submit.disabled = false;
        const errores = error.body && error.body.errors;
        if (errores) {
          Object.keys(errores).forEach((campo) => {
            const slot = form.querySelector(`[data-error-for="${campo}"]`) || form.querySelector(`[data-error-for="${campo.split(".")[0]}"]`);
            if (slot && !slot.textContent) slot.textContent = errores[campo][0];
          });
          return;
        }
        toast("No se pudo guardar la programación.", "error");
      });
  });

  tabla.addEventListener("click", (e) => {
    const btn = e.target.closest("[data-accion]");
    if (!btn) return;

    const fila = btn.closest("[data-programacion-row]");
    const id = fila.dataset.id;
    const accion = btn.dataset.accion;

    if (accion === "editar") {
      abrirModal(JSON.parse(fila.dataset.programacion));
      return;
    }

    if (accion === "eliminar") {
      if (!window.confirm("Se eliminará esta programación. Los correos ya enviados se conservan. ¿Continuar?")) return;
      request(`${urlBase}/${id}`, "DELETE")
        .then(() => window.location.reload())
        .catch(() => toast("No se pudo eliminar la programación.", "error"));
      return;
    }

    if (accion === "enviar-ahora" && !window.confirm("Se enviará ahora el reporte del mes anterior a los destinatarios del cliente. ¿Continuar?")) return;

    btn.disabled = true;
    // Éxito o fallo, se recarga: el servidor ya guardó el resultado y lo pinta fijo.
    request(`${urlBase}/${id}/${accion}`, "POST")
      .catch(() => null)
      .then(() => window.location.reload());
  });
})();
