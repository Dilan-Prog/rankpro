@extends('layouts.admin')

@section('styles')
    @vite('resources/css/admin/ads.css')
@endsection

@section('content')
    @php
        $fases = ['briefing', 'configuracion', 'lanzamiento', 'reporte'];
        $ordenActual = $campana->fase_actual->orden();
    @endphp

    <div class="page-header">
        <div>
            <h1 class="page-header__title">{{ $campana->nombre }}</h1>
            <p class="page-header__subtitle">{{ $campana->cliente->nombre }} · {{ \App\Support\Labels::plataforma($campana->plataforma) }}</p>
        </div>
        <div style="display:flex; gap: var(--space-2);">
            <a href="{{ route('admin.ads.edit', $campana) }}" class="btn btn--secondary">
                <i class="fa-solid fa-pen"></i> Editar
            </a>
            <a href="{{ route('admin.ads.index', ['plataforma' => $campana->plataforma]) }}" class="btn btn--secondary">
                <i class="fa-solid fa-arrow-left"></i> Volver a Ads
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
        <x-stat-card label="Fase Actual" value="{{ \App\Support\Labels::faseAds($campana->fase_actual->value) }}" icon="fa-diagram-project" color="primary" />
        <x-stat-card label="Ciclo" value="{{ $campana->ciclo_actual }}" icon="fa-arrows-rotate" color="teal" />
        <x-stat-card label="Presupuesto Mensual" value="${{ number_format($campana->presupuesto_mensual) }}" icon="fa-dollar-sign" color="amber" />
        <x-stat-card label="ROAS Promedio" value="{{ $campana->metricas->count() ? round((float) $campana->metricas->avg('roas'), 2).'x' : '—' }}" icon="fa-chart-line" color="emerald" />
    </div>

    {{-- ---------- Rastreador visual de fases ---------- --}}
    <div class="fase-tracker">
        @foreach ($fases as $fase)
            @php
                $ordenFase = \App\Enums\FaseAds::from($fase)->orden();
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
                <div class="fase-tracker__label">{{ \App\Support\Labels::faseAds($fase) }}</div>
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
        @include('admin.ads._fase-' . $campana->fase_actual->value)
    @endif

    {{-- ---------- Grupos + Creativos: visibles desde Configuración en adelante ---------- --}}
    @if (in_array($campana->fase_actual->value, ['configuracion', 'lanzamiento', 'reporte', 'cerrada']))
        <div class="card" style="margin-top: var(--space-6);" id="gruposCard">
            <div class="card__header">
                <h2 class="card__header-title">Grupos de Anuncios</h2>
                <button type="button" class="btn btn--ghost" onclick="window.AgencyOS.openModal('grupoModal')">
                    <i class="fa-solid fa-plus"></i> Agregar Grupo
                </button>
            </div>
            <div data-grupos-body>
                @include('admin.ads._grupos-tabla', ['grupos' => $campana->grupos])
            </div>
        </div>

        <div class="card" style="margin-top: var(--space-6);" id="creativosCard">
            <div class="card__header">
                <h2 class="card__header-title">Creativos</h2>
                <button type="button" class="btn btn--ghost" onclick="window.AgencyOS.openModal('creativoModal')">
                    <i class="fa-solid fa-plus"></i> Agregar Creativo
                </button>
            </div>
            <div data-creativos-body>
                @include('admin.ads._creativos-tabla', ['creativos' => $campana->creativos])
            </div>
        </div>
    @endif

    {{-- ---------- Métricas + Optimizaciones: visibles desde Lanzamiento en adelante ---------- --}}
    @if (in_array($campana->fase_actual->value, ['lanzamiento', 'reporte', 'cerrada']))
        <div class="card" style="margin-top: var(--space-6);" id="metricasCard">
            <div class="card__header">
                <h2 class="card__header-title">Métricas por Mes</h2>
                <button type="button" class="btn btn--ghost" onclick="window.AgencyOS.openModal('metricaModal')">
                    <i class="fa-solid fa-plus"></i> Agregar Métricas
                </button>
            </div>
            <div data-metricas-body>
                @include('admin.ads._metricas-tabla', ['metricas' => $campana->metricas])
            </div>
        </div>

        <div class="card" style="margin-top: var(--space-6);" id="optimizacionesCard">
            <div class="card__header">
                <h2 class="card__header-title">Optimizaciones Realizadas</h2>
                <button type="button" class="btn btn--ghost" onclick="window.AgencyOS.openModal('optimizacionModal')">
                    <i class="fa-solid fa-plus"></i> Registrar Optimización
                </button>
            </div>
            <div data-optimizaciones-body>
                @include('admin.ads._optimizaciones-tabla', ['optimizaciones' => $campana->optimizaciones])
            </div>
        </div>

        <div class="card" style="margin-top: var(--space-6);">
            <div class="card__header">
                <h2 class="card__header-title">Clics de Google Ads (tracking)</h2>
                <a href="{{ route('admin.clientes.clics', $campana->cliente_id) }}" class="btn btn--ghost">Ver todo con filtros</a>
            </div>
            @include('admin.ads._clics-tabla', ['clics' => $clics, 'campana' => $campana])
        </div>

        <div class="card" style="margin-top: var(--space-6);">
            <div class="card__header">
                <h2 class="card__header-title">Conversiones (tracking)</h2>
                <a href="{{ route('admin.clientes.conversiones', $campana->cliente_id) }}" class="btn btn--ghost">Ver todo con filtros</a>
            </div>
            @include('admin.ads._conversiones-tabla', ['conversiones' => $conversiones])
        </div>
    @endif

    {{-- ---------- Historial de reportes por ciclo ---------- --}}
    @if ($campana->reportes->where('aprobado', true)->isNotEmpty())
        <div class="card" style="margin-top: var(--space-6);">
            <div class="card__header">
                <h2 class="card__header-title">Historial de Reportes</h2>
            </div>
            <x-data-table :headers="['Ciclo', 'Inversión Total', 'Conversiones', 'ROAS Promedio', '¿Continúa?', 'Aprobado']">
                @foreach ($campana->reportes->where('aprobado', true) as $r)
                    <tr>
                        <td class="u-mono">#{{ $r->ciclo }}</td>
                        <td class="u-mono">${{ number_format($r->inversion_total ?? 0) }}</td>
                        <td class="u-mono">{{ number_format($r->conversiones_total ?? 0) }}</td>
                        <td class="u-mono">{{ $r->roas_promedio ?? '—' }}</td>
                        <td>{{ $r->continua_campana ? 'Sí' : 'No' }}</td>
                        <td class="u-mono" style="font-size:var(--text-xs); color:var(--color-muted-foreground);">{{ $r->fecha_aprobacion?->format('Y-m-d') }}</td>
                    </tr>
                @endforeach
            </x-data-table>
        </div>
    @endif

    {{-- ---------- Modales ---------- --}}
    <x-modal id="grupoModal" size="lg">
        <x-slot:header><h2 data-grupo-modal-title>Agregar Grupo de Anuncios</h2></x-slot:header>

        <form id="grupoForm"
            data-store-action="{{ route('admin.ads.grupos.store', $campana) }}"
            data-update-action-template="{{ route('admin.ads.grupos.update', ['grupo' => '__ID__']) }}">
            <div class="form-grid form-grid--2">
                <div class="field">
                    <label class="field__label" for="g_nombre">Nombre del grupo</label>
                    <input class="input" type="text" name="nombre" id="g_nombre" required>
                </div>
                <div class="field">
                    <label class="field__label" for="g_audiencia">Audiencia objetivo</label>
                    <input class="input" type="text" name="audiencia" id="g_audiencia">
                </div>
            </div>
            <div class="form-grid form-grid--2" style="margin-top: var(--space-3);">
                <div class="field">
                    <label class="field__label" for="g_presupuesto">Presupuesto del grupo (MXN)</label>
                    <input class="input" type="number" step="0.01" min="0" name="presupuesto" id="g_presupuesto">
                </div>
                <div class="field">
                    <label class="field__label" for="g_estado">Estado</label>
                    <select class="select" name="estado" id="g_estado" required>
                        <option value="activo">Activo</option>
                        <option value="pausado">Pausado</option>
                    </select>
                </div>
            </div>
            <div class="form-actions" style="margin-top: var(--space-4);">
                <button type="submit" class="btn btn--primary" data-grupo-submit-label>Agregar Grupo</button>
                <button type="button" class="btn btn--secondary" data-modal-close="grupoModal">Cancelar</button>
            </div>
        </form>

        <div style="margin-top: var(--space-6); padding-top: var(--space-6); border-top:1px solid var(--color-border);">
            <h3 class="fase-form__section-title">Palabras clave (Keyword Planner)</h3>

            <p class="field__hint" data-keywords-locked-hint style="margin-bottom: var(--space-3);">
                Guarda el grupo primero para poder agregar palabras clave.
            </p>

            <div data-keywords-block hidden>
                <div class="table-wrap" data-keywords-table-container></div>
            </div>

            <datalist id="columnasSugeridas">
                @foreach ($columnasSugeridas as $sugerida)
                    <option value="{{ $sugerida }}"></option>
                @endforeach
            </datalist>
        </div>
    </x-modal>

    <x-modal id="creativoModal">
        <x-slot:header><h2 data-creativo-modal-title>Agregar Creativo</h2></x-slot:header>
        <form id="creativoForm"
            data-store-action="{{ route('admin.ads.creativos.store', $campana) }}"
            data-update-action-template="{{ route('admin.ads.creativos.update', ['creativo' => '__ID__']) }}">
            <div class="field">
                <label class="field__label" for="cr_titulo">Título del anuncio</label>
                <input class="input" type="text" name="titulo" id="cr_titulo" required>
            </div>
            <div class="field" style="margin-top: var(--space-3);">
                <label class="field__label" for="cr_copy">Copy / descripción</label>
                <textarea class="textarea" name="copy" id="cr_copy"></textarea>
            </div>
            <div class="form-grid form-grid--2" style="margin-top: var(--space-3);">
                <div class="field">
                    <label class="field__label" for="cr_tipo">Tipo</label>
                    <select class="select" name="tipo" id="cr_tipo" required>
                        <option value="imagen">Imagen</option>
                        <option value="video">Video</option>
                        <option value="carrusel">Carrusel</option>
                    </select>
                </div>
                <div class="field">
                    <label class="field__label" for="cr_url">URL del creativo</label>
                    <input class="input" type="text" name="url_creativo" id="cr_url">
                </div>
            </div>
            <div class="form-grid form-grid--2" style="margin-top: var(--space-3);">
                <div class="field">
                    <label class="field__label" for="cr_estado">Estado</label>
                    <select class="select" name="estado" id="cr_estado" required>
                        <option value="activo">Activo</option>
                        <option value="pausado">Pausado</option>
                    </select>
                </div>
                <div class="field" style="display:flex; align-items:flex-end;">
                    <label class="checkbox-item">
                        <input type="checkbox" name="ab_testing" id="cr_ab" value="1">
                        A/B testing
                    </label>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn--primary" data-creativo-submit-label>Agregar</button>
                <button type="button" class="btn btn--secondary" data-modal-close="creativoModal">Cancelar</button>
            </div>
        </form>
    </x-modal>

    <x-modal id="metricaModal">
        <x-slot:header><h2 data-metrica-modal-title>Agregar Métricas del Mes</h2></x-slot:header>
        <form id="metricaForm"
            data-store-action="{{ route('admin.ads.metricas.store', $campana) }}"
            data-update-action-template="{{ route('admin.ads.metricas.update', ['metrica' => '__ID__']) }}">
            <div class="form-grid form-grid--2">
                <div class="field">
                    <label class="field__label" for="m_mes">Mes</label>
                    <select class="select" name="mes" id="m_mes" required>
                        @foreach (['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'] as $i => $mesNombre)
                            <option value="{{ $i + 1 }}" @selected($i + 1 === now()->month)>{{ $mesNombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label class="field__label" for="m_anio">Año</label>
                    <input class="input" type="number" min="2000" max="2100" name="anio" id="m_anio" value="{{ now()->year }}" required>
                </div>
            </div>
            <div class="form-grid form-grid--2" style="margin-top: var(--space-3);">
                <div class="field">
                    <label class="field__label" for="m_inversion">Inversión real (MXN)</label>
                    <input class="input" type="number" step="0.01" min="0" name="inversion_real" id="m_inversion">
                </div>
                <div class="field">
                    <label class="field__label" for="m_impresiones">Impresiones</label>
                    <input class="input" type="number" min="0" name="impresiones" id="m_impresiones">
                </div>
            </div>
            <div class="form-grid form-grid--2" style="margin-top: var(--space-3);">
                <div class="field">
                    <label class="field__label" for="m_clics">Clics</label>
                    <input class="input" type="number" min="0" name="clics" id="m_clics">
                </div>
                <div class="field">
                    <label class="field__label" for="m_ctr">CTR (%)</label>
                    <input class="input" type="number" step="0.001" min="0" name="ctr" id="m_ctr">
                </div>
            </div>
            <div class="form-grid form-grid--2" style="margin-top: var(--space-3);">
                <div class="field">
                    <label class="field__label" for="m_cpc">CPC promedio</label>
                    <input class="input" type="number" step="0.01" min="0" name="cpc" id="m_cpc">
                </div>
                <div class="field">
                    <label class="field__label" for="m_conversiones">Conversiones</label>
                    <input class="input" type="number" min="0" name="conversiones" id="m_conversiones">
                </div>
            </div>
            <div class="form-grid form-grid--2" style="margin-top: var(--space-3);">
                <div class="field">
                    <label class="field__label" for="m_cpl">CPL</label>
                    <input class="input" type="number" step="0.01" min="0" name="cpl" id="m_cpl">
                </div>
                <div class="field">
                    <label class="field__label" for="m_cpa">CPA</label>
                    <input class="input" type="number" step="0.01" min="0" name="cpa" id="m_cpa">
                </div>
            </div>
            <div class="form-grid form-grid--2" style="margin-top: var(--space-3);">
                <div class="field">
                    <label class="field__label" for="m_roas">ROAS</label>
                    <input class="input" type="number" step="0.01" min="0" name="roas" id="m_roas">
                </div>
                <div class="field">
                    <label class="field__label" for="m_valor">Valor de conversión (MXN)</label>
                    <input class="input" type="number" step="0.01" min="0" name="valor_conversion" id="m_valor">
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn--primary" data-metrica-submit-label>Agregar</button>
                <button type="button" class="btn btn--secondary" data-modal-close="metricaModal">Cancelar</button>
            </div>
        </form>
    </x-modal>

    <x-modal id="optimizacionModal">
        <x-slot:header><h2 data-optimizacion-modal-title>Registrar Optimización</h2></x-slot:header>
        <form id="optimizacionForm"
            data-store-action="{{ route('admin.ads.optimizaciones.store', $campana) }}"
            data-update-action-template="{{ route('admin.ads.optimizaciones.update', ['optimizacion' => '__ID__']) }}">
            <div class="form-grid form-grid--2">
                <div class="field">
                    <label class="field__label" for="o_fecha">Fecha</label>
                    <input class="input" type="date" name="fecha" id="o_fecha" value="{{ now()->format('Y-m-d') }}" required>
                </div>
                <div class="field">
                    <label class="field__label" for="o_tipo">Tipo</label>
                    <select class="select" name="tipo" id="o_tipo" required>
                        <option value="puja">Puja</option>
                        <option value="audiencia">Audiencia</option>
                        <option value="creativo">Creativo</option>
                        <option value="presupuesto">Presupuesto</option>
                        <option value="keyword">Keyword</option>
                    </select>
                </div>
            </div>
            <div class="field" style="margin-top: var(--space-3);">
                <label class="field__label" for="o_descripcion">Descripción del cambio</label>
                <textarea class="textarea" name="descripcion" id="o_descripcion" required></textarea>
            </div>
            <div class="field" style="margin-top: var(--space-3);">
                <label class="field__label" for="o_resultado">Resultado obtenido</label>
                <textarea class="textarea" name="resultado" id="o_resultado"></textarea>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn--primary" data-optimizacion-submit-label>Registrar</button>
                <button type="button" class="btn btn--secondary" data-modal-close="optimizacionModal">Cancelar</button>
            </div>
        </form>
    </x-modal>
@endsection

@section('scripts')
    @vite('resources/js/ads.js')
@endsection
