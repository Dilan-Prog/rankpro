/**
 * Roles y Permisos module — AJAX-modal CRUD (create/edit/delete) over a real
 * `role_permissions` list, plus a read-only "Matriz de permisos" tab that is
 * a second view of the exact same data. Closest structural precedents:
 * usuarios.js (AJAX create/edit modal pattern, read-only detail modal,
 * twin-render card/row builder) and desarrollo.js's initDesarrolloSubTabs()
 * (2-tab switcher).
 *
 * IMPORTANT: there is no 2-tier permission model here — a role simply has a
 * list of Permission ids, one per app module. The "Módulos visibles"
 * checklist in the create/edit modal IS the permission list; there is no
 * separate set of action-flags to toggle.
 */
(function () {
  "use strict";

  const { toast } = window.AgencyOS;
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

  // Full list of real Permission rows ({id, label, module}), embedded by the
  // server on #rolesGrid — used to render the on/off chip list in every
  // card and in the read-only detail modal without a second lookup.
  const permisosList = (() => {
    const grid = document.getElementById("rolesGrid");
    if (!grid) return [];
    try {
      return JSON.parse(grid.dataset.permisos || "[]");
    } catch (e) {
      return [];
    }
  })();

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

  function roleRequest(url, method, payload) {
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

  function clearFieldErrors(form) {
    form.querySelectorAll("[data-error-for]").forEach((span) => {
      span.textContent = "";
    });
  }

  function renderRoleFieldErrors(form, errors) {
    clearFieldErrors(form);
    Object.keys(errors || {}).forEach((field) => {
      const span = form.querySelector(`[data-error-for="${field}"]`);
      if (span) span.textContent = errors[field][0];
    });
  }

  // ---------- Sub-tabs: Roles / Matriz de permisos — exact pattern from desarrollo.js's initDesarrolloSubTabs() ----------

  function initRolesTabs() {
    const tabs = document.querySelectorAll("#rolesTabs .tabs__item");
    if (!tabs.length) return;

    tabs.forEach((tab) => {
      tab.addEventListener("click", () => {
        tabs.forEach((t) => t.classList.remove("is-active"));
        tab.classList.add("is-active");

        document.querySelectorAll("[data-panel-content]").forEach((panel) => {
          panel.hidden = panel.dataset.panelContent !== tab.dataset.panel;
        });
      });
    });
  }

  // ---------- Subtitle / empty-state sync (server-computed once at page load; kept in sync after AJAX CRUD) ----------

  function patchRolesSubtitle() {
    const subtitle = document.getElementById("rolesCountSubtitle");
    if (!subtitle) return;
    const count = document.querySelectorAll("[data-role-card]").length;
    subtitle.textContent =
      `${count} rol${count === 1 ? "" : "es"} definido${count === 1 ? "" : "s"} · ${permisosList.length} módulos`;
  }

  function maybeShowEmptyState() {
    const grid = document.getElementById("rolesGrid");
    if (!grid) return;
    if (grid.querySelectorAll("[data-role-card]").length > 0) return;
    if (grid.querySelector(".empty-state")) return;
    grid.innerHTML = `
      <div class="empty-state">
        <div class="empty-state__icon"><i class="fa-solid fa-user-shield"></i></div>
        <p class="empty-state__text">No hay roles definidos todavía.</p>
      </div>`;
  }

  // ---------- Card rendering (twin of index.blade.php's server-rendered role card) ----------

  function roleCardInnerHtml(r) {
    const modules = r.permission_modules || [];
    const chips = permisosList
      .map((p) => {
        const granted = modules.includes(p.module);
        return `<span class="role-chip ${granted ? "role-chip--on" : "role-chip--off"}"><i class="fa-solid ${granted ? "fa-check" : "fa-minus"}"></i>${escapeHtml(p.label)}</span>`;
      })
      .join("");

    const description = r.description ? `<p class="role-card__description">${escapeHtml(r.description)}</p>` : "";

    return `
      <div class="role-card__header">
        <div style="min-width:0;">
          <div class="role-card__name">${escapeHtml(r.label)}</div>
          <div class="u-mono role-card__slug">${escapeHtml(r.name)}</div>
        </div>
        <div class="role-card__actions">
          <button type="button" class="btn--icon" title="Editar" data-edit-role="${r.id}">
            <i class="fa-solid fa-pen"></i>
          </button>
          <button type="button" class="btn--icon" title="Eliminar" style="color:var(--text-danger);" data-delete-role="${r.id}">
            <i class="fa-solid fa-trash"></i>
          </button>
        </div>
      </div>
      <div class="role-card__meta">
        <span class="badge badge--primary">${r.users_count} usuario${r.users_count === 1 ? "" : "s"}</span>
      </div>
      ${description}
      <div class="role-card__chips">${chips}</div>
      <div class="role-card__footer">Módulos visibles (${modules.length}/${permisosList.length})</div>`;
  }

  function upsertRoleCard(r) {
    const grid = document.getElementById("rolesGrid");
    if (!grid) return;

    const emptyState = grid.querySelector(".empty-state");
    if (emptyState) emptyState.remove();

    let card = grid.querySelector(`[data-role-card][data-role-id="${r.id}"]`);
    if (!card) {
      card = document.createElement("div");
      card.className = "card card--padded role-card";
      card.setAttribute("data-role-card", "");
      grid.appendChild(card);
    }

    card.dataset.roleId = String(r.id);
    card.dataset.role = JSON.stringify(r);
    card.innerHTML = roleCardInnerHtml(r);

    patchRolesSubtitle();
  }

  function findRoleFromTrigger(btn, attr) {
    const card = btn.closest("[data-role-card]");
    if (card) return JSON.parse(card.dataset.role);
    const id = btn.dataset[attr];
    const fallbackCard = document.querySelector(`[data-role-card][data-role-id="${id}"]`);
    return fallbackCard ? JSON.parse(fallbackCard.dataset.role) : null;
  }

  // ---------- Read-only detail modal ----------

  function openRoleDetailModal(r) {
    document.getElementById("roleDetailModalLabel").textContent = r.label;
    document.getElementById("roleDetailModalName").textContent = r.name;
    document.getElementById("roleDetailDescription").textContent = r.description || "—";
    document.getElementById("roleDetailUsers").textContent = `${r.users_count} usuario${r.users_count === 1 ? "" : "s"}`;
    document.getElementById("roleDetailModulos").textContent = `${(r.permission_modules || []).length}/${permisosList.length}`;

    const grantedLabels = permisosList
      .filter((p) => (r.permission_modules || []).includes(p.module))
      .map((p) => p.label);

    const tagsEl = document.getElementById("roleDetailTags");
    tagsEl.innerHTML = grantedLabels.length
      ? grantedLabels.map((label) => `<span class="badge badge--primary">${escapeHtml(label)}</span>`).join("")
      : '<span style="color:var(--color-muted-foreground);font-size:var(--text-sm)">Sin módulos asignados</span>';

    const editBtn = document.getElementById("roleDetailEdit");
    if (editBtn) editBtn.dataset.editRole = String(r.id);
    const deleteBtn = document.getElementById("roleDetailDelete");
    if (deleteBtn) deleteBtn.dataset.deleteRole = String(r.id);

    window.AgencyOS.openModal("roleDetailModal");
  }

  function initRoleCardClicks() {
    document.addEventListener("click", (e) => {
      if (e.target.closest("[data-edit-role], [data-delete-role]")) return;
      const card = e.target.closest("[data-role-card]");
      if (!card) return;
      openRoleDetailModal(JSON.parse(card.dataset.role));
    });
  }

  // ---------- Create/edit form modal ----------

  function updateToggleAllLabel() {
    const btn = document.getElementById("roleFormToggleAll");
    if (!btn) return;
    const boxes = document.querySelectorAll('#roleFormPermissions input[name="permissions[]"]');
    const allChecked = boxes.length > 0 && Array.from(boxes).every((cb) => cb.checked);
    btn.textContent = allChecked ? "Quitar todos" : "Seleccionar todos";
  }

  function fillRoleForm(form, r) {
    form.reset();
    form.querySelectorAll('input[name="permissions[]"]').forEach((cb) => {
      cb.checked = false;
    });
    if (!r) return;
    form.querySelector("#rf_name").value = r.name || "";
    form.querySelector("#rf_label").value = r.label || "";
    form.querySelector("#rf_description").value = r.description || "";
    (r.permission_ids || []).forEach((id) => {
      const cb = form.querySelector(`input[name="permissions[]"][value="${id}"]`);
      if (cb) cb.checked = true;
    });
  }

  function openRoleFormModal(r) {
    const form = document.getElementById("roleForm");
    if (!form) return;

    const title = document.getElementById("roleFormModalTitle");
    const submitBtn = document.getElementById("roleFormSubmit");

    clearFieldErrors(form);
    fillRoleForm(form, r);
    form.dataset.editingId = r?.id ?? "";

    if (title) title.textContent = r ? "Editar Rol" : "Nuevo Rol";
    if (submitBtn) {
      submitBtn.innerHTML = r
        ? '<i class="fa-solid fa-check"></i> Guardar Cambios'
        : '<i class="fa-solid fa-check"></i> Crear Rol';
    }

    updateToggleAllLabel();
    window.AgencyOS.openModal("roleFormModal");
  }

  function initRoleForm() {
    const form = document.getElementById("roleForm");
    if (!form) return;

    document.querySelectorAll("[data-open-role-modal]").forEach((btn) => {
      btn.addEventListener("click", () => openRoleFormModal(null));
    });

    document.addEventListener("click", (e) => {
      const btn = e.target.closest("[data-edit-role]");
      if (!btn) return;
      const r = findRoleFromTrigger(btn, "editRole");
      if (!r) return;
      window.AgencyOS.closeModal("roleDetailModal");
      openRoleFormModal(r);
    });

    const toggleAllBtn = document.getElementById("roleFormToggleAll");
    if (toggleAllBtn) {
      toggleAllBtn.addEventListener("click", () => {
        const boxes = document.querySelectorAll('#roleFormPermissions input[name="permissions[]"]');
        const allChecked = Array.from(boxes).every((cb) => cb.checked);
        boxes.forEach((cb) => {
          cb.checked = !allChecked;
        });
        updateToggleAllLabel();
      });
    }

    form.querySelectorAll('input[name="permissions[]"]').forEach((cb) => {
      cb.addEventListener("change", updateToggleAllLabel);
    });

    form.addEventListener("submit", (e) => {
      e.preventDefault();
      const editingId = form.dataset.editingId;
      const isEditing = !!editingId;
      const url = isEditing
        ? form.dataset.updateActionTemplate.replace("__ID__", editingId)
        : form.dataset.storeAction;
      const method = isEditing ? "PUT" : "POST";

      // `permissions[]` is a multi-value field — FormData.getAll(...) is
      // required here. Object.fromEntries(new FormData(form).entries())
      // would silently collapse it to only the LAST checked checkbox.
      const formData = new FormData(form);
      const payload = {
        name: formData.get("name"),
        label: formData.get("label"),
        description: formData.get("description"),
        permissions: formData.getAll("permissions[]").map((v) => Number(v)),
      };

      const submitBtn = document.getElementById("roleFormSubmit");
      if (submitBtn) submitBtn.disabled = true;

      roleRequest(url, method, payload)
        .then((r) => {
          upsertRoleCard(r);
          window.AgencyOS.closeModal("roleFormModal");
          toast(isEditing ? "Rol actualizado." : "Rol creado.", "success");
        })
        .catch((err) => {
          if (err.message === "validation_failed") {
            renderRoleFieldErrors(form, err.data.errors || {});
            toast("Revisa los campos marcados.", "error");
          } else {
            toast("No se pudo guardar el rol.", "error");
          }
        })
        .finally(() => {
          if (submitBtn) submitBtn.disabled = false;
        });
    });
  }

  // ---------- Delete confirmation modal — dedicated modal since the server's guard message (users still on this role) is real and worth showing in full ----------

  function initRoleDelete() {
    const confirmBtn = document.getElementById("roleDeleteConfirm");
    if (!confirmBtn) return;

    function openDeleteModal(r) {
      document.getElementById("roleDeleteName").textContent = r.name;
      const warning = document.getElementById("roleDeleteWarning");
      warning.textContent =
        r.users_count > 0
          ? `Se eliminará el rol "${r.label}". ${r.users_count} usuario${r.users_count === 1 ? "" : "s"} con este rol quedará${r.users_count === 1 ? "" : "n"} sin permisos hasta reasignarlo.`
          : `Se eliminará el rol "${r.label}". Ningún usuario lo tiene asignado.`;
      const errorEl = document.getElementById("roleDeleteError");
      if (errorEl) errorEl.textContent = "";
      confirmBtn.dataset.roleId = String(r.id);

      window.AgencyOS.closeModal("roleDetailModal");
      window.AgencyOS.openModal("roleDeleteModal");
    }

    document.addEventListener("click", (e) => {
      const btn = e.target.closest("[data-delete-role]");
      if (!btn) return;
      e.stopPropagation();
      const r = findRoleFromTrigger(btn, "deleteRole");
      if (!r) return;
      openDeleteModal(r);
    });

    confirmBtn.addEventListener("click", () => {
      const id = confirmBtn.dataset.roleId;
      if (!id) return;
      const url = confirmBtn.dataset.destroyActionTemplate.replace("__ID__", id);

      confirmBtn.disabled = true;
      roleRequest(url, "DELETE")
        .then(() => {
          document.querySelector(`[data-role-card][data-role-id="${id}"]`)?.remove();
          patchRolesSubtitle();
          maybeShowEmptyState();
          window.AgencyOS.closeModal("roleDeleteModal");
          toast("Rol eliminado.", "success");
        })
        .catch((err) => {
          // The 422 "role still has users" guard is real and must reach the
          // user verbatim — surface it in the modal, not a generic message.
          const message = err.data && err.data.message ? err.data.message : "No se pudo eliminar el rol.";
          const errorEl = document.getElementById("roleDeleteError");
          if (errorEl) errorEl.textContent = message;
          toast(message, "error");
        })
        .finally(() => {
          confirmBtn.disabled = false;
        });
    });
  }

  document.addEventListener("shell:ready", () => {
    initRolesTabs();
    initRoleCardClicks();
    initRoleForm();
    initRoleDelete();
  });
})();
