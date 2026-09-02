@extends('layouts.admin')

@section('styles')
    @vite('resources/css/admin/seo.css')
@endsection

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Módulo SEO</h1>
            <p class="page-header__subtitle">{{ $clientesConSeo }} cliente{{ $clientesConSeo === 1 ? '' : 's' }} con SEO · {{ $campanasActivas }} campaña{{ $campanasActivas === 1 ? '' : 's' }} activa{{ $campanasActivas === 1 ? '' : 's' }}</p>
        </div>
        <button type="button" class="btn btn--primary" data-open-seo-modal>
            <i class="fa-solid fa-plus"></i> Nueva Campaña SEO
        </button>
    </div>

    @if (session('status'))
        <div class="form-status"><i class="fa-solid fa-circle-check" style="margin-top:2px"></i><span>{{ session('status') }}</span></div>
    @endif

    {{-- ---------- KPIs ---------- --}}
    <div class="kpi-grid">
        <x-stat-card label="Clientes con SEO" value="{{ $clientesConSeo }}" icon="fa-users" color="primary" />
        <x-stat-card label="Campañas Activas" value="{{ $campanasActivas }}" icon="fa-bullhorn" color="emerald" />
        <x-stat-card label="SEO Score Promedio" value="{{ $scorePromedio !== null ? $scorePromedio.'/100' : '—' }}" :sub="$scorePromedio !== null ? 'promedio de campañas activas' : null" icon="fa-gauge-high" color="amber" />
        <x-stat-card label="Tráfico Orgánico Total" value="{{ number_format($traficoTotal) }}" icon="fa-arrow-trend-up" color="teal" />
    </div>

    {{-- ---------- Client picker: searchable grid, one card per client with an SEO servicio ---------- --}}
    <input type="search" class="input input--search" id="seoClienteSearch" placeholder="Buscar cliente...">

    <div class="empty-state" data-seo-cliente-empty {{ $clientes->isEmpty() ? '' : 'hidden' }}>
        <div class="empty-state__icon"><i class="fa-solid fa-magnifying-glass"></i></div>
        <p class="empty-state__text">No se encontraron clientes con servicio SEO.</p>
    </div>

    <div class="seo-cliente-grid" data-seo-cliente-grid>
        @foreach ($clientes as $c)
            <div class="seo-cliente-card" data-seo-cliente-card data-cliente-id="{{ $c['cliente_id'] }}" data-search="{{ mb_strtolower($c['cliente'].' '.($c['contacto'] ?? '')) }}" data-cliente="{{ json_encode($c) }}">
                @if ($c['campana_id'])
                    <a href="{{ $c['show_url'] }}" class="seo-cliente-card__link">
                        <div class="seo-cliente-card__header">
                            <span class="seo-cliente-card__avatar">{{ \App\Support\Labels::initials($c['cliente']) }}</span>
                            <span class="seo-cliente-card__id">
                                <span class="seo-cliente-card__name">{{ $c['cliente'] }}</span>
                                @if ($c['contacto'])
                                    <span class="seo-cliente-card__contacto">{{ $c['contacto'] }}</span>
                                @endif
                            </span>
                            <x-badge :status="$c['estado']" />
                        </div>
                        <div class="seo-cliente-card__body">
                            <div class="seo-cliente-card__stat">
                                <span class="seo-cliente-card__stat-label">Fase</span>
                                <span class="seo-cliente-card__stat-value">{{ \App\Support\Labels::faseSeo($c['fase_actual']) }}</span>
                            </div>
                            <div class="seo-cliente-card__stat">
                                <span class="seo-cliente-card__stat-label">SEO Score</span>
                                <span class="seo-cliente-card__stat-value u-mono">{{ $c['seo_score'] ?? '—' }}/100</span>
                            </div>
                            <div class="seo-cliente-card__stat">
                                <span class="seo-cliente-card__stat-label">Tráfico orgánico</span>
                                <span class="seo-cliente-card__stat-value u-mono">{{ $c['trafico_actual'] !== null ? number_format($c['trafico_actual']) : '—' }}</span>
                            </div>
                            <div class="seo-cliente-card__stat">
                                <span class="seo-cliente-card__stat-label">MRR</span>
                                <span class="seo-cliente-card__stat-value u-mono">${{ number_format($c['mrr']) }}</span>
                            </div>
                        </div>
                    </a>
                    <div class="seo-cliente-card__footer">
                        <button type="button" class="btn--icon" title="Editar" data-edit-seo-cliente><i class="fa-solid fa-pen"></i></button>
                        <button type="button" class="btn--icon" title="Eliminar" style="color:var(--text-danger);" data-delete-seo-cliente><i class="fa-solid fa-trash"></i></button>
                    </div>
                @else
                    <div class="seo-cliente-card__header">
                        <span class="seo-cliente-card__avatar">{{ \App\Support\Labels::initials($c['cliente']) }}</span>
                        <span class="seo-cliente-card__id">
                            <span class="seo-cliente-card__name">{{ $c['cliente'] }}</span>
                            @if ($c['contacto'])
                                <span class="seo-cliente-card__contacto">{{ $c['contacto'] }}</span>
                            @endif
                        </span>
                        <span class="seo-cliente-card__sin-campana">Sin campaña</span>
                    </div>
                    <div class="seo-cliente-card__body seo-cliente-card__body--empty">
                        <p class="seo-cliente-card__empty-text">Este cliente aún no tiene una campaña SEO.</p>
                        <button type="button" class="btn btn--secondary btn--sm" data-open-seo-modal data-preselect-cliente="{{ $c['cliente_id'] }}">
                            <i class="fa-solid fa-plus"></i> Crear campaña
                        </button>
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    {{-- ---------- Create/edit modal — minimal general-data fields only; phase-specific data entry stays on show.blade.php ---------- --}}
    <x-modal id="seoCampanaFormModal">
        <x-slot:header><h2 id="seoCampanaFormModalTitle" style="margin-bottom:0;">Nueva Campaña SEO</h2></x-slot:header>
        <form id="seoCampanaForm" novalidate
              data-store-action="{{ route('admin.seo.store') }}"
              data-update-action-template="{{ route('admin.seo.update', ['campana' => '__ID__']) }}">
            @csrf
            <div class="form-grid form-grid--2">
                <div class="field">
                    <label class="field__label" for="cliente_id">Cliente</label>
                    <select class="select" name="cliente_id" id="cliente_id" required>
                        <option value="">— Selecciona un cliente —</option>
                        @foreach ($clientes as $c)
                            <option value="{{ $c['cliente_id'] }}">{{ $c['cliente'] }}</option>
                        @endforeach
                    </select>
                    <span class="field__error" data-error-for="cliente_id"></span>
                </div>
                <div class="field">
                    <label class="field__label" for="servicio_id">Servicio</label>
                    <select class="select" name="servicio_id" id="servicio_id" required>
                        <option value="">— Selecciona un cliente primero —</option>
                        @foreach ($clientes as $c)
                            @foreach ($c['servicios_seo'] as $servicio)
                                <option value="{{ $servicio['id'] }}" data-cliente="{{ $c['cliente_id'] }}">{{ $servicio['nombre'] }}</option>
                            @endforeach
                        @endforeach
                    </select>
                    <span class="field__error" data-error-for="servicio_id"></span>
                </div>
            </div>

            <div class="field" style="margin-top: var(--space-4);">
                <label class="field__label" for="sf_nombre">Nombre de la campaña</label>
                <input class="input" type="text" name="nombre" id="sf_nombre" required>
                <span class="field__error" data-error-for="nombre"></span>
            </div>

            <div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
                <div class="field">
                    <label class="field__label" for="sf_url_sitio">URL del sitio</label>
                    <input class="input" type="text" name="url_sitio" id="sf_url_sitio" placeholder="https://ejemplo.com">
                    <span class="field__error" data-error-for="url_sitio"></span>
                </div>
                <div class="field">
                    <label class="field__label" for="sf_fecha_inicio">Fecha de inicio</label>
                    <input class="input" type="date" name="fecha_inicio" id="sf_fecha_inicio">
                    <span class="field__error" data-error-for="fecha_inicio"></span>
                </div>
            </div>

            <div data-edit-only>
                <div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
                    <div class="field">
                        <label class="field__label" for="sf_estado">Estado</label>
                        <select class="select" name="estado" id="sf_estado" required>
                            @foreach (['activa' => 'Activa', 'pausada' => 'Pausada', 'finalizada' => 'Finalizada'] as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <span class="field__error" data-error-for="estado"></span>
                    </div>
                </div>
                <div class="field" style="margin-top: var(--space-4);">
                    <label class="field__label" for="sf_notas">Notas</label>
                    <textarea class="textarea" name="notas" id="sf_notas"></textarea>
                    <span class="field__error" data-error-for="notas"></span>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn--primary" id="seoCampanaFormSubmit"><i class="fa-solid fa-check"></i> Crear Campaña</button>
                <button type="button" class="btn btn--secondary" data-modal-close="seoCampanaFormModal">Cancelar</button>
            </div>
        </form>
    </x-modal>
@endsection

@section('scripts')
    @vite('resources/js/seo.js')
@endsection
