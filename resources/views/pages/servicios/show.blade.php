@extends('layouts.app')

@section('title', $servicio['meta_title'])
@section('description', $servicio['meta_description'])
@section('canonical', route('servicios.show', $servicio['slug']))

@push('styles')
    @vite('resources/css/web/pages.css')
@endpush

@include('pages.servicios._schema')

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
                        <li><a href="{{ route('servicios.index') }}">Servicios</a></li>
                        <li aria-hidden="true" class="breadcrumb__sep">/</li>
                        <li><span aria-current="page">{{ $servicio['nombre'] }}</span></li>
                    </ol>
                </nav>

                <div class="page-hero__inner">
                    <div class="section-badge">
                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $servicio['icon'] !!}</svg>
                        {{ Str::upper($servicio['nombre']) }}
                    </div>

                    <h1>{{ $servicio['h1'] }}</h1>
                    <p class="page-hero__lead">{{ $servicio['intro'] }}</p>

                    <div class="page-hero__actions">
                        <a href="{{ route('contacto') }}" class="btn btn-primary">Solicitar propuesta</a>
                        <a href="https://wa.me/527341036410" class="btn btn-outline" rel="noopener">WhatsApp directo</a>
                    </div>
                </div>
            </div>
        </section>

        <section class="page-section">
            <div class="container">
                <div class="page-section__inner prose">
                    @foreach ($servicio['secciones'] as $seccion)
                        <h2>{{ $seccion['titulo'] }}</h2>
                        @foreach ($seccion['parrafos'] as $parrafo)
                            <p>{{ $parrafo }}</p>
                        @endforeach
                    @endforeach
                </div>
            </div>
        </section>

        <section class="page-section page-section--alt" aria-labelledby="entregables">
            <div class="container">
                <div class="page-section__inner prose">
                    <h2 id="entregables">Qué incluye el servicio</h2>
                    <p>
                        Estos son los entregables base. El alcance final se ajusta a tu situación y se define
                        por escrito en la propuesta antes de empezar, sin partidas ambiguas.
                    </p>
                    <ul class="check-list">
                        @foreach ($servicio['entregables'] as $entregable)
                            <li>
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"></path></svg>
                                <span>{{ $entregable }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </section>

        <section class="page-section" aria-labelledby="faq">
            <div class="container">
                <div class="page-section__inner">
                    <div class="prose">
                        <h2 id="faq">Preguntas frecuentes sobre {{ $servicio['nombre'] }}</h2>
                    </div>
                    <div class="faq">
                        @foreach ($servicio['faqs'] as $faq)
                            <article class="faq__item">
                                <h3>{{ $faq['p'] }}</h3>
                                <p>{{ $faq['r'] }}</p>
                            </article>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        {{-- Hub -> spoke: la página de servicio devuelve el enlace a los artículos que
             la apoyan. Sin este bloque el blog sería un silo que no alimenta a las
             páginas que convierten. Si el servicio aún no tiene artículos publicados
             no se pinta nada: un bloque vacío solo añade ruido. --}}
        @if (isset($articulos) && $articulos->isNotEmpty())
            <section class="page-section" aria-labelledby="guias">
                <div class="container">
                    <div class="page-section__inner prose">
                        <h2 id="guias">Guías sobre {{ $servicio['nombre'] }}</h2>
                        <p>
                            Lo que publicamos sobre este tema, con el mismo criterio con el que trabajamos:
                            qué se puede esperar, qué no, y cómo comprobarlo por tu cuenta.
                        </p>
                    </div>

                    <div class="services__grid" style="margin-top: 2rem;">
                        @foreach ($articulos as $articulo)
                            <x-blog.tarjeta :articulo="$articulo" />
                        @endforeach
                    </div>

                    <p style="margin-top: 1.5rem;">
                        <a href="{{ route('blog.index') }}">Ver todas las guías del blog</a>
                    </p>
                </div>
            </section>
        @endif

        <section class="page-section page-section--alt" aria-labelledby="otros-servicios">
            <div class="container">
                <div class="page-section__inner prose">
                    <h2 id="otros-servicios">Otros servicios que suelen acompañarlo</h2>
                    <p>
                        Rara vez un canal trabaja solo. Estos son los servicios que con más frecuencia se
                        combinan con {{ $servicio['nombre'] }} en los proyectos que atendemos.
                    </p>
                </div>

                <div class="services__grid" style="margin-top: 2rem;">
                    @foreach (array_slice($otrosServicios, 0, 3) as $otro)
                        <a class="service-card" href="{{ route('servicios.show', $otro['slug']) }}" style="text-decoration: none; display: block; background: #ffffff;">
                            <div class="service-card__icon {{ $otro['gradient'] }}">
                                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $otro['icon'] !!}</svg>
                            </div>
                            <h3 class="service-card__title">{{ $otro['nombre'] }}</h3>
                            <p class="service-card__desc">{{ $otro['resumen'] }}</p>
                            <div class="service-card__link">
                                Ver el servicio
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"></path></svg>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="page-section">
            <div class="container">
                <div class="page-cta">
                    <h2>¿Te interesa {{ $servicio['nombre'] }}?</h2>
                    <p>
                        Escríbenos con el contexto de tu negocio y te respondemos con un diagnóstico inicial
                        y una propuesta de alcance concreta, sin compromiso.
                    </p>
                    <div class="page-cta__actions">
                        <a href="{{ route('contacto') }}" class="btn btn-light">Solicitar propuesta</a>
                        <a href="https://wa.me/527341036410" class="btn btn-ghost" rel="noopener">WhatsApp +52 734 103 6410</a>
                    </div>
                </div>
            </div>
        </section>
    </main>

    @include('components.footer')
@endsection
