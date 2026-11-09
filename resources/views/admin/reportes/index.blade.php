@extends('layouts.admin')

@section('styles')
    @vite('resources/css/admin/reportes.css')
@endsection

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Reportes</h1>
            <p class="page-header__subtitle">{{ $totalReportes }} reporte{{ $totalReportes === 1 ? '' : 's' }} · {{ $borradores }} en borrador</p>
        </div>
        <div style="display:flex; gap:8px; flex-wrap:wrap;">
            <a href="{{ route('admin.reportes.tablero.index') }}" class="btn btn--secondary" data-reportes-tablero-link>
                <i class="fa-solid fa-chart-line"></i> Tablero por cliente
            </a>
            <a href="{{ route('admin.reportes.programados.index') }}" class="btn btn--secondary" data-reportes-programados-link>
                <i class="fa-solid fa-calendar-check"></i> Reportes programados
            </a>
            <button type="button" class="btn btn--secondary" data-open-reporte-modal title="Periodo libre y plantilla sin datos precargados">
                <i class="fa-solid fa-sliders"></i> Reporte personalizado
            </button>
            <button type="button" class="btn btn--primary" data-open-mes-modal>
                <i class="fa-solid fa-wand-magic-sparkles"></i> Generar reporte del mes
            </button>
        </div>
    </div>

    <x-cliente-activo-aviso :cliente="$clienteActivo" />

    {{-- ---------- KPIs ---------- --}}
    <div class="kpi-grid">
        <x-stat-card label="Reportes" value="{{ $totalReportes }}" icon="fa-file-lines" color="primary" />
        <x-stat-card label="En borrador" value="{{ $borradores }}" sub="pendientes de cerrar" icon="fa-pen-ruler" color="amber" />
        <x-stat-card label="Listos" value="{{ $reportes->where('estado', 'listo')->count() }}" sub="revisados, sin entregar" icon="fa-circle-check" color="teal" />
        <x-stat-card label="Entregados este mes" value="{{ $entregadosMes }}" icon="fa-paper-plane" color="emerald" />
    </div>

    <div class="filters-bar">
        <input type="search" class="input input--search" id="reporteSearch" placeholder="Buscar cliente, título o folio...">
        <select class="select" id="reporteAreaFilter">
            <option value="all">Todas las áreas</option>
            @foreach (\App\Enums\AreaReporte::cases() as $area)
                <option value="{{ $area->value }}">{{ $area->label() }}</option>
            @endforeach
        </select>
        <select class="select" id="reporteEstadoFilter">
            <option value="all">Todos los estados</option>
            @foreach (\App\Enums\EstadoReporte::cases() as $estado)
                <option value="{{ $estado->value }}">{{ \App\Support\Labels::estadoReporte($estado->value) }}</option>
            @endforeach
        </select>
    </div>

    <div class="card empty-state" data-reportes-empty {{ $reportes->isEmpty() ? '' : 'hidden' }}>
        <div class="empty-state__icon"><i class="fa-solid fa-file-lines"></i></div>
        <p class="empty-state__text">Aún no hay reportes. Genera el del mes: el título, el folio y los indicadores se llenan solos con lo que ya capturaste.</p>
        <button type="button" class="btn btn--primary" data-open-mes-modal>Generar reporte del mes</button>
    </div>

    <x-data-table :headers="['Cliente', 'Área', 'Título', 'Periodo', 'Estado', 'Entregas', 'Actualizado', '']"
                  data-paginate="15" data-reportes-tabla :hidden="$reportes->isEmpty()"
                  data-reporte-destroy-url="{{ route('admin.reportes.destroy', ['reporte' => '__ID__']) }}">
        @foreach ($reportes as $reporte)
            <tr data-reporte-row
                data-search="{{ mb_strtolower(($reporte['cliente'] ?? '').' '.$reporte['titulo'].' '.($reporte['numero'] ?? '')) }}"
                data-area="{{ $reporte['area'] }}"
                data-estado="{{ $reporte['estado'] }}">
                <td><div style="font-weight:500">{{ $reporte['cliente'] ?? '—' }}</div></td>
                <td><span class="badge badge--primary">{{ $reporte['area_label'] }}</span></td>
                <td>
                    <div>{{ $reporte['titulo'] }}</div>
                    @if ($reporte['numero'])
                        <div class="u-mono" style="font-size:var(--text-xs);color:var(--color-muted-foreground)">{{ $reporte['numero'] }}</div>
                    @endif
                </td>
                <td><span class="u-mono" style="font-size:var(--text-xs)">{{ fecha_mx_texto($reporte['periodo']) }}</span></td>
                <td><x-badge :status="$reporte['estado']" /></td>
                <td class="u-mono">{{ $reporte['entregas'] }}</td>
                <td><span style="font-size:var(--text-xs);color:var(--color-muted-foreground)">{{ fecha_mx_texto($reporte['actualizado']) }}</span></td>
                <td>
                    <div style="display:flex; gap:4px;">
                        <a href="{{ $reporte['show_url'] }}" class="btn--icon" title="Abrir reporte">
                            <i class="fa-solid fa-arrow-up-right-from-square"></i>
                        </a>
                        <button type="button" class="btn--icon" title="Eliminar" style="color:var(--text-danger);" data-delete-reporte="{{ $reporte['id'] }}">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        @endforeach
    </x-data-table>
    <p class="table__empty" id="reporteNoResults" hidden>No se encontraron reportes con esos filtros.</p>

    {{-- ---------- Crear reporte: cliente y área son inmutables, deciden la plantilla de secciones ---------- --}}
    <x-modal id="reporteModal">
        <x-slot:header><h2 style="margin-bottom:0;">Reporte personalizado</h2></x-slot:header>
        <form id="reporteForm" novalidate data-store-action="{{ route('admin.reportes.store') }}">
            @csrf
            <div class="form-grid form-grid--2">
                <div class="field">
                    <label class="field__label" for="rf_cliente_id">Cliente</label>
                    <select class="select" name="cliente_id" id="rf_cliente_id" required>
                        <option value="">— Selecciona un cliente —</option>
                        @foreach ($clientes as $cliente)
                            <option value="{{ $cliente->id }}" @selected($cliente->id === $clienteActivo?->id)>{{ $cliente->nombre_comercial }}</option>
                        @endforeach
                    </select>
                    <span class="field__error" data-error-for="cliente_id"></span>
                </div>
                <div class="field">
                    <label class="field__label" for="rf_area">Área</label>
                    <select class="select" name="area" id="rf_area" required>
                        <option value="">— Selecciona un área —</option>
                        @foreach (\App\Enums\AreaReporte::cases() as $area)
                            <option value="{{ $area->value }}">{{ $area->label() }}</option>
                        @endforeach
                    </select>
                    <span class="field__error" data-error-for="area"></span>
                </div>
            </div>

            <div class="field" style="margin-top: var(--space-4);">
                <label class="field__label" for="rf_titulo">Título</label>
                <input class="input" type="text" name="titulo" id="rf_titulo" required placeholder="Reporte mensual de posicionamiento">
                <span class="field__error" data-error-for="titulo"></span>
            </div>

            <div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
                <div class="field">
                    <label class="field__label" for="rf_periodo_inicio">Periodo — inicio</label>
                    <input class="input" type="date" name="periodo_inicio" id="rf_periodo_inicio" required>
                    <span class="field__error" data-error-for="periodo_inicio"></span>
                </div>
                <div class="field">
                    <label class="field__label" for="rf_periodo_fin">Periodo — fin</label>
                    <input class="input" type="date" name="periodo_fin" id="rf_periodo_fin" required>
                    <span class="field__error" data-error-for="periodo_fin"></span>
                </div>
            </div>

            <div class="field" style="margin-top: var(--space-4);">
                <label class="field__label" for="rf_notas_alcance">Notas de alcance</label>
                <textarea class="textarea" name="notas_alcance" id="rf_notas_alcance" placeholder="Qué cubre el reporte, qué fuentes se usaron, qué queda fuera."></textarea>
                <span class="field__error" data-error-for="notas_alcance"></span>
            </div>

            <div class="form-actions">
                <button type="button" class="btn btn--secondary" data-modal-close="reporteModal">Cancelar</button>
                <button type="submit" class="btn btn--primary">Crear reporte</button>
            </div>
        </form>
    </x-modal>

    {{-- ---------- Generar reporte del mes: 2 pasos (plantilla, periodo) ---------- --}}
    <x-modal id="mesModal">
        <x-slot:header><h2 style="margin-bottom:0;">Generar reporte del mes</h2></x-slot:header>
        <form id="mesForm" novalidate data-store-action="{{ route('admin.reportes.generar-mes') }}">
            @csrf
            <input type="hidden" name="area" value="">

            <div data-mes-paso="1">
                <p class="rep-mes__ayuda">Paso 1 de 2 · Elige la plantilla. El título, el folio y los indicadores se llenan solos con lo que ya está capturado; lo que falte queda marcado «sin dato», nunca en cero.</p>
                <div class="field">
                    <label class="field__label" for="mf_cliente_id">Cliente</label>
                    <select class="select" name="cliente_id" id="mf_cliente_id" required>
                        <option value="">— Selecciona un cliente —</option>
                        @foreach ($clientes as $cliente)
                            <option value="{{ $cliente->id }}" @selected($cliente->id === $clienteActivo?->id)>{{ $cliente->nombre_comercial }}</option>
                        @endforeach
                    </select>
                    <span class="field__error" data-error-for="cliente_id"></span>
                </div>

                <div class="rep-mes__plantillas" role="group" aria-label="Plantilla">
                    @foreach (\App\Enums\AreaReporte::cases() as $area)
                        <button type="button" class="rep-mes__plantilla" data-mes-area="{{ $area->value }}">
                            <strong>{{ $area->label() }}</strong>
                            <span>{{ match ($area->value) { 'seo' => 'Tráfico, keywords y Search Console', 'ads' => 'Inversión, conversiones y CPA', default => 'Métricas del sitio contra su meta' } }}</span>
                        </button>
                    @endforeach
                </div>
                <span class="field__error" data-error-for="area"></span>
            </div>

            <div data-mes-paso="2" hidden>
                <p class="rep-mes__ayuda">Paso 2 de 2 · <strong data-mes-resumen-plantilla></strong></p>
                <div class="field">
                    <label class="field__label" for="mf_mes">Mes del reporte</label>
                    <input class="input" type="month" name="mes" id="mf_mes" value="{{ $mesSugerido }}" max="{{ $mesActual }}" required>
                    <span class="field__hint">Un mes calendario completo; se compara contra el mes anterior.</span>
                    <span class="field__error" data-error-for="mes"></span>
                </div>

                <details class="rep-mes__avanzadas">
                    <summary>Añadir secciones avanzadas</summary>
                    @foreach ($avanzadasPorArea as $area => $secciones)
                        <div data-mes-avanzadas="{{ $area }}" hidden>
                            @forelse ($secciones as $clave => $titulo)
                                <label class="rep-row__check"><input type="checkbox" name="avanzadas[]" value="{{ $clave }}" disabled><span>{{ $titulo }}</span></label>
                            @empty
                                <p class="field__hint">Esta plantilla no tiene secciones avanzadas.</p>
                            @endforelse
                        </div>
                    @endforeach
                    <p class="field__hint">Se crean vacías, listas para pegar datos.</p>
                </details>
                <span class="field__error" data-error-for="avanzadas"></span>
                <p class="field__error" data-mes-duplicado hidden></p>
            </div>

            <div class="form-actions">
                <button type="button" class="btn btn--secondary" data-modal-close="mesModal">Cancelar</button>
                <button type="button" class="btn btn--secondary" data-mes-atras hidden>Atrás</button>
                <button type="submit" class="btn btn--primary" data-mes-generar hidden>Generar reporte</button>
            </div>
        </form>
    </x-modal>
@endsection

@section('scripts')
    @vite('resources/js/reportes.js')
@endsection
