@extends('layouts.admin')

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Editar Keyword</h1>
            <p class="page-header__subtitle">{{ $keyword->keyword }}</p>
        </div>
        <a href="{{ route('admin.keywords.index') }}" class="btn btn--secondary">
            <i class="fa-solid fa-arrow-left"></i> Volver a Keywords
        </a>
    </div>

    <div class="card card--padded form-card">
        <form method="POST" action="{{ route('admin.keywords.update', $keyword) }}">
            @csrf
            @method('PUT')
            @include('admin.keywords._form')

            <div class="form-actions">
                <button type="submit" class="btn btn--primary"><i class="fa-solid fa-check"></i> Guardar Cambios</button>
                <a href="{{ route('admin.keywords.index') }}" class="btn btn--secondary">Cancelar</a>
            </div>
        </form>
    </div>
@endsection
