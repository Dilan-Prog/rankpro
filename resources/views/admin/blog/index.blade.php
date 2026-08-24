@extends('layouts.admin')

@section('styles')
    @vite('resources/css/admin/blog.css')
@endsection

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Blog</h1>
            <p class="page-header__subtitle">{{ $articulos->total() }} artículos · contenido hub-and-spoke</p>
        </div>
        <a href="{{ route('admin.blog.create') }}" class="btn btn--primary">
            <i class="fa-solid fa-plus"></i> Nuevo Artículo
        </a>
    </div>

    @if (session('status'))
        <div class="form-status"><i class="fa-solid fa-circle-check" style="margin-top:2px"></i><span>{{ session('status') }}</span></div>
    @endif

    <div class="filters-bar">
        <input type="search" class="input input--search" id="blogSearch" placeholder="Buscar por título o slug...">
        <select class="select" id="blogClusterFilter">
            <option value="all">Todos los clústeres</option>
            @foreach ($clusters as $c)
                <option value="{{ $c['slug'] }}">{{ $c['nombre'] }}</option>
            @endforeach
        </select>
        <select class="select" id="blogEstadoFilter">
            <option value="all">Todos los estados</option>
            @foreach ($estados as $estado)
                <option value="{{ $estado->value }}">{{ $estado->etiqueta() }}</option>
            @endforeach
        </select>
        <select class="select" id="blogAvisosFilter">
            <option value="all">Con y sin avisos</option>
            <option value="con">Solo con avisos SEO</option>
            <option value="sin">Solo sin avisos</option>
        </select>
    </div>

    @if ($filas->isEmpty())
        <div class="card empty-state">
            <div class="empty-state__icon"><i class="fa-solid fa-newspaper"></i></div>
            <p class="empty-state__text">Aún no hay artículos en el blog.</p>
            <a href="{{ route('admin.blog.create') }}" class="btn btn--primary">Escribir el primer artículo</a>
        </div>
    @else
        <div class="card card--padded">
            <x-data-table :headers="['Título', 'Clúster', 'Estado', 'Autor', 'Publicación', 'Palabras', 'SEO', '']">
                @foreach ($filas as $a)
                    <tr data-blog-row
                        data-search="{{ mb_strtolower($a['titulo'].' '.$a['slug']) }}"
                        data-cluster="{{ $a['cluster'] }}"
                        data-estado="{{ $a['estado'] }}"
                        data-avisos="{{ count($a['avisos']) ? 'con' : 'sin' }}">
                        <td>
                            <a href="{{ route('admin.blog.edit', $a['slug']) }}" class="blog-titulo">{{ $a['titulo'] }}</a>
                            <div class="blog-slug u-mono">/blog/{{ $a['slug'] }}</div>
                        </td>
                        <td><span class="blog-cluster">{{ $a['cluster_nombre'] }}</span></td>
                        <td><x-badge :status="$a['estado']" /></td>
                        <td><span class="blog-meta">{{ $a['autor'] }}</span></td>
                        <td class="u-mono">{{ $a['fecha_publicacion'] ?? '—' }}</td>
                        <td class="u-mono @if ($a['palabras'] < 1500) blog-palabras--corto @endif">{{ number_format($a['palabras']) }}</td>
                        <td>
                            @if (count($a['avisos']))
                                <span class="blog-aviso" title="{{ implode(' ', $a['avisos']) }}">
                                    <i class="fa-solid fa-triangle-exclamation"></i> {{ count($a['avisos']) }}
                                </span>
                            @else
                                <span class="blog-aviso blog-aviso--ok" title="Sin avisos SEO"><i class="fa-solid fa-circle-check"></i></span>
                            @endif
                        </td>
                        <td>
                            <div style="display:flex; gap:4px;">
                                <a href="{{ route('admin.blog.edit', $a['slug']) }}" class="btn--icon" title="Editar">
                                    <i class="fa-solid fa-pen"></i>
                                </a>
                                <form method="POST" action="{{ route('admin.blog.destroy', $a['slug']) }}"
                                    data-confirm="¿Eliminar el artículo &quot;{{ $a['titulo'] }}&quot;?@if ($a['estado'] === 'publicado') Está publicado: su URL saldrá del sitemap.@endif">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn--icon" title="Eliminar" style="color:var(--text-danger);">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-data-table>

            <p class="table__empty" id="blogNoResults" hidden>Sin resultados para los filtros seleccionados.</p>

            @include('admin.integraciones._paginacion', ['paginator' => $articulos])
        </div>
    @endif
@endsection

@section('scripts')
    @vite('resources/js/blog.js')
@endsection
