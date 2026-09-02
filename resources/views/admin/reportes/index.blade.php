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
        <button type="button" class="btn btn--primary" data-open-reporte-modal>
            <i class="fa-solid fa-plus"></i> Nuevo Reporte
        </button>
    </div>

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
        <p class="empty-state__text">Aún no hay reportes. Crea el primero y su plantilla de secciones se genera sola.</p>
        <button type="button" class="btn btn--primary" data-open-reporte-modal>Crear el primer reporte</button>
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
                <td><span class="u-mono" style="font-size:var(--text-xs)">{{ $reporte['periodo'] }}</span></td>
                <td><x-badge :status="$reporte['estado']" /></td>
                <td class="u-mono">{{ $reporte['entregas'] }}</td>
                <td><span style="font-size:var(--text-xs);color:var(--color-muted-foreground)">{{ $reporte['actualizado'] }}</span></td>
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
        <x-slot:header><h2 style="margin-bottom:0;">Nuevo Reporte</h2></x-slot:header>
        <form id="reporteForm" novalidate data-store-action="{{ route('admin.reportes.store') }}">
            @csrf
            <div class="form-grid form-grid--2">
                <div class="field">
                    <label class="field__label" for="rf_cliente_id">Cliente</label>
                    <select class="select" name="cliente_id" id="rf_cliente_id" required>
                        <option value="">— Selecciona un cliente —</option>
                        @foreach ($clientes as $cliente)
                            <option value="{{ $cliente->id }}">{{ $cliente->empresa ?: $cliente->nombre }}</option>
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
@endsection

@section('scripts')
    @vite('resources/js/reportes.js')
@endsection
