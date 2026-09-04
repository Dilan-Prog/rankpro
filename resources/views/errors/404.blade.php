{{--
    Error 404 · Página no encontrada
    ------------------------------------------------------------------
    Reutiliza el layout público y las clases ya existentes del sitio
    (page-hero, services__grid / service-card, page-cta de pages.css y
    components/services.css). No introduce un sistema de diseño nuevo.

    NOTA SEO: el 404 declara noindex vía @section('robots'), que
    components/seo/meta.blade.php respeta desde ago-2026. El estado 404 real ya
    impide la indexación, pero la directiva explícita evita que la página quede
    indexada si alguna vez se sirviera con estado 200 por error de configuración.
--}}

@extends('layouts.app')

@section('robots', 'noindex, follow')
@section('title', 'Página no encontrada · RankPro')
@section('description', 'La página que buscas no existe o cambió de dirección. Consulta nuestros servicios de marketing digital o vuelve al inicio.')

@push('styles')
    @vite('resources/css/web/pages.css')
@endpush

@section('content')
    @include('components.topbar')
    @include('components.navbar')

    <main>
        <section class="page-hero">
            <div class="container">
                <nav class="breadcrumb" aria-label="Ruta de navegación">
                    <ol>
                        <li><a href="{{ url('/') }}">Inicio</a></li>
                        <li aria-hidden="true" class="breadcrumb__sep">/</li>
                        <li><span aria-current="page">Página no encontrada</span></li>
                    </ol>
                </nav>

                <div class="page-hero__inner">
                    <h1>Error 404: esta página no existe</h1>
                    <p class="page-hero__lead">
                        La dirección que abriste no corresponde a ninguna página de RankPro. Es probable
                        que el enlace esté mal escrito, que la página haya cambiado de dirección o que
                        se haya retirado del sitio. Abajo están todos nuestros servicios y las secciones
                        principales para que llegues a donde ibas.
                    </p>
                    <div class="page-hero__actions">
                        <a href="{{ url('/') }}" class="btn btn-primary">Volver al inicio</a>
                        <a href="{{ route('contacto') }}" class="btn btn-outline">Escribirnos</a>
                    </div>
                </div>
            </div>
        </section>

        <section class="services" aria-labelledby="servicios-404">
            <div class="container">
                <div class="section-header">
                    <h2 id="servicios-404">Quizá buscabas uno de nuestros servicios</h2>
                    <p>Estos son los siete frentes que trabajamos. Entra al que corresponda a lo que necesitas.</p>
                </div>

                <div class="services__grid">
                    @foreach (\App\Support\Servicios::navegacion() as $servicio)
                        <a class="service-card" href="{{ $servicio['url'] }}" style="text-decoration: none; display: block;">
                            <div class="service-card__icon {{ $servicio['gradient'] }}">
                                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $servicio['icon'] !!}</svg>
                            </div>
                            <h3 class="service-card__title">{{ $servicio['nombre'] }}</h3>
                            <p class="service-card__desc">{{ $servicio['resumen'] }}</p>
                            <div class="service-card__link">
                                Ver el servicio
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"></path></svg>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="page-section page-section--alt">
            <div class="container">
                <div class="page-section__inner prose">
                    <h2>Otras secciones del sitio</h2>
                    <ul>
                        <li><a href="{{ url('/') }}">Inicio</a> — qué hacemos y para quién.</li>
                        <li><a href="{{ route('servicios.index') }}">Servicios</a> — el detalle de cada frente de trabajo.</li>
                        <li><a href="{{ route('nosotros') }}">Nosotros</a> — cómo trabajamos y quiénes somos.</li>
                        <li><a href="{{ route('contacto') }}">Contacto</a> — para solicitar un diagnóstico.</li>
                    </ul>
                </div>
            </div>
        </section>

        <section class="page-section">
            <div class="container">
                <div class="page-cta">
                    <h2>¿No encuentras lo que buscabas?</h2>
                    <p>
                        Dinos qué necesitabas y te indicamos la página correcta o te damos la respuesta
                        directamente. Si llegaste desde un enlace roto, también nos sirve saberlo.
                    </p>
                    <div class="page-cta__actions">
                        <a href="{{ route('contacto') }}" class="btn btn-light">Ir a contacto</a>
                        <a href="https://wa.me/527341036410" class="btn btn-ghost" rel="noopener nofollow">WhatsApp +52 734 103 6410</a>
                    </div>
                </div>
            </div>
        </section>
    </main>

    @include('components.footer')
@endsection
