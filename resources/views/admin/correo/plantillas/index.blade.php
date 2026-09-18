@extends('layouts.admin')

@section('styles')
    @vite('resources/css/admin/correo-plantillas.css')
@endsection

@section('content')
    <div data-correo-plantillas-index
         data-destroy-url="{{ route('admin.correo.plantillas.destroy', ['plantilla' => '__ID__']) }}"
         data-duplicar-url="{{ route('admin.correo.plantillas.duplicar', ['plantilla' => '__ID__']) }}">

        <div class="page-header">
            <div>
                <h1 class="page-header__title">Plantillas de correo</h1>
                <p class="page-header__subtitle">Correo · {{ $kpis['total'] }} {{ $kpis['total'] === 1 ? 'plantilla' : 'plantillas' }} · editor por bloques con vista previa en vivo</p>
            </div>
            <button type="button" class="btn btn--primary" data-open-plantilla-modal>
                <i class="fa-solid fa-plus"></i> Nueva plantilla
            </button>
        </div>

        {{-- ---------- KPIs (globales, no cambian con los filtros) ---------- --}}
        <div class="kpi-grid">
            <x-stat-card label="Plantillas activas" value="{{ $kpis['activas'] }}" sub="{{ $kpis['total'] - $kpis['activas'] }} archivadas" icon="fa-envelope-open-text" color="primary" />
            <x-stat-card label="Envíos del mes" value="{{ $kpis['envios_mes'] }}" sub="{{ now()->translatedFormat('F Y') }}" icon="fa-paper-plane" color="blue" />
            <x-stat-card label="Apertura estimada" value="{{ $kpis['apertura'] !== null ? $kpis['apertura'].'%' : '—' }}" sub="{{ $kpis['destinatarios'] > 0 ? $kpis['abiertos'].' de '.$kpis['destinatarios'].' destinatarios' : 'sin envíos todavía' }}" icon="fa-eye" color="teal" />
        </div>

        {{-- ---------- Filtros (GET: el servidor filtra) ---------- --}}
        <form method="GET" action="{{ route('admin.correo.plantillas.index') }}" class="card card--padded cp-filtros" data-plantillas-filtros>
            <input type="search" class="input input--search" name="search" value="{{ $filtros['search'] }}" placeholder="Buscar por nombre o asunto…" data-plantillas-search>
            <div class="cp-chips" role="group" aria-label="Categoría">
                <a href="{{ route('admin.correo.plantillas.index', array_filter(['estado' => $filtros['estado'], 'search' => $filtros['search']])) }}"
                   class="cp-chip {{ $filtros['categoria'] === '' ? 'is-active' : '' }}">Todas</a>
                @foreach ($categorias as $categoria)
                    <a href="{{ route('admin.correo.plantillas.index', array_filter(['categoria' => $categoria->value, 'estado' => $filtros['estado'], 'search' => $filtros['search']])) }}"
                       class="cp-chip {{ $filtros['categoria'] === $categoria->value ? 'is-active' : '' }}">{{ $categoria->label() }}</a>
                @endforeach
            </div>
            <input type="hidden" name="categoria" value="{{ $filtros['categoria'] }}">
            <select class="select" name="estado" data-plantillas-estado>
                <option value="">Todos los estados</option>
                @foreach ($estados as $estado)
                    <option value="{{ $estado }}" @selected($filtros['estado'] === $estado)>{{ ucfirst($estado) }}</option>
                @endforeach
            </select>
        </form>

        @php $hayFiltros = $filtros['categoria'] !== '' || $filtros['estado'] !== '' || $filtros['search'] !== ''; @endphp

        <div class="card empty-state" data-plantillas-empty {{ $plantillas->isEmpty() ? '' : 'hidden' }}>
            <div class="empty-state__icon"><i class="fa-solid fa-envelope-open-text"></i></div>
            @if ($hayFiltros)
                <p class="empty-state__text">No hay plantillas que coincidan con esos filtros.</p>
                <a href="{{ route('admin.correo.plantillas.index') }}" class="btn btn--secondary">Quitar filtros</a>
            @else
                <p class="empty-state__text">Aún no hay plantillas. Crea la primera y arma el correo por bloques: cabecera con tu marca, texto, cifras, botón y firma.</p>
                <button type="button" class="btn btn--primary" data-open-plantilla-modal>Crear la primera plantilla</button>
            @endif
        </div>

        <x-data-table :headers="['Nombre', 'Categoría', 'Usos', 'Apertura (estimada)', 'Autor', 'Actualizada', '']"
                      data-paginate="15" data-plantillas-tabla :hidden="$plantillas->isEmpty()">
            @foreach ($plantillas as $p)
                <tr data-plantilla-row="{{ $p['id'] }}">
                    <td>
                        <div class="cp-nombre">
                            <span class="cp-nombre__icono"><i class="fa-solid {{ $p['html_libre'] ? 'fa-code' : 'fa-table-cells-large' }}"></i></span>
                            <div class="cp-nombre__texto">
                                <a href="{{ $p['show_url'] }}" class="cp-nombre__titulo">{{ $p['nombre'] }}</a>
                                <div class="cp-nombre__asunto">{{ $p['asunto'] }}</div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="cp-celda-estado">
                            <span class="cp-categoria">{{ $p['categoria_label'] }}</span>
                            @if ($p['estado'] !== 'activa')
                                <x-badge :status="$p['estado']" />
                            @endif
                        </div>
                    </td>
                    <td class="u-mono">{{ $p['usos'] }}</td>
                    <td class="u-mono">
                        @if ($p['apertura'] !== null)
                            <span class="{{ $p['apertura'] >= 50 ? 'cp-apertura--alta' : 'cp-apertura--baja' }}">{{ $p['apertura'] }}%</span>
                        @else
                            <span class="cp-muted">—</span>
                        @endif
                    </td>
                    <td><span class="cp-muted">{{ $p['autor'] ?? '—' }}</span></td>
                    <td><span class="u-mono cp-muted cp-fecha">{{ $p['actualizada'] ?? '—' }}</span></td>
                    <td>
                        <div class="cp-acciones">
                            <a href="{{ $p['show_url'] }}" class="btn--icon" title="Abrir el editor"><i class="fa-solid fa-pen"></i></a>
                            <button type="button" class="btn--icon" title="Duplicar" data-duplicar-plantilla="{{ $p['id'] }}"><i class="fa-solid fa-copy"></i></button>
                            <button type="button" class="btn--icon cp-accion-danger" title="Eliminar" data-delete-plantilla="{{ $p['id'] }}" data-nombre="{{ $p['nombre'] }}"><i class="fa-solid fa-trash"></i></button>
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-data-table>

        {{-- ---------- Nueva plantilla: nace con los bloques por defecto y abre el editor ---------- --}}
        <x-modal id="plantillaModal">
            <x-slot:header><h2 style="margin-bottom:0;">Nueva plantilla</h2></x-slot:header>
            <form id="plantillaForm" novalidate data-store-action="{{ route('admin.correo.plantillas.store') }}">
                @csrf
                <p class="field__hint" style="margin-bottom: var(--space-4);">
                    Se creará con una cabecera, un párrafo y la firma de RankPro, y se abrirá el editor.
                </p>
                <div class="field">
                    <label class="field__label" for="pf_nombre">Nombre <span style="color:var(--text-danger)">*</span></label>
                    <input class="input" type="text" name="nombre" id="pf_nombre" placeholder="Reporte mensual de resultados" required>
                    <span class="field__error" data-error-for="nombre"></span>
                </div>
                <div class="field" style="margin-top: var(--space-4);">
                    <label class="field__label" for="pf_categoria">Categoría</label>
                    <select class="select" name="categoria" id="pf_categoria">
                        @foreach ($categorias as $categoria)
                            <option value="{{ $categoria->value }}">{{ $categoria->label() }}</option>
                        @endforeach
                    </select>
                    <span class="field__error" data-error-for="categoria"></span>
                </div>
                <div class="field" style="margin-top: var(--space-4);">
                    <label class="field__label" for="pf_asunto">Asunto <span class="cp-muted" style="font-weight:500;">(opcional, si falta se usa el nombre)</span></label>
                    <input class="input" type="text" name="asunto" id="pf_asunto" placeholder="@{{cliente}} · Resultados de @{{mes}}">
                    <span class="field__error" data-error-for="asunto"></span>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn--secondary" data-modal-close="plantillaModal">Cancelar</button>
                    <button type="submit" class="btn btn--primary" data-plantilla-submit>Crear y editar</button>
                </div>
            </form>
        </x-modal>
    </div>
@endsection

@section('scripts')
    @vite('resources/js/correo-plantillas.js')
@endsection
