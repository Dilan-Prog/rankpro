/**
 * Tarjeta «Portal del cliente» del Resumen de cliente: crear, copiar y revocar enlaces.
 * El enlace completo solo se pinta al crearlo (el servidor guarda únicamente su hash) y los mensajes
 * quedan en pantalla, no en un toast.
 */
import { formatearFecha } from "./utils/fecha.js";

(function () {
  "use strict";

  const tarjeta = document.querySelector("[data-portal-adm]");
  if (!tarjeta) return;

  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
  const form = tarjeta.querySelector("[data-portal-form]");
  const lista = tarjeta.querySelector("[data-portal-lista]");
  const nuevo = tarjeta.querySelector("[data-portal-nuevo]");
  const urlInput = tarjeta.querySelector("[data-portal-url]");
  const mensaje = tarjeta.querySelector("[data-portal-mensaje]");

  function decir(texto, esError) {
    mensaje.hidden = !texto;
    mensaje.textContent = texto || "";
    mensaje.classList.toggle("portal-adm__mensaje--error", !!esError);
  }

  function pedir(url, method, payload) {
    return fetch(url, {
      method,
      headers: { "Content-Type": "application/json", Accept: "application/json", "X-CSRF-TOKEN": csrfToken },
      body: payload ? JSON.stringify(payload) : undefined,
    }).then((res) =>
      res
        .json()
        .catch(() => ({}))
        .then((body) => {
          if (!res.ok) {
            const error = new Error("peticion_fallida");
            error.body = body;
            throw error;
          }
          return body;
        })
    );
  }

  // textContent y no innerHTML: la etiqueta y el correo los escribe una persona.
  function pintarFila(acceso) {
    const li = document.createElement("li");
    li.className = "portal-adm__fila";
    li.dataset.portalId = acceso.id;
    li.dataset.estado = "vigente";

    const estado = document.createElement("span");
    estado.className = "portal-adm__estado portal-adm__estado--vigente";
    estado.textContent = "Vigente";

    const meta = document.createElement("span");
    meta.className = "portal-adm__meta";
    const nombre = document.createElement("strong");
    nombre.textContent = acceso.etiqueta || "Sin etiqueta";
    meta.appendChild(nombre);
    if (acceso.correo) meta.appendChild(document.createTextNode(" · " + acceso.correo));
    meta.appendChild(document.createElement("br"));
    meta.appendChild(document.createTextNode("vence " + formatearFecha(acceso.expira_en) + " · 0 accesos · sin accesos aún"));

    const boton = document.createElement("button");
    boton.type = "button";
    boton.className = "btn btn--secondary btn--sm";
    boton.dataset.portalRevocar = "";
    boton.textContent = "Revocar";

    li.append(estado, meta, boton);

    return li;
  }

  form.addEventListener("submit", (e) => {
    e.preventDefault();
    const boton = form.querySelector("[data-portal-crear]");
    boton.disabled = true;
    decir("");

    pedir(tarjeta.dataset.portalStoreUrl, "POST", {
      etiqueta: form.elements.etiqueta.value,
      correo: form.elements.correo.value,
      vigencia_dias: Number(form.elements.vigencia_dias.value),
    })
      .then((data) => {
        boton.disabled = false;
        urlInput.value = data.url;
        nuevo.hidden = false;
        urlInput.focus();
        urlInput.select();
        const vacio = lista.querySelector("[data-portal-vacio]");
        if (vacio) vacio.remove();
        lista.prepend(pintarFila(data.acceso));
        form.reset();
      })
      .catch((error) => {
        boton.disabled = false;
        const errores = error.body && error.body.errors;
        const primero = errores ? Object.values(errores)[0][0] : null;
        decir(primero || (error.body && error.body.message) || "No se pudo crear el enlace.", true);
      });
  });

  tarjeta.addEventListener("click", (e) => {
    if (e.target.closest("[data-portal-copiar]")) {
      urlInput.select();
      const listo = () => decir("Enlace copiado.", false);
      if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(urlInput.value).then(listo, () => document.execCommand("copy") && listo());
      else if (document.execCommand("copy")) listo();
      return;
    }

    const revocar = e.target.closest("[data-portal-revocar]");
    if (!revocar) return;
    const li = revocar.closest("[data-portal-id]");
    if (!li || !window.confirm("¿Revocar este enlace? Quien lo tenga dejará de poder abrirlo.")) return;

    revocar.disabled = true;
    pedir(tarjeta.dataset.portalDestroyUrl.replace("__ID__", li.dataset.portalId), "DELETE")
      .then(() => {
        li.dataset.estado = "revocado";
        const estado = li.querySelector(".portal-adm__estado");
        estado.textContent = "Revocado";
        estado.className = "portal-adm__estado portal-adm__estado--revocado";
        revocar.remove();
        decir("Enlace revocado.", false);
      })
      .catch((error) => {
        revocar.disabled = false;
        decir((error.body && error.body.message) || "No se pudo revocar el enlace.", true);
      });
  });
})();
