{{--
    Landing de conversion para Automatizacion de Procesos con n8n.

    Servicio NUEVO: se dio de alta en App\Support\Servicios, asi que aparece solo
    en el megamenu, el footer, el hub /servicios y el sitemap.

    Traducida del prototipo React/Tailwind a Blade + CSS del sitio. Cambios
    deliberados respecto al diseno original, en linea con los de SEM, SEO y Web:

      1. El formulario de captura se sustituyo por canales de WhatsApp y correo.
         En el prototipo el envio era falso (setSent(true) no mandaba nada).
      2. El telefono del prototipo (+52 55 1234 5678) y el correo (hola@rankpro.mx)
         eran marcadores de posicion; aqui se usan los datos reales de RankPro.

    La cifra "-70% en tiempo de respuesta" y los testimonios anonimos vienen del
    diseno y NO estan verificados: revisalos antes de publicar.
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
    $wa = 'https://wa.me/527341036410';
    $waDiagnostico = $wa . '?text=' . rawurlencode('Hola RankPro, quiero el diagnóstico gratuito de automatización de procesos.');
    $correo = 'administracion@rankprosolutions.com.mx';

    $dolores = [
        ['icon' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><line x1="17" x2="22" y1="8" y2="13"></line><line x1="22" x2="17" y1="8" y2="13"></line>', 'title' => 'Leads que se enfrían', 'desc' => 'Un formulario llega al correo de alguien que está en junta. Cuando alguien responde, el prospecto ya cotizó con otro.'],
        ['icon' => '<circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline>', 'title' => 'Respuesta lenta', 'desc' => 'El primero en contestar suele quedarse la venta. Si tu equipo tarda horas, buena parte de esos leads ya no contesta.'],
        ['icon' => '<path d="m17 2 4 4-4 4"></path><path d="M3 11v-1a4 4 0 0 1 4-4h14"></path><path d="m7 22-4-4 4-4"></path><path d="M21 13v1a4 4 0 0 1-4 4H3"></path>', 'title' => 'Trabajo manual repetitivo', 'desc' => 'Copiar datos entre hojas, CRM y facturación. Horas de gente capaz haciendo lo que un flujo hace solo.'],
    ];

    $soluciones = [
        ['icon' => '<rect width="20" height="16" x="2" y="4" rx="2"></rect><path d="M6 8h.01"></path><path d="M10 8h8"></path><path d="M6 12h.01"></path><path d="M10 12h8"></path>', 'title' => 'Captación automática de leads', 'desc' => 'Formularios, WhatsApp, Meta y Google Ads entran a un solo flujo, sin capturas manuales.'],
        ['icon' => '<ellipse cx="12" cy="5" rx="9" ry="3"></ellipse><path d="M3 5V19A9 3 0 0 0 21 19V5"></path><path d="M3 12A9 3 0 0 0 21 12"></path>', 'title' => 'Integración con tu CRM', 'desc' => 'Cada lead se crea, se asigna y se etiqueta solo, con su origen y campaña intactos.'],
        ['icon' => '<path d="M10.268 21a2 2 0 0 0 3.464 0"></path><path d="M22 8c0-2.3-.8-4.3-2-6"></path><path d="M3.262 15.326A1 1 0 0 0 4 17h16a1 1 0 0 0 .74-1.673C19.41 13.956 18 12.499 18 8A6 6 0 0 0 6 8c0 4.499-1.411 5.956-2.738 7.326"></path><path d="M4 2C2.8 3.7 2 5.7 2 8"></path>', 'title' => 'Notificaciones en tiempo real', 'desc' => 'Aviso al vendedor correcto por WhatsApp o Slack en segundos, con contexto y siguiente paso.'],
        ['icon' => '<path d="M3 3v16a2 2 0 0 0 2 2h16"></path><path d="M18 17V9"></path><path d="M13 17V5"></path><path d="M8 17v-3"></path>', 'title' => 'Reportes automáticos', 'desc' => 'Tableros y resúmenes que llegan solos cada lunes. Nadie vuelve a armar el reporte a mano.'],
    ];

    $pasos = [
        ['n' => '01', 'title' => 'Diagnóstico gratuito', 'desc' => 'Mapeamos tus procesos actuales y encontramos dónde se pierde tiempo y dinero. 45 minutos, sin costo.'],
        ['n' => '02', 'title' => 'Diseño del flujo', 'desc' => 'Te entregamos el diagrama del flujo propuesto, los sistemas que conecta y el ahorro estimado.'],
        ['n' => '03', 'title' => 'Implementación con n8n', 'desc' => 'Construimos, probamos y ponemos en marcha en tu propia instancia. Tú eres dueño del flujo.'],
        ['n' => '04', 'title' => 'Monitoreo y optimización', 'desc' => 'Alertas si algo falla, ajustes continuos y nuevos flujos conforme crece la operación.'],
    ];

    $testimonios = [
        ['quote' => 'Pasamos de contestar en 4 horas a 3 minutos. Se nota en el cierre.', 'who' => 'Dirección Comercial', 'where' => 'Grupo inmobiliario'],
        ['quote' => 'Dejamos de capturar leads a mano. Nadie extraña ese Excel.', 'who' => 'Gerencia de Marketing', 'where' => 'Retail nacional'],
        ['quote' => 'El reporte de los lunes ya llega hecho. Recuperamos dos días al mes.', 'who' => 'Operaciones', 'where' => 'Agencia B2B'],
    ];

    $ico = fn ($p, $s = 16) => '<svg xmlns="http://www.w3.org/2000/svg" width="'.$s.'" height="'.$s.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$p.'</svg>';
    $icoCheck = '<circle cx="12" cy="12" r="10"></circle><path d="m9 12 2 2 4-4"></path>';
    $icoFlecha = '<path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path>';
    $icoWa = '<path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"></path>';
    $icoTel = '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>';
    $icoMail = '<rect width="20" height="16" x="2" y="4" rx="2"></rect><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path>';
    $icoZap = '<path d="M4 14a1 1 0 0 1-.78-1.63l9.9-10.2a.5.5 0 0 1 .86.46l-1.92 6.02A1 1 0 0 0 13 10h7a1 1 0 0 1 .78 1.63l-9.9 10.2a.5.5 0 0 1-.86-.46l1.92-6.02A1 1 0 0 0 11 14z"></path>';
    $icoFlujo = '<rect width="8" height="8" x="3" y="3" rx="2"></rect><path d="M7 11v4a2 2 0 0 0 2 2h4"></path><rect width="8" height="8" x="13" y="13" rx="2"></rect>';
    $icoForm = '<rect width="20" height="16" x="2" y="4" rx="2"></rect><path d="M6 8h.01"></path><path d="M10 8h8"></path><path d="M6 12h.01"></path><path d="M10 12h8"></path>';
    $icoDb = '<ellipse cx="12" cy="5" rx="9" ry="3"></ellipse><path d="M3 5V19A9 3 0 0 0 21 19V5"></path><path d="M3 12A9 3 0 0 0 21 12"></path>';
    $icoCampana = '<path d="M10.268 21a2 2 0 0 0 3.464 0"></path><path d="M3.262 15.326A1 1 0 0 0 4 17h16a1 1 0 0 0 .74-1.673C19.41 13.956 18 12.499 18 8A6 6 0 0 0 6 8c0 4.499-1.411 5.956-2.738 7.326"></path>';
    $icoTablero = '<path d="M3 3v16a2 2 0 0 0 2 2h16"></path><path d="M18 17V9"></path><path d="M13 17V5"></path><path d="M8 17v-3"></path>';
    $icoCohete = '<path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91 0z"></path><path d="m12 15-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z"></path>';
    $icoEscudo = '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"></path>';

    // Nodo del diagrama de flujo.
    $nodo = function (string $icon, string $label, ?string $sub = null, bool $acento = false) use ($ico) {
        $html = '<div class="cv-node' . ($acento ? ' cv-node--acento' : '') . '">';
        $html .= '<span class="cv-node__icon">' . $ico($icon, 17) . '</span>';
        $html .= '<div class="cv-node__label">' . e($label) . '</div>';
        if ($sub !== null) {
            $html .= '<div class="cv-node__sub">' . e($sub) . '</div>';
        }
        return $html . '</div>';
    };
    $conector = '<div class="cv-conn"><span class="cv-conn__line"></span><svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg></div>';
    $conectorV = '<div class="cv-conn-v"><i></i></div>';
@endphp

@section('content')
    @include('components.topbar')
    @include('components.navbar')

    <main class="cv-dark">
        {{-- ---------------------------------------------------------- hero --}}
        <section class="cv-auto-hero">
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

                <div class="cv-auto-hero__grid">
                    <div>
                        <span class="cv-badge cv-badge--auto">{!! $ico($icoZap, 12) !!} Área de IT · Automatización con n8n</span>

                        <h1 class="cv-auto-title">Automatiza tus procesos,<br aria-hidden="true"> <em>recupera tu tiempo.</em></h1>

                        <p class="cv-auto-lead">Conectamos marketing, ventas y operaciones con automatización inteligente: los leads llegan solos al CRM, tu equipo se entera al instante y los reportes se arman sin nadie.</p>

                        <div class="cv-actions">
                            <a href="{{ $waDiagnostico }}" class="cv-btn cv-btn--acento" target="_blank" rel="noopener nofollow">Agenda tu diagnóstico gratis {!! $ico($icoFlecha, 17) !!}</a>
                            <a href="#solucion" class="cv-btn cv-btn--linea">Ver qué automatizamos</a>
                        </div>

                        <div class="cv-auto-notas">
                            <span>{!! $ico($icoCheck, 13) !!} 45 minutos, sin costo</span>
                            <span>{!! $ico($icoCheck, 13) !!} En tu propia instancia</span>
                            <span>{!! $ico($icoCheck, 13) !!} Sin contratos anuales</span>
                        </div>
                    </div>

                    {{-- Diagrama de nodos: decorativo, resumido para lectores de pantalla. --}}
                    <div class="cv-flow-card" role="img" aria-label="Diagrama: un nuevo lead entra desde web, anuncios o WhatsApp, pasa por n8n que lo valida y enriquece, y de ahí va al CRM, al aviso al vendedor y al tablero.">
                        <div class="cv-flow-card__head" aria-hidden="true">
                            <span class="cv-flow-card__title">Flujo activo</span>
                            <span class="cv-flow-card__live"><i></i>ejecutándose</span>
                        </div>

                        <div aria-hidden="true">
                            <div class="cv-flow-row">
                                {!! $nodo($icoForm, 'Nuevo lead', 'Web · Ads · WhatsApp', true) !!}
                                {!! $conector !!}
                                {!! $nodo($icoFlujo, 'n8n', 'valida y enriquece') !!}
                            </div>
                            <div class="cv-flow-tri">
                                {!! $nodo($icoDb, 'CRM', 'asignado') !!}
                                {!! $nodo($icoCampana, 'Aviso', '< 60 seg') !!}
                                {!! $nodo($icoTablero, 'Tablero', 'tiempo real') !!}
                            </div>
                        </div>

                        <div class="cv-flow-card__stats" aria-hidden="true">
                            @foreach ([['428', 'ejecuciones/mes'], ['0', 'capturas manuales'], ['52 s', 'lead a contacto']] as [$v, $l])
                                <div>
                                    <div class="cv-flow-card__stat-v">{{ $v }}</div>
                                    <div class="cv-flow-card__stat-l">{{ $l }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- --------------------------------------------------------- dolor --}}
        <section class="cv-dark-sec cv-dark-sec--line" id="problema">
            <div class="container">
                <div style="max-width:40rem;">
                    <div class="cv-dark-eyebrow">El problema</div>
                    <h2 class="cv-dark-h2">No es falta de leads. Es lo que pasa después.</h2>
                </div>

                <div class="cv-dark-cards cv-dark-cards--3">
                    @foreach ($dolores as $d)
                        <div class="cv-dark-card">
                            <div class="cv-dark-card__icon">{!! $ico($d['icon'], 20) !!}</div>
                            <h3>{{ $d['title'] }}</h3>
                            <p>{{ $d['desc'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- ------------------------------------------------------ solución --}}
        <section class="cv-dark-sec cv-dark-sec--panel" id="solucion">
            <div class="container">
                <div style="max-width:40rem;">
                    <div class="cv-dark-eyebrow">Qué hacemos</div>
                    <h2 class="cv-dark-h2">Un flujo que trabaja mientras tu equipo vende</h2>
                    <p class="cv-dark-p">Cuatro piezas que resuelven la mayor parte del trabajo manual de una agencia o un área comercial.</p>
                </div>

                <div class="cv-dark-cards cv-dark-cards--2">
                    @foreach ($soluciones as $s)
                        <div class="cv-sol">
                            <span class="cv-sol__icon">{!! $ico($s['icon'], 18) !!}</span>
                            <div>
                                <h3>{{ $s['title'] }}</h3>
                                <p>{{ $s['desc'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="cv-flow-full">
                    <div class="cv-flow-card__title">El flujo, de principio a fin</div>
                    <div class="cv-flow-full__row" role="img" aria-label="Un formulario, anuncio o mensaje de WhatsApp entra a la automatización, que aplica reglas y validación, crea el lead asignado en el CRM y notifica al vendedor correcto.">
                        {!! $nodo($icoForm, 'Formulario', 'o anuncio, o WhatsApp') !!}
                        {!! $conector !!}{!! $conectorV !!}
                        {!! $nodo($icoFlujo, 'Automatización', 'reglas y validación', true) !!}
                        {!! $conector !!}{!! $conectorV !!}
                        {!! $nodo($icoDb, 'CRM', 'lead asignado') !!}
                        {!! $conector !!}{!! $conectorV !!}
                        {!! $nodo($icoCampana, 'Notificación', 'al vendedor correcto') !!}
                    </div>
                    <p class="cv-dark-p" style="max-width:42rem;margin-top:2rem;">Cada paso queda registrado: si algo falla, se reintenta y se avisa. Ningún lead se queda en un correo sin abrir.</p>
                </div>
            </div>
        </section>

        {{-- -------------------------------------------------- prueba social --}}
        <section class="cv-dark-sec">
            <div class="container">
                <div class="cv-proof-split">
                    <div>
                        <div class="cv-bignum">-70%</div>
                        <div style="margin-top:.5rem;font-size:1.25rem;font-weight:700;color:#fff;">en tiempo de respuesta a leads</div>
                        <p class="cv-dark-p" style="max-width:28rem;font-size:.875rem;">Promedio de las operaciones que automatizamos en su primer trimestre, medido desde que entra el lead hasta el primer contacto humano.</p>
                    </div>

                    <div class="cv-quotes">
                        @foreach ($testimonios as $t)
                            <figure class="cv-quote">
                                <blockquote>“{{ $t['quote'] }}”</blockquote>
                                <figcaption>
                                    <div class="cv-quote__who">{{ $t['who'] }}</div>
                                    <div class="cv-quote__where">{{ $t['where'] }}</div>
                                </figcaption>
                            </figure>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        {{-- -------------------------------------------------------- proceso --}}
        <section class="cv-dark-sec cv-dark-sec--panel" id="proceso">
            <div class="container">
                <div style="max-width:40rem;">
                    <div class="cv-dark-eyebrow">Cómo funciona</div>
                    <h2 class="cv-dark-h2">Cuatro pasos, sin sorpresas</h2>
                </div>

                <div class="cv-dark-cards cv-dark-cards--4">
                    @foreach ($pasos as $p)
                        <div class="cv-dark-card cv-dark-card--solid">
                            <div class="cv-dark-card__n">{{ $p['n'] }}</div>
                            <h3 style="margin-top:.75rem;">{{ $p['title'] }}</h3>
                            <p>{{ $p['desc'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- -------------------------------------------------- CTA intermedio --}}
        <section style="padding:4rem 0;">
            <div class="container">
                <div class="cv-band">
                    <div>
                        <h2>¿Listo para automatizar? Hablemos.</h2>
                        <p>En 45 minutos te decimos qué se puede automatizar hoy, qué ahorro representa y qué no vale la pena tocar todavía.</p>
                    </div>
                    <a href="{{ $waDiagnostico }}" class="cv-btn cv-btn--oscuro" target="_blank" rel="noopener nofollow">Agendar diagnóstico {!! $ico($icoFlecha, 17) !!}</a>
                </div>
            </div>
        </section>

        {{-- ------------------------------------------------------------ FAQ --}}
        <section class="cv-dark-sec" id="faq">
            <div class="container">
                <div style="max-width:48rem;margin:0 auto;">
                    <div class="cv-dark-eyebrow">Preguntas frecuentes</div>
                    <h2 class="cv-dark-h2">Lo que suelen preguntarnos</h2>

                    <div class="cv-faq cv-faq--dark" style="margin-top:2.5rem;">
                        @foreach ($servicio['faqs'] as $i => $faq)
                            <div class="cv-faq__item">
                                <button type="button" class="cv-faq__trigger" aria-expanded="{{ $i === 0 ? 'true' : 'false' }}" aria-controls="faq-{{ $i }}">
                                    <span>{{ $faq['p'] }}</span>
                                    {!! $ico('<path d="m6 9 6 6 6-6"></path>', 18) !!}
                                </button>
                                <div class="cv-faq__panel" id="faq-{{ $i }}" @if($i !== 0) hidden @endif>{{ $faq['r'] }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        {{-- --------------------------------------------------------- cierre --}}
        <section style="padding:0 0 7rem;">
            <div class="container">
                <div class="cv-auto-cierre">
                    <div>
                        <h2 class="cv-dark-h2" style="margin-top:0;">Cuéntanos qué te está quitando tiempo</h2>
                        <p class="cv-dark-p">Un especialista de IT revisa tu caso y te propone el primer flujo a automatizar, con el ahorro estimado en horas.</p>

                        <ul class="cv-auto-cierre__lista">
                            <li>{!! $ico($icoCohete, 16) !!} Respuesta en menos de 24 h hábiles</li>
                            <li>{!! $ico($icoEscudo, 16) !!} Tus datos no salen de tu infraestructura</li>
                            <li>{!! $ico($icoZap, 16) !!} Primer flujo operando en 2-3 semanas</li>
                        </ul>
                    </div>

                    {{-- El prototipo tenia aqui un formulario que no enviaba nada.
                         Estos canales si funcionan hoy. --}}
                    <div class="cv-auto-canales">
                        <a class="cv-auto-canal" href="{{ $waDiagnostico }}" target="_blank" rel="noopener nofollow">
                            <span class="cv-auto-canal__icon cv-auto-canal__icon--wa">{!! $ico($icoWa, 20) !!}</span>
                            <span>
                                <span class="cv-auto-canal__label">Escríbenos por WhatsApp</span>
                                <span class="cv-auto-canal__sub">+52 734 103 6410 · respuesta en horario hábil</span>
                            </span>
                        </a>
                        <a class="cv-auto-canal" href="mailto:{{ $correo }}?subject={{ rawurlencode('Diagnóstico de automatización de procesos') }}">
                            <span class="cv-auto-canal__icon">{!! $ico($icoMail, 20) !!}</span>
                            <span>
                                <span class="cv-auto-canal__label">Mándanos un correo</span>
                                <span class="cv-auto-canal__sub">{{ $correo }}</span>
                            </span>
                        </a>
                        <a class="cv-auto-canal" href="{{ route('contacto') }}">
                            <span class="cv-auto-canal__icon">{!! $ico($icoTel, 20) !!}</span>
                            <span>
                                <span class="cv-auto-canal__label">Prefiero agendar una llamada</span>
                                <span class="cv-auto-canal__sub">Ver todos los canales de contacto</span>
                            </span>
                        </a>
                    </div>
                </div>
            </div>
        </section>

        {{-- ------------------------------------------------------ guías del blog --}}
        {{-- Hub -> spoke: si no hay artículos publicados para este servicio no se
             pinta nada, para no dejar un bloque vacío en una landing de conversión.
             Hoy este servicio no tiene clúster propio en App\Support\Clusters, así
             que solo aparecerán artículos que lo declaren en la tabla pivote. --}}
        @if (isset($articulos) && $articulos->isNotEmpty())
            <section class="cv-dark-sec" style="padding:4rem 0;" aria-labelledby="guias">
                <div class="container">
                    <h2 id="guias" class="cv-dark-h2" style="font-size:1.5rem;margin-bottom:2rem;">Guías sobre {{ $servicio['nombre'] }}</h2>
                    <div class="cv-dark-cards cv-dark-cards--3" style="margin-top:0;">
                        @foreach ($articulos as $articulo)
                            <x-blog.tarjeta :articulo="$articulo" />
                        @endforeach
                    </div>
                    <p style="margin-top:1.5rem;">
                        <a href="{{ route('blog.index') }}">Ver todas las guías del blog</a>
                    </p>
                </div>
            </section>
        @endif

        {{-- ------------------------------------------------ otros servicios --}}
        <section class="cv-dark-sec cv-dark-sec--panel" style="padding:4rem 0;">
            <div class="container">
                <h2 class="cv-dark-h2" style="font-size:1.5rem;margin-bottom:2rem;">Otros servicios</h2>
                <div class="cv-dark-cards cv-dark-cards--3" style="margin-top:0;">
                    @foreach (array_slice($otrosServicios, 0, 3) as $otro)
                        <a class="cv-dark-card cv-dark-card--solid" href="{{ route('servicios.show', $otro['slug']) }}" style="text-decoration:none;display:block;">
                            <div class="cv-dark-card__icon">{!! $ico($otro['icon'], 20) !!}</div>
                            <h3>{{ $otro['nombre'] }}</h3>
                            <p>{{ $otro['resumen'] }}</p>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    </main>

    <div class="cv-bar">
        <a href="tel:+527341036410" class="cv-bar__icon" aria-label="Llamar a RankPro">{!! $ico($icoTel, 18) !!}</a>
        <a href="{{ $wa }}" class="cv-bar__icon cv-bar__icon--wa" aria-label="Escribir por WhatsApp" target="_blank" rel="noopener nofollow">{!! $ico($icoWa, 18) !!}</a>
        <a href="{{ $waDiagnostico }}" class="cv-btn cv-btn--acento cv-bar__cta" target="_blank" rel="noopener nofollow">Diagnóstico gratis {!! $ico($icoFlecha, 16) !!}</a>
    </div>

    @include('components.footer')
@endsection
