{{--
    Landing de conversion para Desarrollo Web a la medida.

    Traducida del prototipo React/Tailwind a Blade + CSS del sitio. Cambios
    deliberados respecto al diseno original:

      1. El formulario de captura se sustituyo por CTAs de WhatsApp y correo,
         igual que en las landings de SEM y SEO. En el prototipo el envio era
         falso (setSent(true) no mandaba nada). Aqui el CTA ademas ARRASTRA la
         cotizacion configurada al mensaje de WhatsApp, cosa que el formulario
         original perdia.
      2. El telefono del prototipo (+52 55 1234 5678) era un marcador de
         posicion; aqui se usa el numero real de RankPro.

    Los precios del configurador vienen del diseno y son rangos comerciales:
    revisalos antes de publicar, porque se muestran como compromiso al cliente.
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
    $waGenerico = $wa . '?text=' . rawurlencode('Hola RankPro, quiero cotizar un sitio web a la medida.');
    $correo = 'administracion@rankprosolutions.com.mx';

    $tipos = [
        'landing' => ['label' => 'Landing de campaña', 'desc' => '1 objetivo, máxima conversión', 'base' => 38000, 'semanas' => 3, 'icon' => '<path d="M4 14a1 1 0 0 1-.78-1.63l9.9-10.2a.5.5 0 0 1 .86.46l-1.92 6.02A1 1 0 0 0 13 10h7a1 1 0 0 1 .78 1.63l-9.9 10.2a.5.5 0 0 1-.86-.46l1.92-6.02A1 1 0 0 0 11 14z"></path>'],
        'corporativo' => ['label' => 'Sitio corporativo', 'desc' => 'Servicios, contenido, blog', 'base' => 78000, 'semanas' => 6, 'icon' => '<path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"></path><path d="m22 17.65-9.17 4.16a2 2 0 0 1-1.66 0L2 17.65"></path><path d="m22 12.65-9.17 4.16a2 2 0 0 1-1.66 0L2 12.65"></path>'],
        'ecommerce' => ['label' => 'E-commerce', 'desc' => 'Catálogo, pagos, envíos', 'base' => 145000, 'semanas' => 10, 'icon' => '<circle cx="8" cy="21" r="1"></circle><circle cx="19" cy="21" r="1"></circle><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"></path>'],
        'plataforma' => ['label' => 'Plataforma / SaaS', 'desc' => 'Cuentas, panel, roles', 'base' => 240000, 'semanas' => 16, 'icon' => '<rect width="7" height="9" x="3" y="3" rx="1"></rect><rect width="7" height="5" x="14" y="3" rx="1"></rect><rect width="7" height="9" x="14" y="12" rx="1"></rect><rect width="7" height="5" x="3" y="16" rx="1"></rect>'],
    ];

    $features = [
        ['key' => 'cms', 'label' => 'CMS editable por tu equipo', 'costo' => 18000, 'semanas' => 1],
        ['key' => 'i18n', 'label' => 'Multi-idioma', 'costo' => 22000, 'semanas' => 1],
        ['key' => 'pagos', 'label' => 'Pagos en línea', 'costo' => 32000, 'semanas' => 2],
        ['key' => 'reservas', 'label' => 'Reservas / agenda', 'costo' => 28000, 'semanas' => 2],
        ['key' => 'crm', 'label' => 'Integración CRM', 'costo' => 19000, 'semanas' => 1],
        ['key' => 'panel', 'label' => 'Panel de administración', 'costo' => 46000, 'semanas' => 3],
        ['key' => 'api', 'label' => 'Integraciones por API', 'costo' => 34000, 'semanas' => 2],
        ['key' => 'seo', 'label' => 'SEO técnico y schema', 'costo' => 14000, 'semanas' => 1],
    ];

    $fases = [
        ['nombre' => 'Descubrimiento', 'peso' => 0.12, 'color' => '#1A2332'],
        ['nombre' => 'UI / Prototipo', 'peso' => 0.24, 'color' => '#0F9D6E'],
        ['nombre' => 'Desarrollo', 'peso' => 0.40, 'color' => '#34D399'],
        ['nombre' => 'QA y contenido', 'peso' => 0.16, 'color' => '#F59E0B'],
        ['nombre' => 'Lanzamiento', 'peso' => 0.08, 'color' => '#6B7280'],
    ];

    $stack = ['React / Next.js', 'TypeScript', 'Tailwind', 'Node', 'PostgreSQL', 'Vercel / AWS', 'Stripe / Conekta', 'GA4 + GTM'];

    $metas = [
        ['valor' => '< 1.8 s', 'label' => 'LCP en móvil', 'nota' => 'Google exige menos de 2.5 s'],
        ['valor' => '> 95', 'label' => 'PageSpeed móvil', 'nota' => 'Medido antes de entregar'],
        ['valor' => '100%', 'label' => 'Responsive real', 'nota' => 'Probado en 12 dispositivos'],
        ['valor' => 'AA', 'label' => 'Accesibilidad WCAG', 'nota' => 'Contraste y navegación por teclado'],
    ];

    $objections = [
        ['q' => '¿Por qué a medida y no WordPress con plantilla?', 'a' => 'Si tu sitio es informativo y no vas a crecer, una plantilla puede bastar y te lo diremos. A medida gana cuando necesitas velocidad real, integraciones, procesos propios o un diseño que no se parezca al de todos: se paga solo en conversión y en no rehacerlo en 18 meses.'],
        ['q' => '¿De quién es el código?', 'a' => 'Tuyo. Trabajamos en tu repositorio de GitHub desde el primer commit, con acceso de propietario para ti. Sin licencias atadas a nosotros ni hosting cautivo.'],
        ['q' => '¿Qué pasa si el alcance cambia a medio proyecto?', 'a' => 'Cotizamos por fases. Los cambios dentro del alcance entran sin costo; lo nuevo se cotiza aparte y tú apruebas antes de que se programe una línea.'],
        ['q' => '¿Incluye contenido y fotos?', 'a' => 'Incluye estructura, redacción de la copy base y montaje. Fotografía y video se cotizan aparte o los aporta tu equipo.'],
        ['q' => '¿Y después del lanzamiento?', 'a' => '30 días de garantía sobre cualquier bug, más planes de mantenimiento mensuales opcionales con mejoras continuas y monitoreo de velocidad.'],
    ];

    // Estado inicial del configurador: corporativo, 8 plantillas, CMS + SEO.
    // Se calcula en PHP para que la pagina se sirva ya con numeros correctos
    // aunque el JavaScript no llegue a ejecutarse.
    $iniTipo = 'corporativo';
    $iniPaginas = 8;
    $iniExtras = ['cms', 'seo'];
    $extrasIni = array_values(array_filter($features, fn ($f) => in_array($f['key'], $iniExtras, true)));
    $costoExtras = array_sum(array_column($extrasIni, 'costo'));
    $semanasExtras = array_sum(array_column($extrasIni, 'semanas'));
    $paginasExtra = max(0, $iniPaginas - 5) * 6200;
    $total = $tipos[$iniTipo]['base'] + $paginasExtra + $costoExtras;
    $semanasIni = max(2, (int) round($tipos[$iniTipo]['semanas'] + $semanasExtras + intdiv(max(0, $iniPaginas - 5), 4)));
    $money = fn ($n) => '$' . number_format(round($n), 0, '.', ',');

    $cfgJs = [
        'tipos' => collect($tipos)->map(fn ($t, $k) => ['label' => $t['label'], 'base' => $t['base'], 'semanas' => $t['semanas']])->all(),
        'features' => $features,
    ];

    $ico = fn ($p, $s = 16) => '<svg xmlns="http://www.w3.org/2000/svg" width="'.$s.'" height="'.$s.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$p.'</svg>';
    $icoCheck = '<circle cx="12" cy="12" r="10"></circle><path d="m9 12 2 2 4-4"></path>';
    $icoFlecha = '<path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path>';
    $icoWa = '<path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"></path>';
    $icoTel = '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>';
    $icoGit = '<path d="M15 22v-4a4.8 4.8 0 0 0-1-3.5c3 0 6-2 6-5.5.08-1.25-.27-2.48-1-3.5.28-1.15.28-2.35 0-3.5 0 0-1 0-3 1.5-2.64-.5-5.36-.5-8 0C6 2 5 2 5 2c-.3 1.15-.3 2.35 0 3.5A5.4 5.4 0 0 0 4 9c0 3.5 3 5.5 6 5.5-.39.49-.68 1.05-.85 1.65-.17.6-.22 1.23-.15 1.85v4"></path><path d="M9 18c-4.51 2-5-2-7-2"></path>';
    $icoGauge = '<path d="m12 14 4-4"></path><path d="M3.34 19a10 10 0 1 1 17.32 0"></path>';
    $icoEscudo = '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"></path>';
    $icoDoc = '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"></path><path d="M14 2v4a2 2 0 0 0 2 2h4"></path><path d="m9 18 3-3-3-3"></path>';
@endphp

@section('content')
    @include('components.topbar')
    @include('components.navbar')

    <main>
        {{-- ---------------------------------------------------------- hero --}}
        <section class="cv-hero" style="background:#ffffff;">
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

                <div class="cv-hero__grid" style="align-items:center;">
                    <div>
                        <span class="cv-badge">{!! $ico('<path d="m18 16 4-4-4-4"></path><path d="m6 8-4 4 4 4"></path><path d="m14.5 4-5 16"></path>', 12) !!} Desarrollo web a la medida</span>

                        <h1 class="cv-title">Tu sitio, armado a tu medida.<br aria-hidden="true"> <span class="text-brand">Con precio y fecha</span> antes de la junta.</h1>

                        <p class="cv-lead">Elige lo que necesitas y verás el rango de inversión, las semanas de entrega y el cronograma por fases al instante. Sin correos de ida y vuelta para saber cuánto cuesta.</p>

                        <div class="cv-actions">
                            <a href="#configurador" class="cv-btn cv-btn--primary">Armar mi cotización {!! $ico($icoFlecha, 17) !!}</a>
                            <a href="{{ $waGenerico }}" class="cv-btn cv-btn--ghost" target="_blank" rel="noopener">{!! $ico($icoWa, 16) !!} Tengo un caso raro</a>
                        </div>

                        <div class="cv-trust">
                            <span>{!! $ico($icoGit, 13) !!} Código en tu repositorio</span>
                            <span>{!! $ico($icoGauge, 13) !!} PageSpeed &gt; 95 al entregar</span>
                            <span>{!! $ico($icoEscudo, 13) !!} 30 días de garantía</span>
                        </div>
                    </div>

                    {{-- Mock de navegador: decorativo, se oculta a lectores de pantalla. --}}
                    <div class="cv-mock" aria-hidden="true">
                        <div class="cv-mock__bar">
                            <span class="cv-mock__dot" style="background:#EF4444;"></span>
                            <span class="cv-mock__dot" style="background:#F59E0B;"></span>
                            <span class="cv-mock__dot" style="background:#0F9D6E;"></span>
                            <div class="cv-mock__url">https://tumarca.mx</div>
                        </div>
                        <div class="cv-mock__body">
                            <div class="cv-mock__row">
                                <div class="cv-sk cv-sk--ink" style="height:.875rem;width:6rem;"></div>
                                <div class="cv-mock__nav">
                                    @for ($i = 0; $i < 3; $i++)
                                        <div class="cv-sk" style="height:.625rem;width:3rem;"></div>
                                    @endfor
                                    <div class="cv-sk cv-sk--grad" style="height:1.5rem;width:5rem;border-radius:.375rem;"></div>
                                </div>
                            </div>

                            <div class="cv-mock__hero">
                                <div class="cv-mock__lines">
                                    <div class="cv-sk cv-sk--ink" style="height:1.5rem;width:100%;"></div>
                                    <div class="cv-sk cv-sk--brand" style="height:1.5rem;width:75%;"></div>
                                    <div class="cv-sk" style="height:.5rem;width:100%;"></div>
                                    <div class="cv-sk" style="height:.5rem;width:83%;"></div>
                                    <div class="cv-sk cv-sk--grad" style="height:2rem;width:8rem;border-radius:.5rem;margin-top:.5rem;"></div>
                                </div>
                                <div class="cv-mock__tiles">
                                    @for ($i = 0; $i < 4; $i++)
                                        <div class="cv-mock__tile">
                                            <div class="cv-sk cv-sk--soft" style="height:1.5rem;width:1.5rem;border-radius:.5rem;margin-bottom:.5rem;"></div>
                                            <div class="cv-sk" style="height:.375rem;width:100%;"></div>
                                            <div class="cv-sk" style="height:.375rem;width:66%;margin-top:.375rem;"></div>
                                        </div>
                                    @endfor
                                </div>
                            </div>

                            <div class="cv-mock__metas">
                                @foreach ($metas as $m)
                                    <div>
                                        <div class="cv-mock__meta-v">{{ $m['valor'] }}</div>
                                        <div class="cv-mock__meta-l">{{ $m['label'] }}</div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- -------------------------------------------------- configurador --}}
        <section class="cv-section cv-section--alt" id="configurador">
            <div class="container">
                <div style="max-width:40rem;">
                    <span class="cv-badge">Configurador</span>
                    <h2 class="cv-calc__title">Arma tu proyecto y mira el precio moverse</h2>
                    <p class="cv-card__text">Rangos reales de proyectos entregados en México. La propuesta final se ajusta después de entender tu operación, pero el orden de magnitud es este.</p>
                </div>

                <div class="cv-config" data-cv-config='@json($cfgJs)' data-wa="{{ $wa }}">
                    <div class="cv-config__col">
                        <div class="cv-box">
                            <div class="cv-box__title">1 · ¿Qué vas a construir?</div>
                            <div class="cv-tipos">
                                @foreach ($tipos as $key => $op)
                                    <button type="button" class="cv-tipo" data-tipo="{{ $key }}" aria-pressed="{{ $key === $iniTipo ? 'true' : 'false' }}">
                                        <span class="cv-tipo__icon">{!! $ico($op['icon'], 17) !!}</span>
                                        <span>
                                            <span class="cv-tipo__label">{{ $op['label'] }}</span>
                                            <span class="cv-tipo__desc">{{ $op['desc'] }}</span>
                                            <span class="cv-tipo__base">desde {{ $money($op['base']) }}</span>
                                        </span>
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <div class="cv-box">
                            <div class="cv-calc__row">
                                <span class="cv-box__title" id="lbl-paginas">2 · ¿Cuántas plantillas de página?</span>
                                <span class="cv-calc__value" data-out-paginas>{{ $iniPaginas }}</span>
                            </div>
                            <input class="cv-calc__range" type="range" min="1" max="40" value="{{ $iniPaginas }}" data-rango-paginas aria-labelledby="lbl-paginas" style="margin-top:1rem;">
                            <div class="cv-scale">
                                <span>1 plantilla</span><span>5 incluidas</span><span>40+</span>
                            </div>
                        </div>

                        <div class="cv-box">
                            <div class="cv-box__title">3 · ¿Qué debe hacer?</div>
                            <div class="cv-chips">
                                @foreach ($features as $f)
                                    <button type="button" class="cv-chip" data-feature="{{ $f['key'] }}" aria-pressed="{{ in_array($f['key'], $iniExtras, true) ? 'true' : 'false' }}">
                                        <span class="cv-chip__check">{!! $ico($icoCheck, 14) !!}</span>{{ $f['label'] }}
                                    </button>
                                @endforeach
                            </div>

                            <div class="cv-switch-row">
                                <div>
                                    <div class="cv-box__title">Entrega acelerada</div>
                                    <div class="cv-tipo__desc">Equipo dedicado, ~30% menos tiempo</div>
                                </div>
                                <button type="button" class="cv-switch" data-switch-rush aria-pressed="false" aria-label="Activar entrega acelerada">
                                    <span class="cv-switch__knob"></span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="cv-config__resumen">
                        <div class="cv-resumen">
                            <div class="cv-resumen__eyebrow">Tu proyecto</div>
                            <div class="cv-resumen__label">Rango de inversión</div>
                            <div class="cv-resumen__rango" data-out-rango>{{ $money($total * 0.9) }} – {{ $money($total * 1.15) }}</div>
                            <div class="cv-resumen__nota">MXN + IVA · pago en 3 hitos</div>

                            <div class="cv-resumen__grid">
                                <div>
                                    <div class="cv-resumen__n" data-out-semanas>{{ $semanasIni }}</div>
                                    <div class="cv-resumen__n-label">semanas de entrega</div>
                                </div>
                                <div>
                                    <div class="cv-resumen__n" data-out-personas>{{ 2 + min(4, count($extrasIni)) }}</div>
                                    <div class="cv-resumen__n-label">personas asignadas</div>
                                </div>
                            </div>

                            <ul class="cv-resumen__desglose" data-out-desglose>
                                <li><span>{{ $tipos[$iniTipo]['label'] }}</span><span>{{ $money($tipos[$iniTipo]['base']) }}</span></li>
                                @if ($paginasExtra > 0)
                                    <li><span>{{ $iniPaginas - 5 }} plantillas extra</span><span>{{ $money($paginasExtra) }}</span></li>
                                @endif
                                @foreach ($extrasIni as $f)
                                    <li><span>{{ $f['label'] }}</span><span>{{ $money($f['costo']) }}</span></li>
                                @endforeach
                            </ul>

                            {{-- El CTA arrastra la configuracion al mensaje de WhatsApp. --}}
                            <a href="{{ $waGenerico }}" class="cv-btn cv-btn--primary cv-btn--block" style="margin-top:1.75rem;" target="_blank" rel="noopener" data-cta-wa>Recibir propuesta detallada {!! $ico($icoFlecha, 16) !!}</a>
                            <p class="cv-resumen__pie">Te llega con alcance, cronograma y supuestos. Sin compromiso. También puedes escribirnos a <a href="mailto:{{ $correo }}" style="color:var(--brand-light);">{{ $correo }}</a>.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- --------------------------------------------------- cronograma --}}
        <section class="cv-section">
            <div class="container" style="max-width:64rem;">
                <span class="cv-badge">Cronograma estimado</span>
                <h2 class="cv-calc__title">Cómo se reparten esas <span data-out-semanas-titulo>{{ $semanasIni }}</span> semanas</h2>

                <div class="cv-gantt" data-cv-gantt>
                    @php $acumulado = 0; @endphp
                    @foreach ($fases as $f)
                        <div class="cv-gantt__row" data-fase data-peso="{{ $f['peso'] }}">
                            <div class="cv-gantt__name">{{ $f['nombre'] }}</div>
                            <div class="cv-gantt__track">
                                <div class="cv-gantt__bar" style="left:{{ $acumulado * 100 }}%;width:{{ $f['peso'] * 100 }}%;background:{{ $f['color'] }};">
                                    <span data-fase-sem>{{ max(1, round($semanasIni * $f['peso'])) }} sem</span>
                                </div>
                            </div>
                            <div class="cv-gantt__pct">{{ round($f['peso'] * 100) }}% del esfuerzo</div>
                        </div>
                        @php $acumulado += $f['peso']; @endphp
                    @endforeach
                </div>

                <div class="cv-trust" style="margin-top:2rem;">
                    <span>{!! $ico($icoCheck, 13) !!} Demo navegable desde la semana 2</span>
                    <span>{!! $ico($icoCheck, 13) !!} Entregas semanales en ambiente de pruebas</span>
                </div>
            </div>
        </section>

        {{-- ------------------------------------------------ stack y estándares --}}
        <section class="cv-section" style="background:var(--ink);">
            <div class="container">
                <div class="cv-stack">
                    <div>
                        <div class="cv-stack__eyebrow">Con qué lo construimos</div>
                        <h2 class="cv-title cv-title--light" style="font-size:clamp(1.75rem,4vw,2rem);margin-top:.5rem;">Tecnología que tu equipo puede mantener</h2>
                        <p class="cv-lead cv-lead--light" style="font-size:1rem;">Nada de frameworks exóticos ni código que solo nosotros entendemos. Stack estándar de mercado, documentado, en tu repositorio, con capacitación al cierre.</p>

                        <div class="cv-stack__tags">
                            @foreach ($stack as $s)
                                <span class="cv-stack__tag">{{ $s }}</span>
                            @endforeach
                        </div>

                        <div class="cv-stack__notes">
                            <span>{!! $ico($icoGit, 16) !!} Repositorio a tu nombre</span>
                            <span>{!! $ico($icoDoc, 16) !!} Documentación técnica</span>
                        </div>
                    </div>

                    <div class="cv-metas">
                        @foreach ($metas as $m)
                            <div class="cv-meta">
                                <div class="cv-meta__v">{{ $m['valor'] }}</div>
                                <div class="cv-meta__l">{{ $m['label'] }}</div>
                                <div class="cv-meta__n">{{ $m['nota'] }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        {{-- ----------------------------------------------------- objeciones --}}
        <section class="cv-section cv-section--alt">
            <div class="container">
                <div class="cv-head">
                    <h2>Antes de que preguntes</h2>
                </div>

                <div class="cv-faq">
                    @foreach ($objections as $i => $o)
                        <div class="cv-faq__item" style="background:#ffffff;">
                            <button type="button" class="cv-faq__trigger" aria-expanded="{{ $i === 0 ? 'true' : 'false' }}" aria-controls="obj-{{ $i }}">
                                <span>{{ $o['q'] }}</span>
                                {!! $ico('<path d="m6 9 6 6 6-6"></path>', 18) !!}
                            </button>
                            <div class="cv-faq__panel" id="obj-{{ $i }}" @if($i !== 0) hidden @endif>{{ $o['a'] }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- ------------------------------------------------------ CTA final --}}
        <section class="cv-section cv-section--brand">
            <div class="container">
                <div class="cv-final">
                    <h2>Cotiza tu proyecto sin esperar una junta</h2>
                    <p>Arma la configuración arriba y mándanosla: te devolvemos la propuesta con alcance y cronograma.</p>
                    <div class="cv-actions">
                        <a href="#configurador" class="cv-btn cv-btn--white">Armar mi cotización {!! $ico($icoFlecha, 17) !!}</a>
                        <a href="{{ route('contacto') }}" class="cv-btn cv-btn--outline-light">Prefiero agendar una llamada</a>
                    </div>
                </div>
            </div>
        </section>

        {{-- --------------------------------------------------- otros servicios --}}
        <section class="cv-section cv-section--alt cv-section--tight">
            <div class="container">
                <div class="cv-head cv-head--left" style="margin-bottom:2rem;">
                    <h2 style="font-size:1.5rem;">Otros servicios</h2>
                </div>
                <div class="cv-cards">
                    @foreach (array_slice($otrosServicios, 0, 3) as $otro)
                        <a class="cv-card-soft" href="{{ route('servicios.show', $otro['slug']) }}" style="text-decoration:none;display:block;background:#ffffff;">
                            <div class="cv-card-soft__icon">{!! $ico($otro['icon'], 20) !!}</div>
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
        <a href="{{ $wa }}" class="cv-bar__icon cv-bar__icon--wa" aria-label="Escribir por WhatsApp" target="_blank" rel="noopener">{!! $ico($icoWa, 18) !!}</a>
        <a href="#configurador" class="cv-btn cv-btn--primary cv-bar__cta">Cotizar ahora {!! $ico($icoFlecha, 16) !!}</a>
    </div>

    @include('components.footer')
@endsection
