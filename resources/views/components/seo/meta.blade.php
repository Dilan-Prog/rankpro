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
    $seoCanonical = trim($__env->yieldContent('canonical')) ?: url()->current();

    // Imagen social: absoluta siempre. Override con @section('og_image').
    $seoImage = trim($__env->yieldContent('og_image')) ?: asset('images/og-rankpro.jpg');
    if (! \Illuminate\Support\Str::startsWith($seoImage, ['http://', 'https://'])) {
        $seoImage = asset(ltrim($seoImage, '/'));
    }

    // Protección para entornos que no son producción: nunca indexar staging/local.
    $seoRobots = config('app.env') === 'production'
        ? 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1'
        : 'noindex, nofollow';

    // Verificación de Google Search Console.
    // NOTA: la clave services.google.site_verification NO existe todavía en config/services.php.
    // Se lee con fallback a env() para no tener que tocar ese archivo. Para dejarlo "config-cache safe",
    // añadir en config/services.php:
    //     'google' => ['site_verification' => env('GOOGLE_SITE_VERIFICATION')],
    // y definir GOOGLE_SITE_VERIFICATION=... en el .env
    $seoGoogleVerification = config('services.google.site_verification') ?: env('GOOGLE_SITE_VERIFICATION');
@endphp

<title>{{ $seoTitle }}</title>
<meta name="description" content="{{ $seoDescription }}">
<meta name="author" content="RankPro">
<meta name="robots" content="{{ $seoRobots }}">
<link rel="canonical" href="{{ $seoCanonical }}">

{{-- Open Graph --}}
<meta property="og:type" content="website">
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
<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
<link rel="icon" type="image/png" sizes="192x192" href="{{ asset('images/icon-192.png') }}">
<link rel="icon" type="image/png" sizes="512x512" href="{{ asset('images/icon-512.png') }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/apple-touch-icon.png') }}">
<meta name="theme-color" content="#0F9D6E">

@if($seoGoogleVerification)
    <meta name="google-site-verification" content="{{ $seoGoogleVerification }}">
@endif
