<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($pageTitle) ? $pageTitle . ' · AgencyOS Admin' : 'AgencyOS Admin' }}</title>
    <meta name="description" content="@yield('description', 'Panel de administración de la agencia: clientes, SEO, ads, desarrollo y finanzas.')">

    {{-- Applies the saved theme before first paint to avoid a dark→light flash on load --}}
    <script>
        (function () {
            var theme = localStorage.getItem("agencyos-theme");
            if (theme === "light") document.documentElement.setAttribute("data-theme", "light");
        })();
    </script>

    {{-- Chart.js and Font Awesome load via CDN across the whole admin panel --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js" defer></script>

    @vite(['resources/css/admin/global.css', 'resources/css/admin/sidebar.css', 'resources/js/global.js', 'resources/js/theme.js'])
    @yield('styles')
</head>
<body>
    <div class="app-shell">
        <x-sidebar />

        <div class="app-shell__main">
            <x-header :title="$pageTitle ?? 'AgencyOS Admin'" />

            <main class="app-shell__content">
                @yield('content')
            </main>
        </div>
    </div>

    @yield('scripts')
</body>
</html>
