@extends('layouts.admin')

@section('styles')
    @vite('resources/css/admin/blog.css')
@endsection

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Nuevo Artículo</h1>
            <p class="page-header__subtitle">Redacción en Markdown con vista previa</p>
        </div>
        <a href="{{ route('admin.blog.index') }}" class="btn btn--secondary">
            <i class="fa-solid fa-arrow-left"></i> Volver al Blog
        </a>
    </div>

    <div class="card card--padded form-card">
        <form method="POST" action="{{ route('admin.blog.store') }}">
            @csrf
            @include('admin.blog._form')

            <div class="form-actions">
                <button type="submit" class="btn btn--primary"><i class="fa-solid fa-check"></i> Crear Artículo</button>
                <a href="{{ route('admin.blog.index') }}" class="btn btn--secondary">Cancelar</a>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
    @vite('resources/js/blog.js')
@endsection
