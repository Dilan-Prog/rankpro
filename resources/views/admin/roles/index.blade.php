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
        <div class="roles-grid" id="rolesGrid" data-rol-admin="{{ config('permisos.rol_admin', 'admin') }}"
             data-duplicar-template="{{ route('admin.roles.duplicar', ['role' => '__ID__']) }}"
             data-permisos="{{ $permisos->map(fn ($p) => ['id' => $p->id, 'label' => $p->label, 'module' => $p->module, 'grupo' => \App\Support\GruposPermisos::tituloDe($p->module)])->values()->toJson() }}">
            @forelse ($roles as $r)
                <div class="card card--padded role-card" data-role-card data-role-id="{{ $r['id'] }}" data-role="{{ json_encode($r) }}">
                    <div class="role-card__header">
                        <div style="min-width:0;">
                            <div class="role-card__name">{{ $r['label'] }}</div>
                            <div class="u-mono role-card__slug">{{ $r['name'] }}</div>
                        </div>
                        <div class="role-card__actions">
                            <button type="button" class="btn--icon" title="Duplicar" data-duplicate-role="{{ $r['id'] }}">
                                <i class="fa-solid fa-copy"></i>
                            </button>
                            <button type="button" class="btn--icon" title="Editar" data-edit-role="{{ $r['id'] }}">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            {{-- El rol admin no se elimina (lo rechaza también el servidor): no se ofrece el botón. --}}
                            @if ($r['name'] !== config('permisos.rol_admin', 'admin'))
                                <button type="button" class="btn--icon" title="Eliminar" style="color:var(--text-danger);" data-delete-role="{{ $r['id'] }}">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            @endif
                        </div>
                    </div>
                    <div class="role-card__meta">
                        <span class="badge badge--primary">{{ $r['users_count'] }} usuario{{ $r['users_count'] === 1 ? '' : 's' }}</span>
                    </div>
                    @if ($r['description'])
                        <p class="role-card__description">{{ $r['description'] }}</p>
                    @endif
                    <div class="role-card__chips">
                        {{-- Solo los módulos concedidos: con ~30 módulos, listar también los no concedidos volvía la tarjeta ilegible. La matriz y la ficha muestran el detalle completo. --}}
                        @php $concedidos = $permisos->filter(fn ($p) => in_array($p->module, $r['permission_modules'], true))->values(); @endphp
                        @foreach ($concedidos->take(8) as $permiso)
                            <span class="role-chip role-chip--on"><i class="fa-solid fa-check"></i>{{ $permiso->label }}</span>
                        @endforeach
                        @if ($concedidos->count() > 8)
                            <span class="role-chip">+{{ $concedidos->count() - 8 }} más</span>
                        @elseif ($concedidos->isEmpty())
                            <span class="role-chip role-chip--off">Sin módulos asignados</span>
                        @endif
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
            @forelse ($gruposPermisos as $grupo)
                <tr class="matrix-group">
                    <td colspan="{{ $roles->count() + 1 }}">{{ $grupo['titulo'] }}</td>
                </tr>
                @foreach ($grupo['permisos'] as $permiso)
                    <tr>
                        <td>{{ $permiso->label }}</td>
                        @foreach ($roles as $role)
                            <td>
                                @if (in_array($permiso->id, $role['permission_ids'], true))
                                    <i class="fa-solid fa-check matrix-check" title="Concedido" aria-hidden="true"></i>
                                    <span class="matrix-sr">Concedido</span>
                                @else
                                    <i class="fa-solid fa-minus matrix-dash" title="No concedido" aria-hidden="true"></i>
                                    <span class="matrix-sr">No concedido</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
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
            <div class="record-modal__section-label">Usuarios con este rol</div>
            <div class="record-modal__tags" id="roleDetailUsuarios"></div>
        </div>

        <div class="record-modal__section" style="margin-top: var(--space-5);">
            <div class="record-modal__section-label">Módulos concedidos</div>
            <div id="roleDetailTags"></div>
        </div>

        <div class="form-actions">
            <button type="button" class="btn btn--primary" id="roleDetailEdit"><i class="fa-solid fa-pen"></i> Editar</button>
            <button type="button" class="btn btn--secondary" id="roleDetailDuplicate"><i class="fa-solid fa-copy"></i> Duplicar</button>
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
                <div class="perm-toolbar">
                    <span class="field__label">Módulos visibles</span>
                    <span class="perm-toolbar__acciones">
                        <button type="button" class="link-action" id="roleFormSelectAll">Seleccionar todos</button>
                        <button type="button" class="link-action" id="roleFormSelectNone">Ninguno</button>
                    </span>
                </div>
                <div id="roleFormPermissions">
                    @foreach ($gruposPermisos as $grupo)
                        <fieldset class="perm-group">
                            <legend class="perm-group__header">
                                <span class="perm-group__title">{{ $grupo['titulo'] }}</span>
                                <span class="perm-toolbar__acciones">
                                    <button type="button" class="link-action" data-group-all>Todos</button>
                                    <button type="button" class="link-action" data-group-none>Ninguno</button>
                                </span>
                            </legend>
                            <div class="checkbox-group">
                                @foreach ($grupo['permisos'] as $permiso)
                                    <label class="checkbox-item">
                                        <input type="checkbox" name="permissions[]" value="{{ $permiso->id }}">
                                        {{ $permiso->label }}
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
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
