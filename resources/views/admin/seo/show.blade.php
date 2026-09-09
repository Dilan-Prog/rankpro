@extends('layouts.admin')

@section('styles')
    @vite('resources/css/admin/seo.css')
@endsection

@section('content')
    @php
        $fases = ['auditoria', 'estrategia', 'ejecucion', 'reporte'];
        $ordenActual = $campana->fase_actual->orden();

        // Real keyword bank for this client — powers the "Agregar Posición"
        // modal's datalist + auto-fill (see initPosiciones() in seo.js),
        // same source query _fase-estrategia.blade.php already uses for its
        // own "Keywords objetivo" multi-select.
        $keywordsBancoPosiciones = \App\Models\Keyword::where('cliente_id', $campana->cliente_id)->orderBy('keyword')->get();
        $keywordsBancoPosicionesMap = $keywordsBancoPosiciones->mapWithKeys(fn ($kw) => [
            mb_strtolower($kw->keyword) => [
                'volumen_busqueda' => $kw->volumen_busqueda,
                'dificultad' => $kw->dificultad,
                'url_asignada' => $kw->url_asignada,
            ],
        ]);
    @endphp

    <div class="page-header">
        <div>
            <h1 class="page-header__title">{{ $campana->nombre }}</h1>
            <p class="page-header__subtitle">{{ $campana->cliente->nombre }} @if ($campana->url_sitio) · {{ $campana->url_sitio }} @endif</p>
        </div>
        <div style="display:flex; gap: var(--space-2);">
            <a href="{{ route('admin.seo.index', ['editar' => $campana->id]) }}" class="btn btn--secondary">
                <i class="fa-solid fa-pen"></i> Editar
            </a>
            <a href="{{ route('admin.seo.index') }}" class="btn btn--secondary">
                <i class="fa-solid fa-arrow-left"></i> Volver a SEO
            </a>
        </div>
    </div>

    @if (session('status'))
        <div class="form-status"><i class="fa-solid fa-circle-check" style="margin-top:2px"></i><span>{{ session('status') }}</span></div>
    @endif
    @if ($errors->any())
        <div class="form-status form-status--error"><i class="fa-solid fa-triangle-exclamation" style="margin-top:2px"></i><span>{{ $errors->first() }}</span></div>
    @endif

    <div class="kpi-grid">
        <x-stat-card label="Fase Actual" value="{{ \App\Support\Labels::faseSeo($campana->fase_actual->value) }}" icon="fa-diagram-project" color="primary" />
        <x-stat-card label="Ciclo" value="{{ $campana->ciclo_actual }}" icon="fa-arrows-rotate" color="teal" />
        <x-stat-card label="SEO Score" value="{{ $campana->faseAuditoria->seo_score ?? '—' }}/100" icon="fa-gauge-high" color="amber" />
        <x-stat-card label="Tráfico Orgánico" value="{{ number_format($campana->reporteActual->trafico_actual ?? 0) }}" sub="actual" icon="fa-arrow-trend-up" color="emerald" />
    </div>

    {{-- ---------- Rastreador visual de fases ---------- --}}
    <div class="fase-tracker">
        @foreach ($fases as $fase)
            @php
                $ordenFase = \App\Enums\FaseSeo::from($fase)->orden();
                $estadoPaso = $ordenFase < $ordenActual ? 'done' : ($ordenFase === $ordenActual ? 'current' : 'locked');
            @endphp
            <div class="fase-tracker__step fase-tracker__step--{{ $estadoPaso }}">
                <div class="fase-tracker__circle">
                    @if ($estadoPaso === 'done')
                        <i class="fa-solid fa-check"></i>
                    @else
                        {{ $ordenFase }}
                    @endif
                </div>
                <div class="fase-tracker__label">{{ \App\Support\Labels::faseSeo($fase) }}</div>
            </div>
            @if (! $loop->last)
                <div class="fase-tracker__line fase-tracker__line--{{ $ordenFase < $ordenActual ? 'done' : 'locked' }}"></div>
            @endif
        @endforeach
    </div>

    {{-- ---------- Tabs ---------- --}}
    <div class="tabs" id="seoTabs">
        <button type="button" class="tabs__item is-active" data-panel="resumen">Resumen</button>
        <button type="button" class="tabs__item" data-panel="posiciones">Posiciones</button>
        <button type="button" class="tabs__item" data-panel="linkbuilding">Link Building</button>
        <button type="button" class="tabs__item" data-panel="onpage">On-Page</button>
        <button type="button" class="tabs__item" data-panel="tecnico">Técnico</button>
        <button type="button" class="tabs__item" data-panel="contenido">Contenido</button>
        <button type="button" class="tabs__item" data-panel="reporte">Reporte</button>
    </div>

    {{-- ---------- Tab: Resumen ---------- --}}
    <div data-panel-content="resumen">
        @if ($campana->fase_actual->value === 'cerrada')
            <div class="card card--padded fase-panel">
                <div class="fase-panel__header">
                    <h2 class="card__header-title"><i class="fa-solid fa-flag-checkered"></i> Campaña Cerrada</h2>
                </div>
                <p style="color:var(--color-muted-foreground); font-size:var(--text-sm);">
                    Esta campaña se cerró
                    @if ($campana->reporteActual?->fecha_aprobacion) el {{ $campana->reporteActual->fecha_aprobacion->format('Y-m-d') }} @endif
                    después de {{ $campana->ciclo_actual }} {{ $campana->ciclo_actual === 1 ? 'ciclo' : 'ciclos' }}.
                </p>
                @if ($campana->reporteActual?->satisfaccion_cliente)
                    <p style="margin-top: var(--space-2); font-size:var(--text-sm);">
                        Satisfacción del cliente:
                        @for ($i = 1; $i <= 5; $i++)
                            <i class="fa-solid fa-star" style="color:{{ $i <= $campana->reporteActual->satisfaccion_cliente ? 'var(--text-warning)' : 'var(--color-border)' }}"></i>
                        @endfor
                    </p>
                @endif
            </div>
        @else
            @include('admin.seo._fase-' . $campana->fase_actual->value)
        @endif
    </div>

    {{-- ---------- Tab: Posiciones ---------- --}}
    <div data-panel-content="posiciones" hidden>
        {{-- ---------- Keywords objetivo: vista previa desde Estrategia en adelante ---------- --}}
        @if (in_array($campana->fase_actual->value, ['estrategia', 'ejecucion', 'reporte', 'cerrada']))
            @php $keywordsObjetivo = $campana->faseEstrategia?->keywords() ?? collect(); @endphp
            <div class="card">
                <div class="card__header">
                    <h2 class="card__header-title">Keywords Objetivo</h2>
                    <a href="{{ route('admin.keywords.index') }}" class="btn btn--ghost">
                        <i class="fa-solid fa-arrow-up-right-from-square"></i> Ver banco de keywords
                    </a>
                </div>
                @if ($keywordsObjetivo->isEmpty())
                    <div class="empty-state">
                        <div class="empty-state__icon"><i class="fa-solid fa-key"></i></div>
                        <p class="empty-state__text">Sin keywords asignadas a esta campaña todavía.</p>
                        <a href="{{ route('admin.keywords.index') }}" class="btn btn--primary">Agregar keyword</a>
                    </div>
                @else
                    <x-data-table :headers="['Keyword', 'Tipo', 'Volumen', 'Dificultad', 'Estado']" data-paginate="15">
                        @foreach ($keywordsObjetivo as $k)
                            <tr>
                                <td>{{ $k->keyword }}</td>
                                <td><span style="font-size:var(--text-xs); color:var(--color-muted-foreground);">{{ \App\Support\Labels::tipoKeyword($k->tipo) }}</span></td>
                                <td class="u-mono">{{ number_format($k->volumen_busqueda ?? 0) }}</td>
                                <td class="u-mono">{{ $k->dificultad ?? '—' }}</td>
                                <td><x-badge :status="$k->estado" /></td>
                            </tr>
                        @endforeach
                    </x-data-table>
                @endif
            </div>
        @endif

        @if (in_array($campana->fase_actual->value, ['ejecucion', 'reporte', 'cerrada']))
            <div class="card" style="margin-top: var(--space-6);" id="posicionesCard">
                <div class="card__header">
                    <h2 class="card__header-title">Seguimiento de Posiciones</h2>
                    <button type="button" class="btn btn--ghost" onclick="window.AgencyOS.openModal('posicionModal')">
                        <i class="fa-solid fa-plus"></i> Agregar Posición
                    </button>
                </div>
                <div data-posiciones-body>
                    @include('admin.seo._posiciones-tabla', ['posiciones' => $campana->posiciones])
                </div>
            </div>
        @endif
    </div>

    {{-- ---------- Tab: Link Building ---------- --}}
    <div data-panel-content="linkbuilding" hidden>
        @if (in_array($campana->fase_actual->value, ['ejecucion', 'reporte', 'cerrada']))
            <div class="card" id="backlinksCard">
                <div class="card__header">
                    <h2 class="card__header-title">Link Building</h2>
                    <button type="button" class="btn btn--ghost" onclick="window.AgencyOS.openModal('backlinkModal')">
                        <i class="fa-solid fa-plus"></i> Agregar Backlink
                    </button>
                </div>
                <div data-backlinks-body>
                    @include('admin.seo._backlinks-tabla', ['backlinks' => $campana->backlinks])
                </div>
            </div>
        @endif
    </div>

    {{-- ---------- Tab: On-Page ---------- --}}
    <div data-panel-content="onpage" hidden>
        @if (in_array($campana->fase_actual->value, ['ejecucion', 'reporte', 'cerrada']))
            <div class="card" id="onpageCard">
                <div class="card__header">
                    <h2 class="card__header-title">Acciones On-Page</h2>
                    <button type="button" class="btn btn--ghost" onclick="window.AgencyOS.openModal('onpageModal')">
                        <i class="fa-solid fa-plus"></i> Agregar Acción
                    </button>
                </div>
                <div data-onpage-body>
                    @include('admin.seo._onpage-lista', ['acciones' => $campana->onPageAcciones])
                </div>
            </div>
        @endif
    </div>

    {{-- ---------- Tab: Técnico ---------- --}}
    <div data-panel-content="tecnico" hidden>
        @php $auditoriaTecnico = $campana->faseAuditoria; @endphp
        @if (! $auditoriaTecnico)
            <div class="card card--padded">
                <div class="empty-state">
                    <div class="empty-state__icon"><i class="fa-solid fa-magnifying-glass-chart"></i></div>
                    <p class="empty-state__text">Aún no hay datos de auditoría para esta campaña.</p>
                </div>
            </div>
        @else
            <div class="kpi-grid">
                <x-stat-card label="LCP Móvil" value="{{ $auditoriaTecnico->lcp_mobile !== null ? $auditoriaTecnico->lcp_mobile . 's' : '—' }}" icon="fa-stopwatch" color="blue" />
                <x-stat-card label="CLS Móvil" value="{{ $auditoriaTecnico->cls_mobile ?? '—' }}" icon="fa-arrows-up-down" color="amber" />
                <x-stat-card label="Errores Técnicos" value="{{ $auditoriaTecnico->errores_tecnicos ?? '—' }}" icon="fa-triangle-exclamation" color="red" />
            </div>

            @foreach (\App\Models\SeoFaseAuditoria::TECNICO_CHECKLIST as $categoria => $items)
                <div class="card card--padded" style="margin-top: var(--space-6);">
                    <h3 class="fase-form__section-title" style="margin-top:0;">{{ $categoria }}</h3>
                    <div class="checkbox-group">
                        @foreach ($items as $key => $label)
                            <label class="checkbox-item">
                                <input type="checkbox" form="faseForm" data-tecnico-checklist-item="{{ $key }}" @checked(old('tecnico_checklist.' . $key, $auditoriaTecnico->tecnico_checklist[$key] ?? false))>
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                </div>
            @endforeach
        @endif
    </div>

    {{-- ---------- Tab: Contenido ---------- --}}
    <div data-panel-content="contenido" hidden>
        @if (in_array($campana->fase_actual->value, ['ejecucion', 'reporte', 'cerrada']))
            <div class="card" id="contenidoCard">
                <div class="card__header">
                    <h2 class="card__header-title">Contenido</h2>
                    <button type="button" class="btn btn--ghost" onclick="window.AgencyOS.openModal('contenidoModal')">
                        <i class="fa-solid fa-plus"></i> Agregar Contenido
                    </button>
                </div>
                <div data-contenido-body>
                    @include('admin.seo._contenido-tabla', ['contenidos' => $campana->contenido])
                </div>
            </div>
        @endif
    </div>

    {{-- ---------- Tab: Reporte ---------- --}}
    <div data-panel-content="reporte" hidden>
        {{-- ---------- Métricas mensuales: base de las gráficas de tendencia / reporte de crecimiento ---------- --}}
        @if (in_array($campana->fase_actual->value, ['ejecucion', 'reporte', 'cerrada']))
            <div class="card card--padded" id="metricasCard">
                <div class="card__header" style="padding:0; border-bottom:none; margin-bottom: var(--space-4);">
                    <h2 class="card__header-title">Tendencia Mensual</h2>
                    <button type="button" class="btn btn--ghost" onclick="window.AgencyOS.openModal('metricaModal')">
                        <i class="fa-solid fa-plus"></i> Agregar Mes
                    </button>
                </div>

                @if ($campana->metricasMensuales->isNotEmpty())
                    @php
                        $metricasChartData = $campana->metricasMensuales->map(fn ($m) => [
                            'label' => str_pad($m->mes, 2, '0', STR_PAD_LEFT) . '/' . $m->anio,
                            'trafico' => $m->trafico_organico,
                            'top3' => $m->keywords_top3,
                            'top10' => $m->keywords_top10,
                            'backlinks' => $m->backlinks_total,
                        ]);
                    @endphp
                    <div style="height:260px; margin-bottom: var(--space-5);">
                        <canvas id="metricasChart" data-metricas="{{ $metricasChartData->toJson() }}"></canvas>
                    </div>
                @endif

                <div data-metricas-body>
                    @include('admin.seo._metricas-mensuales-tabla', ['metricas' => $campana->metricasMensuales])
                </div>
            </div>
        @endif

        {{-- ---------- Conclusiones / recomendaciones del ciclo actual ---------- --}}
        @if ($campana->reporteActual?->conclusiones || $campana->reporteActual?->recomendaciones || $campana->reporteActual?->notas_cierre)
            <div class="card card--padded" style="margin-top: var(--space-6);">
                <h2 class="card__header-title" style="margin-bottom: var(--space-4);">Cierre del Ciclo {{ $campana->ciclo_actual }}</h2>
                @if ($campana->reporteActual->conclusiones)
                    <div class="record-modal__section">
                        <div class="record-modal__section-label">Conclusiones</div>
                        <p style="font-size:var(--text-sm);">{!! nl2br(e($campana->reporteActual->conclusiones)) !!}</p>
                    </div>
                @endif
                @if ($campana->reporteActual->recomendaciones)
                    <div class="record-modal__section">
                        <div class="record-modal__section-label">Recomendaciones</div>
                        <p style="font-size:var(--text-sm);">{!! nl2br(e($campana->reporteActual->recomendaciones)) !!}</p>
                    </div>
                @endif
                @if ($campana->reporteActual->notas_cierre)
                    <div class="record-modal__section">
                        <div class="record-modal__section-label">Notas de Cierre</div>
                        <p style="font-size:var(--text-sm);">{!! nl2br(e($campana->reporteActual->notas_cierre)) !!}</p>
                    </div>
                @endif
            </div>
        @endif

        {{-- ---------- Historial de reportes por ciclo ---------- --}}
        @if ($campana->reportes->where('aprobado', true)->isNotEmpty())
            <div class="card" style="margin-top: var(--space-6);">
                <div class="card__header">
                    <h2 class="card__header-title">Historial de Reportes</h2>
                </div>
                <x-data-table :headers="['Ciclo', 'Tráfico Inicio → Actual', 'Top 3 / Top 10', 'Backlinks Totales', '¿Continúa?', 'Aprobado']" data-paginate="15">
                    @foreach ($campana->reportes->where('aprobado', true) as $r)
                        <tr>
                            <td class="u-mono">#{{ $r->ciclo }}</td>
                            <td class="u-mono">{{ number_format($r->trafico_inicio ?? 0) }} → {{ number_format($r->trafico_actual ?? 0) }}</td>
                            <td class="u-mono">{{ $r->keywords_top3 ?? 0 }} / {{ $r->keywords_top10 ?? 0 }}</td>
                            <td class="u-mono">{{ number_format($r->backlinks_total ?? 0) }}</td>
                            <td>{{ $r->continua_campana ? 'Sí' : 'No' }}</td>
                            <td class="u-mono" style="font-size:var(--text-xs); color:var(--color-muted-foreground);">{{ $r->fecha_aprobacion?->format('Y-m-d') }}</td>
                        </tr>
                    @endforeach
                </x-data-table>
            </div>
        @endif
    </div>

    {{-- ---------- Modales ---------- --}}
    <x-modal id="posicionModal">
        <x-slot:header><h2>Agregar Posición</h2></x-slot:header>
        <form id="posicionForm" data-action="{{ route('admin.seo.posiciones.store', $campana) }}" data-keyword-bank="{{ $keywordsBancoPosicionesMap->toJson() }}">
            <div class="field">
                <label class="field__label" for="pos_keyword">Keyword</label>
                <input class="input" type="text" name="keyword" id="pos_keyword" list="pos_keyword_bank" autocomplete="off" required>
                @if ($keywordsBancoPosiciones->isNotEmpty())
                    <datalist id="pos_keyword_bank">
                        @foreach ($keywordsBancoPosiciones as $kw)
                            <option value="{{ $kw->keyword }}"></option>
                        @endforeach
                    </datalist>
                    <span class="field__hint">Sugerencias del banco de keywords de este cliente — también puedes escribir una nueva.</span>
                @else
                    <span class="field__hint">Este cliente no tiene keywords en el <a href="{{ route('admin.keywords.index') }}" target="_blank" rel="noopener" style="text-decoration:underline;">banco de keywords</a> todavía.</span>
                @endif
            </div>
            <div class="field" style="margin-top: var(--space-3);">
                <label class="field__label" for="pos_url">URL de la página</label>
                <input class="input" type="text" name="url_pagina" id="pos_url" placeholder="/pagina">
            </div>
            <div class="form-grid form-grid--2" style="margin-top: var(--space-3);">
                <div class="field">
                    <label class="field__label" for="pos_actual">Posición actual</label>
                    <input class="input" type="number" min="0" name="posicion_actual" id="pos_actual">
                </div>
                <div class="field">
                    <label class="field__label" for="pos_anterior">Posición anterior</label>
                    <input class="input" type="number" min="0" name="posicion_anterior" id="pos_anterior">
                </div>
            </div>
            <div class="form-grid form-grid--2" style="margin-top: var(--space-3);">
                <div class="field">
                    <label class="field__label" for="pos_volumen">Volumen de búsqueda</label>
                    <input class="input" type="number" min="0" name="volumen_busqueda" id="pos_volumen">
                </div>
                <div class="field">
                    <label class="field__label" for="pos_dificultad">Dificultad (0-100)</label>
                    <input class="input" type="number" min="0" max="100" name="dificultad_keyword" id="pos_dificultad">
                </div>
            </div>
            <div class="form-grid form-grid--2" style="margin-top: var(--space-3);">
                <div class="field">
                    <label class="field__label" for="pos_dispositivo">Dispositivo</label>
                    <select class="select" name="dispositivo" id="pos_dispositivo" required>
                        <option value="mobile">Mobile</option>
                        <option value="desktop">Desktop</option>
                    </select>
                </div>
                <div class="field">
                    <label class="field__label" for="pos_pais">País</label>
                    <input class="input" type="text" name="pais" id="pos_pais" value="MX" maxlength="10">
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn--primary">Agregar</button>
                <button type="button" class="btn btn--secondary" data-modal-close="posicionModal">Cancelar</button>
            </div>
        </form>
    </x-modal>

    <x-modal id="backlinkModal">
        <x-slot:header><h2>Agregar Backlink</h2></x-slot:header>
        <form id="backlinkForm" data-action="{{ route('admin.seo.backlinks.store', $campana) }}">
            <div class="field">
                <label class="field__label" for="bl_origen">Dominio referente (URL origen)</label>
                <input class="input" type="text" name="url_origen" id="bl_origen" required placeholder="ejemplo.com">
            </div>
            <div class="field" style="margin-top: var(--space-3);">
                <label class="field__label" for="bl_destino">URL de destino</label>
                <input class="input" type="text" name="url_destino" id="bl_destino" required placeholder="/pagina">
            </div>
            <div class="form-grid form-grid--2" style="margin-top: var(--space-3);">
                <div class="field">
                    <label class="field__label" for="bl_dadr">DA / DR</label>
                    <input class="input" type="number" min="0" max="100" name="da_dr" id="bl_dadr">
                </div>
                <div class="field">
                    <label class="field__label" for="bl_tipo">Tipo</label>
                    <select class="select" name="tipo" id="bl_tipo" required>
                        <option value="dofollow">Dofollow</option>
                        <option value="nofollow">Nofollow</option>
                    </select>
                </div>
            </div>
            <div class="form-grid form-grid--2" style="margin-top: var(--space-3);">
                <div class="field">
                    <label class="field__label" for="bl_estado">Estado</label>
                    <select class="select" name="estado" id="bl_estado" required>
                        <option value="activo">Activo</option>
                        <option value="caido">Caído</option>
                    </select>
                </div>
                <div class="field">
                    <label class="field__label" for="bl_fecha">Fecha conseguido</label>
                    <input class="input" type="date" name="fecha_conseguido" id="bl_fecha" value="{{ now()->format('Y-m-d') }}">
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn--primary">Agregar</button>
                <button type="button" class="btn btn--secondary" data-modal-close="backlinkModal">Cancelar</button>
            </div>
        </form>
    </x-modal>

    <x-modal id="contenidoModal">
        <x-slot:header><h2 data-contenido-modal-title>Agregar Contenido</h2></x-slot:header>
        <form id="contenidoForm"
            data-store-action="{{ route('admin.seo.contenido.store', $campana) }}"
            data-update-action-template="{{ route('admin.seo.contenido.update', ['contenido' => '__ID__']) }}">
            <div class="field">
                <label class="field__label" for="ct_titulo">Título</label>
                <input class="input" type="text" name="titulo" id="ct_titulo" required>
            </div>
            <div class="form-grid form-grid--2" style="margin-top: var(--space-3);">
                <div class="field">
                    <label class="field__label" for="ct_keyword">Keyword objetivo</label>
                    <input class="input" type="text" name="keyword_objetivo" id="ct_keyword" list="pos_keyword_bank" autocomplete="off">
                    @if ($keywordsBancoPosiciones->isNotEmpty())
                        <span class="field__hint">Sugerencias del banco de keywords de este cliente.</span>
                    @endif
                </div>
                <div class="field">
                    <label class="field__label" for="ct_url">URL</label>
                    <input class="input" type="text" name="url" id="ct_url" placeholder="/blog/articulo">
                </div>
            </div>
            <div class="form-grid form-grid--2" style="margin-top: var(--space-3);">
                <div class="field">
                    <label class="field__label" for="ct_trafico">Tráfico generado</label>
                    <input class="input" type="number" min="0" name="trafico_generado" id="ct_trafico">
                </div>
                <div class="field">
                    <label class="field__label" for="ct_estado">Estado</label>
                    <select class="select" name="estado" id="ct_estado" required>
                        <option value="borrador">Borrador</option>
                        <option value="publicado">Publicado</option>
                        <option value="actualizar">Actualizar</option>
                    </select>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn--primary" data-contenido-submit-label>Agregar</button>
                <button type="button" class="btn btn--secondary" data-modal-close="contenidoModal">Cancelar</button>
            </div>
        </form>
    </x-modal>

    <x-modal id="onpageModal">
        <x-slot:header><h2 data-onpage-modal-title>Agregar Acción On-Page</h2></x-slot:header>
        <form id="onpageForm"
            data-store-action="{{ route('admin.seo.onpage.store', $campana) }}"
            data-update-action-template="{{ route('admin.seo.onpage.update', ['accion' => '__ID__']) }}">
            <div class="field">
                <label class="field__label" for="op_url">URL de la página</label>
                <input class="input" type="text" name="url_pagina" id="op_url" required placeholder="/pagina">
                <span class="field__error" data-error-for="url_pagina">@error('url_pagina'){{ $message }}@enderror</span>
            </div>
            <div class="field" style="margin-top: var(--space-3);">
                <label class="field__label" for="op_accion">Acción realizada</label>
                <textarea class="textarea" name="accion" id="op_accion" required></textarea>
                <span class="field__error" data-error-for="accion">@error('accion'){{ $message }}@enderror</span>
            </div>
            <div class="form-grid form-grid--2" style="margin-top: var(--space-3);">
                <div class="field">
                    <label class="field__label" for="op_fecha">Fecha</label>
                    <input class="input" type="date" name="fecha" id="op_fecha" value="{{ now()->format('Y-m-d') }}">
                    <span class="field__error" data-error-for="fecha">@error('fecha'){{ $message }}@enderror</span>
                </div>
                <div class="field">
                    <label class="field__label" for="op_responsable">Responsable</label>
                    <select class="select" name="responsable_id" id="op_responsable">
                        <option value="">— Sin asignar —</option>
                        @foreach ($usuarios as $id => $nombre)
                            <option value="{{ $id }}">{{ $nombre }}</option>
                        @endforeach
                    </select>
                    <span class="field__error" data-error-for="responsable_id">@error('responsable_id'){{ $message }}@enderror</span>
                </div>
            </div>
            <div class="field" style="margin-top: var(--space-3);">
                <label class="field__label" for="op_estado">Estado</label>
                <select class="select" name="estado" id="op_estado" required>
                    <option value="en_progreso">En progreso</option>
                    <option value="completada">Completada</option>
                    <option value="pausada">Pausada</option>
                </select>
                <span class="field__error" data-error-for="estado">@error('estado'){{ $message }}@enderror</span>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn--primary" data-onpage-submit-label>Agregar</button>
                <button type="button" class="btn btn--secondary" data-modal-close="onpageModal">Cancelar</button>
            </div>
        </form>
    </x-modal>

    <x-modal id="metricaModal">
        <x-slot:header><h2 data-metrica-modal-title>Agregar Mes</h2></x-slot:header>
        <form id="metricaForm"
            data-store-action="{{ route('admin.seo.metricas-mensuales.store', $campana) }}"
            data-update-action-template="{{ route('admin.seo.metricas-mensuales.update', ['metrica' => '__ID__']) }}">
            <div class="form-grid form-grid--2">
                <div class="field">
                    <label class="field__label" for="mm_mes">Mes</label>
                    <select class="select" name="mes" id="mm_mes" required>
                        @foreach (['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'] as $i => $nombreMes)
                            <option value="{{ $i + 1 }}" @selected(($i + 1) === now()->month)>{{ $nombreMes }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label class="field__label" for="mm_anio">Año</label>
                    <input class="input" type="number" name="anio" id="mm_anio" value="{{ now()->year }}" required>
                </div>
            </div>
            <div class="field" style="margin-top: var(--space-3);">
                <label class="field__label" for="mm_trafico">Tráfico orgánico</label>
                <input class="input" type="number" min="0" name="trafico_organico" id="mm_trafico">
            </div>
            <div class="form-grid form-grid--2" style="margin-top: var(--space-3);">
                <div class="field">
                    <label class="field__label" for="mm_top3">Keywords Top 3</label>
                    <input class="input" type="number" min="0" name="keywords_top3" id="mm_top3">
                </div>
                <div class="field">
                    <label class="field__label" for="mm_top10">Keywords Top 10</label>
                    <input class="input" type="number" min="0" name="keywords_top10" id="mm_top10">
                </div>
            </div>
            <div class="form-grid form-grid--2" style="margin-top: var(--space-3);">
                <div class="field">
                    <label class="field__label" for="mm_top100">Keywords Top 100</label>
                    <input class="input" type="number" min="0" name="keywords_top100" id="mm_top100">
                </div>
                <div class="field">
                    <label class="field__label" for="mm_backlinks">Backlinks totales</label>
                    <input class="input" type="number" min="0" name="backlinks_total" id="mm_backlinks">
                </div>
            </div>
            <div class="form-grid form-grid--2" style="margin-top: var(--space-3);">
                <div class="field">
                    <label class="field__label" for="mm_resueltos">Errores resueltos</label>
                    <input class="input" type="number" min="0" name="errores_resueltos" id="mm_resueltos">
                </div>
                <div class="field">
                    <label class="field__label" for="mm_pendientes">Errores pendientes</label>
                    <input class="input" type="number" min="0" name="errores_pendientes" id="mm_pendientes">
                </div>
            </div>
            <div class="field" style="margin-top: var(--space-3);">
                <label class="field__label" for="mm_notas">Notas del mes</label>
                <textarea class="textarea" name="notas" id="mm_notas" rows="2"></textarea>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn--primary" data-metrica-submit-label>Agregar</button>
                <button type="button" class="btn btn--secondary" data-modal-close="metricaModal">Cancelar</button>
            </div>
        </form>
    </x-modal>
@endsection

@section('scripts')
    @vite('resources/js/seo.js')
@endsection
