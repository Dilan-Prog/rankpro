@extends('layouts.admin')

@section('styles')
    @vite('resources/css/admin/roles.css')
@endsection

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Roles y Permisos</h1>
            <p class="page-header__subtitle" id="rolesCountSubtitle">{{ $roles->count() }} rol{{ $roles->count() === 1 ? '' : 'es' }} definido{{ $roles->count() === 1 ? '' : 's' }} · {{ $permisos->count() }} módulos</p>
        </div>
        <button type="button" class="btn btn--primary" data-open-role-modal>
            <i class="fa-solid fa-plus"></i> Nuevo Rol
        </button>
    </div>

    @if (session('status'))
        <div class="form-status"><i class="fa-solid fa-circle-check" style="margin-top:2px"></i><span>{{ session('status') }}</span></div>
    @endif

    {{-- ---------- Sub-tabs: Roles / Matriz de permisos ---------- --}}
    <div class="tabs" id="rolesTabs">
        <button type="button" class="tabs__item is-active" data-panel="roles">Roles</button>
        <button type="button" class="tabs__item" data-panel="matriz">Matriz de permisos</button>
    </div>

    {{-- ==================================================================
         Tab: Roles
         ================================================================== --}}
    <div data-panel-content="roles">
        <div class="roles-grid" id="rolesGrid" data-permisos="{{ $permisos->map(fn ($p) => ['id' => $p->id, 'label' => $p->label, 'module' => $p->module])->values()->toJson() }}">
            @forelse ($roles as $r)
                <div class="card card--padded role-card" data-role-card data-role-id="{{ $r['id'] }}" data-role="{{ json_encode($r) }}">
                    <div class="role-card__header">
                        <div style="min-width:0;">
                            <div class="role-card__name">{{ $r['label'] }}</div>
                            <div class="u-mono role-card__slug">{{ $r['name'] }}</div>
                        </div>
                        <div class="role-card__actions">
                            <button type="button" class="btn--icon" title="Editar" data-edit-role="{{ $r['id'] }}">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            <button type="button" class="btn--icon" title="Eliminar" style="color:var(--text-danger);" data-delete-role="{{ $r['id'] }}">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    <div class="role-card__meta">
                        <span class="badge badge--primary">{{ $r['users_count'] }} usuario{{ $r['users_count'] === 1 ? '' : 's' }}</span>
                    </div>
                    @if ($r['description'])
                        <p class="role-card__description">{{ $r['description'] }}</p>
                    @endif
                    <div class="role-card__chips">
                        @foreach ($permisos as $permiso)
                            @php $granted = in_array($permiso->module, $r['permission_modules'], true); @endphp
                            <span class="role-chip {{ $granted ? 'role-chip--on' : 'role-chip--off' }}">
                                <i class="fa-solid {{ $granted ? 'fa-check' : 'fa-minus' }}"></i>{{ $permiso->label }}
                            </span>
                        @endforeach
                    </div>
                    <div class="role-card__footer">
                        Módulos visibles ({{ count($r['permission_modules']) }}/{{ $permisos->count() }})
                    </div>
                </div>
            @empty
                <div class="empty-state">
                    <div class="empty-state__icon"><i class="fa-solid fa-user-shield"></i></div>
                    <p class="empty-state__text">No hay roles definidos todavía.</p>
                </div>
            @endforelse
        </div>
    </div>

    {{-- ==================================================================
         Tab: Matriz de permisos
         ================================================================== --}}
    <div data-panel-content="matriz" hidden>
        <x-data-table :headers="$roles->pluck('label')->prepend('Permiso')->all()">
            @forelse ($permisos as $permiso)
                <tr>
                    <td>{{ $permiso->label }}</td>
                    @foreach ($roles as $role)
                        <td>
                            @if (in_array($permiso->id, $role['permission_ids'], true))
                                <i class="fa-solid fa-check matrix-check" title="Concedido"></i>
                            @else
                                <i class="fa-solid fa-minus matrix-dash" title="No concedido"></i>
                            @endif
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ $roles->count() + 1 }}" class="table__empty">No hay permisos definidos.</td></tr>
            @endforelse
        </x-data-table>
    </div>

    {{-- ---------- Read-only detail modal ---------- --}}
    <x-modal id="roleDetailModal">
        <x-slot:header>
            <h2 id="roleDetailModalLabel" style="margin-bottom:4px;"></h2>
            <div id="roleDetailModalName" class="u-mono" style="font-size:var(--text-sm); color:var(--color-muted-foreground);"></div>
        </x-slot:header>
        <div class="form-grid form-grid--2">
            <div class="field">
                <span class="field__label">Descripción</span>
                <span id="roleDetailDescription" style="font-size: var(--text-sm);">—</span>
            </div>
            <div class="field">
                <span class="field__label">Usuarios</span>
                <span id="roleDetailUsers" style="font-size: var(--text-sm);">—</span>
            </div>
            <div class="field">
                <span class="field__label">Módulos visibles</span>
                <span id="roleDetailModulos" style="font-size: var(--text-sm);">—</span>
            </div>
        </div>

        <div class="record-modal__section" style="margin-top: var(--space-5);">
            <div class="record-modal__section-label">Módulos concedidos</div>
            <div class="record-modal__tags" id="roleDetailTags"></div>
        </div>

        <div class="form-actions">
            <button type="button" class="btn btn--primary" id="roleDetailEdit"><i class="fa-solid fa-pen"></i> Editar</button>
            <button type="button" class="btn btn--secondary" id="roleDetailDelete" style="color:var(--text-danger);"><i class="fa-solid fa-trash"></i> Eliminar</button>
        </div>
    </x-modal>

    {{-- ---------- Create/edit modal ---------- --}}
    <x-modal id="roleFormModal">
        <x-slot:header>
            <h2 id="roleFormModalTitle" style="margin-bottom:0;">Nuevo Rol</h2>
        </x-slot:header>
        <form id="roleForm" novalidate
              data-store-action="{{ route('admin.roles.store') }}"
              data-update-action-template="{{ route('admin.roles.update', ['role' => '__ID__']) }}">
            @csrf

            <div class="form-grid form-grid--2">
                <div class="field">
                    <label class="field__label" for="rf_name">Nombre (slug)</label>
                    <input class="input" type="text" name="name" id="rf_name" required>
                    <span class="field__hint">Minúsculas, números y guion bajo (ej. "editor_seo").</span>
                    <span class="field__error" data-error-for="name"></span>
                </div>
                <div class="field">
                    <label class="field__label" for="rf_label">Nombre para mostrar</label>
                    <input class="input" type="text" name="label" id="rf_label" required>
                    <span class="field__error" data-error-for="label"></span>
                </div>
            </div>

            <div class="field" style="margin-top: var(--space-4);">
                <label class="field__label" for="rf_description">Descripción</label>
                <textarea class="textarea" name="description" id="rf_description" rows="2"></textarea>
                <span class="field__error" data-error-for="description"></span>
            </div>

            <div style="margin-top: var(--space-5);">
                <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom: var(--space-2);">
                    <span class="field__label">Módulos visibles</span>
                    <button type="button" class="link-action" id="roleFormToggleAll">Seleccionar todos</button>
                </div>
                <div class="checkbox-group" id="roleFormPermissions">
                    @foreach ($permisos as $permiso)
                        <label class="checkbox-item">
                            <input type="checkbox" name="permissions[]" value="{{ $permiso->id }}">
                            {{ $permiso->label }}
                        </label>
                    @endforeach
                </div>
                <span class="field__error" data-error-for="permissions"></span>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn--primary" id="roleFormSubmit"><i class="fa-solid fa-check"></i> Crear Rol</button>
                <button type="button" class="btn btn--secondary" data-modal-close="roleFormModal">Cancelar</button>
            </div>
        </form>
    </x-modal>

    {{-- ---------- Delete confirmation modal ---------- --}}
    <x-modal id="roleDeleteModal">
        <x-slot:header>
            <h2 style="margin-bottom:4px;">Eliminar rol</h2>
            <div id="roleDeleteName" class="u-mono" style="font-size:var(--text-sm); color:var(--color-muted-foreground);"></div>
        </x-slot:header>
        <p id="roleDeleteWarning" style="font-size:var(--text-sm); line-height:1.6; margin-bottom: var(--space-4);"></p>
        <span class="field__error" data-error-for="delete" id="roleDeleteError"></span>
        <div class="form-actions">
            <button type="button" class="btn btn--danger" id="roleDeleteConfirm" data-destroy-action-template="{{ url('/admin/roles') }}/__ID__">
                <i class="fa-solid fa-trash"></i> Eliminar
            </button>
            <button type="button" class="btn btn--secondary" data-modal-close="roleDeleteModal">Cancelar</button>
        </div>
    </x-modal>
@endsection

@section('scripts')
    @vite('resources/js/roles.js')
@endsection
