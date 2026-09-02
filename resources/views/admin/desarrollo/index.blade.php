@extends('layouts.admin')

@section('styles')
    @vite('resources/css/admin/desarrollo.css')
@endsection

@section('content')
    @php
        // ---------- Server-rendered fallback aggregates — recomputeDesarrolloKpis()
        // and recomputeBugsKpis() in desarrollo.js override these immediately on
        // load and after every filter/CRUD change, from the currently-visible
        // [data-proyecto-row] / [data-bug-row] elements' embedded JSON.
        $totalProyectos = $proyectos->count();
        $totalPresupuesto = (float) $proyectos->sum('presupuesto');
        $totalCobrado = (float) $proyectos->sum('pagos_recibidos');
        $totalPendiente = (float) $proyectos->sum('pendiente');
        $pctCobrado = $totalPresupuesto > 0 ? round($totalCobrado / $totalPresupuesto * 100, 1) : 0;
        $avanceProm = $totalProyectos > 0 ? round($proyectos->avg('porcentaje_avance'), 1) : 0;

        $bugsAbiertos = $bugs->where('estado', 'abierto')->count();
        $bugsEnProgreso = $bugs->where('estado', 'en_progreso')->count();
        $bugsResueltos = $bugs->where('estado', 'resuelto')->count();
        $bugsAlta = $bugs->where('prioridad', 'alta')->count();

        // 4-segment fase mini-visual on each proyecto card — mirrors
        // show.blade.php's .fase-tracker done/current/locked logic exactly
        // (FaseProyecto::orden(): 1=Planeación..4=Control, 5=Cerrado).
        $faseSegs = ['planeacion' => 1, 'organizacion' => 2, 'direccion' => 3, 'control' => 4];

        // "Entrega en N días" / "Entrega vencida" — computed once server-side
        // with now(); JS doesn't need to keep this in sync with filters since
        // it never changes independent of the calendar date.
        $entregaInfo = function (array $p) {
            if (! $p['fecha_entrega_estimada'] || $p['fase_actual'] === 'cerrado') {
                return null;
            }
            $fecha = \Illuminate\Support\Carbon::parse($p['fecha_entrega_estimada'])->startOfDay();
            $hoy = now()->startOfDay();

            return (int) ceil(($fecha->timestamp - $hoy->timestamp) / 86400);
        };

        // Entregas tab — initial PHP-sorted pass (fecha_entrega_estimada asc,
        // nulls last) as a non-JS fallback; renderEntregas() in desarrollo.js
        // rebuilds this from [data-proyecto-row] whenever filters change.
        $proyectosEntregas = $proyectos->sortBy(fn ($p) => $p['fecha_entrega_estimada'] ?? '9999-99-99')->values();
    @endphp

    <div class="page-header">
        <div>
            <h1 class="page-header__title">Módulo de Desarrollo</h1>
            <p class="page-header__subtitle">{{ $enProceso }} proyectos en proceso</p>
        </div>
        <button type="button" class="btn btn--primary" data-open-proyecto-modal>
            <i class="fa-solid fa-plus"></i> Nuevo Proyecto
        </button>
    </div>

    @if (session('status'))
        <div class="form-status"><i class="fa-solid fa-circle-check" style="margin-top:2px"></i><span>{{ session('status') }}</span></div>
    @endif

    {{-- ---------- Sub-tabs: Proyectos / Bugs / Entregas ---------- --}}
    <div class="tabs" id="desarrolloTabs">
        <button type="button" class="tabs__item is-active" data-panel="proyectos">Proyectos</button>
        <button type="button" class="tabs__item" data-panel="bugs">Bugs</button>
        <button type="button" class="tabs__item" data-panel="entregas">Entregas</button>
    </div>

    {{-- ---------- KPI row — always visible, scoped to the Proyectos-tab filters ---------- --}}
    <div class="kpi-grid">
        <x-stat-card id="kpiProyectos" label="Proyectos" value="{{ $totalProyectos }}" sub="{{ $enProceso }} en curso" icon="fa-diagram-project" color="primary" />
        <x-stat-card id="kpiPresupuesto" label="Presupuesto" value="${{ number_format($totalPresupuesto) }}" icon="fa-dollar-sign" color="amber" />
        <x-stat-card id="kpiCobrado" label="Cobrado" value="${{ number_format($totalCobrado) }}" sub="{{ $pctCobrado }}% del total" icon="fa-circle-check" color="emerald" />
        <x-stat-card id="kpiPorCobrar" label="Por cobrar" value="${{ number_format($totalPendiente) }}" icon="fa-hourglass-half" color="red" />
        <x-stat-card id="kpiAvance" label="Avance promedio" value="{{ $avanceProm }}%" icon="fa-chart-line" color="teal" />
    </div>

    {{-- ==================================================================
         Tab: Proyectos
         ================================================================== --}}
    <div data-panel-content="proyectos">
        <div style="display:flex; flex-wrap:wrap; gap: var(--space-3); align-items:center; justify-content:space-between; margin-bottom: var(--space-4);">
            <div style="display:flex; flex-wrap:wrap; gap: var(--space-3);">
                <select class="select" id="desarrolloClienteFilter" style="min-width:200px;">
                    <option value="all">Todos los clientes</option>
                    @foreach ($clientes as $cliente)
                        <option value="{{ $cliente->id }}">{{ $cliente->nombre }}</option>
                    @endforeach
                </select>
                <select class="select" id="desarrolloEstadoFilter" style="min-width:160px;">
                    <option value="all">Todos los estados</option>
                    <option value="activo">Activo</option>
                    <option value="pausado">Pausado</option>
                    <option value="cancelado">Cancelado</option>
                    <option value="cerrado">Cerrado</option>
                </select>
            </div>
            <div class="view-toggle">
                <button type="button" class="view-toggle__btn" data-view-toggle-btn="list" data-view-toggle-group="desarrolloIndex" title="Vista de lista">
                    <i class="fa-solid fa-list"></i>
                </button>
                <button type="button" class="view-toggle__btn" data-view-toggle-btn="kanban" data-view-toggle-group="desarrolloIndex" title="Vista Kanban">
                    <i class="fa-solid fa-table-columns"></i>
                </button>
            </div>
        </div>

        {{-- ---------- Cobrado vs pendiente por proyecto (Chart.js, stacked bar) ---------- --}}
        <div class="card card--padded" style="margin-bottom: var(--space-6);">
            <div class="card__header-title" style="margin-bottom: var(--space-4);">Cobrado vs pendiente por proyecto</div>
            <div style="height:260px;">
                <canvas id="desarrolloChart"></canvas>
            </div>
        </div>

        <div class="empty-state" data-proyectos-empty {{ $proyectos->isEmpty() ? '' : 'hidden' }}>
            <div class="empty-state__icon"><i class="fa-solid fa-code"></i></div>
            <p class="empty-state__text">No hay proyectos que coincidan con los filtros actuales.</p>
        </div>

        <div data-view-panel="list" data-view-toggle-group="desarrolloIndex" data-proyectos-list style="display:flex; flex-direction:column; gap: var(--space-4);">
            @foreach ($proyectos as $p)
                @php $dias = $entregaInfo($p); @endphp
                <div class="card card--padded" data-proyecto-row data-proyecto-id="{{ $p['id'] }}" data-cliente-id="{{ $p['cliente_id'] }}" data-estado="{{ $p['estado'] }}" data-proyecto="{{ json_encode($p) }}">
                    <div class="proyecto-card__head">
                        <div>
                            <div style="display:flex; align-items:center; gap:10px; margin-bottom:6px; flex-wrap:wrap;">
                                <a href="{{ $p['show_url'] }}" style="font-weight:600; color:var(--color-foreground); font-size:var(--text-lg);">{{ $p['nombre'] }}</a>
                                <x-badge :status="$p['fase_actual']" />
                                <x-badge :status="$p['estado']" />
                                @if ($dias !== null)
                                    @if ($dias < 0)
                                        <span class="badge badge--danger">Entrega vencida ({{ abs($dias) }}d)</span>
                                    @elseif ($dias === 0)
                                        <span class="badge badge--warning">Entrega hoy</span>
                                    @else
                                        <span style="font-size:var(--text-xs); color:var(--color-muted-foreground);">Entrega en {{ $dias }} día{{ $dias === 1 ? '' : 's' }}</span>
                                    @endif
                                @endif
                            </div>
                            <div style="display:flex; flex-wrap:wrap; gap: 6px; align-items:center; font-size:var(--text-xs); color:var(--color-muted-foreground);">
                                <span>{{ $p['cliente'] }}</span><span>·</span><span>{{ \App\Support\Labels::tipoProyecto($p['tipo']) }}</span>
                                @if ($p['responsable'])
                                    <span>·</span><span>Responsable: {{ $p['responsable'] }}</span>
                                @endif
                            </div>
                        </div>
                        <div style="text-align:right; display:flex; align-items:flex-start; gap: var(--space-3);">
                            <div>
                                <div class="u-mono" style="font-size:var(--text-2xl); font-weight:700;">{{ $p['porcentaje_avance'] }}%</div>
                                <div style="font-size:var(--text-xs); color:var(--color-muted-foreground);">completado</div>
                            </div>
                            <div style="display:flex; gap:4px;">
                                <button type="button" class="btn--icon" title="Editar" data-edit-proyecto="{{ $p['id'] }}"><i class="fa-solid fa-pen"></i></button>
                                <button type="button" class="btn--icon" title="Eliminar" style="color:var(--text-danger);" data-delete-proyecto="{{ $p['id'] }}"><i class="fa-solid fa-trash"></i></button>
                            </div>
                        </div>
                    </div>

                    <div class="proyecto-card__fase-mini" style="margin: var(--space-4) 0;" title="Fase actual: {{ \App\Support\Labels::faseProyecto($p['fase_actual']) }}">
                        @foreach ($faseSegs as $key => $orden)
                            @php
                                $state = ($orden < $p['fase_orden'] || $p['fase_actual'] === 'cerrado') ? 'done' : ($orden === $p['fase_orden'] ? 'current' : 'locked');
                            @endphp
                            <span class="proyecto-card__fase-seg proyecto-card__fase-seg--{{ $state }}" title="{{ \App\Support\Labels::faseProyecto($key) }}"></span>
                        @endforeach
                    </div>

                    <div class="proyecto-card__stats">
                        <div>
                            <div class="proyecto-card__stat-label">Presupuesto</div>
                            <div class="proyecto-card__stat-value u-mono">${{ number_format($p['presupuesto']) }}</div>
                        </div>
                        <div>
                            <div class="proyecto-card__stat-label">Cobrado</div>
                            <div class="proyecto-card__stat-value u-mono" style="color:var(--text-success)">${{ number_format($p['pagos_recibidos']) }}</div>
                        </div>
                        <div>
                            <div class="proyecto-card__stat-label">Pendiente</div>
                            <div class="proyecto-card__stat-value u-mono" style="color:var(--text-warning)">${{ number_format($p['pendiente']) }}</div>
                        </div>
                        <div>
                            <div class="proyecto-card__stat-label">Bugs abiertos</div>
                            <div class="proyecto-card__stat-value u-mono" style="{{ $p['bugs_abiertos_count'] > 0 ? 'color:var(--text-danger)' : '' }}">{{ $p['bugs_abiertos_count'] }}</div>
                        </div>
                    </div>

                    <div class="proyecto-card__footer">
                        <div style="display:flex; gap:12px; flex-wrap:wrap; color:var(--color-muted-foreground);">
                            <span>Inicio: {{ $p['fecha_inicio'] ?? '—' }}</span>
                            <span>Entrega estimada: {{ $p['fecha_entrega_estimada'] ?? '—' }}</span>
                            @if ($p['url_repositorio'])
                                <a href="{{ $p['url_repositorio'] }}" target="_blank" rel="noopener" style="color:var(--color-primary);"><i class="fa-solid fa-code-branch"></i> Repositorio</a>
                            @endif
                            @if ($p['url_staging'])
                                <a href="{{ $p['url_staging'] }}" target="_blank" rel="noopener" style="color:var(--color-primary);"><i class="fa-solid fa-flask"></i> Staging</a>
                            @endif
                        </div>
                        <a href="{{ $p['show_url'] }}" style="color:var(--color-primary); font-weight:500;">Ver detalle →</a>
                    </div>
                </div>
            @endforeach
        </div>

        <div data-view-panel="kanban" data-view-toggle-group="desarrolloIndex" hidden>
            @include('admin.desarrollo._proyectos-kanban', ['proyectos' => $proyectos])
        </div>
    </div>

    {{-- ==================================================================
         Tab: Bugs (global, cross-project)
         ================================================================== --}}
    <div data-panel-content="bugs" hidden>
        <div class="kpi-grid" style="margin-bottom: var(--space-6);">
            <x-stat-card id="kpiBugsAbiertos" label="Abiertos" value="{{ $bugsAbiertos }}" icon="fa-bug" color="red" />
            <x-stat-card id="kpiBugsEnProgreso" label="En progreso" value="{{ $bugsEnProgreso }}" icon="fa-spinner" color="blue" />
            <x-stat-card id="kpiBugsResueltos" label="Resueltos" value="{{ $bugsResueltos }}" icon="fa-circle-check" color="emerald" />
            <x-stat-card id="kpiBugsAlta" label="Prioridad alta" value="{{ $bugsAlta }}" icon="fa-triangle-exclamation" color="amber" />
        </div>

        <div style="display:flex; flex-wrap:wrap; gap: var(--space-3); align-items:center; justify-content:space-between; margin-bottom: var(--space-4);">
            <div style="display:flex; flex-wrap:wrap; gap: var(--space-3);">
                <select class="select" id="bugsProyectoFilter" style="min-width:200px;">
                    <option value="all">Todos los proyectos</option>
                    @foreach ($proyectos as $p)
                        <option value="{{ $p['id'] }}">{{ $p['nombre'] }}</option>
                    @endforeach
                </select>
                <select class="select" id="bugsPrioridadFilter" style="min-width:150px;">
                    <option value="all">Todas las prioridades</option>
                    <option value="alta">Alta</option>
                    <option value="media">Media</option>
                    <option value="baja">Baja</option>
                </select>
                <select class="select" id="bugsEstadoFilter" style="min-width:150px;">
                    <option value="all">Todos los estados</option>
                    <option value="abierto">Abierto</option>
                    <option value="en_progreso">En Progreso</option>
                    <option value="resuelto">Resuelto</option>
                </select>
            </div>
            <button type="button" class="btn btn--primary" data-open-bug-modal>
                <i class="fa-solid fa-plus"></i> Reportar Bug
            </button>
        </div>

        <div class="empty-state" data-desarrollo-bugs-empty {{ $bugs->isEmpty() ? '' : 'hidden' }}>
            <div class="empty-state__icon"><i class="fa-solid fa-bug"></i></div>
            <p class="empty-state__text">No hay bugs que coincidan con los filtros actuales.</p>
        </div>
        <x-data-table :headers="['Proyecto', 'Descripción', 'Prioridad', 'Estado', 'Creado', 'Resuelto', 'Días abierto', '']" data-paginate="15" data-desarrollo-bugs-table :hidden="$bugs->isEmpty()">
            @foreach ($bugs as $b)
                <tr data-bug-row data-bug-id="{{ $b['id'] }}" data-proyecto-id="{{ $b['proyecto_id'] }}" data-prioridad="{{ $b['prioridad'] }}" data-estado="{{ $b['estado'] }}" data-bug="{{ json_encode($b) }}">
                    <td><a href="{{ route('admin.desarrollo.show', $b['proyecto_id']) }}" style="color:var(--color-foreground); font-weight:500;">{{ $b['proyecto_nombre'] }}</a></td>
                    <td>{{ $b['titulo'] }}</td>
                    <td><x-badge :status="$b['prioridad']" /></td>
                    <td><x-badge :status="$b['estado']" /></td>
                    <td class="u-mono" style="font-size:var(--text-xs); color:var(--color-muted-foreground);">{{ $b['created_at'] }}</td>
                    <td class="u-mono" style="font-size:var(--text-xs); color:var(--color-muted-foreground);">{{ $b['fecha_resolucion'] ?? '—' }}</td>
                    <td class="u-mono">{{ $b['dias_abierto'] ?? '—' }}</td>
                    <td>
                        <div style="display:flex; gap:4px;">
                            <button type="button" class="btn--icon" title="Editar" data-edit-bug-global="{{ $b['id'] }}"><i class="fa-solid fa-pen"></i></button>
                            <button type="button" class="btn--icon" title="Eliminar" style="color:var(--text-danger);" data-delete-bug-global="{{ $b['id'] }}"><i class="fa-solid fa-trash"></i></button>
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-data-table>
    </div>

    {{-- ==================================================================
         Tab: Entregas
         ================================================================== --}}
    <div data-panel-content="entregas" hidden>
        <div class="empty-state" data-entregas-empty {{ $proyectosEntregas->isEmpty() ? '' : 'hidden' }}>
            <div class="empty-state__icon"><i class="fa-solid fa-truck"></i></div>
            <p class="empty-state__text">No hay entregas que coincidan con los filtros actuales.</p>
        </div>
        <div data-entregas-list>
            @foreach ($proyectosEntregas as $p)
                @php $dias = $entregaInfo($p); @endphp
                <div class="entrega-card">
                    <div>
                        <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px; flex-wrap:wrap;">
                            <a href="{{ $p['show_url'] }}" style="font-weight:600; color:var(--color-foreground);">{{ $p['nombre'] }}</a>
                            <x-badge :status="$p['fase_actual']" />
                        </div>
                        <div style="font-size:var(--text-xs); color:var(--color-muted-foreground);">{{ $p['cliente'] }}</div>
                    </div>
                    <div style="text-align:right;">
                        <div class="u-mono" style="font-size:var(--text-sm);">{{ $p['fecha_entrega_estimada'] ?? 'Sin fecha' }}</div>
                        @if ($dias !== null)
                            @if ($dias < 0)
                                <span class="badge badge--danger">Vencida ({{ abs($dias) }}d)</span>
                            @elseif ($dias === 0)
                                <span class="badge badge--warning">Hoy</span>
                            @else
                                <span style="font-size:var(--text-xs); color:var(--color-muted-foreground);">En {{ $dias }} día{{ $dias === 1 ? '' : 's' }}</span>
                            @endif
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- ---------- Create/edit modal — pagos_recibidos/fecha_entrega_real/estado only matter (and are only shown) in edit mode; store() doesn't validate them ---------- --}}
    <x-modal id="proyectoFormModal">
        <x-slot:header><h2 id="proyectoFormModalTitle" style="margin-bottom:0;">Nuevo Proyecto</h2></x-slot:header>
        <form id="proyectoForm" novalidate
              data-store-action="{{ route('admin.desarrollo.store') }}"
              data-update-action-template="{{ route('admin.desarrollo.update', ['proyecto' => '__ID__']) }}">
            @csrf
            <div class="form-grid form-grid--2">
                <div class="field">
                    <label class="field__label" for="pf_cliente_id">Cliente</label>
                    <select class="select" name="cliente_id" id="pf_cliente_id" required>
                        <option value="">— Selecciona un cliente —</option>
                        @foreach ($clientes as $cliente)
                            <option value="{{ $cliente->id }}">{{ $cliente->nombre }}</option>
                        @endforeach
                    </select>
                    <span class="field__error" data-error-for="cliente_id"></span>
                </div>
                <div class="field">
                    <label class="field__label" for="pf_tipo">Tipo</label>
                    <select class="select" name="tipo" id="pf_tipo" required>
                        @foreach (['web_nueva' => 'Web Nueva', 'rediseno' => 'Rediseño', 'software' => 'Software', 'landing' => 'Landing Page'] as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <span class="field__error" data-error-for="tipo"></span>
                </div>
            </div>

            <div class="field" style="margin-top: var(--space-4);">
                <label class="field__label" for="pf_nombre">Nombre del proyecto</label>
                <input class="input" type="text" name="nombre" id="pf_nombre" required>
                <span class="field__error" data-error-for="nombre"></span>
            </div>

            <div class="field" style="margin-top: var(--space-4);">
                <label class="field__label" for="pf_descripcion">Descripción</label>
                <textarea class="textarea" name="descripcion" id="pf_descripcion"></textarea>
                <span class="field__error" data-error-for="descripcion"></span>
            </div>

            <div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
                <div class="field">
                    <label class="field__label" for="pf_presupuesto">Presupuesto (MXN)</label>
                    <input class="input" type="number" step="0.01" min="0" name="presupuesto" id="pf_presupuesto" value="0" required>
                    <span class="field__error" data-error-for="presupuesto"></span>
                </div>
                <div class="field">
                    <label class="field__label" for="pf_anticipo">Anticipo (MXN)</label>
                    <input class="input" type="number" step="0.01" min="0" name="anticipo" id="pf_anticipo" value="0">
                    <span class="field__error" data-error-for="anticipo"></span>
                </div>
            </div>

            <div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
                <div class="field">
                    <label class="field__label" for="pf_forma_pago">Forma de pago</label>
                    <select class="select" name="forma_pago" id="pf_forma_pago">
                        <option value="">— Sin especificar —</option>
                        @foreach (['mensual' => 'Mensual', 'etapas' => 'Por Etapas', 'unico' => 'Pago Único'] as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <span class="field__error" data-error-for="forma_pago"></span>
                </div>
                <div class="field">
                    <label class="field__label" for="pf_fecha_inicio">Fecha de inicio</label>
                    <input class="input" type="date" name="fecha_inicio" id="pf_fecha_inicio">
                    <span class="field__error" data-error-for="fecha_inicio"></span>
                </div>
            </div>

            <div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
                <div class="field">
                    <label class="field__label" for="pf_fecha_entrega_estimada">Entrega estimada</label>
                    <input class="input" type="date" name="fecha_entrega_estimada" id="pf_fecha_entrega_estimada">
                    <span class="field__error" data-error-for="fecha_entrega_estimada"></span>
                </div>
                <div class="field">
                    <label class="field__label" for="pf_responsable">Responsable</label>
                    <input class="input" type="text" name="responsable" id="pf_responsable">
                    <span class="field__error" data-error-for="responsable"></span>
                </div>
            </div>

            <div data-edit-only>
                <div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
                    <div class="field">
                        <label class="field__label" for="pf_pagos_recibidos">Pagos recibidos (MXN)</label>
                        <input class="input" type="number" step="0.01" min="0" name="pagos_recibidos" id="pf_pagos_recibidos">
                        <span class="field__error" data-error-for="pagos_recibidos"></span>
                    </div>
                    <div class="field">
                        <label class="field__label" for="pf_fecha_entrega_real">Entrega real</label>
                        <input class="input" type="date" name="fecha_entrega_real" id="pf_fecha_entrega_real">
                        <span class="field__error" data-error-for="fecha_entrega_real"></span>
                    </div>
                </div>
                <div class="field" style="margin-top: var(--space-4);">
                    <label class="field__label" for="pf_estado">Estado</label>
                    <select class="select" name="estado" id="pf_estado">
                        @foreach (['activo' => 'Activo', 'pausado' => 'Pausado', 'cancelado' => 'Cancelado', 'cerrado' => 'Cerrado'] as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <span class="field__error" data-error-for="estado"></span>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn--primary" id="proyectoFormSubmit"><i class="fa-solid fa-check"></i> Crear Proyecto</button>
                <button type="button" class="btn btn--secondary" data-modal-close="proyectoFormModal">Cancelar</button>
            </div>
        </form>
    </x-modal>

    {{-- ---------- Global bug create/edit modal — needs an explicit proyecto_id select (no implicit "current project" like show.blade.php's nested #bugModal) ---------- --}}
    <x-modal id="globalBugFormModal">
        <x-slot:header><h2 id="globalBugFormModalTitle" style="margin-bottom:0;">Reportar Bug</h2></x-slot:header>
        <form id="globalBugForm" novalidate>
            @csrf
            <div class="field">
                <label class="field__label" for="gb_proyecto_id">Proyecto</label>
                <select class="select" name="proyecto_id" id="gb_proyecto_id" required>
                    <option value="">— Selecciona un proyecto —</option>
                    @foreach ($proyectos as $p)
                        <option value="{{ $p['id'] }}">{{ $p['nombre'] }}</option>
                    @endforeach
                </select>
                <span class="field__error" data-error-for="proyecto_id"></span>
            </div>

            <div class="field" style="margin-top: var(--space-4);">
                <label class="field__label" for="gb_titulo">Título</label>
                <input class="input" type="text" name="titulo" id="gb_titulo" required>
                <span class="field__error" data-error-for="titulo"></span>
            </div>

            <div class="field" style="margin-top: var(--space-4);">
                <label class="field__label" for="gb_descripcion">Descripción</label>
                <textarea class="textarea" name="descripcion" id="gb_descripcion"></textarea>
                <span class="field__error" data-error-for="descripcion"></span>
            </div>

            <div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
                <div class="field">
                    <label class="field__label" for="gb_prioridad">Prioridad</label>
                    <select class="select" name="prioridad" id="gb_prioridad" required>
                        <option value="alta">Alta</option>
                        <option value="media" selected>Media</option>
                        <option value="baja">Baja</option>
                    </select>
                    <span class="field__error" data-error-for="prioridad"></span>
                </div>
                <div class="field">
                    <label class="field__label" for="gb_estado">Estado</label>
                    <select class="select" name="estado" id="gb_estado" required>
                        <option value="abierto">Abierto</option>
                        <option value="en_progreso">En Progreso</option>
                        <option value="resuelto">Resuelto</option>
                    </select>
                    <span class="field__error" data-error-for="estado"></span>
                </div>
            </div>

            <div class="field" style="margin-top: var(--space-4);">
                <label class="field__label" for="gb_fecha_resolucion">Fecha de resolución</label>
                <input class="input" type="date" name="fecha_resolucion" id="gb_fecha_resolucion">
                <span class="field__error" data-error-for="fecha_resolucion"></span>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn--primary" id="globalBugFormSubmit"><i class="fa-solid fa-check"></i> Reportar Bug</button>
                <button type="button" class="btn btn--secondary" data-modal-close="globalBugFormModal">Cancelar</button>
            </div>
        </form>
    </x-modal>
@endsection

@section('scripts')
    @vite('resources/js/desarrollo.js')
@endsection
