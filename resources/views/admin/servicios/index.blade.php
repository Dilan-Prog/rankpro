@extends('layouts.admin')

@section('styles')
    @vite('resources/css/admin/servicios.css')
@endsection

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Gestión de Servicios</h1>
            <p class="page-header__subtitle">{{ $activos }} servicios activos</p>
        </div>
        <a href="{{ route('admin.servicios.create') }}" class="btn btn--primary">
            <i class="fa-solid fa-plus"></i> Asignar Servicio
        </a>
    </div>

    @php
        $tipos = ['seo' => 'SEO', 'google_ads' => 'Google Ads', 'meta_ads' => 'Meta Ads', 'tiktok_ads' => 'TikTok Ads', 'rediseno' => 'Rediseño', 'software' => 'Software'];
    @endphp

    <div class="tabs" id="servicioTabs">
        <button type="button" class="tabs__item is-active" data-tipo="all">Todos</button>
        @foreach ($tipos as $value => $label)
            <button type="button" class="tabs__item" data-tipo="{{ $value }}">{{ $label }}</button>
        @endforeach
    </div>

    @if ($servicios->isEmpty())
        <div class="card empty-state">
            <div class="empty-state__icon"><i class="fa-solid fa-briefcase"></i></div>
            <p class="empty-state__text">Aún no hay servicios asignados.</p>
            <a href="{{ route('admin.servicios.create') }}" class="btn btn--primary">Asignar el primer servicio</a>
        </div>
    @else
        <x-data-table :headers="['Cliente', 'Servicio', 'Tipo', 'Estado', 'Inicio', 'Precio Mensual', '']">
            @foreach ($servicios as $servicio)
                <tr data-servicio-row data-tipo="{{ $servicio['tipo'] }}">
                    <td><div style="font-weight:500">{{ $servicio['cliente'] }}</div></td>
                    <td>{{ $servicio['nombre'] }}</td>
                    <td>{{ $tipos[$servicio['tipo']] ?? $servicio['tipo'] }}</td>
                    <td><x-badge :status="$servicio['estado']" /></td>
                    <td><span style="font-size:var(--text-xs);color:var(--color-muted-foreground)">{{ $servicio['fecha_inicio'] ?? '—' }}</span></td>
                    <td class="u-mono">{{ $servicio['precio_mensual'] > 0 ? '$'.number_format($servicio['precio_mensual']) : '—' }}</td>
                    <td>
                        <div style="display:flex; gap:4px;">
                            <a href="{{ route('admin.servicios.edit', $servicio['id']) }}" class="btn--icon" title="Editar">
                                <i class="fa-solid fa-pen"></i>
                            </a>
                            <form method="POST" action="{{ route('admin.servicios.destroy', $servicio['id']) }}"
                                data-confirm="¿Eliminar el servicio &quot;{{ $servicio['nombre'] }}&quot;?">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn--icon" title="Eliminar" style="color:var(--text-danger);">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-data-table>
        <p class="table__empty" id="servicioNoResults" hidden>No hay servicios de este tipo.</p>
    @endif
@endsection

@section('scripts')
    @vite('resources/js/servicios.js')
@endsection
