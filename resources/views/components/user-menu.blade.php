{{--
    User dropdown menu. Usage: <x-user-menu id="userMenu" /> (or with an
    extra class, e.g. class="user-menu--sidebar", for the footer-anchored
    instance which opens upward instead of downward — see .user-menu--sidebar
    in global.css). Opened via [data-dropdown-trigger="{id}"], wired
    generically by global.js's initDropdowns().
--}}
@props(['id'])
<div {{ $attributes->merge(['class' => 'dropdown-panel user-menu']) }} id="{{ $id }}" data-dropdown-panel hidden>
    <div class="user-menu__header">
        <div class="user-menu__name">{{ auth()->user()->name ?? 'Usuario' }}</div>
        <div class="user-menu__role">{{ auth()->user()->email ?? (auth()->user()->role->label ?? 'Sin rol') }}</div>
    </div>
    <div class="user-menu__list">
        @if (Route::has('admin.usuarios.index'))
            <a href="{{ route('admin.usuarios.index') }}" class="user-menu__item">
                <i class="fa-solid fa-users-gear"></i> Usuarios
            </a>
        @endif
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="user-menu__item user-menu__item--danger">
                <i class="fa-solid fa-right-from-bracket"></i> Cerrar sesión
            </button>
        </form>
    </div>
</div>
