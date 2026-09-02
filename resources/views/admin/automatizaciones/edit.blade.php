@extends('layouts.admin')

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Editar Proyecto de Automatización</h1>
            <p class="page-header__subtitle">{{ $proyecto->nombre }} — {{ $proyecto->cliente->nombre }}</p>
        </div>
        <a href="{{ route('admin.automatizaciones.show', $proyecto) }}" class="btn btn--secondary">
            <i class="fa-solid fa-arrow-left"></i> Volver al proyecto
        </a>
    </div>

    <div class="card card--padded form-card">
        <form method="POST" action="{{ route('admin.automatizaciones.update', $proyecto) }}">
            @csrf
            @method('PUT')
            @include('admin.automatizaciones._proyecto-form')

            <div class="form-actions">
                <button type="submit" class="btn btn--primary"><i class="fa-solid fa-check"></i> Guardar Cambios</button>
                <a href="{{ route('admin.automatizaciones.show', $proyecto) }}" class="btn btn--secondary">Cancelar</a>
            </div>
        </form>
    </div>
@endsection
