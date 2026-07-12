@extends('layouts.admin')

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Nueva Keyword</h1>
            <p class="page-header__subtitle">Añadir al banco de keywords</p>
        </div>
        <a href="{{ route('admin.keywords.index') }}" class="btn btn--secondary">
            <i class="fa-solid fa-arrow-left"></i> Volver a Keywords
        </a>
    </div>

    <div class="card card--padded form-card">
        <form method="POST" action="{{ route('admin.keywords.store') }}">
            @csrf
            @include('admin.keywords._form')

            <div class="form-actions">
                <button type="submit" class="btn btn--primary"><i class="fa-solid fa-check"></i> Añadir Keyword</button>
                <a href="{{ route('admin.keywords.index') }}" class="btn btn--secondary">Cancelar</a>
            </div>
        </form>
    </div>
@endsection
