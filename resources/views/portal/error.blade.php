{{-- Error del portal (404/410): página propia y sin datos del cliente; el mismo texto para enlace vencido o revocado. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <meta name="referrer" content="no-referrer">
    <title>{{ $titulo }}</title>
    @vite('resources/css/portal/portal.css')
</head>
<body class="portal portal--error">
<main class="portal-error" id="portalContenido">
    <p class="portal-error__codigo">{{ $codigo }}</p>
    <h1 class="portal-error__titulo">{{ $titulo }}</h1>
    <p>{{ $detalle }}</p>
</main>
</body>
</html>
