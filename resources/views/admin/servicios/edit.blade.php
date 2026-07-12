@extends('layouts.admin')

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Editar Servicio</h1>
            <p class="page-header__subtitle">{{ $servicio->nombre }}</p>
        </div>
        <a href="{{ route('admin.servicios.index') }}" class="btn btn--secondary">
            <i class="fa-solid fa-arrow-left"></i> Volver a Servicios
        </a>
    </div>

    <div class="card card--padded form-card">
        <form method="POST" action="{{ route('admin.servicios.update', $servicio) }}">
            @csrf
            @method('PUT')
            @include('admin.servicios._form')

            <div class="form-actions">
                <button type="submit" class="btn btn--primary"><i class="fa-solid fa-check"></i> Guardar Cambios</button>
                <a href="{{ route('admin.servicios.index') }}" class="btn btn--secondary">Cancelar</a>
            </div>
        </form>
    </div>
@endsection
