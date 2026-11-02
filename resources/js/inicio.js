// Módulo «inicio»: marcar tareas como hechas desde el resumen.
(() => {
  const bloque = document.querySelector("[data-inicio-tareas]");
  if (!bloque) return;

  const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
  const barra = bloque.querySelector("[data-inicio-barra]");
  const contador = bloque.querySelector("[data-inicio-progreso]");
  const indicador = bloque.querySelector('[role="progressbar"]');

  function pintarProgreso({ hechas, total, porcentaje }) {
    barra.style.width = `${porcentaje}%`;
    indicador.setAttribute("aria-valuenow", String(porcentaje));
    contador.textContent = `${hechas} de ${total} hechas`;
  }

  bloque.addEventListener("change", async (e) => {
    const casilla = e.target.closest("[data-inicio-check]");
    if (!casilla) return;

    const fila = casilla.closest("[data-tarea-id]");
    casilla.disabled = true;

    try {
      const res = await fetch(`${bloque.dataset.urlBase}/${fila.dataset.tareaId}/completar`, {
        method: "PATCH",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
          "X-CSRF-TOKEN": csrf,
        },
        body: JSON.stringify({ hecha: casilla.checked }),
      });
      if (!res.ok) throw new Error(String(res.status));

      const datos = await res.json();
      pintarProgreso(datos.progreso);
      // Se tacha en lugar de quitarla: deja deshacer un clic accidental sin recargar.
      fila.classList.toggle("is-hecha", casilla.checked);
    } catch {
      // Sin respuesta válida el servidor no cambió nada: se devuelve la casilla a su estado real.
      casilla.checked = !casilla.checked;
    } finally {
      casilla.disabled = false;
    }
  });
})();
