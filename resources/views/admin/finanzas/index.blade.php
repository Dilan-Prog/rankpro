@extends('layouts.admin')

@section('styles')
    @vite('resources/css/admin/finanzas.css')
@endsection

@section('content')
    @php
        // ---------- KPI formatting: reuses the old view's single K-style compacting
        // convention (number_format($x/1000, 1) . 'K') consistently across all 6
        // tiles, instead of mixing it with Ads' M/K formatCompact() convention.
        $money = fn ($x) => ($x < 0 ? '-$' : '$') . number_format(abs((float) $x) / 1000, 1) . 'K';
        $margenPct = $cobrado > 0 ? round($utilidad / $cobrado * 100, 1) : 0;
        $utilidadColor = $utilidad >= 0 ? 'emerald' : 'red';

        // ---------- Cartera buckets: sorted pagado/pendiente/vencido for a stable
        // display order (the controller returns them grouped in whatever order
        // Collection::groupBy() encountered them in), with a color per estado
        // matching <x-badge>'s own success/warning/danger semantics.
        $ordenCartera = ['pagado' => 0, 'pendiente' => 1, 'vencido' => 2];
        $carteraBuckets = collect($carteraBuckets)->sortBy(fn ($b) => $ordenCartera[$b['estado']] ?? 99)->values()->all();
        $carteraColores = ['pagado' => 'var(--text-success)', 'pendiente' => '#F59E0B', 'vencido' => 'var(--text-danger)'];

        // ---------- "Por cliente" tab: top 8 clients shown in the concentración
        // list (mrrPorCliente already comes sorted desc from the controller); the
        // chart itself plots all of them. Top-3 concentration guards against
        // fewer than 3 clients existing and against a zero total MRR.
        $mrrTotalClientes = (float) $mrrPorCliente->sum('mrr');
        $top8Clientes = $mrrPorCliente->take(8);
        $top3Pct = $mrrTotalClientes > 0
            ? round($mrrPorCliente->take(min(3, $mrrPorCliente->count()))->sum('mrr') / $mrrTotalClientes * 100, 1)
            : 0;
    @endphp

    <div class="page-header">
        <div>
            <h1 class="page-header__title">Finanzas</h1>
            <p class="page-header__subtitle">Facturación, ingresos y proyecciones</p>
        </div>
        <div style="display:flex; gap: var(--space-3);">
            <a id="finanzasExportBtn" href="{{ route('admin.finanzas.exportar') }}" class="btn btn--secondary">
                <i class="fa-solid fa-file-export"></i> Exportar
            </a>
            <button type="button" class="btn btn--primary" data-open-finanza-modal>
                <i class="fa-solid fa-plus"></i> Nuevo Registro
            </button>
        </div>
    </div>

    @if (session('status'))
        <div class="form-status"><i class="fa-solid fa-circle-check" style="margin-top:2px"></i><span>{{ session('status') }}</span></div>
    @endif

    <div class="kpi-grid">
        <x-stat-card label="MRR Activo" value="{{ $money($mrr) }}" sub="MXN · servicios activos" icon="fa-arrow-trend-up" color="emerald" />
        <x-stat-card label="Cobrado (este mes)" value="{{ $money($cobrado) }}" sub="{{ $facturasPagadas }} facturas pagadas" icon="fa-circle-check" color="primary" />
        <x-stat-card label="Por cobrar" value="{{ $money($pendiente) }}" sub="{{ $facturasPendientes }} facturas pendientes" icon="fa-triangle-exclamation" color="amber" />
        <x-stat-card label="Ingresos (6 meses)" value="{{ $money($ingresos6m) }}" icon="fa-chart-column" color="teal" />
        <x-stat-card label="Utilidad neta" value="{{ $money($utilidad) }}" sub="{{ $margenPct }}% de margen" icon="fa-scale-balanced" color="{{ $utilidadColor }}" />
        <x-stat-card label="Ticket promedio" value="{{ $money($ticketPromedio) }}" sub="por cliente activo" icon="fa-receipt" color="blue" />
    </div>

    <div class="tabs" id="finanzasTabs">
        <button type="button" class="tabs__item is-active" data-panel="resumen">Resumen</button>
        <button type="button" class="tabs__item" data-panel="facturacion">Facturación</button>
        <button type="button" class="tabs__item" data-panel="cliente">Por cliente</button>
    </div>

    {{-- ==================================================================
         Tab: Resumen
         ================================================================== --}}
    <div data-panel-content="resumen">
        <div class="card card--padded" style="margin-bottom: var(--space-6);">
            <div class="chart-head">
                <h2 class="card__header-title">Ingresos, gastos y utilidad</h2>
            </div>
            <div class="chart-wrap" style="height:260px;">
                <canvas id="financeChart" role="img" aria-label="Gráfica de ingresos, gastos y utilidad por mes"
                    data-revenue="{{ json_encode($revenueData) }}"></canvas>
            </div>
        </div>

        <div class="card card--padded">
            <div class="chart-head">
                <h2 class="card__header-title">Estado de la cartera</h2>
            </div>
            @if (empty($carteraBuckets))
                <p style="font-size:var(--text-sm); color:var(--color-muted-foreground); margin:0;">Sin facturas de ingreso registradas.</p>
            @else
                <div class="cartera-list">
                    @foreach ($carteraBuckets as $bucket)
                        <div class="cartera-row">
                            <div class="cartera-row__head">
                                <div style="display:flex; align-items:center; gap: var(--space-2);">
                                    <x-badge :status="$bucket['estado']" />
                                    <span style="font-size:var(--text-xs); color:var(--color-muted-foreground);">{{ $bucket['count'] }} {{ $bucket['count'] === 1 ? 'factura' : 'facturas' }}</span>
                                </div>
                                <strong class="u-mono">${{ number_format($bucket['monto']) }}</strong>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-bar__fill" style="width:{{ $bucket['porcentaje'] }}%; background:{{ $carteraColores[$bucket['estado']] ?? 'var(--color-primary)' }};"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- ==================================================================
         Tab: Facturación
         ================================================================== --}}
    <div data-panel-content="facturacion" hidden>
        <div class="filters-bar">
            <input type="search" class="input input--search" id="facturasSearch" placeholder="Buscar por cliente, concepto o folio...">
            <select class="select" id="facturasClienteFilter">
                <option value="all">Todos los clientes</option>
                @foreach ($clientes as $cliente)
                    <option value="{{ $cliente->id }}">{{ $cliente->nombre }}</option>
                @endforeach
            </select>
            <select class="select" id="facturasEstadoFilter">
                <option value="all">Todos los estados</option>
                <option value="pagado">Pagado</option>
                <option value="pendiente">Pendiente</option>
                <option value="vencido">Vencido</option>
            </select>
        </div>

        <div class="empty-state" data-facturas-empty {{ $facturas->isEmpty() ? '' : 'hidden' }}>
            <div class="empty-state__icon"><i class="fa-solid fa-file-invoice-dollar"></i></div>
            <p class="empty-state__text">No hay registros que coincidan con los filtros actuales.</p>
        </div>

        <x-data-table :headers="['Folio', 'Cliente', 'Concepto', 'Tipo', 'Monto', 'Vencimiento', 'Estado', 'Fecha de Pago', '']" data-paginate="15" data-facturas-table :hidden="$facturas->isEmpty()">
            @foreach ($facturas as $f)
                <tr data-factura-row data-factura-id="{{ $f['id'] }}" data-cliente-id="{{ $f['cliente_id'] }}" data-estado="{{ $f['estado'] }}"
                    data-search="{{ strtolower($f['cliente'].' '.$f['concepto'].' '.$f['folio']) }}" data-factura="{{ json_encode($f) }}">
                    <td class="u-mono" style="font-size:var(--text-xs); color:var(--color-muted-foreground);">{{ $f['folio'] }}</td>
                    <td><div style="font-weight:500">{{ $f['cliente'] }}</div></td>
                    <td><span style="font-size:var(--text-sm); color:var(--color-muted-foreground);">{{ $f['concepto'] }}</span></td>
                    <td><span style="font-size:var(--text-xs); text-transform:capitalize; color:{{ $f['tipo'] === 'ingreso' ? 'var(--text-success)' : 'var(--text-danger)' }};">{{ $f['tipo'] }}</span></td>
                    <td class="u-mono"><strong>${{ number_format($f['monto']) }}</strong></td>
                    <td class="u-mono" style="font-size:var(--text-xs); color:var(--color-muted-foreground);">{{ $f['fecha_vencimiento'] ?? '—' }}</td>
                    <td><x-badge :status="$f['estado']" /></td>
                    <td class="u-mono" style="font-size:var(--text-xs); color:var(--color-muted-foreground);">{{ $f['fecha_pago'] ?? '—' }}</td>
                    <td>
                        <div style="display:flex; gap:4px;">
                            <button type="button" class="btn--icon" title="Editar" data-edit-finanza="{{ $f['id'] }}"><i class="fa-solid fa-pen"></i></button>
                            <button type="button" class="btn--icon" title="Eliminar" style="color:var(--text-danger);" data-delete-finanza="{{ $f['id'] }}"><i class="fa-solid fa-trash"></i></button>
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-data-table>

        <div class="finanzas-totals" data-facturas-totals>
            <span>Ingresos: <strong class="u-mono" data-total-ingreso>$0</strong></span>
            <span>Gastos: <strong class="u-mono" data-total-gasto>$0</strong></span>
            <span>Balance: <strong class="u-mono" data-total-balance>$0</strong></span>
        </div>
    </div>

    {{-- ==================================================================
         Tab: Por cliente
         ================================================================== --}}
    <div data-panel-content="cliente" hidden>
        <div class="card card--padded" style="margin-bottom: var(--space-6);">
            <div class="chart-head">
                <h2 class="card__header-title">MRR por cliente</h2>
            </div>
            <div class="chart-wrap" style="height:{{ max(220, $mrrPorCliente->count() * 34) }}px;">
                <canvas id="finanzasClienteChart" data-mrr-por-cliente="{{ $mrrPorCliente->toJson() }}"></canvas>
            </div>
        </div>

        <div class="card card--padded">
            <div class="chart-head">
                <h2 class="card__header-title">Concentración</h2>
            </div>
            @if ($mrrPorCliente->isEmpty())
                <p style="font-size:var(--text-sm); color:var(--color-muted-foreground); margin:0;">Sin clientes con MRR activo.</p>
            @else
                {{-- Top 8 clients by MRR — the chart above plots all of them, this list is truncated for readability. --}}
                <div class="concentracion-list">
                    @foreach ($top8Clientes as $c)
                        <div class="concentracion-row">
                            <span class="concentracion-row__name">{{ $c['cliente'] }}</span>
                            <span class="u-mono concentracion-row__mrr">${{ number_format($c['mrr']) }}</span>
                            <span class="concentracion-row__pct">{{ $mrrTotalClientes > 0 ? round($c['mrr'] / $mrrTotalClientes * 100, 1) : 0 }}%</span>
                        </div>
                    @endforeach
                </div>
                <p style="font-size:var(--text-sm); color:var(--color-muted-foreground); margin-top: var(--space-4); margin-bottom:0;">
                    Los {{ min(3, $mrrPorCliente->count()) }} principales clientes concentran {{ $top3Pct }}% del MRR total.
                </p>
            @endif
        </div>
    </div>

    {{-- ---------- Create/edit modal — servicio_id intentionally omitted (see finanzas.js comment); AJAX-only, so field errors are unconditional empty spans, never @error(...)@enderror ---------- --}}
    <x-modal id="finanzaFormModal">
        <x-slot:header><h2 id="finanzaFormModalTitle" style="margin-bottom:0;">Nuevo Registro</h2></x-slot:header>
        <form id="finanzaForm" novalidate
              data-store-action="{{ route('admin.finanzas.store') }}"
              data-update-action-template="{{ route('admin.finanzas.update', ['finanza' => '__ID__']) }}">
            @csrf
            <div class="form-grid form-grid--2">
                <div class="field">
                    <label class="field__label" for="cliente_id">Cliente</label>
                    <select class="select" name="cliente_id" id="cliente_id" required>
                        <option value="">— Selecciona un cliente —</option>
                        @foreach ($clientes as $cliente)
                            <option value="{{ $cliente->id }}">{{ $cliente->nombre }}</option>
                        @endforeach
                    </select>
                    <span class="field__error" data-error-for="cliente_id"></span>
                </div>
                <div class="field">
                    <label class="field__label" for="ff_tipo">Tipo</label>
                    <select class="select" name="tipo" id="ff_tipo" required>
                        <option value="ingreso" selected>Ingreso</option>
                        <option value="gasto">Gasto</option>
                    </select>
                    <span class="field__error" data-error-for="tipo"></span>
                </div>
            </div>

            <div class="field" style="margin-top: var(--space-4);">
                <label class="field__label" for="ff_concepto">Concepto</label>
                <input class="input" type="text" name="concepto" id="ff_concepto" required placeholder="Ej. SEO + Google Ads — Julio 2025">
                <span class="field__error" data-error-for="concepto"></span>
            </div>

            <div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
                <div class="field">
                    <label class="field__label" for="ff_monto">Monto (MXN)</label>
                    <input class="input" type="number" step="0.01" min="0" name="monto" id="ff_monto" required>
                    <span class="field__error" data-error-for="monto"></span>
                </div>
                <div class="field">
                    <label class="field__label" for="ff_estado">Estado</label>
                    <select class="select" name="estado" id="ff_estado" required>
                        <option value="pagado">Pagado</option>
                        <option value="pendiente" selected>Pendiente</option>
                        <option value="vencido">Vencido</option>
                    </select>
                    <span class="field__error" data-error-for="estado"></span>
                </div>
            </div>

            <div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
                <div class="field">
                    <label class="field__label" for="ff_mes">Mes</label>
                    <select class="select" name="mes" id="ff_mes" required>
                        @foreach (['1'=>'Enero','2'=>'Febrero','3'=>'Marzo','4'=>'Abril','5'=>'Mayo','6'=>'Junio','7'=>'Julio','8'=>'Agosto','9'=>'Septiembre','10'=>'Octubre','11'=>'Noviembre','12'=>'Diciembre'] as $value => $label)
                            <option value="{{ $value }}" @selected((int) $value === now()->month)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <span class="field__error" data-error-for="mes"></span>
                </div>
                <div class="field">
                    <label class="field__label" for="ff_anio">Año</label>
                    <input class="input" type="number" min="2000" max="2100" name="anio" id="ff_anio" value="{{ now()->year }}" required>
                    <span class="field__error" data-error-for="anio"></span>
                </div>
            </div>

            <div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
                <div class="field">
                    <label class="field__label" for="ff_fecha_emision">Fecha de emisión</label>
                    <input class="input" type="date" name="fecha_emision" id="ff_fecha_emision">
                    <span class="field__error" data-error-for="fecha_emision"></span>
                </div>
                <div class="field">
                    <label class="field__label" for="ff_fecha_vencimiento">Fecha de vencimiento</label>
                    <input class="input" type="date" name="fecha_vencimiento" id="ff_fecha_vencimiento">
                    <span class="field__error" data-error-for="fecha_vencimiento"></span>
                </div>
            </div>

            <div class="field" style="margin-top: var(--space-4); max-width: 240px;">
                <label class="field__label" for="ff_fecha_pago">Fecha de pago (si ya se pagó)</label>
                <input class="input" type="date" name="fecha_pago" id="ff_fecha_pago">
                <span class="field__error" data-error-for="fecha_pago"></span>
            </div>

            <div class="field" style="margin-top: var(--space-4);">
                <label class="field__label" for="ff_notas">Notas</label>
                <textarea class="textarea" name="notas" id="ff_notas"></textarea>
                <span class="field__error" data-error-for="notas"></span>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn--primary" id="finanzaFormSubmit"><i class="fa-solid fa-check"></i> Crear Registro</button>
                <button type="button" class="btn btn--secondary" data-modal-close="finanzaFormModal">Cancelar</button>
            </div>
        </form>
    </x-modal>
@endsection

@section('scripts')
    @vite('resources/js/finanzas.js')
@endsection
