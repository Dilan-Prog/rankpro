<!DOCTYPE html>
<html lang="es-MX">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Medicion: Consent Mode v2 + GTM. Va antes que nada para que el estado de
         consentimiento se declare antes de cargar cualquier etiqueta. --}}
    @include('components.analytics.gtm')

    {{-- Orígenes externos realmente usados: Google Fonts (Manrope, importada en resources/css/web/app.css) --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="dns-prefetch" href="https://fonts.googleapis.com">

    {{-- SEO: título, description, canonical, robots, Open Graph, Twitter Card, iconos --}}
    @include('components.seo.meta')

    {{-- SEO: datos estructurados globales (Organization + WebSite) --}}
    @include('components.seo.jsonld')

    @stack('head')

    @stack('styles')
    @vite(['resources/css/web/app.css', 'resources/js/app.js'])
</head>
<body>
    @include('components.analytics.gtm-noscript')

    @yield('content')

    @include('components.whatsapp-float')
    @include('components.cookie-banner')

    @stack('scripts')

    {{-- Schema específico de cada página --}}
    @stack('jsonld')
</body>
</html>
