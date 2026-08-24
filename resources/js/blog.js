/**
 * Blog module.
 *
 * Índice: multi-filtro en cliente (búsqueda + clúster + estado + avisos SEO)
 * sobre la tabla renderizada en servidor.
 *
 * Formulario: contadores de caracteres de meta_title/meta_description con los
 * límites de la SERP (60 y 70-160), contador de palabras y vista previa en
 * vivo del Markdown contra admin.blog.preview (que devuelve JSON).
 */
(function () {
  "use strict";

  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

  // ---------- Índice ----------

  function initFiltros() {
    const search = document.getElementById("blogSearch");
    const clusterFilter = document.getElementById("blogClusterFilter");
    const estadoFilter = document.getElementById("blogEstadoFilter");
    const avisosFilter = document.getElementById("blogAvisosFilter");
    const rows = document.querySelectorAll("[data-blog-row]");
    const noResults = document.getElementById("blogNoResults");
    if (!rows.length) return;

    function applyFilters() {
      const term = (search?.value || "").trim().toLowerCase();
      const cluster = clusterFilter?.value || "all";
      const estado = estadoFilter?.value || "all";
      const avisos = avisosFilter?.value || "all";
      let visible = 0;

      rows.forEach((row) => {
        const show =
          (!term || row.dataset.search.includes(term)) &&
          (cluster === "all" || row.dataset.cluster === cluster) &&
          (estado === "all" || row.dataset.estado === estado) &&
          (avisos === "all" || row.dataset.avisos === avisos);
        row.style.display = show ? "" : "none";
        if (show) visible++;
      });

      if (noResults) noResults.hidden = visible !== 0;
    }

    if (search) {
      search.addEventListener("input", window.AgencyOS.debounce(applyFilters, 150));
    }
    [clusterFilter, estadoFilter, avisosFilter].forEach(
      (el) => el && el.addEventListener("change", applyFilters)
    );
  }

  // ---------- Contadores ----------

  function initContadores() {
    document.querySelectorAll("[data-blog-contado]").forEach((campo) => {
      const nombre = campo.dataset.blogContado;
      const salida = document.querySelector(`[data-blog-contador="${nombre}"]`);
      if (!salida) return;

      const min = parseInt(salida.dataset.min || "0", 10);
      const max = parseInt(salida.dataset.max || "0", 10);

      function actualizar() {
        const largo = campo.value.length;
        salida.textContent = `${largo}/${max}`;
        salida.classList.toggle("is-invalid", largo > max || largo < min);
      }

      campo.addEventListener("input", actualizar);
      actualizar();
    });
  }

  function contarPalabras(texto) {
    const limpio = texto.replace(/[#>*_`\-\[\]()!]/g, " ").trim();
    if (!limpio) return 0;
    return limpio.split(/\s+/).length;
  }

  // ---------- Editor: contador de palabras + vista previa ----------

  function initEditor() {
    const textarea = document.querySelector("[data-blog-markdown]");
    if (!textarea) return;

    const salidaPalabras = document.querySelector("[data-blog-palabras]");
    const preview = document.querySelector("[data-blog-preview-body]");
    const boton = document.querySelector("[data-blog-preview-refresh]");
    const url = preview?.dataset.previewUrl;

    function actualizarPalabras() {
      if (!salidaPalabras) return;
      salidaPalabras.textContent = `${contarPalabras(textarea.value)} palabras`;
    }

    function renderizar() {
      if (!preview || !url) return;
      const contenido = textarea.value;
      if (!contenido.trim()) {
        preview.innerHTML =
          '<p class="blog-editor__placeholder">Escribe Markdown a la izquierda para ver el resultado aquí.</p>';
        return;
      }

      fetch(url, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
          "X-CSRF-TOKEN": csrfToken || "",
          "X-Requested-With": "XMLHttpRequest",
        },
        body: JSON.stringify({ contenido: contenido }),
      })
        .then((res) => (res.ok ? res.json() : Promise.reject(res)))
        .then((data) => {
          preview.innerHTML = data.html;
          if (salidaPalabras && typeof data.palabras === "number") {
            salidaPalabras.textContent = `${data.palabras} palabras`;
          }
        })
        .catch(() => {
          preview.innerHTML =
            '<p class="blog-editor__placeholder">No se pudo generar la vista previa.</p>';
        });
    }

    const renderizarDiferido = window.AgencyOS.debounce(renderizar, 800);

    textarea.addEventListener("input", () => {
      actualizarPalabras();
      renderizarDiferido();
    });

    if (boton) boton.addEventListener("click", renderizar);

    actualizarPalabras();
    if (textarea.value.trim()) renderizar();
  }

  // ---------- Slug ----------

  function initSlugPreview() {
    const slug = document.getElementById("slug");
    const salida = document.querySelector("[data-blog-slug-preview]");
    if (!slug || !salida) return;

    slug.addEventListener("input", () => {
      salida.textContent = slug.value || "mi-articulo";
    });
  }

  function init() {
    initFiltros();
    initContadores();
    initEditor();
    initSlugPreview();
  }

  document.addEventListener("shell:ready", init);
})();
