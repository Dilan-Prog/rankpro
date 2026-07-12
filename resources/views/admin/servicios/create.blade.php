@extends('layouts.admin')

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Nuevo Servicio</h1>
            <p class="page-header__subtitle">Asignar un servicio a un cliente</p>
        </div>
        <a href="{{ route('admin.servicios.index') }}" class="btn btn--secondary">
            <i class="fa-solid fa-arrow-left"></i> Volver a Servicios
        </a>
    </div>

    <div class="card card--padded form-card">
        <form method="POST" action="{{ route('admin.servicios.store') }}">
            @csrf
            @include('admin.servicios._form')

            <div class="form-actions">
                <button type="submit" class="btn btn--primary"><i class="fa-solid fa-check"></i> Crear Servicio</button>
                <a href="{{ route('admin.servicios.index') }}" class="btn btn--secondary">Cancelar</a>
            </div>
        </form>
    </div>
@endsection
