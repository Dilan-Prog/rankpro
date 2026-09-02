/**
 * Usuarios del Sistema module — a flat top-level list (no client-scoping),
 * closest structural precedent is clientes.js (search+filter bar over a
 * server-rendered table, AJAX create/edit modal, twin-render row builder)
 * combined with archivos.js's read-only detail-modal pattern
 * (openArchivoDetailModal -> openUsuarioDetailModal). "Desactivar" replaces
 * "Eliminar": non-destructive, PATCH-in-place (the row stays, only its
 * Estado/actions change), never a DOM removal.
 */
(function () {
  "use strict";

  const { toast } = window.AgencyOS;
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

  const ESTADO_BADGE = {
    activo: ["Activo", "badge--success"],
    inactivo: ["Inactivo", "badge--neutral"],
  };

  function escapeHtml(value) {
    return String(value == null ? "" : value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  function jsonHeaders() {
    return {
      "Content-Type": "application/json",
      Accept: "application/json",
      "X-CSRF-TOKEN": csrfToken,
    };
  }

  function usuarioRequest(url, method, payload) {
    return fetch(url, {
      method,
      headers: jsonHeaders(),
      body: payload ? JSON.stringify(payload) : undefined,
    }).then((res) => {
      if (!res.ok) {
        return res
          .json()
          .catch(() => ({}))
          .then((data) => {
            const err = new Error(res.status === 422 ? "validation_failed" : "request_failed");
            err.status = res.status;
            err.data = data;
            throw err;
          });
      }
      return res.json();
    });
  }

  function badgeHtml(estado) {
    const [label, cls] = ESTADO_BADGE[estado] || [estado, "badge--neutral"];
    return `<span class="badge ${cls}">${label}</span>`;
  }

  function clearFieldErrors(form) {
    form.querySelectorAll("[data-error-for]").forEach((span) => {
      span.textContent = "";
    });
  }

  function renderUsuarioFieldErrors(form, errors) {
    clearFieldErrors(form);
    Object.keys(errors || {}).forEach((field) => {
      const span = form.querySelector(`[data-error-for="${field}"]`);
      if (span) span.textContent = errors[field][0];
    });
  }

  // ---------- Filters ----------

  function initUsuarioFilters() {
    const search = document.getElementById("usuarioSearch");
    const rolFilter = document.getElementById("usuarioRolFilter");
    const areaFilter = document.getElementById("usuarioAreaFilter");
    const estadoFilter = document.getElementById("usuarioEstadoFilter");
    const noResults = document.getElementById("usuarioNoResults");
    if (!search) return;

    function applyUsuarioFilters() {
      // Re-query fresh every call — rows can be inserted via AJAX create
      // after this listener was wired.
      const rows = document.querySelectorAll("[data-usuario-row]");
      const term = search.value.trim().toLowerCase();
      const rol = rolFilter ? rolFilter.value : "all";
      const area = areaFilter ? areaFilter.value : "all";
      const estado = estadoFilter ? estadoFilter.value : "all";
      let visible = 0;

      rows.forEach((row) => {
        const matchesSearch = !term || row.dataset.search.includes(term);
        const matchesRol = rol === "all" || row.dataset.role === rol;
        const matchesArea = area === "all" || row.dataset.area === area;
        const matchesEstado = estado === "all" || row.dataset.estado === estado;
        const show = matchesSearch && matchesRol && matchesArea && matchesEstado;
        row.style.display = show ? "" : "none";
        if (show) visible++;
      });

      if (noResults) noResults.hidden = visible !== 0;
    }

    search.addEventListener("input", window.AgencyOS.debounce(applyUsuarioFilters, 150));
    if (rolFilter) rolFilter.addEventListener("change", applyUsuarioFilters);
    if (areaFilter) areaFilter.addEventListener("change", applyUsuarioFilters);
    if (estadoFilter) estadoFilter.addEventListener("change", applyUsuarioFilters);

    // Exposed so upsertUsuarioRow can re-apply filters after a live create/edit/deactivate.
    window.__applyUsuarioFilters = applyUsuarioFilters;
  }

  function applyUsuarioFilters() {
    if (window.__applyUsuarioFilters) window.__applyUsuarioFilters();
  }

  // ---------- KPI / subtitle sync (server-computed once at page load; kept in sync after AJAX CRUD) ----------

  function setKpiValue(id, value) {
    const valueEl = document.getElementById(id)?.querySelector(".kpi__value");
    if (valueEl) valueEl.textContent = value;
  }

  function patchUsuarioKpis() {
    const rows = Array.from(document.querySelectorAll("[data-usuario-row]"));
    const total = rows.length;
    const activos = rows.filter((r) => r.dataset.estado === "activo").length;
    const internos = rows.filter((r) => r.dataset.area !== "externo").length;
    const rolesEnUso = new Set(rows.map((r) => r.dataset.role).filter((v) => v)).size;

    setKpiValue("usuarioKpiTotal", String(total));
    setKpiValue("usuarioKpiActivos", String(activos));
    setKpiValue("usuarioKpiInternos", String(internos));
    setKpiValue("usuarioKpiRoles", String(rolesEnUso));

    const subtitle = document.getElementById("usuariosCountSubtitle");
    if (subtitle) {
      subtitle.textContent =
        `${total} usuario${total === 1 ? "" : "s"} · ${activos} activo${activos === 1 ? "" : "s"} · ${rolesEnUso} rol${rolesEnUso === 1 ? "" : "es"} en uso`;
    }
  }

  // ---------- Row rendering (twin of index.blade.php's server-rendered <tr>) ----------

  function usuarioRowHtml(u) {
    const cuentas = u.cuentas_asignadas && u.cuentas_asignadas.length ? u.cuentas_asignadas.join(", ") : "";
    const initials = (u.name || "Usuario")
      .trim()
      .split(/\s+/)
      .filter(Boolean)
      .map((part) => part.charAt(0).toUpperCase())
      .slice(0, 2)
      .join("");
    const canDeactivate = !u.is_self && u.is_active;

    return `
      <td>
        <div style="display:flex; align-items:center; gap:10px; min-width:0;">
          <span class="usuario-avatar">${escapeHtml(initials)}</span>
          <div style="min-width:0;">
            <div style="font-weight:500; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${escapeHtml(u.name)}</div>
            <div style="font-size:var(--text-xs); color:var(--color-muted-foreground); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${escapeHtml(u.email)}</div>
          </div>
        </div>
      </td>
      <td>${escapeHtml(u.role_label || "Sin rol")}</td>
      <td>${escapeHtml(u.area_label || "—")}</td>
      <td><span class="usuario-cuentas-cell" title="${escapeHtml(cuentas)}">${escapeHtml(cuentas || "—")}</span></td>
      <td>${badgeHtml(u.is_active ? "activo" : "inactivo")}</td>
      <td><span style="font-size:var(--text-xs); color:var(--color-muted-foreground);">${escapeHtml(u.last_login_at || "Nunca")}</span></td>
      <td>
        <div style="display:flex; gap:4px;">
          <button type="button" class="btn--icon" title="Editar" data-edit-usuario="${u.id}">
            <i class="fa-solid fa-pen"></i>
          </button>
          <button type="button" class="btn--icon" title="Desactivar" style="color:var(--text-danger);" data-deactivate-usuario="${u.id}" ${canDeactivate ? "" : "hidden"}>
            <i class="fa-solid fa-user-slash"></i>
          </button>
        </div>
      </td>`;
  }

  function upsertUsuarioRow(u) {
    const tbody = document.querySelector(".table-wrap table tbody");
    if (!tbody) return;

    let row = tbody.querySelector(`[data-usuario-id="${u.id}"]`);
    if (!row) {
      row = document.createElement("tr");
      row.className = "is-clickable";
      row.setAttribute("data-usuario-row", "");
      tbody.appendChild(row);
    }

    row.dataset.usuarioId = String(u.id);
    row.dataset.search = ((u.name || "") + " " + (u.email || "")).toLowerCase();
    row.dataset.role = u.role_id != null ? String(u.role_id) : "";
    row.dataset.area = u.area || "";
    row.dataset.estado = u.is_active ? "activo" : "inactivo";
    row.dataset.usuario = JSON.stringify(u);
    row.innerHTML = usuarioRowHtml(u);

    patchUsuarioKpis();
    applyUsuarioFilters();
  }

  // ---------- Read-only detail modal ----------

  function openUsuarioDetailModal(u) {
    document.getElementById("usuarioDetailModalName").textContent = u.name;
    document.getElementById("usuarioDetailModalEmail").textContent = u.email;
    document.getElementById("usuarioDetailRol").textContent = u.role_label || "Sin rol";
    document.getElementById("usuarioDetailArea").textContent = u.area_label || "—";
    document.getElementById("usuarioDetailTelefono").textContent = u.telefono || "—";
    document.getElementById("usuarioDetailEstado").innerHTML = badgeHtml(u.is_active ? "activo" : "inactivo");
    document.getElementById("usuarioDetailUltimoAcceso").textContent = u.last_login_at || "Nunca";

    const cuentasEl = document.getElementById("usuarioDetailCuentas");
    cuentasEl.innerHTML = u.cuentas_asignadas && u.cuentas_asignadas.length
      ? u.cuentas_asignadas.map((c) => `<span class="badge badge--primary">${escapeHtml(c)}</span>`).join("")
      : '<span style="color:var(--color-muted-foreground);font-size:var(--text-sm)">Sin cuentas asignadas</span>';

    const editBtn = document.getElementById("usuarioDetailEdit");
    if (editBtn) editBtn.dataset.editUsuario = String(u.id);

    const deactivateBtn = document.getElementById("usuarioDetailDeactivate");
    if (deactivateBtn) {
      const canDeactivate = !u.is_self && u.is_active;
      deactivateBtn.hidden = !canDeactivate;
      deactivateBtn.dataset.deactivateUsuario = String(u.id);
    }

    window.AgencyOS.openModal("usuarioDetailModal");
  }

  function initUsuarioRowClicks() {
    document.addEventListener("click", (e) => {
      if (e.target.closest("[data-edit-usuario], [data-deactivate-usuario]")) return;
      const row = e.target.closest("[data-usuario-row]");
      if (!row) return;
      openUsuarioDetailModal(JSON.parse(row.dataset.usuario));
    });
  }

  // ---------- Create/edit form modal ----------

  function fillUsuarioForm(form, u) {
    form.reset();
    if (!u) return;
    form.querySelector("#uf_name").value = u.name || "";
    form.querySelector("#uf_email").value = u.email || "";
    form.querySelector("#uf_role_id").value = u.role_id != null ? String(u.role_id) : "";
    form.querySelector("#uf_area").value = u.area || "";
    form.querySelector("#uf_telefono").value = u.telefono || "";
    form.querySelector("#uf_is_active").value = u.is_active ? "1" : "0";
  }

  function openUsuarioFormModal(u) {
    const form = document.getElementById("usuarioForm");
    if (!form) return;

    const title = document.getElementById("usuarioFormModalTitle");
    const submitBtn = document.getElementById("usuarioFormSubmit");
    const createOnlyWrap = form.querySelector("[data-create-only]");
    const cuentasNote = document.getElementById("usuarioFormCuentasNote");

    clearFieldErrors(form);
    fillUsuarioForm(form, u);
    form.dataset.editingId = u?.id ?? "";

    if (createOnlyWrap) createOnlyWrap.hidden = !!u;

    if (cuentasNote) {
      if (u) {
        const cuentas = u.cuentas_asignadas && u.cuentas_asignadas.length ? u.cuentas_asignadas.join(", ") : "Sin cuentas asignadas";
        cuentasNote.textContent = `Cuentas actuales: ${cuentas}`;
        cuentasNote.hidden = false;
      } else {
        cuentasNote.hidden = true;
      }
    }

    if (title) title.textContent = u ? "Editar Usuario" : "Nuevo Usuario";
    if (submitBtn) submitBtn.innerHTML = u
      ? '<i class="fa-solid fa-check"></i> Guardar Cambios'
      : '<i class="fa-solid fa-check"></i> Crear Usuario';

    window.AgencyOS.openModal("usuarioFormModal");
  }

  function initUsuarioForm() {
    const form = document.getElementById("usuarioForm");
    if (!form) return;

    document.querySelectorAll("[data-open-usuario-modal]").forEach((btn) => {
      btn.addEventListener("click", () => openUsuarioFormModal(null));
    });

    document.addEventListener("click", (e) => {
      const btn = e.target.closest("[data-edit-usuario]");
      if (!btn) return;
      const row = btn.closest("[data-usuario-row]");
      const u = row
        ? JSON.parse(row.dataset.usuario)
        : JSON.parse(document.querySelector(`[data-usuario-row][data-usuario-id="${btn.dataset.editUsuario}"]`)?.dataset.usuario || "null");
      if (!u) return;
      window.AgencyOS.closeModal("usuarioDetailModal");
      openUsuarioFormModal(u);
    });

    form.addEventListener("submit", (e) => {
      e.preventDefault();
      const editingId = form.dataset.editingId;
      const isEditing = !!editingId;
      const url = isEditing
        ? form.dataset.updateActionTemplate.replace("__ID__", editingId)
        : form.dataset.storeAction;
      const method = isEditing ? "PUT" : "POST";

      const payload = Object.fromEntries(new FormData(form).entries());
      if (isEditing) {
        // Editing never touches the password — exclude these entirely
        // rather than relying on the field being visually hidden.
        delete payload.password;
        delete payload.password_confirmation;
      }

      const submitBtn = document.getElementById("usuarioFormSubmit");
      if (submitBtn) submitBtn.disabled = true;

      usuarioRequest(url, method, payload)
        .then((u) => {
          upsertUsuarioRow(u);
          window.AgencyOS.closeModal("usuarioFormModal");
          toast(isEditing ? "Usuario actualizado." : "Usuario creado.", "success");
        })
        .catch((err) => {
          if (err.message === "validation_failed") {
            renderUsuarioFieldErrors(form, err.data.errors || {});
            toast("Revisa los campos marcados.", "error");
          } else {
            toast("No se pudo guardar el usuario.", "error");
          }
        })
        .finally(() => {
          if (submitBtn) submitBtn.disabled = false;
        });
    });
  }

  // ---------- Desactivar — native window.confirm(), non-destructive PATCH-in-place ----------

  function deactivateUsuario(id) {
    const row = document.querySelector(`[data-usuario-row][data-usuario-id="${id}"]`);
    const u = row ? JSON.parse(row.dataset.usuario) : null;
    const name = u ? u.name : "este usuario";

    if (!window.confirm(`¿Desactivar a ${name}? Perderá acceso al sistema, pero su historial se conserva.`)) return;

    usuarioRequest(`/admin/usuarios/${id}/desactivar`, "POST")
      .then((updated) => {
        upsertUsuarioRow(updated);
        window.AgencyOS.closeModal("usuarioDetailModal");
        toast("Usuario desactivado.", "success");
      })
      .catch((err) => {
        const message = err.data && err.data.message ? err.data.message : "No se pudo desactivar el usuario.";
        toast(message, "error");
      });
  }

  function initUsuarioDeactivate() {
    document.addEventListener("click", (e) => {
      const btn = e.target.closest("[data-deactivate-usuario]");
      if (!btn) return;
      e.stopPropagation();
      deactivateUsuario(btn.dataset.deactivateUsuario);
    });
  }

  document.addEventListener("shell:ready", () => {
    initUsuarioFilters();
    initUsuarioRowClicks();
    initUsuarioForm();
    initUsuarioDeactivate();
  });
})();
