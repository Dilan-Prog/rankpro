@extends('layouts.admin')

@section('styles')
    @vite('resources/css/admin/blog.css')
@endsection

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Editar Artículo</h1>
            <p class="page-header__subtitle">{{ $articulo->titulo }}</p>
        </div>
        <div style="display:flex; gap: var(--space-2);">
            @if ($articulo->estado->esPublico())
                <a href="{{ route('blog.show', $articulo->slug) }}" class="btn btn--secondary" target="_blank" rel="noopener">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i> Ver publicado
                </a>
            @endif
            <a href="{{ route('admin.blog.index') }}" class="btn btn--secondary">
                <i class="fa-solid fa-arrow-left"></i> Volver al Blog
            </a>
        </div>
    </div>

    <div class="card card--padded form-card">
        <form method="POST" action="{{ route('admin.blog.update', $articulo->slug) }}">
            @csrf
            @method('PUT')
            @include('admin.blog._form')

            <div class="form-actions">
                <button type="submit" class="btn btn--primary"><i class="fa-solid fa-check"></i> Guardar Cambios</button>
                <a href="{{ route('admin.blog.index') }}" class="btn btn--secondary">Cancelar</a>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
    @vite('resources/js/blog.js')
@endsection
