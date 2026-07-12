@extends('layouts.admin')

@section('styles')
    @vite('resources/css/admin/seo.css')
@endsection

@section('content')
    @php
        $fases = ['auditoria', 'estrategia', 'ejecucion', 'reporte'];
        $ordenActual = $campana->fase_actual->orden();
    @endphp

    <div class="page-header">
        <div>
            <h1 class="page-header__title">{{ $campana->nombre }}</h1>
            <p class="page-header__subtitle">{{ $campana->cliente->nombre }} @if ($campana->url_sitio) · {{ $campana->url_sitio }} @endif</p>
        </div>
        <div style="display:flex; gap: var(--space-2);">
            <a href="{{ route('admin.seo.edit', $campana) }}" class="btn btn--secondary">
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

    {{-- ---------- Keywords objetivo: vista previa desde Estrategia en adelante ---------- --}}
    @if (in_array($campana->fase_actual->value, ['estrategia', 'ejecucion', 'reporte', 'cerrada']))
        @php $keywordsObjetivo = $campana->faseEstrategia?->keywords() ?? collect(); @endphp
        <div class="card" style="margin-top: var(--space-6);">
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
                    <a href="{{ route('admin.keywords.create') }}" class="btn btn--primary">Agregar keyword</a>
                </div>
            @else
                <x-data-table :headers="['Keyword', 'Tipo', 'Volumen', 'Dificultad', 'Estado']">
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

    {{-- ---------- Posiciones + Backlinks + Contenido: visibles desde Ejecución en adelante ---------- --}}
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

        <div class="card" style="margin-top: var(--space-6);" id="backlinksCard">
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

        <div class="card" style="margin-top: var(--space-6);" id="contenidoCard">
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

    {{-- ---------- Historial de reportes por ciclo ---------- --}}
    @if ($campana->reportes->where('aprobado', true)->isNotEmpty())
        <div class="card" style="margin-top: var(--space-6);">
            <div class="card__header">
                <h2 class="card__header-title">Historial de Reportes</h2>
            </div>
            <x-data-table :headers="['Ciclo', 'Tráfico Inicio → Actual', 'Top 3 / Top 10', 'Backlinks Totales', '¿Continúa?', 'Aprobado']">
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

    {{-- ---------- Modales ---------- --}}
    <x-modal id="posicionModal">
        <x-slot:header><h2>Agregar Posición</h2></x-slot:header>
        <form id="posicionForm" data-action="{{ route('admin.seo.posiciones.store', $campana) }}">
            <div class="field">
                <label class="field__label" for="pos_keyword">Keyword</label>
                <input class="input" type="text" name="keyword" id="pos_keyword" required>
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
                    <input class="input" type="text" name="keyword_objetivo" id="ct_keyword">
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
@endsection

@section('scripts')
    @vite('resources/js/seo.js')
@endsection
