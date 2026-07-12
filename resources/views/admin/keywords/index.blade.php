@extends('layouts.admin')

@section('styles')
    @vite('resources/css/admin/keywords.css')
@endsection

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Banco de Keywords</h1>
            <p class="page-header__subtitle">{{ $keywords->count() }} keywords · repositorio centralizado</p>
        </div>
        <a href="{{ route('admin.keywords.create') }}" class="btn btn--primary">
            <i class="fa-solid fa-plus"></i> Añadir Keyword
        </a>
    </div>

    <div class="filters-bar">
        <input type="search" class="input input--search" id="kwSearch" placeholder="Buscar keyword...">
        <select class="select" id="kwTipoFilter">
            <option value="all">Todos los tipos</option>
            <option value="principal">Principal</option>
            <option value="secundaria">Secundaria</option>
            <option value="long_tail">Long Tail</option>
            <option value="lsi">LSI</option>
        </select>
        <select class="select" id="kwEstadoFilter">
            <option value="all">Todos los estados</option>
            <option value="en_uso">En Uso</option>
            <option value="seguimiento">Seguimiento</option>
            <option value="descartada">Descartada</option>
        </select>
        <select class="select" id="kwClienteFilter">
            <option value="all">Todos los clientes</option>
            @foreach ($clientes as $id => $nombre)
                <option value="{{ $id }}">{{ $nombre }}</option>
            @endforeach
        </select>
    </div>

    @if ($keywords->isEmpty())
        <div class="card empty-state">
            <div class="empty-state__icon"><i class="fa-solid fa-key"></i></div>
            <p class="empty-state__text">Aún no hay keywords en el banco.</p>
            <a href="{{ route('admin.keywords.create') }}" class="btn btn--primary">Añadir la primera keyword</a>
        </div>
    @else
        <x-data-table :headers="['Keyword', 'Tipo', 'Volumen', 'KD', 'CPC Est.', 'Intención', 'URL', 'Posición', 'Estado', 'Cliente', 'Fuente', '']">
            @foreach ($keywords as $k)
                <tr data-kw-row
                    data-search="{{ mb_strtolower($k['keyword']) }}"
                    data-tipo="{{ $k['tipo'] }}"
                    data-estado="{{ $k['estado'] }}"
                    data-cliente="{{ $k['cliente_id'] }}">
                    <td><div style="font-weight:500">{{ $k['keyword'] }}</div></td>
                    <td><span style="text-transform:capitalize; font-size:var(--text-xs); color:var(--color-muted-foreground)">{{ \App\Support\Labels::tipoKeyword($k['tipo']) }}</span></td>
                    <td class="u-mono">{{ number_format($k['volumen_busqueda'] ?? 0) }}</td>
                    <td class="u-mono">{{ $k['dificultad'] ?? '—' }}</td>
                    <td class="u-mono" style="color:var(--text-success)">${{ number_format($k['cpc_estimado'], 2) }}</td>
                    <td><span style="font-size:var(--text-xs); color:var(--color-muted-foreground)">{{ \App\Support\Labels::intencion($k['intencion']) }}</span></td>
                    <td><span class="u-mono" style="font-size:var(--text-xs); color:var(--color-primary);">{{ $k['url_asignada'] ?? '—' }}</span></td>
                    <td class="u-mono">{{ $k['posicion_actual'] ? '#'.$k['posicion_actual'] : '—' }}</td>
                    <td><x-badge :status="$k['estado']" /></td>
                    <td><span style="font-size:var(--text-xs); color:var(--color-muted-foreground)">{{ $k['cliente'] }}</span></td>
                    <td><span style="font-size:var(--text-xs); color:var(--color-muted-foreground)">{{ \App\Support\Labels::herramientaOrigen($k['herramienta_origen']) }}</span></td>
                    <td>
                        <div style="display:flex; gap:4px;">
                            <a href="{{ route('admin.keywords.edit', $k['id']) }}" class="btn--icon" title="Editar">
                                <i class="fa-solid fa-pen"></i>
                            </a>
                            <form method="POST" action="{{ route('admin.keywords.destroy', $k['id']) }}"
                                data-confirm="¿Eliminar la keyword &quot;{{ $k['keyword'] }}&quot;?">
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
        <p class="table__empty" id="kwNoResults" hidden>Sin resultados para los filtros seleccionados.</p>
    @endif
@endsection

@section('scripts')
    @vite('resources/js/keywords.js')
@endsection
