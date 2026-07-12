@extends('layouts.admin')

@section('styles')
    @vite('resources/css/admin/integraciones.css')
@endsection

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Integraciones</h1>
            <p class="page-header__subtitle">Conecta tus herramientas — módulo disponible próximamente</p>
        </div>
    </div>

    <x-alert variant="banner" icon="fa-bolt" color="#F59E0B">
        Este módulo está en desarrollo activo. Las integraciones estarán disponibles próximamente. Puedes solicitar una integración prioritaria a tu account manager.
    </x-alert>

    <div class="integraciones-grid">
        @foreach ($integraciones as $integracion)
            <div class="integraciones-card-wrap">
                <div class="card card--padded integraciones-card">
                    <div class="integraciones-card__head">
                        <span class="integraciones-card__icon"><i class="fa-{{ $integracion['brand'] ? 'brands' : 'solid' }} {{ $integracion['icon'] }}"></i></span>
                        <div>
                            <div style="font-weight:600; font-size:var(--text-sm);">{{ $integracion['name'] }}</div>
                            <div style="font-size:var(--text-xs); color:var(--color-muted-foreground); margin-top:2px;">{{ $integracion['desc'] }}</div>
                        </div>
                    </div>
                    <div class="integraciones-card__status">
                        <span class="integraciones-card__dot"></span>
                        <span>No conectado</span>
                    </div>
                </div>
                <div class="integraciones-card__overlay">
                    <div class="integraciones-card__badge">
                        <i class="fa-solid fa-lock"></i> Próximamente
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection
