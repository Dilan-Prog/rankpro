@extends('layouts.admin')

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Editar Rol</h1>
            <p class="page-header__subtitle">{{ $role->label }}</p>
        </div>
        <a href="{{ route('admin.roles.index') }}" class="btn btn--secondary">
            <i class="fa-solid fa-arrow-left"></i> Volver
        </a>
    </div>

    <div class="card card--padded form-card">
        <form method="POST" action="{{ route('admin.roles.update', $role) }}">
            @csrf
            @method('PUT')
            @include('admin.roles._form')

            <div class="form-actions">
                <button type="submit" class="btn btn--primary"><i class="fa-solid fa-check"></i> Guardar Cambios</button>
                <a href="{{ route('admin.roles.index') }}" class="btn btn--secondary">Cancelar</a>
            </div>
        </form>
    </div>
@endsection
