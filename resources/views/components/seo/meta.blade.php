{{--
    Parcial SEO: metadatos del <head>
    ------------------------------------------------------------------
    Contrato para las páginas hijas:
        @section('title', '...')        título de la pestaña / SERP
        @section('description', '...')  meta description
        @section('canonical', '...')    URL canónica (opcional, override)
        @section('og_image', '...')     URL absoluta de la imagen social (opcional)

    Assets sociales / iconos (ya generados en public/images/):
        og-rankpro.jpg 1200x630 · apple-touch-icon.png 180x180 · icon-192.png · icon-512.png
        Se regeneran desde storage/app/logos-originales/ si cambia la identidad de marca.
--}}
@php
    // Título y descripción por defecto (los @yield conservan el comportamiento previo).
    $seoTitle = trim($__env->yieldContent('title', 'RankPro · Agencia de Marketing Digital en México'));
    $seoDescription = trim($__env->yieldContent('description', 'Agencia de marketing digital en México: SEM, SEO, desarrollo web y optimización de velocidad.'));

    // Canónica: por defecto la URL actual SIN query string; se puede sobrescribir con @section('canonical').
    // Las vistas paginadas (p. ej. /blog?page=2) DEBEN sobrescribirla incluyendo el query string,
    // porque url()->current() lo descarta y todas las páginas declararían la misma canónica.
    $seoCanonical = trim($__env->yieldContent('canonical')) ?: url()->current();

    // La portada se sirve en "/" pero url()->current() devuelve el origen sin barra final.
    // Normalizarla evita que Search Console reporte la canónica y la URL rastreada como distintas.
    if ($seoCanonical === rtrim(url('/'), '/')) {
        $seoCanonical .= '/';
    }

    // Imagen social: absoluta siempre. Override con @section('og_image').
    $seoImage = trim($__env->yieldContent('og_image')) ?: asset('images/og-rankpro.jpg');
    if (! \Illuminate\Support\Str::startsWith($seoImage, ['http://', 'https://'])) {
        $seoImage = asset(ltrim($seoImage, '/'));
    }

    // Indexabilidad. Decisión explícita vía SEO_INDEXABLE (config/seo.php); si no está
    // definida, se cae al comportamiento anterior basado en APP_ENV.
    //
    // Motivo del cambio: el .env de producción no tenía APP_ENV=production y el sitio entero
    // estuvo emitiendo noindex sin aviso. Un fallo de configuración no debe desindexar en silencio.
    $seoIndexable = config('seo.indexable');
    $seoIndexable = $seoIndexable === null
        ? config('app.env') === 'production'
        : filter_var($seoIndexable, FILTER_VALIDATE_BOOLEAN);

    $seoRobots = $seoIndexable
        ? config('seo.robots_index')
        : config('seo.robots_noindex');

    // Override por pagina: @section('robots', 'noindex, follow').
    // Lo necesitan las paginas que nunca deben indexarse aunque el sitio si lo sea
    // (errores, resultados de busqueda, paginas de agradecimiento). Se aplica solo
    // cuando el sitio es indexable: en staging manda siempre el noindex global.
    $seoRobotsOverride = trim($__env->yieldContent('robots'));
    if ($seoIndexable && $seoRobotsOverride !== '') {
        $seoRobots = $seoRobotsOverride;
    }

    // Tipo Open Graph. Por defecto "website"; los artículos del blog declaran
    // @section('og_type', 'article'). yieldContent sobre una sección no definida
    // devuelve '', así que las páginas existentes no cambian.
    $seoOgType = trim($__env->yieldContent('og_type')) ?: 'website';

    // Verificación de Google Search Console.
    // La clave vive en config/services.php (config-cache safe). Definir
    // GOOGLE_SITE_VERIFICATION=... en el .env de producción.
    $seoGoogleVerification = config('services.google.site_verification');
@endphp

<title>{{ $seoTitle }}</title>
<meta name="description" content="{{ $seoDescription }}">
<meta name="author" content="RankPro">
<meta name="robots" content="{{ $seoRobots }}">
<link rel="canonical" href="{{ $seoCanonical }}">

{{-- Open Graph --}}
<meta property="og:type" content="{{ $seoOgType }}">
<meta property="og:site_name" content="RankPro">
<meta property="og:locale" content="es_MX">
<meta property="og:title" content="{{ $seoTitle }}">
<meta property="og:description" content="{{ $seoDescription }}">
<meta property="og:url" content="{{ $seoCanonical }}">
<meta property="og:image" content="{{ $seoImage }}">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="{{ $seoTitle }}">

{{-- Twitter / X --}}
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $seoTitle }}">
<meta name="twitter:description" content="{{ $seoDescription }}">
<meta name="twitter:image" content="{{ $seoImage }}">

{{-- Iconos y color de marca (--brand de resources/css/web/app.css) --}}
<link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
<link rel="icon" type="image/png" sizes="192x192" href="{{ asset('images/icon-192.png') }}">
<link rel="icon" type="image/png" sizes="512x512" href="{{ asset('images/icon-512.png') }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/apple-touch-icon.png') }}">
<meta name="theme-color" content="#0F9D6E">

@if($seoGoogleVerification)
    <meta name="google-site-verification" content="{{ $seoGoogleVerification }}">
@endif
