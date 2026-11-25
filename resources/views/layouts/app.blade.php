<!DOCTYPE html>
<html lang="es-MX">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Medicion: Consent Mode v2 + GTM. Va antes que nada para que el estado de
         consentimiento se declare antes de cargar cualquier etiqueta. --}}
    @include('components.analytics.gtm')
    {{-- gtag.js directo (GA4 y/o Google Ads): solo si no hay GTM; ver App\Support\Medicion. --}}
    @include('components.analytics.gtag')
    {{-- Datos para el JS publico (modo y etiquetas de conversion); va tras gtm/gtag. --}}
    @include('components.analytics.config')

    {{-- Fuente propia: sin peticiones a Google Fonts. --}}
    <link rel="preload" href="{{ Vite::asset('resources/fonts/plus-jakarta-sans-latin.woff2') }}" as="font" type="font/woff2" crossorigin>

    {{-- SEO: título, description, canonical, robots, Open Graph, Twitter Card, iconos --}}
    @include('components.seo.meta')

    {{-- SEO: datos estructurados globales (Organization + WebSite) --}}
    @include('components.seo.jsonld')

    @stack('head')

    @stack('styles')
    @vite(['resources/css/web/app.css', 'resources/js/app.js'])
</head>
<body class="@yield('body_class')">
    @include('components.analytics.gtm-noscript')

    @yield('content')

    @include('components.whatsapp-float')
    @include('components.cookie-banner')

    @stack('scripts')

    {{-- Schema específico de cada página --}}
    @stack('jsonld')
</body>
</html>
