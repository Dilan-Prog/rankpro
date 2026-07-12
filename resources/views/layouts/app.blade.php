<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'RankPro · Agencia de Marketing Digital en México')</title>
    <meta name="description" content="@yield('description', 'Agencia de marketing digital en México: SEM, SEO, desarrollo web y optimización de velocidad.')">

    @stack('styles')
    @vite(['resources/css/web/app.css', 'resources/js/app.js'])
</head>
<body>
    @yield('content')

    @stack('scripts')
</body>
</html>
