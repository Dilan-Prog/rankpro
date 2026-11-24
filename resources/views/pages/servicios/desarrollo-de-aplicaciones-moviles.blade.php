{{--
    Landing de desarrollo de aplicaciones móviles (AP-01). Metadatos, guías y FAQ
    en Servicios::todos(); el FAQPage sale de las mismas `faqs`.

    Sin precios, plazos, tecnologías ni clientes: no hay datos confirmados. No
    agregarlos sin confirmación del dueño.
--}}
@extends('layouts.app')

@section('title', $servicio['meta_title'])
@section('description', $servicio['meta_description'])
@section('canonical', route('servicios.show', $servicio['slug']))

@push('styles')
    @vite(['resources/css/web/pages.css', 'resources/css/web/servicios-conversion.css'])
@endpush

@push('scripts')
    @vite('resources/js/servicios-conversion.js')
@endpush

@include('pages.servicios._schema')

@php
    $wa = 'https://wa.me/525562230266?text='.rawurlencode('Hola RankPro, quiero cotizar una aplicación móvil.');
    $urlWeb = route('servicios.show', 'desarrollo-web');
    $rejilla = 'display:grid;gap:1.5rem;grid-template-columns:repeat(auto-fit,minmax(min(100%,18rem),1fr));';
    $parrafo = 'margin-top:1rem;line-height:1.7;color:var(--muted);';
    $celda = 'padding:0.75rem;vertical-align:top;';

    // Enlace a una guía solo si está publicada; si no, nada.
    $guia = fn (string $slug, string $antes) => isset($guias[$slug])
        ? new \Illuminate\Support\HtmlString(e($antes).' <a href="'.e(route('blog.show', $slug)).'">'.e($guias[$slug]).'</a>.')
        : '';

    $resumen = [
        'Partimos del objetivo de negocio, no de la tecnología.',
        'Diseñamos primero y desarrollamos después.',
        'Publicamos en las tiendas con tus propias cuentas.',
        'Medimos el uso para decidir qué mejorar.',
    ];

    $tipos = [
        ['Apps para clientes', 'Catálogo, pedidos, reservaciones, cuentas y notificaciones. Tienen sentido cuando tu cliente vuelve seguido y agradece no empezar de cero cada vez.'],
        ['Apps internas', 'Herramientas para tu equipo en campo, inventario o procesos. Sustituyen hojas sueltas, fotos por mensaje y capturas dobles.'],
        ['Apps de servicios', 'Seguimiento de solicitudes, citas y pagos. El usuario sabe en qué va lo suyo sin llamar para preguntar.'],
        ['Versión inicial', 'Pocas funciones para validar una idea con usuarios reales antes de invertir en el resto.'],
    ];

    $plataformas = [
        ['Solo Android', 'Tu audiencia usa mayormente este sistema', 'Menor alcance si parte de tus usuarios usa otro'],
        ['Solo iOS', 'Tu público usa iPhone', 'Requisitos propios de la tienda'],
        ['Ambas', 'Quieres llegar a toda tu audiencia', 'Más pruebas y mantenimiento'],
    ];

    $pasos = [
        ['Descubrimiento', 'Objetivos, usuarios, funciones esenciales y qué se queda fuera de la primera versión.'],
        ['Diseño', 'Flujos y pantallas que validas antes de programar.'],
        ['Desarrollo', 'Entregas por etapas para que veas avances reales.'],
        ['Pruebas', 'En dispositivos y versiones de sistema distintos.'],
        ['Publicación', 'Preparación de fichas, capturas y cumplimiento de requisitos de cada tienda.'],
        ['Mejora continua', 'Uso real, correcciones y nuevas funciones.'],
    ];

    $costos = [
        ['Funciones', 'Mostrar información cuesta menos que manejar cuentas, pagos o seguimiento en tiempo real.'],
        ['Plataformas', 'Atender dos sistemas implica más pruebas que atender uno.'],
        ['Diseño', 'No es igual adaptar una interfaz sencilla que crear animaciones y pantallas propias.'],
        ['Integraciones', 'Pagos, mapas, facturación o tu sistema interno dependen de lo que cada proveedor permita.'],
        ['Administración', 'Casi toda app necesita un panel para que tu equipo cargue datos y atienda solicitudes.'],
        ['Mantenimiento', 'Los sistemas cambian cada año y la app tiene que seguirles el paso.'],
    ];

    $errores = [
        ['Copiar una app famosa.', 'Lo que ves es el resultado de años y equipos grandes; conviene empezar por la parte que resuelve tu caso.'],
        ['Pedir todas las funciones desde el inicio.', 'Cada una se diseña, se prueba y se mantiene. Las que nadie usa también cuestan.'],
        ['Olvidar quién la va a operar.', 'Si nadie responde los pedidos o actualiza el catálogo, la app se abandona pronto.'],
        ['Publicar sin plan para atraer usuarios.', 'Estar en la tienda no significa que te encuentren: hay que invitar a tus clientes a instalarla y darles un motivo.'],
        ['Abrir las cuentas a nombre de un tercero.', 'Si después cambias de proveedor, recuperar el acceso puede ser complicado.'],
    ];

    $ico = fn ($p, $s = 16) => '<svg xmlns="http://www.w3.org/2000/svg" width="'.$s.'" height="'.$s.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$p.'</svg>';
    $icoCheck = '<circle cx="12" cy="12" r="10"></circle><path d="m9 12 2 2 4-4"></path>';
    $icoFlecha = '<path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path>';
