@extends('layouts.admin')

@section('styles')
    @vite('resources/css/admin/ads.css')
@endsection

@section('content')
    @php
        $plataformas = ['google_ads' => ['Google Ads', '#4285F4'], 'meta_ads' => ['Meta Ads', '#1877F2'], 'tiktok_ads' => ['TikTok Ads', '#FE2C55']];
        $colorActual = $plataformas[$plataforma][1];
    @endphp

    <div class="page-header">
        <div>
            <h1 class="page-header__title">Módulo Ads</h1>
            <p class="page-header__subtitle">Gestión de campañas publicitarias multicanal</p>
        </div>
        <a href="{{ route('admin.ads.create') }}" class="btn btn--primary">
            <i class="fa-solid fa-plus"></i> Nueva Campaña
        </a>
    </div>

    <div class="ads-platform-tabs">
        @foreach ($plataformas as $value => [$label, $color])
            <a href="{{ route('admin.ads.index', ['plataforma' => $value]) }}"
                class="ads-platform-tab {{ $plataforma === $value ? 'is-active' : '' }}"
                style="{{ $plataforma === $value ? '--tab-color:'.$color : '' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    <div class="kpi-grid">
        <x-stat-card label="Inversión Total" value="${{ number_format($kpis['inversion']/1000, 1) }}K" sub="MXN este mes" icon="fa-dollar-sign" color="teal" />
        <x-stat-card label="Impresiones" value="{{ $kpis['impresiones'] > 1000000 ? number_format($kpis['impresiones']/1000000, 1).'M' : number_format($kpis['impresiones']/1000, 0).'K' }}" sub="total campañas" icon="fa-eye" color="blue" />
        <x-stat-card label="Clics" value="{{ number_format($kpis['clics']/1000, 1) }}K" sub="total campañas" icon="fa-arrow-pointer" color="primary" />
        <x-stat-card label="Conversiones" value="{{ $kpis['conversiones'] }}" sub="total campañas" icon="fa-circle-check" color="emerald" />
    </div>

    @if ($campanas->isEmpty())
        <div class="card empty-state">
            <div class="empty-state__icon"><i class="fa-solid fa-bullhorn"></i></div>
            <p class="empty-state__text">No hay campañas activas en {{ $plataformas[$plataforma][0] }}</p>
            <a href="{{ route('admin.ads.create') }}" class="btn" style="background:{{ $colorActual }}; color:#fff;">Crear primera campaña</a>
        </div>
    @else
        <x-data-table :headers="['Campaña', 'Cliente', 'Fase', 'Ciclo', 'Objetivo', 'Estado', 'Invertido', 'Impr.', 'Clics', 'Conv.', 'ROAS', '']">
            @foreach ($campanas as $c)
                <tr>
                    <td><a href="{{ route('admin.ads.show', $c['id']) }}" style="font-weight:500; color:var(--color-foreground);">{{ $c['nombre'] }}</a></td>
                    <td>{{ $c['cliente'] }}</td>
                    <td><x-badge :status="$c['fase_actual']" /></td>
                    <td class="u-mono">{{ $c['ciclo_actual'] }}</td>
                    <td><span style="font-size:var(--text-xs); color:var(--color-muted-foreground);">{{ \App\Support\Labels::objetivo($c['objetivo']) }}</span></td>
                    <td><x-badge :status="$c['estado']" /></td>
                    <td class="u-mono">${{ number_format($c['inversion']) }}</td>
                    <td class="u-mono">{{ $c['impresiones'] > 1000000 ? number_format($c['impresiones']/1000000, 1).'M' : number_format($c['impresiones']/1000, 0).'K' }}</td>
                    <td class="u-mono">{{ number_format($c['clics']) }}</td>
                    <td class="u-mono">{{ $c['conversiones'] }}</td>
                    <td class="u-mono"><strong style="color:{{ $c['roas'] >= 5 ? 'var(--text-success)' : ($c['roas'] >= 3 ? 'var(--text-warning)' : 'var(--text-danger)') }}">{{ $c['roas'] }}x</strong></td>
                    <td>
                        <div style="display:flex; gap:4px;">
                            <a href="{{ route('admin.ads.edit', $c['id']) }}" class="btn--icon" title="Editar"><i class="fa-solid fa-pen"></i></a>
                            <form method="POST" action="{{ route('admin.ads.destroy', $c['id']) }}" data-confirm="¿Eliminar la campaña &quot;{{ $c['nombre'] }}&quot;?">
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
@endsection
