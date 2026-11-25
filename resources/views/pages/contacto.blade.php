@extends('layouts.app')

@section('title', 'Contacto | RankPro Agencia de Marketing Digital')
@section('description', 'Escríbenos por WhatsApp al +52 55 6223 0266 o por correo. Diagnóstico inicial sin costo para tu proyecto de marketing digital en México.')
@section('canonical', route('contacto'))
@section('body_class', 'rd rd-contacto')

@push('styles')
    @vite(['resources/css/web/pages.css', 'resources/css/web/rediseno/base.css', 'resources/css/web/rediseno/contacto.css'])
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
                // La Organization (con su unico contactPoint E.164) vive solo en
                // components/seo/jsonld.blade.php: duplicarla aqui con otro formato de
                // telefono daba dos nodos con el mismo @id y datos contradictorios.
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

                <div class="page-hero__dos">
                <div class="page-hero__inner">
                    <h1>Hablemos de tu proyecto</h1>
                    <p class="page-hero__lead">
                        La forma más rápida de empezar es escribirnos por WhatsApp o por correo. Respondemos
                        en horario hábil y la primera conversación es un diagnóstico sin costo: nos cuentas
                        qué vendes y qué has intentado, y te decimos con honestidad si podemos ayudarte.
                    </p>
                </div>
                {{-- Adorno sin texto ni cifras, para no aparentar datos reales. --}}
                <div class="page-hero__adorno" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 520 400" focusable="false">
                        <rect x="1" y="1" width="518" height="398" rx="24" fill="#fff" stroke="#E3E9E6"/>
                        <path d="M1 25A24 24 0 0 1 25 1H495A24 24 0 0 1 519 25V80H1Z" fill="#0B1712"/>
                        <circle cx="52" cy="40" r="20" fill="#4FD1A5"/><path d="M44 47a11 11 0 1 1 4 3l-6 2Z" fill="none" stroke="#0B1712" stroke-width="2.4" stroke-linejoin="round"/>
                        <rect x="86" y="27" width="124" height="10" rx="5" fill="#fff"/><rect x="86" y="46" width="78" height="8" rx="4" fill="#B5C4BD"/>
                        <path d="M30 120A18 18 0 0 1 48 102H282A18 18 0 0 1 300 120V150A18 18 0 0 1 282 168H36A6 6 0 0 1 30 162Z" fill="#F4F7F5"/>
                        <rect x="52" y="122" width="212" height="8" rx="4" fill="#C9D3CE"/><rect x="52" y="141" width="140" height="8" rx="4" fill="#C9D3CE"/>
                        <path d="M220 204A18 18 0 0 1 238 186H472A18 18 0 0 1 490 204V246A6 6 0 0 1 484 252H238A18 18 0 0 1 220 234Z" fill="#0B7D58"/>
                        <rect x="244" y="206" width="218" height="8" rx="4" fill="#C6E7D9"/><rect x="244" y="225" width="156" height="8" rx="4" fill="#C6E7D9"/>
                        <path d="M30 288A18 18 0 0 1 48 270H126A18 18 0 0 1 144 288V296A18 18 0 0 1 126 314H36A6 6 0 0 1 30 308Z" fill="#F4F7F5"/>
                        <g fill="#8A9891"><circle cx="66" cy="292" r="5"/><circle cx="87" cy="292" r="5"/><circle cx="108" cy="292" r="5"/></g>
                        <path d="M1 334H519" stroke="#EEF2F0"/><rect x="26" y="348" width="412" height="36" rx="18" fill="#F4F7F5"/><rect x="46" y="362" width="130" height="8" rx="4" fill="#C9D3CE"/>
                        <circle cx="474" cy="366" r="18" fill="#0F9D6E"/><path d="M467 366h14m-6-6 6 6-6 6" fill="none" stroke="#fff" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                </div>
            </div>
        </section>

        <section class="page-section">
            <div class="container">
                <div class="contact-grid contact-grid--simple con-grid">
                    <div>
                        <div class="prose con-intro">
                            <h2>Canales directos</h2>
                            <p>
                                Estos canales están atendidos por personas del equipo, no por un bot. Si nos
                                escribes fuera de horario, te respondemos al siguiente día hábil.
                            </p>
                        </div>

                        <ul class="contact-channels">
                            <li>
                                <a class="contact-channel" href="https://wa.me/525562230266" rel="noopener nofollow">
                                    <span class="contact-channel__icon" aria-hidden="true">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"></path></svg>
                                    </span>
                                    <span>
                                        <span class="contact-channel__label">WhatsApp</span>
                                        <span class="contact-channel__value">+52 55 6223 0266 · respuesta en horario hábil</span>
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

                        <div class="prose con-ayuda">
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
                        <a href="https://wa.me/525562230266" class="btn btn-light" rel="noopener nofollow">WhatsApp +52 55 6223 0266</a>
                        <a href="mailto:administracion@rankprosolutions.com.mx" class="btn btn-ghost">Enviar correo</a>
                    </div>
                </div>
            </div>
        </section>
    </main>

    @include('components.footer')
@endsection
