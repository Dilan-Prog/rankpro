@extends('layouts.admin')

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title">{{ $cliente->nombre }}</h1>
            <p class="page-header__subtitle">Ficha completa de cliente</p>
        </div>
        <a href="{{ route('admin.clientes.index') }}" class="btn btn--secondary">
            <i class="fa-solid fa-arrow-left"></i> Volver a Clientes
        </a>
    </div>

    <div class="card empty-state">
        <div class="empty-state__icon"><i class="fa-solid fa-address-card"></i></div>
        <p class="empty-state__text">La ficha completa de cliente (historial, contratos, actividad) está en desarrollo. Por ahora, usa el listado para ver el detalle rápido.</p>
        <a href="{{ route('admin.clientes.index') }}" class="btn btn--primary">Volver al listado</a>
    </div>
@endsection
