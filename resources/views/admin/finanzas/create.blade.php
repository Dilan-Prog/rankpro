@extends('layouts.admin')

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Nuevo Registro Financiero</h1>
            <p class="page-header__subtitle">Ingreso o gasto</p>
        </div>
        <a href="{{ route('admin.finanzas.index') }}" class="btn btn--secondary">
            <i class="fa-solid fa-arrow-left"></i> Volver a Finanzas
        </a>
    </div>

    <div class="card card--padded form-card">
        <form method="POST" action="{{ route('admin.finanzas.store') }}">
            @csrf
            @include('admin.finanzas._form')

            <div class="form-actions">
                <button type="submit" class="btn btn--primary"><i class="fa-solid fa-check"></i> Crear Registro</button>
                <a href="{{ route('admin.finanzas.index') }}" class="btn btn--secondary">Cancelar</a>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
    @vite('resources/js/finanzas.js')
@endsection
