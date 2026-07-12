@extends('layouts.admin')

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Editar Cliente</h1>
            <p class="page-header__subtitle">{{ $cliente->nombre }}</p>
        </div>
        <a href="{{ route('admin.clientes.index') }}" class="btn btn--secondary">
            <i class="fa-solid fa-arrow-left"></i> Volver a Clientes
        </a>
    </div>

    <div class="card card--padded form-card">
        <form method="POST" action="{{ route('admin.clientes.update', $cliente) }}">
            @csrf
            @method('PUT')
            @include('admin.clientes._form')

            <div class="form-actions">
                <button type="submit" class="btn btn--primary"><i class="fa-solid fa-check"></i> Guardar Cambios</button>
                <a href="{{ route('admin.clientes.index') }}" class="btn btn--secondary">Cancelar</a>
            </div>
        </form>
    </div>
@endsection
