@extends('layouts.admin')

@section('styles')
    @vite('resources/css/admin/usuarios.css')
@endsection

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Usuarios del Sistema</h1>
            <p class="page-header__subtitle" id="usuariosCountSubtitle">{{ $usuariosTotal }} usuario{{ $usuariosTotal === 1 ? '' : 's' }} · {{ $usuariosActivos }} activo{{ $usuariosActivos === 1 ? '' : 's' }} · {{ $rolesEnUso }} rol{{ $rolesEnUso === 1 ? '' : 'es' }} en uso</p>
        </div>
        <button type="button" class="btn btn--primary" data-open-usuario-modal>
            <i class="fa-solid fa-plus"></i> Nuevo Usuario
        </button>
    </div>

    @if (session('status'))
        <div class="form-status"><i class="fa-solid fa-circle-check" style="margin-top:2px"></i><span>{{ session('status') }}</span></div>
    @endif

    <div class="kpi-grid">
        <x-stat-card id="usuarioKpiTotal" label="Usuarios Totales" value="{{ $usuariosTotal }}" icon="fa-users" color="primary" />
        <x-stat-card id="usuarioKpiActivos" label="Activos" value="{{ $usuariosActivos }}" sub="con acceso vigente" icon="fa-circle-check" color="emerald" />
        <x-stat-card id="usuarioKpiInternos" label="Internos" value="{{ $usuariosInternos }}" sub="equipo de la agencia" icon="fa-briefcase" color="teal" />
        <x-stat-card id="usuarioKpiRoles" label="Roles en Uso" value="{{ $rolesEnUso }}" sub="de {{ $roles->count() }} disponibles" icon="fa-user-shield" color="amber" />
    </div>

    <div class="filters-bar">
        <input type="search" class="input input--search" id="usuarioSearch" placeholder="Buscar nombre o correo...">
        <select class="select" id="usuarioRolFilter">
            <option value="all">Todos los roles</option>
            @foreach ($roles as $role)
                <option value="{{ $role->id }}">{{ $role->label }}</option>
            @endforeach
        </select>
        <select class="select" id="usuarioAreaFilter">
            <option value="all">Todas las áreas</option>
            @foreach ($areas as $area)
                <option value="{{ $area['value'] }}">{{ $area['label'] }}</option>
            @endforeach
        </select>
        <select class="select" id="usuarioEstadoFilter">
            <option value="all">Todos los estados</option>
            <option value="activo">Activo</option>
            <option value="inactivo">Inactivo</option>
        </select>
    </div>

    <div class="empty-state" id="usuarioNoResults" hidden>
        <div class="empty-state__icon"><i class="fa-solid fa-magnifying-glass"></i></div>
        <p class="empty-state__text">No se encontraron usuarios con esos filtros.</p>
    </div>

    <x-data-table :headers="['Usuario', 'Rol', 'Área', 'Cuentas asignadas', 'Estado', 'Último acceso', '']">
        @foreach ($usuarios as $u)
            <tr class="is-clickable" data-usuario-row data-usuario-id="{{ $u['id'] }}"
                data-search="{{ mb_strtolower($u['name'].' '.$u['email']) }}"
                data-role="{{ $u['role_id'] }}"
                data-area="{{ $u['area'] }}"
                data-estado="{{ $u['is_active'] ? 'activo' : 'inactivo' }}"
                data-usuario="{{ json_encode($u) }}">
                <td>
                    <div style="display:flex; align-items:center; gap:10px; min-width:0;">
                        <span class="usuario-avatar">{{ \App\Support\Labels::initials($u['name']) }}</span>
                        <div style="min-width:0;">
                            <div style="font-weight:500; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ $u['name'] }}</div>
                            <div style="font-size:var(--text-xs); color:var(--color-muted-foreground); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ $u['email'] }}</div>
                        </div>
                    </div>
                </td>
                <td>{{ $u['role_label'] ?? 'Sin rol' }}</td>
                <td>{{ $u['area_label'] ?? '—' }}</td>
                <td>
                    <span class="usuario-cuentas-cell" title="{{ $u['cuentas_asignadas'] ? implode(', ', $u['cuentas_asignadas']) : '' }}">
                        {{ $u['cuentas_asignadas'] ? implode(', ', $u['cuentas_asignadas']) : '—' }}
                    </span>
                </td>
                <td><x-badge :status="$u['is_active'] ? 'activo' : 'inactivo'" /></td>
                <td><span style="font-size:var(--text-xs); color:var(--color-muted-foreground);">{{ $u['last_login_at'] ?? 'Nunca' }}</span></td>
                <td>
                    <div style="display:flex; gap:4px;">
                        <button type="button" class="btn--icon" title="Editar" data-edit-usuario="{{ $u['id'] }}">
                            <i class="fa-solid fa-pen"></i>
                        </button>
                        <button type="button" class="btn--icon" title="Desactivar" style="color:var(--text-danger);" data-deactivate-usuario="{{ $u['id'] }}" @if ($u['is_self'] || ! $u['is_active']) hidden @endif>
                            <i class="fa-solid fa-user-slash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        @endforeach
    </x-data-table>

    {{-- ---------- Read-only detail modal ---------- --}}
    <x-modal id="usuarioDetailModal">
        <x-slot:header>
            <h2 id="usuarioDetailModalName" style="margin-bottom:4px;"></h2>
            <div id="usuarioDetailModalEmail" style="font-size:var(--text-sm); color:var(--color-muted-foreground);"></div>
        </x-slot:header>
        <div class="form-grid form-grid--2">
            <div class="field">
                <span class="field__label">Rol</span>
                <span id="usuarioDetailRol" style="font-size: var(--text-sm);">—</span>
            </div>
            <div class="field">
                <span class="field__label">Área</span>
                <span id="usuarioDetailArea" style="font-size: var(--text-sm);">—</span>
            </div>
            <div class="field">
                <span class="field__label">Teléfono</span>
                <span id="usuarioDetailTelefono" style="font-size: var(--text-sm);">—</span>
            </div>
            <div class="field">
                <span class="field__label">Estado</span>
                <span id="usuarioDetailEstado"></span>
            </div>
            <div class="field">
                <span class="field__label">Último acceso</span>
                <span id="usuarioDetailUltimoAcceso" style="font-size: var(--text-sm);">—</span>
            </div>
        </div>

        <div class="record-modal__section" style="margin-top: var(--space-5);">
            <div class="record-modal__section-label">Cuentas asignadas</div>
            <div class="record-modal__tags" id="usuarioDetailCuentas"></div>
        </div>

        <div class="form-actions">
            <button type="button" class="btn btn--primary" id="usuarioDetailEdit"><i class="fa-solid fa-pen"></i> Editar</button>
            <button type="button" class="btn btn--secondary" id="usuarioDetailDeactivate" style="color:var(--text-danger);"><i class="fa-solid fa-user-slash"></i> Desactivar</button>
        </div>
    </x-modal>

    {{-- ---------- Create/edit modal ---------- --}}
    <x-modal id="usuarioFormModal">
        <x-slot:header>
            <h2 id="usuarioFormModalTitle" style="margin-bottom:0;">Nuevo Usuario</h2>
        </x-slot:header>
        <form id="usuarioForm" novalidate
              data-store-action="{{ route('admin.usuarios.store') }}"
              data-update-action-template="{{ route('admin.usuarios.update', ['user' => '__ID__']) }}">
            @csrf

            <div class="form-grid form-grid--2">
                <div class="field">
                    <label class="field__label" for="uf_name">Nombre</label>
                    <input class="input" type="text" name="name" id="uf_name" required>
                    <span class="field__error" data-error-for="name"></span>
                </div>
                <div class="field">
                    <label class="field__label" for="uf_email">Email</label>
                    <input class="input" type="email" name="email" id="uf_email" required>
                    <span class="field__error" data-error-for="email"></span>
                </div>
            </div>

            {{-- Password fields — create only, never sent/shown when editing. --}}
            <div data-create-only>
                <div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
                    <div class="field">
                        <label class="field__label" for="uf_password">Contraseña</label>
                        <input class="input" type="password" name="password" id="uf_password" autocomplete="new-password">
                        <span class="field__error" data-error-for="password"></span>
                    </div>
                    <div class="field">
                        <label class="field__label" for="uf_password_confirmation">Confirmar contraseña</label>
                        <input class="input" type="password" name="password_confirmation" id="uf_password_confirmation" autocomplete="new-password">
                        <span class="field__error" data-error-for="password_confirmation"></span>
                    </div>
                </div>
            </div>

            <div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
                <div class="field">
                    <label class="field__label" for="uf_role_id">Rol</label>
                    <select class="select" name="role_id" id="uf_role_id">
                        <option value="">Sin rol</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role->id }}">{{ $role->label }}</option>
                        @endforeach
                    </select>
                    <span class="field__error" data-error-for="role_id"></span>
                </div>
                <div class="field">
                    <label class="field__label" for="uf_area">Área</label>
                    <select class="select" name="area" id="uf_area">
                        <option value="">Sin área</option>
                        @foreach ($areas as $area)
                            <option value="{{ $area['value'] }}">{{ $area['label'] }}</option>
                        @endforeach
                    </select>
                    <span class="field__error" data-error-for="area"></span>
                </div>
            </div>

            <div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
                <div class="field">
                    <label class="field__label" for="uf_telefono">Teléfono</label>
                    <input class="input" type="text" name="telefono" id="uf_telefono">
                    <span class="field__error" data-error-for="telefono"></span>
                </div>
                <div class="field">
                    <label class="field__label" for="uf_is_active">Estado</label>
                    <select class="select" name="is_active" id="uf_is_active">
                        <option value="1">Activo</option>
                        <option value="0">Inactivo</option>
                    </select>
                    <span class="field__error" data-error-for="is_active"></span>
                </div>
            </div>

            {{-- Read-only note, edit mode only — cuentas_asignadas is derived data, never an editable input. --}}
            <p class="field__hint" id="usuarioFormCuentasNote" style="margin-top: var(--space-4);" hidden></p>

            <p class="field__hint" style="margin-top: var(--space-2);">
                <a href="{{ route('admin.roles.index') }}" target="_blank" rel="noopener">Ver permisos de los roles</a>
            </p>

            <div class="form-actions">
                <button type="submit" class="btn btn--primary" id="usuarioFormSubmit"><i class="fa-solid fa-check"></i> Crear Usuario</button>
                <button type="button" class="btn btn--secondary" data-modal-close="usuarioFormModal">Cancelar</button>
            </div>
        </form>
    </x-modal>
@endsection

@section('scripts')
    @vite('resources/js/usuarios.js')
@endsection
