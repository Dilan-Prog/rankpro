@extends('layouts.admin')

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Editar Registro Financiero</h1>
            <p class="page-header__subtitle">{{ $finanza->concepto }}</p>
        </div>
        <a href="{{ route('admin.finanzas.index') }}" class="btn btn--secondary">
            <i class="fa-solid fa-arrow-left"></i> Volver a Finanzas
        </a>
    </div>

    <div class="card card--padded form-card">
        <form method="POST" action="{{ route('admin.finanzas.update', $finanza) }}">
            @csrf
            @method('PUT')
            @include('admin.finanzas._form')

            <div class="form-actions">
                <button type="submit" class="btn btn--primary"><i class="fa-solid fa-check"></i> Guardar Cambios</button>
                <a href="{{ route('admin.finanzas.index') }}" class="btn btn--secondary">Cancelar</a>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
    @vite('resources/js/finanzas.js')
@endsection
