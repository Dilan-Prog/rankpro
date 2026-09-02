@extends('layouts.admin')

@section('styles')
    @vite('resources/css/admin/ads.css')
@endsection

@section('content')
    @php
        // Single source of truth for platform label/color, shared by the tabs,
        // the table's plataforma pill, and the "Reparto por plataforma" bars.
        // ads.js mirrors this exact map (PLATAFORMA_META) so JS-built rows and
        // cards look identical to these server-rendered ones.
        $plataformas = ['google_ads' => ['Google Ads', '#4285F4'], 'meta_ads' => ['Meta Ads', '#1877F2'], 'tiktok_ads' => ['TikTok Ads', '#FE2C55']];

        $countPorPlataforma = collect($plataformas)->keys()->mapWithKeys(fn ($p) => [$p => $campanas->where('plataforma', $p)->count()]);
        $clienteIds = $campanas->pluck('cliente_id')->unique();

        // Total-ratio ROAS (totalIngreso / totalInversion), never an average of
        // per-campaign roas values — matches recomputeAdsAggregates() in ads.js
        // and avoids the null-vs-zero averaging pitfall found in Keywords.
        $totalInversion = (float) $campanas->sum('inversion');
        $totalIngreso = (float) $campanas->sum('ingreso_atribuido');
        $totalConversiones = (int) $campanas->sum('conversiones');
        $totalClics = (int) $campanas->sum('clics');
        $totalImpresiones = (int) $campanas->sum('impresiones');
        $totalRoas = $totalInversion > 0 ? round($totalIngreso / $totalInversion, 1) : 0;
        $totalCpa = $totalConversiones > 0 ? round($totalInversion / $totalConversiones, 2) : 0;
        $totalCtr = $totalImpresiones > 0 ? round(($totalClics / $totalImpresiones) * 100, 2) : 0;

        // Mirrors AgencyOS.formatCompact() exactly (M above 1M, K above 1K,
        // plain number below) so the server-painted "Impr." cells match
        // whatever ads.js renders for freshly created/edited rows.
        $compact = function ($n) {
            $n = (float) $n;
            if (abs($n) >= 1000000) return rtrim(rtrim(number_format($n / 1000000, 1), '0'), '.') . 'M';
            if (abs($n) >= 1000) return rtrim(rtrim(number_format($n / 1000, 1), '0'), '.') . 'K';
            return number_format($n, 0);
        };

        $headers = ['Campaña', 'Cliente', 'Plataforma', 'Objetivo', 'Fase', 'Estado', 'Invertido', 'Impr.', 'Clics', 'Conv.', 'ROAS', ''];
    @endphp

    <div class="page-header">
        <div>
            <h1 class="page-header__title">Módulo Ads</h1>
            <p class="page-header__subtitle" id="adsCountSubtitle">{{ $campanas->count() }} campaña{{ $campanas->count() === 1 ? '' : 's' }} · {{ $clienteIds->count() }} cliente{{ $clienteIds->count() === 1 ? '' : 's' }}</p>
        </div>
        <button type="button" class="btn btn--primary" data-open-campana-modal>
            <i class="fa-solid fa-plus"></i> Nueva Campaña
        </button>
    </div>

    {{-- ---------- Account picker: "Todas las cuentas" + one card per cliente with >=1 campaign ---------- --}}
    <input type="search" class="input input--search ads-account-search" id="adsAccountSearch" placeholder="Buscar cuenta...">
    <div class="ads-account-row" id="adsAccountRow"></div>
    <div class="ads-account-empty" id="adsAccountEmpty" hidden>No se encontraron cuentas para "<span id="adsAccountEmptyQuery"></span>".</div>

    {{-- ---------- Platform tabs (client-side only — no page reload) ---------- --}}
    <div class="ads-platform-tabs" id="adsPlatformTabs">
        <button type="button" class="ads-platform-tab is-active" data-plataforma-tab="todas">
            Todas <span class="ads-platform-tab__count" data-plataforma-count="todas">{{ $campanas->count() }}</span>
        </button>
        @foreach ($plataformas as $value => [$label, $color])
            <button type="button" class="ads-platform-tab" data-plataforma-tab="{{ $value }}" style="--tab-color:{{ $color }}">
                {{ $label }} <span class="ads-platform-tab__count" data-plataforma-count="{{ $value }}">{{ $countPorPlataforma[$value] }}</span>
            </button>
        @endforeach
    </div>

    {{-- ---------- KPIs — server-rendered with full unfiltered totals as a sensible fallback; recomputeAdsAggregates() overrides these immediately on load and after every filter/CRUD change ---------- --}}
    <div class="kpi-grid">
        <x-stat-card id="kpiInversion" label="Inversión" value="${{ number_format($totalInversion) }}" icon="fa-dollar-sign" color="teal" />
        <x-stat-card id="kpiIngreso" label="Ingreso Atribuido" value="${{ number_format($totalIngreso) }}" icon="fa-sack-dollar" color="emerald" />
        <x-stat-card id="kpiRoas" label="ROAS" value="{{ number_format($totalRoas, 1) }}x" icon="fa-chart-line" color="primary" />
        <x-stat-card id="kpiConversiones" label="Conversiones" value="{{ number_format($totalConversiones) }}" sub="CPA ${{ number_format($totalCpa, 2) }}" icon="fa-circle-check" color="blue" />
        <x-stat-card id="kpiClics" label="Clics" value="{{ number_format($totalClics) }}" sub="CTR {{ number_format($totalCtr, 2) }}%" icon="fa-arrow-pointer" color="amber" />
        <x-stat-card id="kpiImpresiones" label="Impresiones" value="{{ $compact($totalImpresiones) }}" icon="fa-eye" color="red" />
    </div>

    {{-- ---------- Inversión vs Ingreso atribuido (Chart.js, one bar-pair per currently visible campaign) ---------- --}}
    <div class="card card--padded" style="margin-bottom: var(--space-6);">
        <div class="card__header-title" style="margin-bottom: var(--space-4);">Inversión vs Ingreso atribuido</div>
        <div style="height:260px;">
            <canvas id="adsChart"></canvas>
        </div>
    </div>

    {{-- ---------- Reparto por plataforma — plain CSS proportional bars, scoped to the account picker only (ignores the platform tab) ---------- --}}
    <div class="card card--padded" style="margin-bottom: var(--space-6);">
        <div class="card__header-title" style="margin-bottom: var(--space-4);">Reparto por plataforma</div>
        <div id="adsPlatformSplit"></div>
    </div>

    {{-- ---------- Campaigns table ---------- --}}
    <div class="empty-state" data-campanas-empty {{ $campanas->isEmpty() ? '' : 'hidden' }}>
        <div class="empty-state__icon"><i class="fa-solid fa-bullhorn"></i></div>
        <p class="empty-state__text">No hay campañas que coincidan con los filtros actuales.</p>
    </div>
    <x-data-table :headers="$headers" data-paginate="15" data-campanas-table :hidden="$campanas->isEmpty()">
        @foreach ($campanas as $c)
            @php
                [$platLabel, $platColor] = $plataformas[$c['plataforma']] ?? [$c['plataforma'], '#6b7280'];
            @endphp
            <tr data-campana-row data-campana-id="{{ $c['id'] }}" data-plataforma="{{ $c['plataforma'] }}" data-cliente-id="{{ $c['cliente_id'] }}" data-campana="{{ json_encode($c) }}">
                <td><a href="{{ $c['show_url'] }}" style="font-weight:500; color:var(--color-foreground);">{{ $c['nombre'] }}</a></td>
                <td>{{ $c['cliente'] }}</td>
                <td><span class="ads-plataforma-pill" style="--pill-color:{{ $platColor }}">{{ $platLabel }}</span></td>
                <td><span style="font-size:var(--text-xs); color:var(--color-muted-foreground);">{{ \App\Support\Labels::objetivo($c['objetivo']) }}</span></td>
                <td><x-badge :status="$c['fase_actual']" /></td>
                <td><x-badge :status="$c['estado']" /></td>
                <td class="u-mono">${{ number_format($c['inversion']) }}</td>
                <td class="u-mono">{{ $compact($c['impresiones']) }}</td>
                <td class="u-mono">{{ number_format($c['clics']) }}</td>
                <td class="u-mono">{{ number_format($c['conversiones']) }}</td>
                <td class="u-mono"><strong style="color:{{ $c['roas'] >= 5 ? 'var(--text-success)' : ($c['roas'] >= 3 ? 'var(--text-warning)' : 'var(--text-danger)') }}">{{ $c['roas'] }}x</strong></td>
                <td>
                    <div style="display:flex; gap:4px;">
                        <button type="button" class="btn--icon" title="Editar" data-edit-campana="{{ $c['id'] }}"><i class="fa-solid fa-pen"></i></button>
                        <button type="button" class="btn--icon" title="Eliminar" style="color:var(--text-danger);" data-delete-campana="{{ $c['id'] }}"><i class="fa-solid fa-trash"></i></button>
                    </div>
                </td>
            </tr>
        @endforeach
    </x-data-table>

    {{-- ---------- Create/edit modal — estado/fecha_fin/notas only matter (and are only shown) in edit mode; store() doesn't validate them, so the create flow stays minimal ---------- --}}
    <x-modal id="campanaFormModal">
        <x-slot:header><h2 id="campanaFormModalTitle" style="margin-bottom:0;">Nueva Campaña</h2></x-slot:header>
        <form id="campanaForm" novalidate
              data-store-action="{{ route('admin.ads.store') }}"
              data-update-action-template="{{ route('admin.ads.update', ['campana' => '__ID__']) }}">
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
                    <label class="field__label" for="servicio_id">Servicio</label>
                    <select class="select" name="servicio_id" id="servicio_id" required>
                        <option value="">— Selecciona un cliente primero —</option>
                        @foreach ($clientes as $cliente)
                            @foreach ($cliente->servicios as $servicio)
                                <option value="{{ $servicio->id }}" data-cliente="{{ $cliente->id }}">{{ $servicio->nombre }}</option>
                            @endforeach
                        @endforeach
                    </select>
                    <span class="field__error" data-error-for="servicio_id"></span>
                </div>
            </div>

            <div class="field" style="margin-top: var(--space-4);">
                <label class="field__label" for="cf_nombre">Nombre de la campaña</label>
                <input class="input" type="text" name="nombre" id="cf_nombre" required>
                <span class="field__error" data-error-for="nombre"></span>
            </div>

            <div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
                <div class="field">
                    <label class="field__label" for="cf_plataforma">Plataforma</label>
                    <select class="select" name="plataforma" id="cf_plataforma" required>
                        @foreach ($plataformas as $value => [$label, $color])
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <span class="field__error" data-error-for="plataforma"></span>
                </div>
                <div class="field">
                    <label class="field__label" for="cf_objetivo">Objetivo</label>
                    <select class="select" name="objetivo" id="cf_objetivo" required>
                        @foreach (['leads' => 'Leads', 'ventas' => 'Ventas', 'trafico' => 'Tráfico', 'branding' => 'Branding'] as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <span class="field__error" data-error-for="objetivo"></span>
                </div>
            </div>

            <div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
                <div class="field">
                    <label class="field__label" for="cf_presupuesto">Presupuesto mensual (MXN)</label>
                    <input class="input" type="number" step="0.01" min="0" name="presupuesto_mensual" id="cf_presupuesto" value="0" required>
                    <span class="field__error" data-error-for="presupuesto_mensual"></span>
                </div>
                <div class="field">
                    <label class="field__label" for="cf_fecha_inicio">Fecha de inicio</label>
                    <input class="input" type="date" name="fecha_inicio" id="cf_fecha_inicio">
                    <span class="field__error" data-error-for="fecha_inicio"></span>
                </div>
            </div>

            <div data-edit-only>
                <div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
                    <div class="field">
                        <label class="field__label" for="cf_estado">Estado</label>
                        <select class="select" name="estado" id="cf_estado" required>
                            @foreach (['activa' => 'Activa', 'pausada' => 'Pausada', 'finalizada' => 'Finalizada'] as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <span class="field__error" data-error-for="estado"></span>
                    </div>
                    <div class="field">
                        <label class="field__label" for="cf_fecha_fin">Fecha de fin</label>
                        <input class="input" type="date" name="fecha_fin" id="cf_fecha_fin">
                        <span class="field__error" data-error-for="fecha_fin"></span>
                    </div>
                </div>
                <div class="field" style="margin-top: var(--space-4);">
                    <label class="field__label" for="cf_notas">Notas</label>
                    <textarea class="textarea" name="notas" id="cf_notas"></textarea>
                    <span class="field__error" data-error-for="notas"></span>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn--primary" id="campanaFormSubmit"><i class="fa-solid fa-check"></i> Crear Campaña</button>
                <button type="button" class="btn btn--secondary" data-modal-close="campanaFormModal">Cancelar</button>
            </div>
        </form>
    </x-modal>
@endsection

@section('scripts')
    @vite('resources/js/ads.js')
@endsection
