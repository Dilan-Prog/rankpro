{{--
    Top header bar. Receives the current module's short label as $title
    (passed by layouts/admin.blade.php from the controller's $pageTitle).
--}}
@props(['title' => ''])
<header class="topbar">
    <div class="topbar__left">
        <button class="topbar__menu-btn" id="sidebarToggle" type="button" aria-label="Abrir menú" aria-expanded="false">
            <i class="fa-solid fa-bars"></i>
        </button>
        <span class="topbar__title">{{ $title }}</span>
    </div>
    <div class="topbar__actions">
        <button class="topbar__theme" type="button" id="themeToggle" aria-label="Cambiar a modo claro" title="Cambiar tema">
            <i class="fa-solid fa-sun" data-theme-icon="light"></i>
            <i class="fa-solid fa-moon" data-theme-icon="dark"></i>
        </button>
        <button class="topbar__bell" type="button" aria-label="Notificaciones">
            <i class="fa-solid fa-bell"></i>
            <span class="topbar__bell-dot"></span>
        </button>
        <span class="topbar__avatar">AM</span>
    </div>
</header>
