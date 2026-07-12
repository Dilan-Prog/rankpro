@extends('layouts.admin')

@section('styles')
    @vite('resources/css/admin/finanzas.css')
@endsection

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Finanzas</h1>
            <p class="page-header__subtitle">Facturación, ingresos y proyecciones</p>
        </div>
        <a href="{{ route('admin.finanzas.create') }}" class="btn btn--primary">
            <i class="fa-solid fa-plus"></i> Nuevo Registro
        </a>
    </div>

    @if (session('status'))
        <div class="form-status"><i class="fa-solid fa-circle-check" style="margin-top:2px"></i><span>{{ session('status') }}</span></div>
    @endif

    <div class="kpi-grid">
        <x-stat-card label="MRR Actual" value="${{ number_format($mrr/1000, 1) }}K" sub="MXN · servicios activos" icon="fa-arrow-trend-up" color="emerald" />
        <x-stat-card label="Cobrado" value="${{ number_format($cobrado/1000, 0) }}K" sub="{{ $facturasPagadas }} facturas pagadas" icon="fa-circle-check" color="primary" />
        <x-stat-card label="Pendiente de cobro" value="${{ number_format($pendiente/1000, 0) }}K" sub="{{ $facturasPendientes }} facturas pendientes" icon="fa-triangle-exclamation" color="amber" />
        <x-stat-card label="Utilidad" value="${{ number_format($utilidad/1000, 1) }}K" sub="cobrado − gastos" icon="fa-chart-line" color="teal" />
    </div>

    <div class="card card--padded" style="margin-bottom: var(--space-6);">
        <div class="chart-head">
            <h2 class="card__header-title">Ingresos vs Gastos</h2>
        </div>
        <div class="chart-wrap">
            <canvas id="financeChart" role="img" aria-label="Gráfica de ingresos y gastos por mes"
                data-revenue="{{ $revenueData->toJson() }}"></canvas>
        </div>
    </div>

    <div class="card">
        <div class="card__header">
            <h2 class="card__header-title">Facturación</h2>
        </div>
        @if ($facturas->isEmpty())
            <div class="empty-state">
                <div class="empty-state__icon"><i class="fa-solid fa-file-invoice-dollar"></i></div>
                <p class="empty-state__text">Sin registros financieros todavía.</p>
            </div>
        @else
            <x-data-table :headers="['Cliente', 'Concepto', 'Tipo', 'Monto', 'Vencimiento', 'Estado', 'Fecha de Pago', '']">
                @foreach ($facturas as $f)
                    <tr>
                        <td><div style="font-weight:500">{{ $f['cliente'] }}</div></td>
                        <td><span style="font-size:var(--text-sm); color:var(--color-muted-foreground);">{{ $f['concepto'] }}</span></td>
                        <td><span style="font-size:var(--text-xs); text-transform:capitalize; color:{{ $f['tipo'] === 'ingreso' ? 'var(--text-success)' : 'var(--text-danger)' }};">{{ $f['tipo'] }}</span></td>
                        <td class="u-mono"><strong>${{ number_format($f['monto']) }}</strong></td>
                        <td class="u-mono" style="font-size:var(--text-xs); color:var(--color-muted-foreground);">{{ $f['fecha_vencimiento'] ?? '—' }}</td>
                        <td><x-badge :status="$f['estado']" /></td>
                        <td class="u-mono" style="font-size:var(--text-xs); color:var(--color-muted-foreground);">{{ $f['fecha_pago'] ?? '—' }}</td>
                        <td>
                            <div style="display:flex; gap:4px;">
                                <a href="{{ route('admin.finanzas.edit', $f['id']) }}" class="btn--icon" title="Editar"><i class="fa-solid fa-pen"></i></a>
                                <form method="POST" action="{{ route('admin.finanzas.destroy', $f['id']) }}" data-confirm="¿Eliminar este registro financiero?">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn--icon" title="Eliminar" style="color:var(--text-danger);"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-data-table>
        @endif
    </div>
@endsection

@section('scripts')
    @vite('resources/js/finanzas.js')
@endsection
