@extends('layouts.app')

@section('title', 'Contacto | RankPro Agencia de Marketing Digital')
@section('description', 'Escríbenos por WhatsApp al +52 734 103 6410 o por correo. Diagnóstico inicial sin costo para tu proyecto de marketing digital en México.')
@section('canonical', route('contacto'))

@push('styles')
    @vite('resources/css/web/pages.css')
@endpush

@push('jsonld')
    <script type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'ContactPage',
                    'name' => 'Contacto RankPro',
                    'url' => route('contacto'),
                    'description' => 'Canales de contacto directo con RankPro: WhatsApp y correo electrónico.',
                    'about' => ['@id' => url('/#organization')],
                ],
                [
                    '@type' => 'Organization',
                    '@id' => url('/#organization'),
                    'contactPoint' => [
                        [
                            '@type' => 'ContactPoint',
                            'contactType' => 'ventas',
                            'telephone' => '+52-734-103-6410',
                            'email' => 'administracion@rankprosolutions.com.mx',
                            'areaServed' => 'MX',
                            'availableLanguage' => ['es-MX'],
                        ],
                        [
                            '@type' => 'ContactPoint',
                            'contactType' => 'atención al cliente',
                            'email' => 'administracion@rankprosolutions.com.mx',
                            'areaServed' => 'MX',
                            'availableLanguage' => ['es-MX'],
                        ],
                    ],
                ],
                [
                    '@type' => 'BreadcrumbList',
                    'itemListElement' => [
                        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Inicio', 'item' => url('/')],
                        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Contacto', 'item' => route('contacto')],
                    ],
                ],
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
    </script>
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
                        <li><span aria-current="page">Contacto</span></li>
                    </ol>
                </nav>

                <div class="page-hero__inner">
                    <h1>Hablemos de tu proyecto</h1>
                    <p class="page-hero__lead">
                        La forma más rápida de empezar es escribirnos por WhatsApp o por correo. Respondemos
                        en horario hábil y la primera conversación es un diagnóstico sin costo: nos cuentas
                        qué vendes y qué has intentado, y te decimos con honestidad si podemos ayudarte.
                    </p>
                </div>
            </div>
        </section>

        <section class="page-section">
            <div class="container">
                <div class="contact-grid contact-grid--simple">
                    <div>
                        <div class="prose">
                            <h2>Canales directos</h2>
                            <p>
                                Estos canales están atendidos por personas del equipo, no por un bot. Si nos
                                escribes fuera de horario, te respondemos al siguiente día hábil.
                            </p>
                        </div>

                        <ul class="contact-channels" style="margin-top: 1.5rem;">
                            <li>
                                <a class="contact-channel" href="https://wa.me/527341036410" rel="noopener">
                                    <span class="contact-channel__icon" aria-hidden="true">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"></path></svg>
                                    </span>
                                    <span>
                                        <span class="contact-channel__label">WhatsApp</span>
                                        <span class="contact-channel__value">+52 734 103 6410 · respuesta en horario hábil</span>
                                    </span>
                                </a>
                            </li>
                            <li>
                                <a class="contact-channel" href="mailto:administracion@rankprosolutions.com.mx">
                                    <span class="contact-channel__icon" aria-hidden="true">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"></rect><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path></svg>
                                    </span>
                                    <span>
                                        <span class="contact-channel__label">Correo electrónico</span>
                                        <span class="contact-channel__value">administracion@rankprosolutions.com.mx</span>
                                    </span>
                                </a>
                            </li>
                            <li>
                                <div class="contact-channel">
                                    <span class="contact-channel__icon" aria-hidden="true">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                                    </span>
                                    <span>
                                        <span class="contact-channel__label">Zona de servicio</span>
                                        <span class="contact-channel__value">México · atención presencial en nuestra zona y remota en toda la República</span>
                                    </span>
                                </div>
                            </li>
                        </ul>

                        <div class="prose" style="margin-top: 2.5rem;">
                            <h2>Qué nos ayuda a responderte mejor</h2>
                            <p>
                                No necesitas tener nada preparado, pero si al escribirnos incluyes estos datos
                                podemos darte una respuesta útil desde el primer mensaje en lugar de pedirte
                                información por partes.
                            </p>
                            <ul>
                                <li>Qué vendes y a quién se lo vendes.</li>
                                <li>La dirección de tu sitio web, si ya tienes uno.</li>
                                <li>Qué has intentado antes en marketing digital y cómo te fue.</li>
                                <li>Qué te gustaría que pasara en los próximos seis meses.</li>
                                <li>Un rango de presupuesto aproximado, aunque sea amplio.</li>
                            </ul>
                            <p>
                                Si todavía no sabes qué servicio necesitas, revisa
                                <a href="{{ route('servicios.index') }}">nuestros servicios</a> o simplemente
                                escríbenos: parte del diagnóstico es justamente ayudarte a decidir por dónde
                                empezar.
                            </p>
                        </div>
                    </div>

                </div>
            </div>
        </section>

        <section class="page-section page-section--alt">
            <div class="container">
                <div class="page-cta">
                    <h2>Escríbenos ahora por WhatsApp</h2>
                    <p>Es el canal más rápido y el que sí está funcionando el día de hoy.</p>
                    <div class="page-cta__actions">
                        <a href="https://wa.me/527341036410" class="btn btn-light" rel="noopener">WhatsApp +52 734 103 6410</a>
                        <a href="mailto:administracion@rankprosolutions.com.mx" class="btn btn-ghost">Enviar correo</a>
                    </div>
                </div>
            </div>
        </section>
    </main>

    @include('components.footer')
@endsection
