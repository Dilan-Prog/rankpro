{{--
    Layout del portal de solo lectura del cliente. Sin sidebar del panel, sin recursos externos (ni fuentes
    ni scripts de terceros) y sin cookies: el enlace es la credencial. Recibe $marca (App\Support\Portal\Marca)
    y $expira (vigencia del enlace). El token se toma de la ruta para armar el menú.
--}}
@php
    $token = request()->route('token');
    $menu = [
        'portal.inicio' => 'Inicio',
        'portal.reportes' => 'Reportes',
        'portal.propuestas' => 'Propuestas',
        'portal.metricas' => 'Métricas',
    ];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <meta name="referrer" content="no-referrer">
    <meta name="color-scheme" content="light">
    <title>@yield('titulo', 'Portal') · {{ $marca['nombre'] }}</title>
    <style>
        :root { --portal-primario: {{ $marca['color'] }}; --portal-sobre-primario: {{ $marca['sobre_color'] }}; }
    </style>
    @vite('resources/css/portal/portal.css')
</head>
<body class="portal">
<a class="portal-saltar" href="#portalContenido">Saltar al contenido</a>
<header class="portal-cab">
    <div class="portal-cab__marca">
        @if ($marca['logo'])
            <img class="portal-cab__logo" src="{{ route('portal.logo', ['token' => $token]) }}" alt="Logo de {{ $marca['nombre'] }}" width="44" height="44">
        @endif
        <div class="portal-cab__textos">
            <span class="portal-cab__nombre">{{ $marca['nombre'] }}</span>
            @if ($marca['slogan'])
                <span class="portal-cab__slogan">{{ $marca['slogan'] }}</span>
            @endif
        </div>
    </div>
    <nav class="portal-menu" aria-label="Secciones del portal">
        @foreach ($menu as $ruta => $etiqueta)
            <a href="{{ route($ruta, ['token' => $token]) }}" @class(['portal-menu__enlace', 'is-activo' => request()->routeIs($ruta)]) @if (request()->routeIs($ruta)) aria-current="page" @endif>{{ $etiqueta }}</a>
        @endforeach
    </nav>
</header>

<main class="portal-main" id="portalContenido">
    @yield('contenido')
</main>

<footer class="portal-pie">
    <p>Portal de solo lectura. Este enlace vence el {{ fecha_mx($expira) }}; si necesitas uno nuevo, pídelo a tu contacto en RankPro.</p>
    <p class="portal-pie__firma">Preparado por RankPro Solutions</p>
</footer>
</body>
</html>
