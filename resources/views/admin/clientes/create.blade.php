@extends('layouts.admin')

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Nuevo Cliente</h1>
            <p class="page-header__subtitle">Formulario de alta de cliente</p>
        </div>
        <a href="{{ route('admin.clientes.index') }}" class="btn btn--secondary">
            <i class="fa-solid fa-arrow-left"></i> Volver a Clientes
        </a>
    </div>

    <div class="card card--padded form-card">
        <form method="POST" action="{{ route('admin.clientes.store') }}">
            @csrf
            @include('admin.clientes._form')

            <div class="form-actions">
                <button type="submit" class="btn btn--primary"><i class="fa-solid fa-check"></i> Crear Cliente</button>
                <a href="{{ route('admin.clientes.index') }}" class="btn btn--secondary">Cancelar</a>
            </div>
        </form>
    </div>
@endsection
