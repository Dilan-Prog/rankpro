@extends('layouts.admin')

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Nueva Campaña</h1>
            <p class="page-header__subtitle">Crear una campaña publicitaria</p>
        </div>
        <a href="{{ route('admin.ads.index') }}" class="btn btn--secondary">
            <i class="fa-solid fa-arrow-left"></i> Volver a Ads
        </a>
    </div>

    <div class="card card--padded form-card">
        <form method="POST" action="{{ route('admin.ads.store') }}">
            @csrf
            @include('admin.ads._form')

            <div class="form-actions">
                <button type="submit" class="btn btn--primary"><i class="fa-solid fa-check"></i> Crear Campaña</button>
                <a href="{{ route('admin.ads.index') }}" class="btn btn--secondary">Cancelar</a>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
    @vite('resources/js/ads.js')
@endsection
