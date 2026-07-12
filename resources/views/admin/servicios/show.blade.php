@extends('layouts.admin')

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title">{{ $servicio->nombre }}</h1>
            <p class="page-header__subtitle">Detalle de servicio</p>
        </div>
        <a href="{{ route('admin.servicios.index') }}" class="btn btn--secondary">
            <i class="fa-solid fa-arrow-left"></i> Volver a Servicios
        </a>
    </div>

    <div class="card empty-state">
        <div class="empty-state__icon"><i class="fa-solid fa-briefcase"></i></div>
        <p class="empty-state__text">La ficha detallada de servicio está en desarrollo.</p>
        <a href="{{ route('admin.servicios.index') }}" class="btn btn--primary">Volver al listado</a>
    </div>
@endsection
