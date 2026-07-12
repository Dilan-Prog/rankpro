@extends('layouts.admin')

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Editar Campaña SEO</h1>
            <p class="page-header__subtitle">{{ $campana->nombre }} — {{ $campana->cliente->nombre }}</p>
        </div>
        <a href="{{ route('admin.seo.show', $campana) }}" class="btn btn--secondary">
            <i class="fa-solid fa-arrow-left"></i> Volver a la campaña
        </a>
    </div>

    <div class="card card--padded form-card">
        <form method="POST" action="{{ route('admin.seo.update', $campana) }}">
            @csrf
            @method('PUT')
            @include('admin.seo._campana-form')

            <div class="form-actions">
                <button type="submit" class="btn btn--primary"><i class="fa-solid fa-check"></i> Guardar Cambios</button>
                <a href="{{ route('admin.seo.show', $campana) }}" class="btn btn--secondary">Cancelar</a>
            </div>
        </form>
    </div>
@endsection
