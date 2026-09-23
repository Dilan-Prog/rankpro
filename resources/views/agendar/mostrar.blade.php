@extends('layouts.app')

@section('title', 'Agendar una reunión | RankPro Agencia de Marketing Digital')
@section('description', 'Elige el día y la hora que mejor te acomoden para una llamada con RankPro. Sin formularios largos: agenda en menos de un minuto.')
@section('canonical', route('agendar.mostrar'))

@push('styles')
    @vite('resources/css/web/agendar.css')
@endpush

{{--
    Página pública sin sesión. $config puede ser null (nunca se configuró nada)
    o venir con activa=false — en ambos casos mostramos el aviso de "no
    disponible" en vez de romper la vista. Todo el flujo (calendario de mes +
    horarios + formulario + confirmación) vive dentro de UN SOLO
    <div data-agendar>, que envuelve TODO — incluyendo el panel lateral de
    resumen — para que root.querySelector (ver resources/js/modules/agendar.js)
    alcance cualquier botón/panel sin importar en qué columna del diseño viva.

    Rediseño 2026-09: calendario de mes completo (en vez de fila de días) +
    panel lateral con resumen de la cita + chips de "tema" (que se anteponen
    a las notas libres antes de mandarlas al backend, ver agendar.js) +
    botones de Google Calendar / .ics 100% cliente en la confirmación.
    Estructura y paleta inspiradas en un mockup React/Tailwind que el usuario
    proporcionó, traducidas a Blade + CSS propio + JS vanilla (ver CLAUDE.md).
--}}
@section('content')
    @include('components.topbar')
    @include('components.navbar')

    <main>
        @if (!$config || !$config->activa)
            <section class="page-hero">
                <div class="container">
                    <nav class="breadcrumb" aria-label="Ruta de navegación">
                        <ol>
                            <li><a href="{{ url('/') }}">Inicio</a></li>
                            <li aria-hidden="true" class="breadcrumb__sep">/</li>
                            <li><span aria-current="page">Agendar reunión</span></li>
                        </ol>
                    </nav>

                    <div class="page-hero__inner">
                        <h1>Agenda una reunión con nosotros</h1>
                        <p class="page-hero__lead">Escríbenos y coordinamos un horario juntos.</p>
                    </div>
                </div>
            </section>

            <section class="page-section">
                <div class="container">
                    <div class="agendar-no-disponible">
                        <h2>Agendar reuniones no está disponible por ahora.</h2>
                        <p>Escríbenos por WhatsApp o usa el formulario de contacto y te respondemos lo antes posible.</p>
                        <div class="agendar-no-disponible__acciones">
                            <a href="https://wa.me/527341036410" class="btn btn-primary" rel="noopener nofollow">WhatsApp +52 734 103 6410</a>
                            <a href="{{ route('contacto') }}" class="btn btn-outline">Ir a contacto</a>
                        </div>
                    </div>
                </div>
            </section>
        @else
            <section class="agendar-hero">
                <div class="container agendar-hero__inner">
                    <span class="agendar-badge">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"></rect><path d="M16 2v4M8 2v4M3 10h18"></path></svg>
                        CONSULTORÍA GRATUITA
                    </span>
                    <h1 class="agendar-hero__titulo">Agenda en <span class="agendar-hero__resaltado">menos de 1 minuto</span></h1>
                    <p class="agendar-hero__lead">Elige día y hora, deja tus datos y listo. Sin llamadas de venta ni compromiso.</p>
                </div>
            </section>

            <section class="agendar-seccion">
                <div class="container">
                    <div class="agendar" data-agendar
                         data-url-disponibilidad="{{ route('agendar.disponibilidad') }}"
                         data-url-agendar="{{ route('agendar.agendar') }}"
                         data-duracion-minutos="{{ $config->duracion_minutos }}"
                         data-dias-visibles="{{ $config->dias_visibles }}">

                        <div class="agendar__tarjeta">
                            <aside class="agendar__lateral">
                                <div class="agendar__marca">RANKPRO</div>
                                <h2 class="agendar__lateral-titulo">Consultoría estratégica</h2>

                                <ul class="agendar__resumen">
                                    <li>
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><path d="M12 6v6l4 2"></path></svg>
                                        {{ $config->duracion_minutos }} minutos
                                    </li>
                                    <li>
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="2" y="6" width="14" height="12" rx="2"></rect><path d="M16 10l6-4v12l-6-4"></path></svg>
                                        Videollamada (enlace por correo)
                                    </li>
                                    <li>
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><path d="M2 12h20M12 2a15 15 0 0 1 0 20 15 15 0 0 1 0-20z"></path></svg>
                                        Hora de Ciudad de México
                                    </li>
                                    <li class="agendar__resumen-elegido" data-agendar-resumen-elegido hidden>
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"></rect><path d="M16 2v4M8 2v4M3 10h18"></path></svg>
                                        <span data-agendar-resumen-elegido-texto></span>
                                    </li>
                                </ul>

                                <div class="agendar__temas">
                                    <div class="agendar__temas-titulo">¿Sobre qué quieres hablar? <span class="agendar__opcional">(opcional)</span></div>
                                    <div class="agendar__temas-lista" data-agendar-temas>
                                        @foreach (['SEO & Posicionamiento', 'Google Ads / SEM', 'Desarrollo Web', 'Redes Sociales', 'Analytics & Data', 'Estrategia 360°'] as $tema)
                                            <button type="button" class="agendar__tema" data-agendar-tema="{{ $tema }}">{{ $tema }}</button>
                                        @endforeach
                                    </div>
                                </div>
                            </aside>

                            <div class="agendar__contenido">
                                <div class="agendar__paso" data-agendar-paso="elegir">
                                    <div class="agendar__elegir">
                                        <div class="agendar__calendario">
                                            <div class="agendar__calendario-cab">
                                                <h3 class="agendar__calendario-mes" data-agendar-mes-titulo></h3>
                                                <div class="agendar__calendario-nav">
                                                    <button type="button" class="agendar__nav-btn" data-agendar-mes-prev aria-label="Mes anterior">
                                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 18l-6-6 6-6"></path></svg>
                                                    </button>
                                                    <button type="button" class="agendar__nav-btn" data-agendar-mes-next aria-label="Mes siguiente">
                                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 18l6-6-6-6"></path></svg>
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="agendar__calendario-dow">
                                                <span>LUN</span><span>MAR</span><span>MIÉ</span><span>JUE</span><span>VIE</span><span>SÁB</span><span>DOM</span>
                                            </div>
                                            <div class="agendar__calendario-grid" data-agendar-calendario></div>
                                        </div>

                                        <div class="agendar__horarios-panel">
                                            <div data-agendar-fecha-elegida class="agendar__horarios-fecha"></div>
                                            <p class="agendar__sin-fecha" data-agendar-sin-fecha>Elige un día disponible.</p>
                                            <div class="agendar__horarios" data-agendar-horarios hidden></div>
                                            <button type="button" class="btn btn-primary agendar__siguiente" data-agendar-siguiente hidden>Siguiente</button>
                                        </div>
                                    </div>
                                </div>

                                <div class="agendar__paso" data-agendar-paso="formulario" hidden>
                                    <button type="button" class="agendar__volver" data-agendar-cambiar-horario>
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 12H5M12 19l-7-7 7-7"></path></svg>
                                        Cambiar horario
                                    </button>
                                    <h3 class="agendar__form-titulo">Tus datos</h3>
                                    <p class="agendar__form-subtitulo">Solo lo necesario para enviarte el enlace de la videollamada.</p>

                                    <form class="agendar__form" data-agendar-form novalidate>
                                        <div class="field field--full">
                                            <label class="field__label" for="agendar_nombre">Nombre <span class="agendar__requerido">*</span></label>
                                            <input class="input" type="text" name="nombre" id="agendar_nombre" maxlength="255" placeholder="Juan García" required>
                                            <span class="field__error" data-error-for="nombre"></span>
                                        </div>
                                        <div class="field">
                                            <label class="field__label" for="agendar_email">Correo <span class="agendar__requerido">*</span></label>
                                            <input class="input" type="email" name="email" id="agendar_email" maxlength="255" placeholder="juan@empresa.com" required>
                                            <span class="field__error" data-error-for="email"></span>
                                        </div>
                                        <div class="field">
                                            <label class="field__label" for="agendar_telefono">WhatsApp <span class="agendar__requerido">*</span></label>
                                            <input class="input" type="tel" name="telefono" id="agendar_telefono" maxlength="30" placeholder="+52 55 1234 5678" required>
                                            <span class="field__error" data-error-for="telefono"></span>
                                        </div>
                                        <div class="field field--full">
                                            <label class="field__label" for="agendar_empresa">Empresa o sitio web <span class="agendar__opcional">(opcional)</span></label>
                                            <input class="input" type="text" name="empresa" id="agendar_empresa" maxlength="255" placeholder="miempresa.com">
                                        </div>
                                        <div class="field field--full">
                                            <label class="field__label" for="agendar_notas">¿Algo que debamos saber? <span class="agendar__opcional">(opcional)</span></label>
                                            <textarea class="input" name="notas" id="agendar_notas" rows="2" maxlength="1000" placeholder="Tu objetivo principal…"></textarea>
                                            <span class="field__error" data-error-for="notas"></span>
                                        </div>

                                        <div class="agendar__aviso" data-agendar-aviso hidden></div>

                                        <button type="submit" class="btn btn-primary agendar__confirmar" data-agendar-enviar>
                                            Confirmar · <span data-agendar-confirmar-texto></span>
                                        </button>
                                        <p class="agendar__form-nota">Puedes reprogramar o cancelar desde el correo de confirmación.</p>
                                    </form>
                                </div>

                                <div class="agendar__paso" data-agendar-paso="confirmacion" hidden>
                                    <div class="agendar__confirmacion" data-agendar-confirmacion></div>
                                </div>
                            </div>
                        </div>

                        <div class="agendar__badges">
                            <div class="agendar__badge-item">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                                <div>
                                    <div class="agendar__badge-label">100% gratuita</div>
                                    <div class="agendar__badge-sub">Sin costo ni compromiso</div>
                                </div>
                            </div>
                            <div class="agendar__badge-item">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><path d="M12 6v6l4 2"></path></svg>
                                <div>
                                    <div class="agendar__badge-label">{{ $config->duracion_minutos }} minutos</div>
                                    <div class="agendar__badge-sub">Sesión puntual y enfocada</div>
                                </div>
                            </div>
                            <div class="agendar__badge-item">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 18v-6a9 9 0 0 1 18 0v6"></path><path d="M21 19a2 2 0 0 1-2 2h-1v-6h3v4zM3 19a2 2 0 0 0 2 2h1v-6H3v4z"></path></svg>
                                <div>
                                    <div class="agendar__badge-label">Experto real</div>
                                    <div class="agendar__badge-sub">No robots, no vendedores</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        @endif
    </main>

    @include('components.footer')
@endsection

@push('scripts')
    @vite('resources/js/modules/agendar.js')
@endpush
