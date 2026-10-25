{{--
    Encabezado, KPIs y pestañas compartidos por Redactar e Historial.

    Son dos rutas distintas (redactar/index) que se ven como una sola pantalla
    "Enviar correo": cada una incluye este parcial con su pestaña activa. Los
    KPIs vienen del controlador (CorreoEnviosController::kpis): Redactar usa el
    mes en curso; el Historial, el periodo elegido en los chips.

    Variables: $tab ('nuevo' | 'historial'), $kpis, $periodos (solo historial),
    $envio (solo al editar), $titulo, $subtitulo (HTML ya escapado).
--}}
@php
    $etiquetaPeriodo = mb_strtolower($periodos[$kpis['periodo']] ?? 'este mes');
    $nuevoLabel = ($envio ?? null) ? 'Editar envío' : 'Nuevo envío';
@endphp

<div class="page-header cenv-encabezado">
    <div>
        <h1 class="page-header__title">{{ $titulo ?? 'Enviar correo' }}</h1>
        <p class="page-header__subtitle">{!! $subtitulo ?? 'Envía ahora o programa · un correo puede ir a varias personas a la vez' !!}</p>
    </div>
    <div class="correo-header-actions">
        @if ($tab === 'nuevo')
            <a href="{{ route('admin.correo.envios.index') }}" class="btn btn--secondary cenv-btn">
                <i class="fa-solid fa-inbox" aria-hidden="true"></i> Ver historial
            </a>
        @else
            <a href="{{ route('admin.correo.plantillas.index') }}" class="btn btn--secondary cenv-btn">
                <i class="fa-solid fa-layer-group" aria-hidden="true"></i> Plantillas
            </a>
            <a href="{{ route('admin.correo.envios.create') }}" class="btn btn--primary cenv-btn">
                <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i> Redactar correo
            </a>
        @endif
    </div>
</div>

<section class="cenv-kpis" aria-label="Resumen de envíos">
    <div class="cenv-kpi">
        <div class="cenv-kpi__top">
            <span class="cenv-kpi__label">Correos entregados</span>
            <span class="cenv-kpi__icono cenv-kpi__icono--entregados" aria-hidden="true"><i class="fa-solid fa-paper-plane"></i></span>
        </div>
        <div class="cenv-kpi__valor">{{ number_format($kpis['alcanzados']) }}</div>
        <div class="cenv-kpi__sub">suma de destinatarios · {{ $etiquetaPeriodo }}</div>
    </div>
    <div class="cenv-kpi">
        <div class="cenv-kpi__top">
            <span class="cenv-kpi__label">Programados</span>
            <span class="cenv-kpi__icono cenv-kpi__icono--programados" aria-hidden="true"><i class="fa-solid fa-clock"></i></span>
        </div>
        <div class="cenv-kpi__valor">{{ number_format($kpis['programados']) }}</div>
        <div class="cenv-kpi__sub">en cola, cancelables</div>
    </div>
    <div class="cenv-kpi" title="Estimada por píxel de seguimiento: algunos clientes de correo bloquean o precargan imágenes.">
        <div class="cenv-kpi__top">
            <span class="cenv-kpi__label">Aperturas</span>
            <span class="cenv-kpi__icono cenv-kpi__icono--aperturas" aria-hidden="true"><i class="fa-solid fa-eye"></i></span>
        </div>
        <div class="cenv-kpi__valor">{{ number_format($kpis['abiertos']) }}</div>
        <div class="cenv-kpi__sub">
            {{ $kpis['apertura'] !== null ? $kpis['apertura'].'% del total entregado' : 'sin entregas todavía' }} · estimado
        </div>
    </div>
    <div class="cenv-kpi">
        <div class="cenv-kpi__top">
            <span class="cenv-kpi__label">Fallidos</span>
            <span class="cenv-kpi__icono cenv-kpi__icono--fallidos" aria-hidden="true"><i class="fa-solid fa-triangle-exclamation"></i></span>
        </div>
        <div class="cenv-kpi__valor">{{ number_format($kpis['fallidos']) }}</div>
        <div class="cenv-kpi__sub">{{ $kpis['fallidos'] > 0 ? 'requieren revisión' : 'sin errores' }}</div>
    </div>
</section>

<div class="cenv-barra">
    <nav class="cenv-tabs" aria-label="Secciones de Enviar correo">
        <a href="{{ route('admin.correo.envios.create') }}"
           class="cenv-tabs__item {{ $tab === 'nuevo' ? 'is-active' : '' }}"
           @if ($tab === 'nuevo') aria-current="page" @endif>{{ $nuevoLabel }}</a>
        <a href="{{ route('admin.correo.envios.index') }}"
           class="cenv-tabs__item {{ $tab === 'historial' ? 'is-active' : '' }}"
           @if ($tab === 'historial') aria-current="page" @endif>Historial y programados</a>
    </nav>

    @if ($tab === 'historial' && ! empty($periodos))
        {{-- Pills GET (sin JS): conservan estado/search del listado y solo cambian `periodo`. --}}
        <nav class="cenv-periodos" aria-label="Periodo de las métricas">
            @foreach ($periodos as $clave => $label)
                <a href="{{ route('admin.correo.envios.index', array_merge(request()->query(), ['periodo' => $clave])) }}"
                   class="cenv-periodos__chip {{ $kpis['periodo'] === $clave ? 'is-active' : '' }}"
                   @if ($kpis['periodo'] === $clave) aria-current="true" @endif>{{ $label }}</a>
            @endforeach
        </nav>
    @endif
</div>
