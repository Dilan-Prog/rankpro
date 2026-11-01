{{--
    Landing de conversion para SEM & Google Ads.

    Traducida del prototipo React/Tailwind a Blade + CSS del sitio. Dos cambios
    deliberados respecto al diseno original:

      1. El formulario de captura se sustituyo por CTAs de WhatsApp y correo.
         En el prototipo el envio era falso (setSent(true) no mandaba nada), asi
         que los leads se habrian perdido en silencio.
      2. El telefono del prototipo (+52 55 1234 5678) era un marcador de posicion;
         aqui se usa el numero real de RankPro.

    Las cifras de prueba social del diseno (ROAS 4.8x, 312 cuentas, -38% CPL), las
    curvas "de nuestras cuentas", el reparto del gasto "promedio" y las insignias
    Google/Meta Partner se retiraron (nov-2026): no tenian fuente verificable y el
    sitio no publica cifras ni acreditaciones sin respaldo (ver partners.blade.php).
    Lo que queda son compromisos propios de la oferta (48 h, 7 dias, mes a mes) y
    una calculadora con supuestos declarados como ilustrativos.
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
    $waAuditoria = $wa . '?text=' . rawurlencode('Hola RankPro, quiero la auditoría gratuita de mi cuenta de Google Ads.');
    $correo = 'administracion@rankprosolutions.com.mx';

    $proof = [
        ['value' => '48 h', 'label' => 'Auditoría entregada'],
        ['value' => '7 días', 'label' => 'Del kickoff a las primeras campañas'],
        ['value' => 'Mes a mes', 'label' => 'Sin contratos forzosos'],
    ];

    $auditItems = [
        'Diagnóstico de desperdicio: términos, ubicaciones y horarios que te queman presupuesto.',
        'Revisión de estructura de campañas, pujas y calidad de anuncios.',
        'Análisis de landing pages y seguimiento de conversiones (GA4 / GTM).',
        'Proyección de leads y ROAS con tu presupuesto actual.',
    ];

    $guarantees = [
        ['icon' => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"></path><path d="m9 12 2 2 4-4"></path>', 'title' => 'Sin contratos forzosos', 'desc' => 'Trabajamos mes a mes. Si no ves resultados, te vas sin penalización.'],
        ['icon' => '<path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"></path><path d="M3 5v14a2 2 0 0 0 2 2h15a1 1 0 0 0 1-1v-4"></path>', 'title' => 'Tus cuentas son tuyas', 'desc' => 'Acceso total de administrador desde el día uno. Nada queda a nuestro nombre.'],
        ['icon' => '<circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline>', 'title' => 'Primeras campañas en 7 días', 'desc' => 'Del kickoff al lanzamiento en una semana, con seguimiento configurado.'],
    ];

    $objections = [
        ['q' => '¿Cuánto presupuesto necesito para empezar?', 'a' => 'Desde $30,000 MXN mensuales de inversión publicitaria obtenemos datos suficientes para optimizar. Con menos podemos trabajar, pero el aprendizaje toma más tiempo y te lo diremos antes de firmar.'],
        ['q' => 'Ya intenté Google Ads y no funcionó, ¿qué cambia?', 'a' => 'En la mayoría de las cuentas que auditamos el problema no es el canal: es estructura, términos de búsqueda sin filtrar y conversiones mal medidas. La auditoría te muestra exactamente dónde se está yendo el dinero, con capturas de tu propia cuenta.'],
        ['q' => '¿En cuánto tiempo veo resultados?', 'a' => 'Primeros leads en la semana 1-2. Rentabilidad estable entre el día 60 y 90, cuando el algoritmo ya tiene volumen de conversiones limpias.'],
        ['q' => '¿Qué incluye la fee mensual?', 'a' => 'Estrategia, creación y optimización de campañas, anuncios, seguimiento, reportes y juntas. La inversión publicitaria se paga directo a Google desde tu tarjeta.'],
        ['q' => '¿Firmo algo para la auditoría?', 'a' => 'Nada. Nos das acceso de solo lectura a tu cuenta, la revisamos y te entregamos el diagnóstico en 48 horas. Si decides no trabajar con nosotros, el documento es tuyo.'],
    ];

    $ico = fn ($p, $s = 16) => '<svg xmlns="http://www.w3.org/2000/svg" width="'.$s.'" height="'.$s.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$p.'</svg>';
    $icoCheck = '<circle cx="12" cy="12" r="10"></circle><path d="m9 12 2 2 4-4"></path>';
    $icoFlecha = '<path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path>';
    $icoWa = '<path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"></path>';
    $icoTel = '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>';
@endphp

@section('content')
    @include('components.topbar')
    @include('components.navbar')

    <main>
        {{-- ---------------------------------------------------------- hero --}}
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

                <div class="cv-hero__grid">
                    <div>
                        <span class="cv-badge">{!! $ico('<circle cx="12" cy="12" r="10"></circle><circle cx="12" cy="12" r="6"></circle><circle cx="12" cy="12" r="2"></circle>', 12) !!} SEM &amp; Google Ads</span>

                        <h1 class="cv-title">Deja de pagar clics.<br aria-hidden="true"> Empieza a pagar <span class="text-brand">ventas.</span></h1>

                        <p class="cv-lead">Auditamos tu cuenta de Google Ads gratis y te mostramos, con capturas, cuánto presupuesto se está yendo en búsquedas que nunca van a comprar. Después lo arreglamos.</p>

                        <ul class="cv-checklist">
                            @foreach (['Diagnóstico en 48 horas, sin contrato ni tarjeta.', 'Estructura, pujas y seguimiento reconstruidos desde cero.', 'Reportes con costo por lead y ROAS, no con impresiones.'] as $punto)
                                <li>{!! $ico($icoCheck, 19) !!}{{ $punto }}</li>
                            @endforeach
                        </ul>

                        <div class="cv-actions">
                            <a href="{{ $waAuditoria }}" class="cv-btn cv-btn--primary" target="_blank" rel="noopener nofollow">Auditar mi cuenta gratis {!! $ico($icoFlecha, 17) !!}</a>
                            <a href="mailto:{{ $correo }}?subject={{ rawurlencode('Auditoría de Google Ads') }}" class="cv-btn cv-btn--ghost">Escribir por correo</a>
                        </div>

                        <ul class="cv-proof">
                            @foreach ($proof as $p)
                                <li>
                                    <div class="cv-proof__value">{{ $p['value'] }}</div>
                                    <div class="cv-proof__label">{{ $p['label'] }}</div>
                                </li>
                            @endforeach
                        </ul>

                    </div>

                    {{-- El prototipo tenia aqui un formulario que no enviaba nada.
                         Se conserva la tarjeta, pero con canales que si funcionan. --}}
                    <aside class="cv-hero__aside">
                        <div class="cv-card">
                            <p class="cv-card__eyebrow">{!! $ico('<path d="M4 14a1 1 0 0 1-.78-1.63l9.9-10.2a.5.5 0 0 1 .86.46l-1.92 6.02A1 1 0 0 0 13 10h7a1 1 0 0 1 .78 1.63l-9.9 10.2a.5.5 0 0 1-.86-.46l1.92-6.02A1 1 0 0 0 11 14z"></path>', 13) !!} Auditoría gratuita · 48 horas</p>
                            <h2 class="cv-card__title">Descubre cuánto presupuesto estás desperdiciando</h2>
                            <p class="cv-card__text">Sin compromiso y sin contratos. Solo el diagnóstico de tu cuenta y un plan claro de los primeros 90 días.</p>

                            <ul class="cv-card__list">
                                @foreach ($auditItems as $item)
                                    <li>{!! $ico($icoCheck, 15) !!}{{ $item }}</li>
                                @endforeach
                            </ul>

                            <div class="cv-card__actions">
                                <a href="{{ $waAuditoria }}" class="cv-btn cv-btn--wa cv-btn--block" target="_blank" rel="noopener nofollow">{!! $ico($icoWa, 16) !!} Pedirla por WhatsApp</a>
                                <a href="mailto:{{ $correo }}?subject={{ rawurlencode('Auditoría de Google Ads') }}" class="cv-btn cv-btn--ghost cv-btn--block">{{ $correo }}</a>
                            </div>

                            <p class="cv-card__note">Te contesta un estratega, no un bot. En horario hábil respondemos el mismo día.</p>
                        </div>
                    </aside>
                </div>
            </div>
        </section>

        {{-- ---------------------------------------------------- calculadora --}}
        <section class="cv-section">
            <div class="container">
                <div class="cv-calc" data-cv-calc>
                    <div>
                        <span class="cv-badge">{!! $ico('<polyline points="22 7 13.5 15.5 8.5 10.5 2 17"></polyline><polyline points="16 7 22 7 22 13"></polyline>', 12) !!} Proyección con tus números</span>
                        <h2 class="cv-calc__title">¿Cuánto puede generar tu inversión en Google Ads?</h2>
                        <p class="cv-card__text">Mueve los controles con tus cifras reales. Es un modelo ilustrativo con supuestos fijos (CPC $14, 6.2% de conversión a lead, 22% de cierre) que no son resultados de clientes; el ROAS ya descuenta la fee de gestión.</p>

                        <div class="cv-calc__controls">
                            <div>
                                <div class="cv-calc__row">
                                    <label class="cv-calc__label" for="calc-presupuesto">Inversión publicitaria mensual</label>
                                    <span class="cv-calc__value" data-out-presupuesto>$80k MXN</span>
                                </div>
                                <input class="cv-calc__range" id="calc-presupuesto" type="range" min="20" max="400" step="10" value="80" data-rango-presupuesto>
                            </div>
                            <div>
                                <div class="cv-calc__row">
                                    <label class="cv-calc__label" for="calc-ticket">Ticket promedio por venta</label>
                                    <span class="cv-calc__value" data-out-ticket>$8,000</span>
                                </div>
                                <input class="cv-calc__range" id="calc-ticket" type="range" min="1000" max="80000" step="1000" value="8000" data-rango-ticket>
                            </div>
                        </div>
                    </div>

                    <div class="cv-calc__panel">
                        <p class="cv-calc__panel-eyebrow">Proyección a 90 días</p>
                        <div class="cv-calc__grid">
                            <div>
                                <div class="cv-calc__metric" data-out-clics>5,714</div>
                                <div class="cv-calc__metric-label">clics / mes</div>
                            </div>
                            <div>
                                <div class="cv-calc__metric" data-out-leads>354</div>
                                <div class="cv-calc__metric-label">leads calificados</div>
                            </div>
                            <div>
                                <div class="cv-calc__metric" data-out-ventas>78</div>
                                <div class="cv-calc__metric-label">ventas cerradas</div>
                            </div>
                            <div>
                                <div class="cv-calc__metric" data-out-roas>5.9x</div>
                                <div class="cv-calc__metric-label">ROAS estimado</div>
                            </div>
                        </div>
                        <div class="cv-calc__total">
                            <div class="cv-calc__total-label">Ingreso proyectado mensual</div>
                            <div class="cv-calc__total-value" data-out-ingresos>$624,000</div>
                        </div>
                        <a href="{{ $waAuditoria }}" class="cv-btn cv-btn--white cv-btn--block" style="margin-top:1.5rem;" target="_blank" rel="noopener nofollow">Validar estos números con un experto {!! $ico($icoFlecha, 16) !!}</a>
                        <p class="cv-calc__disclaimer">Estimación ilustrativa, no una promesa de resultados. En la auditoría ajustamos el modelo con el CPC real de tu industria.</p>
                    </div>
                </div>
            </div>
        </section>

        {{-- -------------------------------------------- qué incluye la auditoría --}}
        <section class="cv-section cv-section--alt">
            <div class="container">
                <div class="cv-split">
                    <div>
                        <span class="cv-badge">{!! $ico($icoCheck, 12) !!} La oferta, sin letra chica</span>
                        <h2 class="cv-calc__title">Qué recibes en la auditoría gratuita</h2>
                        <p class="cv-card__text">Un documento de 12 a 18 páginas con hallazgos concretos de tu cuenta y el plan de los primeros 90 días. Te lo presentamos en una llamada de 30 minutos.</p>
                        <div class="cv-actions">
                            <a href="{{ $waAuditoria }}" class="cv-btn cv-btn--primary" target="_blank" rel="noopener nofollow">Reclamar mi auditoría {!! $ico($icoFlecha, 16) !!}</a>
                        </div>
                    </div>
                    <ul class="cv-numbered">
                        @foreach ($auditItems as $i => $item)
                            <li>
                                <span class="cv-numbered__n">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span>
                                <p>{{ $item }}</p>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </section>

        {{-- ------------------------------------------------------ garantías --}}
        <section class="cv-section cv-section--tight">
            <div class="container">
                <div class="cv-cards">
                    @foreach ($guarantees as $g)
                        <div class="cv-card-soft">
                            <div class="cv-card-soft__icon">{!! $ico($g['icon'], 20) !!}</div>
                            <h3>{{ $g['title'] }}</h3>
                            <p>{{ $g['desc'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- ----------------------------------------------------- objeciones --}}
        <section class="cv-section cv-section--tight">
            <div class="container">
                <div class="cv-head">
                    <h2>Antes de que preguntes</h2>
                </div>

                <div class="cv-faq">
                    @foreach ($objections as $i => $o)
                        <div class="cv-faq__item">
                            <button type="button" class="cv-faq__trigger" aria-expanded="{{ $i === 0 ? 'true' : 'false' }}" aria-controls="obj-{{ $i }}">
                                <span>{{ $o['q'] }}</span>
                                {!! $ico('<path d="m6 9 6 6 6-6"></path>', 18) !!}
                            </button>
                            <div class="cv-faq__panel" id="obj-{{ $i }}" @if($i !== 0) hidden @endif>{{ $o['a'] }}</div>
                        </div>
                    @endforeach
                </div>

                <div class="cv-actions" style="justify-content:center;margin-top:2.5rem;">
                    <a href="{{ $waAuditoria }}" class="cv-btn cv-btn--primary" target="_blank" rel="noopener nofollow">Resolver mi caso con un estratega {!! $ico($icoFlecha, 17) !!}</a>
                </div>
            </div>
        </section>

        {{-- ------------------------------------------------------ CTA final --}}
        <section class="cv-section cv-section--brand">
            <div class="container">
                <div class="cv-final">
                    <h2>Tu competencia ya está pujando por tus clientes</h2>
                    <p>Cada auditoría toma 48 horas y no te compromete a nada.</p>
                    <div class="cv-actions">
                        <a href="{{ $waAuditoria }}" class="cv-btn cv-btn--white" target="_blank" rel="noopener nofollow">Reclamar mi auditoría gratis {!! $ico($icoFlecha, 17) !!}</a>
                        <a href="{{ route('agendar.mostrar') }}" class="cv-btn cv-btn--outline-light">Prefiero agendar una llamada</a>
                    </div>
                </div>
            </div>
        </section>

        {{-- ------------------------------------------------------ guías del blog --}}
        {{-- Hub -> spoke: si no hay artículos publicados para este servicio no se
             pinta nada, para no dejar un bloque vacío en una landing de conversión. --}}
        @if (isset($articulos) && $articulos->isNotEmpty())
            <section class="cv-section cv-section--tight" aria-labelledby="guias">
                <div class="container">
                    <div class="cv-head cv-head--left" style="margin-bottom:2rem;">
                        <h2 id="guias" style="font-size:1.5rem;">Guías sobre {{ $servicio['nombre'] }}</h2>
                    </div>
                    <div class="cv-cards">
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

        {{-- ------------------------------------------- otros servicios (enlazado interno) --}}
        <section class="cv-section cv-section--alt cv-section--tight">
            <div class="container">
                <div class="cv-head cv-head--left" style="margin-bottom:2rem;">
                    <h2 style="font-size:1.5rem;">Otros servicios</h2>
                </div>
                <div class="cv-cards">
                    @foreach (array_slice($otrosServicios, 0, 3) as $otro)
                        <a class="cv-card-soft" href="{{ route('servicios.show', $otro['slug']) }}" style="text-decoration:none;display:block;">
                            <div class="cv-card-soft__icon">{!! $ico($otro['icon'], 20) !!}</div>
                            <h3>{{ $otro['nombre'] }}</h3>
                            <p>{{ $otro['resumen'] }}</p>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    </main>

    {{-- Barra fija de contacto en móvil. --}}
    <div class="cv-bar">
        <a href="tel:+527341036410" class="cv-bar__icon" aria-label="Llamar a RankPro">{!! $ico($icoTel, 18) !!}</a>
        <a href="{{ $wa }}" class="cv-bar__icon cv-bar__icon--wa" aria-label="Escribir por WhatsApp" target="_blank" rel="noopener nofollow">{!! $ico($icoWa, 18) !!}</a>
        <a href="{{ $waAuditoria }}" class="cv-btn cv-btn--primary cv-bar__cta" target="_blank" rel="noopener nofollow">Auditoría gratis {!! $ico($icoFlecha, 16) !!}</a>
    </div>

    @include('components.footer')
@endsection
