@extends('layouts.admin')

@section('styles')
    @vite('resources/css/admin/seo.css')
@endsection

@section('content')
<div class="seo-page">
    <div class="seo-encabezado">
        <div>
            <h1 class="page-header__title">Módulo SEO</h1>
            <p class="page-header__subtitle">Posicionamiento orgánico y optimización técnica · {{ $clientesConSeo }} cuenta{{ $clientesConSeo === 1 ? '' : 's' }} con SEO</p>
        </div>
        <div class="seo-encabezado__acciones">
            <label class="seo-buscador">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <input type="search" id="seoClienteSearch" placeholder="Buscar cliente…" aria-label="Buscar cliente">
            </label>
            <button type="button" class="btn btn--primary" data-open-seo-modal>
                <i class="fa-solid fa-plus"></i> Nueva Campaña SEO
            </button>
        </div>
    </div>

    @if (session('status'))
        <div class="form-status"><i class="fa-solid fa-circle-check" style="margin-top:2px"></i><span>{{ session('status') }}</span></div>
    @endif

    <div class="empty-state" data-seo-cliente-empty {{ $clientes->isEmpty() ? '' : 'hidden' }}>
        <div class="empty-state__icon"><i class="fa-solid fa-magnifying-glass"></i></div>
        <p class="empty-state__text">No se encontraron clientes con servicio SEO.</p>
    </div>

    @include('admin.seo._cuentas', ['cuentas' => $clientes, 'editable' => true])

    {{-- ---------- Resumen general de todas las cuentas ---------- --}}
    <h2 class="seo-seccion-titulo">Resumen general</h2>
    <div class="seo-kpis">
        <x-stat-card label="Clientes con SEO" value="{{ $clientesConSeo }}" icon="fa-users" color="primary" />
        <x-stat-card label="Campañas Activas" value="{{ $campanasActivas }}" icon="fa-bullhorn" color="emerald" />
        <x-stat-card label="SEO Score Promedio" value="{{ $scorePromedio !== null ? $scorePromedio.'/100' : '—' }}" :sub="$scorePromedio !== null ? 'promedio de campañas con score' : 'Sin dato todavía'" icon="fa-gauge-high" color="amber" />
        <x-stat-card label="Tráfico Orgánico Total" value="{{ number_format($traficoTotal) }}" :sub="$traficoTotal > 0 ? 'suma del último reporte de cada campaña' : 'Sin dato todavía'" icon="fa-arrow-trend-up" color="teal" />
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
</div>
@endsection

@section('scripts')
    @vite('resources/js/seo.js')
@endsection
