@extends('layouts.admin')

@section('styles')
    @vite('resources/css/admin/dashboard.css')
@endsection

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Dashboard General</h1>
            <p class="page-header__subtitle">{{ $periodoActual }} · Vista ejecutiva</p>
        </div>
        <a class="btn btn--secondary" id="exportReportBtn" href="{{ route('admin.dashboard.exportar') }}">
            <i class="fa-solid fa-download"></i> Exportar reporte
        </a>
    </div>

    <div class="kpi-grid">
        @foreach ($kpis as $kpi)
            <x-stat-card :label="$kpi['label']" :value="$kpi['value']" :sub="$kpi['sub']"
                :trend="$kpi['trend']" :icon="$kpi['icon']" :color="$kpi['color']" :href="$kpi['href']" />
        @endforeach
    </div>

    <div class="grid-2 grid-2--wide-left dashboard__row" style="margin-bottom: var(--space-6);">
        <div class="card card--padded">
            <div class="chart-head">
                <h2 class="card__header-title">Ingresos vs Inversión</h2>
                <span class="chart-range">{{ $chartRange }}</span>
            </div>
            <div class="chart-wrap">
                <canvas id="revenueChart" role="img"
                    aria-label="Gráfica de ingresos e inversión de julio a diciembre 2024"
                    data-revenue="{{ json_encode($revenueData) }}"></canvas>
            </div>
        </div>

        <div class="card card--padded">
            <h2 class="card__header-title" style="margin-bottom: var(--space-4);">Alertas</h2>
            @if (empty($alerts))
                <div class="empty-state">
                    <div class="empty-state__icon"><i class="fa-solid fa-circle-check"></i></div>
                    <p class="empty-state__text">Sin alertas pendientes.</p>
                </div>
            @else
                <ul class="alert-list">
                    @foreach ($alerts as $alert)
                        <x-alert :icon="$alert['icon']" :color="$alert['color']">{{ $alert['msg'] }}</x-alert>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    <div class="grid-2 dashboard__row">
        <div class="card">
            <div class="card__header">
                <h2 class="card__header-title">Top Campañas por ROAS</h2>
                <i class="fa-solid fa-star" style="color:var(--text-warning)"></i>
            </div>
            @if (empty($topRoas))
                <div class="empty-state">
                    <div class="empty-state__icon"><i class="fa-solid fa-chart-line"></i></div>
                    <p class="empty-state__text">Sin métricas de campañas todavía.</p>
                </div>
            @else
                <x-data-table :headers="['#', 'Campaña', 'Cliente', 'Plataforma', 'ROAS']">
                    @foreach ($topRoas as $i => $c)
                        <tr class="is-clickable" data-campana-row data-campana="{{ json_encode($c) }}">
                            <td class="u-mono" style="color:var(--color-muted-foreground)">{{ $i + 1 }}</td>
                            <td>
                                <div style="font-weight:500;max-width:9rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:var(--text-xs)">
                                    {{ $c['name'] }}</div>
                            </td>
                            <td><span style="font-size:var(--text-xs);color:var(--color-muted-foreground)">{{ $c['client'] }}</span></td>
                            <td><span class="badge badge--primary">{{ $c['platform'] }}</span></td>
                            <td class="u-mono"><span style="font-weight:700;color:var(--text-success)">{{ $c['roas'] }}x</span></td>
                        </tr>
                    @endforeach
                </x-data-table>
            @endif
        </div>

        <div class="card">
            <div class="card__header">
                <h2 class="card__header-title">Contratos por Vencer</h2>
                <i class="fa-solid fa-calendar" style="color:var(--text-warning)"></i>
            </div>
            @if (empty($contractsExpiring))
                <div class="empty-state">
                    <div class="empty-state__icon"><i class="fa-solid fa-calendar-check"></i></div>
                    <p class="empty-state__text">Sin contratos por vencer en los próximos días.</p>
                </div>
            @else
                <ul class="dashboard__contracts">
                    @foreach ($contractsExpiring as $c)
                        @php
                            $daysClass = $c['days'] <= 30
                                ? 'dashboard__contract-days--soon'
                                : ($c['days'] <= 60 ? 'dashboard__contract-days--warn' : 'dashboard__contract-days--ok');
                        @endphp
                        <li>
                            <a href="{{ route('admin.clientes.show', $c['id']) }}" class="dashboard__contract" style="text-decoration:none;color:inherit;">
                                <div>
                                    <div class="dashboard__contract-client">{{ $c['client'] }}</div>
                                    <div class="dashboard__contract-date">Vence: {{ $c['end'] }}</div>
                                </div>
                                <div>
                                    <div class="dashboard__contract-days {{ $daysClass }}">{{ $c['days'] }} días</div>
                                    <div class="dashboard__contract-mrr">${{ number_format($c['mrr']) }}/mo</div>
                                </div>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    <div data-ads-base="{{ route('admin.ads.index') }}">
        <x-modal id="campanaModal">
            <x-slot:header>
                <h2 id="campanaModalName" style="margin-bottom:6px;"></h2>
                <div style="display:flex;gap:6px;">
                    <span id="campanaModalBadgeEstado"></span>
                    <span id="campanaModalBadgeFase"></span>
                </div>
            </x-slot:header>

            <div class="record-modal__stats">
                <div>
                    <div class="record-modal__section-label">Cliente</div>
                    <div id="campanaModalClient"></div>
                </div>
                <div>
                    <div class="record-modal__section-label">Plataforma</div>
                    <div id="campanaModalPlatform"></div>
                </div>
                <div>
                    <div class="record-modal__section-label">Presupuesto mensual</div>
                    <div id="campanaModalPresupuesto"></div>
                </div>
                <div>
                    <div class="record-modal__section-label">Gasto total</div>
                    <div id="campanaModalGasto"></div>
                </div>
                <div>
                    <div class="record-modal__section-label">ROAS promedio</div>
                    <div id="campanaModalRoas"></div>
                </div>
            </div>

            <div class="record-modal__section">
                <div class="record-modal__section-label">Último mes</div>
                <div id="campanaModalUltimaMetrica"></div>
            </div>

            <div class="record-modal__actions">
                <a id="campanaModalLink" href="#" class="btn btn--primary">Ver campaña completa</a>
            </div>
        </x-modal>
    </div>
@endsection

@section('scripts')
    @vite('resources/js/dashboard.js')
@endsection