@endphp

@section('content')
    @include('components.topbar')
    @include('components.navbar')

    <main>
        <section class="cv-hero">
            <div class="container">
                <nav class="breadcrumb" aria-label="Ruta de navegación">
                    <ol>
                        <li><a href="{{ route('home') }}">Inicio</a></li>
                        <li><span class="breadcrumb__sep" aria-hidden="true">/</span></li>
                        <li><a href="{{ route('servicios.index') }}">Servicios</a></li>
                        <li><span class="breadcrumb__sep" aria-hidden="true">/</span></li>
                        <li><span aria-current="page">{{ $servicio['nombre'] }}</span></li>
                    </ol>
                </nav>

                <div style="max-width:48rem;">
                    <h1 class="cv-title">{{ $servicio['h1'] }}</h1>
                    <p class="cv-lead" style="max-width:44rem;">{{ $servicio['intro'] }}</p>
                    <ul class="cv-checklist">
                        @foreach ($resumen as $punto)
                            <li>{!! $ico($icoCheck, 18) !!} <span>{{ $punto }}</span></li>
                        @endforeach
                    </ul>
                    <div class="cv-actions">
                        <a href="{{ $wa }}" class="cv-btn cv-btn--primary" target="_blank" rel="noopener nofollow">Cotizar mi app {!! $ico($icoFlecha, 17) !!}</a>
                        <a href="{{ $urlWeb }}" class="cv-btn cv-btn--ghost">Ver desarrollo web</a>
                    </div>
                </div>
            </div>
        </section>

        <section class="cv-section cv-section--tight" aria-labelledby="necesitas">
            <div class="container" style="max-width:48rem;">
                <div class="cv-head" style="margin-bottom:1rem;">
                    <h2 id="necesitas">¿Una app es lo que necesitas?</h2>
                </div>
                <p style="{{ $parrafo }}">Una app tiene sentido cuando tus usuarios la van a usar con frecuencia, necesitan una cuenta, o requieres funciones del teléfono como notificaciones, cámara o ubicación. Si lo que buscas es dar información o vender de forma ocasional, un <a href="{{ $urlWeb }}">sitio web rápido</a> puede resolverlo con menos costo. Te lo decimos con claridad antes de cotizar.</p>
                <p style="{{ $parrafo }}">Para decidirlo hacemos preguntas sencillas: cuántas veces al mes la abriría una misma persona, qué haría ahí que hoy no puede hacer, y qué pasaría si no la instala. Cuando las respuestas son vagas, suele ser mejor probar la idea en la web y dejar la aplicación móvil para después.</p>
            </div>
        </section>

        <section class="cv-section cv-section--alt cv-section--tight" aria-labelledby="tipos">
            <div class="container">
                <div class="cv-head">
                    <h2 id="tipos">Qué tipos de apps desarrollamos</h2>
                </div>
                <div style="{{ $rejilla }}">
                    @foreach ($tipos as [$t, $d])
                        <div class="cv-card-soft" style="background:#ffffff;">
                            <h3>{{ $t }}</h3>
                            <p>{{ $d }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="cv-section cv-section--tight" aria-labelledby="plataformas">
            <div class="container" style="max-width:60rem;">
                <div class="cv-head">
                    <h2 id="plataformas">Android, iOS o ambas</h2>
                </div>
                <div style="overflow-x:auto;" tabindex="0" role="region" aria-labelledby="plataformas">
                    <table style="width:100%;min-width:34rem;border-collapse:collapse;font-size:0.9375rem;color:var(--ink);">
                        <thead>
                            <tr style="border-bottom:2px solid var(--border);text-align:left;">
                                <th scope="col" style="{{ $celda }}">Opción</th>
                                <th scope="col" style="{{ $celda }}">Cuándo conviene</th>
                                <th scope="col" style="{{ $celda }}">Qué considerar</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($plataformas as [$opcion, $cuando, $considera])
                                <tr style="border-bottom:1px solid var(--border);">
                                    <th scope="row" style="{{ $celda }}text-align:left;">{{ $opcion }}</th>
                                    <td style="{{ $celda }}">{{ $cuando }}</td>
                                    <td style="{{ $celda }}">{{ $considera }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p style="{{ $parrafo }}">La decisión depende de tus usuarios, no de la moda. Si ya tienes un sitio con analítica, ahí se ve qué sistema usan quienes te visitan; si es una app interna, basta con saber qué teléfonos tiene tu equipo. {{ $guia('como-crear-una-app-para-android', 'Ver') }}</p>
            </div>
        </section>

        <section class="cv-section cv-section--alt cv-section--tight" aria-labelledby="proceso">
            <div class="container">
                <div class="cv-head">
                    <h2 id="proceso">Cómo trabajamos</h2>
                    <p>El desarrollo de apps sale mejor cuando cada etapa termina con algo que puedes revisar.</p>
                </div>
                <ol style="{{ $rejilla }}list-style:none;padding:0;">
                    @foreach ($pasos as $i => [$t, $d])
                        <li class="cv-card-soft" style="background:#ffffff;">
                            <h3>{{ $i + 1 }}. {{ $t }}</h3>
                            <p>{{ $d }} @if ($t === 'Diseño') {{ $guia('diseno-de-aplicaciones-moviles', 'Ver') }} @endif</p>
                        </li>
                    @endforeach
                </ol>
                <p style="{{ $parrafo }}">{{ $guia('ciclo-de-desarrollo-de-una-app', 'Más detalle en') }}</p>
            </div>
        </section>

        <section class="cv-section cv-section--tight" aria-labelledby="incluye">
            <div class="container" style="max-width:48rem;">
                <div class="cv-head" style="margin-bottom:1rem;">
                    <h2 id="incluye">Qué incluye el servicio</h2>
                    <p>El alcance de cada proyecto se define por escrito en la propuesta, a partir de esta base.</p>
                </div>
                <ul class="cv-checklist">
                    @foreach ($servicio['entregables'] as $entregable)
                        <li>{!! $ico($icoCheck, 18) !!} <span>{{ $entregable }}</span></li>
                    @endforeach
                </ul>
            </div>
        </section>

        <section class="cv-section cv-section--alt cv-section--tight" aria-labelledby="preparar">
            <div class="container" style="max-width:48rem;">
                <div class="cv-head" style="margin-bottom:1rem;">
                    <h2 id="preparar">Qué conviene preparar antes de empezar</h2>
                </div>
                <p style="{{ $parrafo }}">No necesitas un documento técnico. Ayuda más tener claro quién la usará, qué tarea debe poder terminar y quién en tu empresa tomará decisiones. Si la app se va a conectar con un sistema que ya usas, averigua quién lo administra y si permite integraciones.</p>
                <p style="{{ $parrafo }}">También hay trámites que dependen de ti: Google Play y App Store piden una cuenta de desarrollador, tienen reglas propias y revisan las aplicaciones antes de aceptarlas. Te indicamos qué datos reunir, y el aviso de privacidad debe validarlo tu asesor legal según la información que captes.</p>
            </div>
        </section>

        <section class="cv-section cv-section--tight" aria-labelledby="costo">
            <div class="container">
                <div class="cv-head">
                    <h2 id="costo">De qué depende el costo</h2>
                    <p>No hay un precio único. Preferimos cotizar tras entender tu caso, porque estos seis factores mueven el alcance:</p>
                </div>
                <div style="{{ $rejilla }}">
                    @foreach ($costos as [$t, $d])
                        <div class="cv-card-soft">
                            <h3>{{ $t }}</h3>
                            <p>{{ $d }}</p>
                        </div>
                    @endforeach
                </div>
                <p style="{{ $parrafo }}">{{ $guia('cuanto-cuesta-crear-una-app', 'Más en') }}</p>
            </div>
        </section>

        <section class="cv-section cv-section--alt cv-section--tight" aria-labelledby="para-quien">
            <div class="container" style="max-width:48rem;">
                <div class="cv-head" style="margin-bottom:1rem;">
                    <h2 id="para-quien">Para quién es</h2>
                </div>
                <ul class="cv-checklist">
                    <li>{!! $ico($icoCheck, 18) !!} <span>Negocios que quieren fidelizar clientes con una experiencia propia.</span></li>
                    <li>{!! $ico($icoCheck, 18) !!} <span>Empresas que necesitan digitalizar un proceso con su equipo en campo.</span></li>
                    <li>{!! $ico($icoCheck, 18) !!} <span>Emprendedores que quieren validar una idea con una primera versión.</span></li>
                </ul>
                <p style="{{ $parrafo }}">No es para quien busca una app solo por tener presencia. Si tu meta es que te encuentren, rinde más un buen sitio y posicionamiento; el desarrollo de apps en México es una inversión que se justifica por el uso repetido, no por aparecer en una tienda.</p>
            </div>
        </section>

        <section class="cv-section cv-section--tight" aria-labelledby="cuidamos">
            <div class="container">
                <div class="cv-head">
                    <h2 id="cuidamos">Lo que cuidamos en cada aplicación móvil</h2>
                </div>
                <div style="{{ $rejilla }}">
                    <div class="cv-card-soft"><h3>Un problema concreto</h3><p>Cada pantalla debe ayudar a terminar una tarea; lo que no, se queda fuera.</p></div>
                    <div class="cv-card-soft"><h3>Rapidez y facilidad</h3><p>Que se entienda sin manual y responda bien en teléfonos que no son los más nuevos.</p></div>
                    <div class="cv-card-soft"><h3>Datos personales</h3><p>Se piden solo los permisos y datos necesarios, y se protegen en su envío y resguardo.</p></div>
                    <div class="cv-card-soft"><h3>Propiedad clara</h3><p>Las cuentas de las tiendas van a nombre de tu empresa y la entrega del código se pacta por escrito.</p></div>
                    <div class="cv-card-soft"><h3>Mantenimiento realista</h3><p>Antes de empezar hablamos de quién atenderá la app cuando esté publicada y cada cuánto.</p></div>
                </div>
            </div>
        </section>

        <section class="cv-section cv-section--alt cv-section--tight" aria-labelledby="errores">
            <div class="container" style="max-width:56rem;">
                <div class="cv-head" style="margin-bottom:1.5rem;">
                    <h2 id="errores">Errores frecuentes al encargar una app</h2>
                </div>
                <ul style="display:grid;gap:0.75rem;padding-left:1.25rem;line-height:1.7;color:var(--muted);">
                    @foreach ($errores as [$t, $d])
                        <li><strong style="color:var(--ink);">{{ $t }}</strong> {{ $d }}</li>
                    @endforeach
                </ul>
            </div>
        </section>

        @php
            $guiasDecidir = array_intersect_key($guias, array_flip(['como-crear-una-app', 'como-crear-una-app-para-mi-negocio', 'plataformas-para-crear-aplicaciones-moviles']));
        @endphp
        @if ($guiasDecidir !== [])
            <section class="cv-section cv-section--tight" aria-labelledby="guias">
                <div class="container" style="max-width:48rem;">
                    <div class="cv-head" style="margin-bottom:1rem;">
                        <h2 id="guias">Guías antes de decidir</h2>
                    </div>
                    <ul style="display:grid;gap:0.75rem;padding-left:1.25rem;">
                        @foreach ($guiasDecidir as $slugGuia => $textoGuia)
                            <li><a href="{{ route('blog.show', $slugGuia) }}">{{ $textoGuia }}</a></li>
                        @endforeach
                    </ul>
                </div>
            </section>
        @endif

        @if ($landings = array_filter($servicio['landings'] ?? []))
            <section class="cv-section cv-section--tight" aria-labelledby="ciudades">
                <div class="container" style="max-width:48rem;">
                    <div class="cv-head" style="margin-bottom:1rem;">
                        <h2 id="ciudades">Desarrollo de apps por ciudad</h2>
                    </div>
                    <ul style="display:flex;flex-wrap:wrap;justify-content:center;gap:0.75rem;list-style:none;padding:0;">
                        @foreach ($landings as $textoLanding => $ruta)
                            <li><a class="cv-chip" style="text-decoration:none;" href="{{ url($ruta) }}">{{ $textoLanding }}</a></li>
                        @endforeach
                    </ul>
                </div>
            </section>
        @endif

        <section class="cv-section cv-section--tight" aria-labelledby="faq">
            <div class="container">
                <div class="cv-head">
                    <h2 id="faq">Preguntas frecuentes</h2>
                </div>
                <div class="cv-faq">
                    @foreach ($servicio['faqs'] as $i => $faq)
                        <div class="cv-faq__item">
                            <button type="button" class="cv-faq__trigger" aria-expanded="{{ $i === 0 ? 'true' : 'false' }}" aria-controls="faq-{{ $i }}">
                                <span>{{ $faq['p'] }}</span>
                                {!! $ico('<path d="m6 9 6 6 6-6"></path>', 18) !!}
                            </button>
                            <div class="cv-faq__panel" id="faq-{{ $i }}" @if($i !== 0) hidden @endif>
                                <p>{{ $faq['r'] }}</p>
                                @if (isset($faq['guia'], $guias[$faq['guia']]))
                                    <p>{{ $guia($faq['guia'], 'Ver') }}</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="cv-section cv-section--brand">
            <div class="container">
                <div class="cv-final">
                    <h2>Cuéntanos qué debería resolver tu app</h2>
                    <p>Con tu objetivo y tus usuarios te decimos si conviene una app, qué dejar para después y cómo cotizarla.</p>
                    <div class="cv-actions">
                        <a href="{{ $wa }}" class="cv-btn cv-btn--white" target="_blank" rel="noopener nofollow">Cotizar mi app {!! $ico($icoFlecha, 17) !!}</a>
                        <a href="{{ route('agendar.mostrar') }}" class="cv-btn cv-btn--outline-light">Prefiero agendar una llamada</a>
                    </div>
                </div>
            </div>
        </section>
    </main>

    @include('components.footer')
@endsection
