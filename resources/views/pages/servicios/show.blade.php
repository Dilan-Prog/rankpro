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
                        <a href="{{ route('contacto') }}" class="btn btn-primary">{{ $servicio['cta'] ?? 'Solicitar propuesta' }}</a>
                        <a href="https://wa.me/525562230266" class="btn btn-outline" rel="noopener nofollow">WhatsApp directo</a>
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

                    @isset($servicio['tabla'])
                        <h2 id="tabla">{{ $servicio['tabla']['titulo'] }}</h2>
                        <div style="overflow-x:auto;" tabindex="0" role="region" aria-labelledby="tabla">
                            <table style="width:100%;border-collapse:collapse;color:var(--ink);">
                                <thead>
                                    <tr style="border-bottom:2px solid var(--border);text-align:left;">
                                        @foreach ($servicio['tabla']['encabezados'] as $encabezado)
                                            <th scope="col" style="padding:0.75rem;">{{ $encabezado }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($servicio['tabla']['filas'] as $fila)
                                        <tr style="border-bottom:1px solid var(--border);">
                                            @foreach ($fila as $celda)
                                                <td style="padding:0.75rem;">{{ $celda }}</td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <p>{{ $servicio['tabla']['nota'] }}</p>
                    @endisset

                    @if ($landings = array_filter($servicio['landings'] ?? []))
                        <h2>{{ $servicio['landings_titulo'] }}</h2>
                        <ul>
                            @foreach ($landings as $textoLanding => $ruta)
                                <li><a href="{{ url($ruta) }}">{{ $textoLanding }}</a></li>
                            @endforeach
                        </ul>
                    @endif

                    @isset($servicio['relacionado'])
                        <h2>{{ $servicio['relacionado']['titulo'] }}</h2>
                        <p>{{ $servicio['relacionado']['texto'] }} <a href="{{ route('servicios.show', $servicio['relacionado']['servicio']) }}">{{ $servicio['relacionado']['ancla'] }}</a>.</p>
                    @endisset
                </div>
            </div>
        </section>

        <section class="page-section page-section--alt" aria-labelledby="entregables">
            <div class="container">
                <div class="page-section__inner prose">
                    <h2 id="entregables">Qué incluye el servicio</h2>
                    @isset($servicio['incluye'])
                        <p>{{ $servicio['incluye'] }}</p>
                    @endisset
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
                                @if (isset($faq['guia'], $guias[$faq['guia']]))
                                    <p>Más detalle en <a href="{{ route('blog.show', $faq['guia']) }}">{{ $guias[$faq['guia']] }}</a>.</p>
                                @endif
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
        @php
            // Las guías fijas del servicio van en lista; debajo, las recientes que no estén en ella.
            $recientes = $articulos->reject(fn ($a) => isset($guias[$a->slug]));
        @endphp
        @if ($guias !== [] || $recientes->isNotEmpty())
            <section class="page-section" aria-labelledby="guias">
                <div class="container">
                    <div class="page-section__inner prose">
                        <h2 id="guias">{{ $servicio['guias_titulo'] ?? 'Guías sobre '.$servicio['nombre'] }}</h2>
                        <p>
                            Lo que publicamos sobre este tema, con el mismo criterio con el que trabajamos:
                            qué se puede esperar, qué no, y cómo comprobarlo por tu cuenta.
                        </p>
                        @if ($guias !== [])
                            <ul>
                                @foreach ($guias as $slugGuia => $textoGuia)
                                    <li><a href="{{ route('blog.show', $slugGuia) }}">{{ $textoGuia }}</a></li>
                                @endforeach
                            </ul>
                        @endif
                    </div>

                    <div class="services__grid" style="margin-top: 2rem;">
                        @foreach ($recientes as $articulo)
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
                        <a class="service-card" href="{{ route('servicios.show', $otro['slug']) }}" style="text-decoration: none; background: #ffffff;">
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
                        <a href="{{ route('contacto') }}" class="btn btn-light">{{ $servicio['cta'] ?? 'Solicitar propuesta' }}</a>
                        <a href="https://wa.me/525562230266" class="btn btn-ghost" rel="noopener nofollow">WhatsApp +52 55 6223 0266</a>
                    </div>
                </div>
            </div>
        </section>
    </main>

    @include('components.footer')
@endsection
