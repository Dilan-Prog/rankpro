@extends('layouts.admin')

@section('styles')
    @vite('resources/css/admin/archivos.css')
@endsection

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Archivos y Documentos</h1>
            <p class="page-header__subtitle">Repositorio organizado por cliente</p>
        </div>
        <div style="display:flex; gap: var(--space-2);">
            <a href="{{ route('admin.archivos.contratos.create') }}" class="btn btn--secondary">
                <i class="fa-solid fa-file-signature"></i> Generar Contrato
            </a>
            <a href="{{ route('admin.archivos.propuestas.create') }}" class="btn btn--primary">
                <i class="fa-solid fa-file-invoice"></i> Generar Propuesta
            </a>
        </div>
    </div>

    @if (session('status'))
        <div class="form-status"><i class="fa-solid fa-circle-check" style="margin-top:2px"></i><span>{{ session('status') }}</span></div>
    @endif
    @error('archivo')
        <div class="form-status form-status--error"><i class="fa-solid fa-circle-exclamation" style="margin-top:2px"></i><span>{{ $message }}</span></div>
    @enderror

    @if ($clientes->isNotEmpty())
        <select class="select" id="archivoClientSelect" style="margin-bottom: var(--space-6);">
            @foreach ($clientes as $cliente)
                <option value="{{ $cliente->id }}" @selected($cliente->id === $clienteSeleccionado)>{{ $cliente->nombre }}</option>
            @endforeach
        </select>
    @endif

    @php
        $tipoLabels = ['contrato' => 'Contratos', 'propuesta' => 'Propuestas', 'diseno' => 'Diseños y Assets', 'reporte' => 'Reportes', 'otro' => 'Otros'];
        $typeColors = ['pdf' => '#EF4444', 'zip' => '#F59E0B', 'xlsx' => '#10B981', 'fig' => '#0F9D6E'];
    @endphp

    @if ($archivosPorTipo->isEmpty())
        <div class="card empty-state">
            <div class="empty-state__icon"><i class="fa-solid fa-folder-open"></i></div>
            <p class="empty-state__text">No hay documentos para este cliente aún.</p>
        </div>
    @else
        <div style="display:flex; flex-direction:column; gap: var(--space-6);">
            @foreach ($archivosPorTipo as $tipo => $archivos)
                <div>
                    <h2 class="archivos-category__title"><i class="fa-solid fa-folder-open"></i> {{ $tipoLabels[$tipo] ?? ucfirst($tipo) }}</h2>
                    <div class="archivos-grid">
                        @foreach ($archivos as $archivo)
                            <div class="card archivos-file">
                                <a href="{{ route('admin.archivos.download', $archivo) }}" class="archivos-file__icon" style="background:{{ ($typeColors[$archivo->extension] ?? '#64748B') }}18; border-color:{{ ($typeColors[$archivo->extension] ?? '#64748B') }}35;" title="Descargar">
                                    <i class="fa-solid fa-file" style="color:{{ $typeColors[$archivo->extension] ?? '#64748B' }}"></i>
                                </a>
                                <div style="min-width:0; flex:1;">
                                    <a href="{{ route('admin.archivos.download', $archivo) }}" class="archivos-file__name" style="display:block; color:inherit;">{{ $archivo->nombre }}</a>
                                    <div class="archivos-file__meta">
                                        {{ $archivo->tamano ? number_format($archivo->tamano / 1048576, 1).' MB' : '—' }} ·
                                        {{ $archivo->created_at->format('Y-m-d') }}
                                    </div>
                                </div>
                                <form method="POST" action="{{ route('admin.archivos.destroy', $archivo) }}"
                                    data-confirm="¿Eliminar &quot;{{ $archivo->nombre }}&quot;?">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn--icon" title="Eliminar" style="color:var(--text-danger);">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection

@section('scripts')
    @vite('resources/js/archivos.js')
@endsection
