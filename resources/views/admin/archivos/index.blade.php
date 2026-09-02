@extends('layouts.admin')

@section('styles')
    @vite('resources/css/admin/archivos.css')
@endsection

@section('content')
    @php
        // Extension -> accent color, mirrors the old $typeColors map but
        // extended since uploads can now be any of the mimes() the server
        // validates (see ArchivosController::store()'s 'archivo' rule).
        $typeColors = [
            'pdf' => '#EF4444',
            'zip' => '#F59E0B', 'rar' => '#F59E0B',
            'xlsx' => '#10B981', 'xls' => '#10B981', 'csv' => '#10B981',
            'png' => '#0F9D6E', 'jpg' => '#0F9D6E', 'jpeg' => '#0F9D6E', 'gif' => '#0F9D6E', 'svg' => '#0F9D6E', 'fig' => '#0F9D6E',
        ];
    @endphp

    <div class="page-header">
        <div>
            <h1 class="page-header__title">Archivos y Documentos</h1>
            <p class="page-header__subtitle">Repositorio organizado por cliente</p>
        </div>
        <div style="display:flex; gap: var(--space-2); flex-wrap:wrap;">
            <a href="{{ route('admin.archivos.contratos.create') }}" class="btn btn--secondary">
                <i class="fa-solid fa-file-signature"></i> Generar Contrato
            </a>
            <a href="{{ route('admin.archivos.propuestas.create') }}" class="btn btn--primary">
                <i class="fa-solid fa-file-invoice"></i> Generar Propuesta
            </a>
            <button type="button" class="btn btn--primary" data-open-archivo-modal>
                <i class="fa-solid fa-upload"></i> Subir Archivo
            </button>
        </div>
    </div>

    @if (session('status'))
        <div class="form-status"><i class="fa-solid fa-circle-check" style="margin-top:2px"></i><span>{{ session('status') }}</span></div>
    @endif
    @error('archivo')
        <div class="form-status form-status--error"><i class="fa-solid fa-circle-exclamation" style="margin-top:2px"></i><span>{{ $message }}</span></div>
    @enderror

    {{-- ---------- Client picker: searchable horizontal row, one card per client (all clients, even with 0 files) ---------- --}}
    <input type="search" class="input input--search" id="archivoClienteSearch" placeholder="Buscar cliente..." style="margin-bottom: var(--space-3); width:100%;">

    <div class="empty-state" data-archivo-cliente-empty {{ $clientes->isEmpty() ? '' : 'hidden' }}>
        <div class="empty-state__icon"><i class="fa-solid fa-magnifying-glass"></i></div>
        <p class="empty-state__text">No se encontraron clientes.</p>
    </div>

    <div class="archivo-cliente-row" data-archivo-cliente-row>
        @foreach ($clientes as $c)
            <a href="{{ route('admin.archivos.index', ['cliente' => $c['cliente_id']]) }}"
               class="archivo-cliente-card {{ $c['cliente_id'] === $clienteSeleccionado ? 'is-active' : '' }}"
               data-archivo-cliente-card
               data-search="{{ mb_strtolower($c['cliente']) }}">
                <span class="archivo-cliente-card__avatar">{{ \App\Support\Labels::initials($c['cliente']) }}</span>
                <span class="archivo-cliente-card__body">
                    <span class="archivo-cliente-card__name">{{ $c['cliente'] }}</span>
                    <span class="archivo-cliente-card__meta">{{ $c['archivos_count'] }} archivo{{ $c['archivos_count'] === 1 ? '' : 's' }} · {{ $c['peso_mb'] }} MB</span>
                </span>
                <x-badge :status="$c['estado']" />
            </a>
        @endforeach
    </div>

    {{-- ---------- KPIs (per currently selected client) ---------- --}}
    <div class="kpi-grid">
        <x-stat-card id="archivoKpiArchivos" label="Archivos" value="{{ $archivosCount }}" sub="{{ $categorias->count() }} categorías disponibles" icon="fa-folder-open" color="primary" />
        <x-stat-card id="archivoKpiPeso" label="Peso Total" value="{{ $pesoTotalMb }} MB" sub="almacenamiento usado" icon="fa-hard-drive" color="teal" />
        <x-stat-card id="archivoKpiContratos" label="Contratos" value="{{ $contratosCount }}" sub="documentos legales" icon="fa-file-signature" color="emerald" />
        <x-stat-card id="archivoKpiUltimo" label="Último Movimiento" value="{{ $ultimoMovimiento ?? '—' }}" sub="fecha de subida" icon="fa-calendar" color="amber" />
    </div>

    @if ($archivos->isEmpty())
        <div class="card empty-state">
            <div class="empty-state__icon"><i class="fa-solid fa-folder-open"></i></div>
            <p class="empty-state__text">No hay documentos para este cliente aún.</p>
            <button type="button" class="btn btn--primary" data-open-archivo-modal>
                <i class="fa-solid fa-upload"></i> Subir primer archivo
            </button>
        </div>
    @else
        {{-- ---------- Filter bar: search + categoría + orden + list/grid toggle ---------- --}}
        <div class="filters-bar">
            <input type="search" class="input input--search" id="archivoSearch" placeholder="Buscar archivo...">
            <select class="select" id="archivoCategoriaFilter">
                <option value="">Todas las categorías</option>
                @foreach ($categorias as $cat)
                    <option value="{{ $cat['value'] }}">{{ $cat['label'] }}</option>
                @endforeach
            </select>
            <select class="select" id="archivoSort">
                <option value="date">Más recientes</option>
                <option value="name">Nombre A–Z</option>
                <option value="size">Más pesados</option>
            </select>
            <div class="archivo-view-toggle">
                <button type="button" class="archivo-view-toggle__btn is-active" data-archivo-view="list" title="Vista de lista"><i class="fa-solid fa-list"></i></button>
                <button type="button" class="archivo-view-toggle__btn" data-archivo-view="grid" title="Vista de cuadrícula"><i class="fa-solid fa-table-cells-large"></i></button>
            </div>
        </div>

        {{-- "No results" for the search/categoría filters — distinct from the "no files at all" empty-state above, always present (hidden by default) so JS can toggle it without an @if. --}}
        <div class="empty-state" data-archivo-filter-empty hidden>
            <div class="empty-state__icon"><i class="fa-solid fa-magnifying-glass"></i></div>
            <p class="empty-state__text">Ningún archivo coincide con los filtros actuales.</p>
        </div>

        {{-- ---------- List view ---------- --}}
        <div data-archivo-view-panel="list">
            <x-data-table :headers="['Archivo', 'Categoría', 'Tipo', 'Peso', 'Subido por', 'Fecha', '']" data-archivo-list-table>
                @foreach ($archivos as $a)
                    @php $color = $typeColors[$a['extension']] ?? '#64748B'; @endphp
                    <tr class="is-clickable" data-archivo-row data-archivo-id="{{ $a['id'] }}" data-tipo="{{ $a['tipo'] }}" data-search="{{ mb_strtolower($a['nombre']) }}" data-nombre="{{ $a['nombre'] }}" data-fecha="{{ $a['fecha'] }}" data-tamano="{{ $a['tamano'] ?? 0 }}" data-archivo="{{ json_encode($a) }}">
                        <td>
                            <div style="display:flex; align-items:center; gap:10px; min-width:0;">
                                <i class="fa-solid fa-file" style="color:{{ $color }}; flex-shrink:0;"></i>
                                <span style="font-weight:500; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ $a['nombre'] }}</span>
                            </div>
                        </td>
                        <td>{{ $a['tipo_label'] }}</td>
                        <td><span class="archivo-ext-pill" style="--pill-color:{{ $color }}">{{ $a['extension'] ?? '—' }}</span></td>
                        <td class="u-mono">{{ $a['tamano_label'] }}</td>
                        <td>{{ $a['subido_por'] ?? '—' }}</td>
                        <td class="u-mono" style="font-size:var(--text-xs); color:var(--color-muted-foreground);">{{ $a['fecha'] }}</td>
                        <td>
                            <div style="display:flex; gap:4px;">
                                <a href="{{ $a['download_url'] }}" class="btn--icon" title="Descargar"><i class="fa-solid fa-download"></i></a>
                                <button type="button" class="btn--icon" title="Eliminar" style="color:var(--text-danger);" data-delete-archivo="{{ $a['id'] }}"><i class="fa-solid fa-trash"></i></button>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-data-table>
        </div>

        {{-- ---------- Grid view: grouped by categoría ---------- --}}
        <div data-archivo-view-panel="grid" hidden>
            <div data-archivo-category-wrap style="display:flex; flex-direction:column; gap: var(--space-6);">
                @foreach ($categorias as $cat)
                    @php $grupo = $archivos->where('tipo', $cat['value']); @endphp
                    @if ($grupo->isNotEmpty())
                        <div class="archivo-category" data-archivo-category-section data-tipo="{{ $cat['value'] }}">
                            <h2 class="archivos-category__title"><i class="fa-solid fa-folder-open"></i> <span data-archivo-category-heading>{{ $cat['label'] }} ({{ $grupo->count() }})</span></h2>
                            <div class="archivos-grid">
                                @foreach ($grupo as $a)
                                    @php $color = $typeColors[$a['extension']] ?? '#64748B'; @endphp
                                    <div class="card archivos-file" data-archivo-row data-archivo-id="{{ $a['id'] }}" data-tipo="{{ $a['tipo'] }}" data-search="{{ mb_strtolower($a['nombre']) }}" data-nombre="{{ $a['nombre'] }}" data-fecha="{{ $a['fecha'] }}" data-tamano="{{ $a['tamano'] ?? 0 }}" data-archivo="{{ json_encode($a) }}">
                                        <span class="archivos-file__icon" style="background:{{ $color }}18; border-color:{{ $color }}35;">
                                            <i class="fa-solid fa-file" style="color:{{ $color }}"></i>
                                        </span>
                                        <div style="min-width:0; flex:1;">
                                            <div class="archivos-file__name">{{ $a['nombre'] }}</div>
                                            <div class="archivos-file__meta">{{ $a['tamano_label'] }} · {{ $a['fecha'] }} · {{ $a['subido_por'] ?? '—' }}</div>
                                        </div>
                                        <div style="display:flex; gap:4px; flex-shrink:0;">
                                            <a href="{{ $a['download_url'] }}" class="btn--icon" title="Descargar"><i class="fa-solid fa-download"></i></a>
                                            <button type="button" class="btn--icon" title="Eliminar" style="color:var(--text-danger);" data-delete-archivo="{{ $a['id'] }}"><i class="fa-solid fa-trash"></i></button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    @endif

    {{-- ---------- Read-only detail modal ---------- --}}
    <x-modal id="archivoDetailModal">
        <x-slot:header><h2 id="archivoDetailModalTitle" style="margin-bottom:0;">Archivo</h2></x-slot:header>
        <div class="form-grid form-grid--2">
            <div class="field">
                <span class="field__label">Categoría</span>
                <span id="archivoDetailCategoria" style="font-size: var(--text-sm);">—</span>
            </div>
            <div class="field">
                <span class="field__label">Tipo (extensión)</span>
                <span id="archivoDetailTipo" style="font-size: var(--text-sm); text-transform:uppercase;">—</span>
            </div>
            <div class="field">
                <span class="field__label">Peso</span>
                <span id="archivoDetailPeso" style="font-size: var(--text-sm);">—</span>
            </div>
            <div class="field">
                <span class="field__label">Subido por</span>
                <span id="archivoDetailSubidoPor" style="font-size: var(--text-sm);">—</span>
            </div>
            <div class="field">
                <span class="field__label">Fecha</span>
                <span id="archivoDetailFecha" style="font-size: var(--text-sm);">—</span>
            </div>
        </div>
        <div class="form-actions">
            <a href="#" id="archivoDetailDownload" class="btn btn--primary"><i class="fa-solid fa-download"></i> Descargar</a>
            <button type="button" class="btn btn--secondary" id="archivoDetailDelete" style="color:var(--text-danger);"><i class="fa-solid fa-trash"></i> Eliminar</button>
        </div>
    </x-modal>

    {{-- ---------- Upload modal — uploads always go to the currently-viewed client ---------- --}}
    <x-modal id="archivoUploadModal">
        <x-slot:header><h2 style="margin-bottom:0;">Subir Archivo</h2></x-slot:header>
        <form id="archivoUploadForm" novalidate enctype="multipart/form-data" data-store-action="{{ route('admin.archivos.store') }}">
            @csrf
            <input type="hidden" name="cliente_id" value="{{ $clienteSeleccionado }}">

            <div class="field">
                <label class="field__label" for="af_tipo">Categoría</label>
                <select class="select" name="tipo" id="af_tipo" required>
                    @foreach ($categorias as $cat)
                        <option value="{{ $cat['value'] }}">{{ $cat['label'] }}</option>
                    @endforeach
                </select>
                <span class="field__error" data-error-for="tipo"></span>
            </div>

            <div class="field" style="margin-top: var(--space-4);">
                <label class="field__label" for="af_nombre">Nombre (opcional)</label>
                <input class="input" type="text" name="nombre" id="af_nombre" placeholder="Se usará el nombre del archivo si se deja vacío">
                <span class="field__error" data-error-for="nombre"></span>
            </div>

            <div class="field" style="margin-top: var(--space-4);">
                <label class="field__label" for="af_archivo">Archivo</label>
                <input type="file" name="archivo" id="af_archivo" required class="input">
                <span class="field__error" data-error-for="archivo"></span>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn--primary"><i class="fa-solid fa-upload"></i> Subir Archivo</button>
                <button type="button" class="btn btn--secondary" data-modal-close="archivoUploadModal">Cancelar</button>
            </div>
        </form>
    </x-modal>
@endsection

@section('scripts')
    @vite('resources/js/archivos.js')
@endsection
