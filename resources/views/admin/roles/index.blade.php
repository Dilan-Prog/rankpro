@extends('layouts.admin')

@section('styles')
    @vite('resources/css/admin/roles.css')
@endsection

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Roles y Usuarios</h1>
            <p class="page-header__subtitle">{{ $roles->count() }} roles · {{ $usuarios->count() }} usuarios</p>
        </div>
        <a href="{{ route('admin.roles.create') }}" class="btn btn--primary">
            <i class="fa-solid fa-plus"></i> Nuevo Rol
        </a>
    </div>

    @if (session('status'))
        <div class="form-status"><i class="fa-solid fa-circle-check" style="margin-top:2px"></i><span>{{ session('status') }}</span></div>
    @endif
    @error('role')
        <div class="form-status form-status--error"><i class="fa-solid fa-circle-exclamation" style="margin-top:2px"></i><span>{{ $message }}</span></div>
    @enderror
    @error('user')
        <div class="form-status form-status--error"><i class="fa-solid fa-circle-exclamation" style="margin-top:2px"></i><span>{{ $message }}</span></div>
    @enderror

    <div class="tabs" id="rolesTabs">
        <button type="button" class="tabs__item is-active" data-panel="roles">Roles</button>
        <button type="button" class="tabs__item" data-panel="usuarios">Usuarios</button>
    </div>

    <div data-panel-content="roles">
        <div class="roles-grid">
            @foreach ($roles as $role)
                <div class="card card--padded">
                    <div style="display:flex; align-items:flex-start; justify-content:space-between; margin-bottom: var(--space-3);">
                        <div>
                            <div style="font-weight:600;">{{ $role->label }}</div>
                            <div class="u-mono" style="font-size:var(--text-xs); color:var(--color-muted-foreground);">{{ $role->name }}</div>
                        </div>
                        <span class="badge badge--primary">{{ $role->users_count }} {{ $role->users_count === 1 ? 'usuario' : 'usuarios' }}</span>
                    </div>
                    @if ($role->description)
                        <p style="font-size:var(--text-sm); color:var(--color-muted-foreground); margin-bottom: var(--space-3);">{{ $role->description }}</p>
                    @endif
                    <div style="display:flex; flex-wrap:wrap; gap:6px; margin-bottom: var(--space-4);">
                        @forelse ($role->permissions as $permiso)
                            <span class="badge badge--neutral">{{ $permiso->label }}</span>
                        @empty
                            <span style="font-size:var(--text-xs); color:var(--color-muted-foreground);">Sin módulos asignados</span>
                        @endforelse
                    </div>
                    <div style="display:flex; gap: var(--space-2); padding-top: var(--space-3); border-top:1px solid var(--color-border);">
                        <a href="{{ route('admin.roles.edit', $role) }}" class="btn btn--secondary btn--sm" style="flex:1; justify-content:center;">
                            <i class="fa-solid fa-pen"></i> Editar
                        </a>
                        <form method="POST" action="{{ route('admin.roles.destroy', $role) }}"
                            data-confirm="¿Eliminar el rol \"{{ $role->label }}\"? Esta acción no se puede deshacer.">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn--secondary btn--sm" style="color:var(--text-danger);">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div data-panel-content="usuarios" hidden>
        <div style="display:flex; justify-content:flex-end; margin-bottom: var(--space-3);">
            <a href="{{ route('admin.usuarios.create') }}" class="btn btn--primary">
                <i class="fa-solid fa-user-plus"></i> Nuevo Usuario
            </a>
        </div>
        <x-data-table :headers="['Usuario', 'Email', 'Rol', 'Activo', 'Último acceso', '']">
            @foreach ($usuarios as $usuario)
                @php $formId = 'user-form-'.$usuario->id; @endphp
                <tr>
                    <td><div style="font-weight:500">{{ $usuario->name }}</div></td>
                    <td><span style="font-size:var(--text-xs); color:var(--color-muted-foreground);">{{ $usuario->email }}</span></td>
                    <td>
                        <select name="role_id" form="{{ $formId }}" class="select">
                            <option value="">Sin rol</option>
                            @foreach ($roles as $role)
                                <option value="{{ $role->id }}" @selected($usuario->role_id === $role->id)>{{ $role->label }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td>
                        <label class="checkbox-item" style="border:none; padding:0;">
                            <input type="checkbox" name="is_active" value="1" form="{{ $formId }}" @checked($usuario->is_active)
                                @disabled($usuario->id === auth()->id())>
                        </label>
                    </td>
                    <td><span style="font-size:var(--text-xs); color:var(--color-muted-foreground);">{{ $usuario->last_login_at?->format('Y-m-d H:i') ?? 'Nunca' }}</span></td>
                    <td>
                        <form method="POST" action="{{ route('admin.usuarios.update', $usuario) }}" id="{{ $formId }}"></form>
                        <input type="hidden" name="_token" value="{{ csrf_token() }}" form="{{ $formId }}">
                        <input type="hidden" name="_method" value="PUT" form="{{ $formId }}">
                        <button type="submit" form="{{ $formId }}" class="btn btn--ghost">Guardar</button>
                    </td>
                </tr>
            @endforeach
        </x-data-table>
    </div>
@endsection

@section('scripts')
    @vite('resources/js/roles.js')
@endsection
