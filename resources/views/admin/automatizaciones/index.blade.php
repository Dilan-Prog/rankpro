@extends('layouts.admin')

@section('styles')
    @vite('resources/css/admin/automatizaciones.css')
@endsection

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Automatizaciones</h1>
            <p class="page-header__subtitle">{{ $enProceso }} proyectos de automatización</p>
        </div>
        <a href="{{ route('admin.automatizaciones.create') }}" class="btn btn--primary">
            <i class="fa-solid fa-plus"></i> Nuevo Proyecto
        </a>
    </div>

    @if (session('status'))
        <div class="form-status"><i class="fa-solid fa-circle-check" style="margin-top:2px"></i><span>{{ session('status') }}</span></div>
    @endif

    @if ($proyectos->isEmpty())
        <div class="card empty-state">
            <div class="empty-state__icon"><i class="fa-solid fa-robot"></i></div>
            <p class="empty-state__text">Aún no hay proyectos de automatización registrados.</p>
            <a href="{{ route('admin.automatizaciones.create') }}" class="btn btn--primary">Crear el primer proyecto</a>
        </div>
    @else
        <div style="display:flex; flex-direction:column; gap: var(--space-4);">
            @foreach ($proyectos as $p)
                <div class="card card--padded">
                    <div class="proyecto-card__head">
                        <div>
                            <div style="display:flex; align-items:center; gap:10px; margin-bottom:6px; flex-wrap:wrap;">
                                <a href="{{ route('admin.automatizaciones.show', $p['id']) }}" style="font-weight:600; color:var(--color-foreground); font-size:var(--text-lg);">{{ $p['nombre'] }}</a>
                                <x-badge :status="$p['fase_actual']" />
                                <x-badge :status="$p['estado']" />
                            </div>
                            <div style="display:flex; flex-wrap:wrap; gap: 6px; align-items:center; font-size:var(--text-xs); color:var(--color-muted-foreground);">
                                <span>{{ $p['cliente'] }}</span>
                                <span>·</span><span>Ciclo {{ $p['ciclo_actual'] }}</span>
                            </div>
                        </div>
                        <div style="text-align:right; display:flex; align-items:flex-start; gap: var(--space-3);">
                            <div>
                                <div class="u-mono" style="font-size:var(--text-2xl); font-weight:700;">{{ $p['flujos_activos_total'] ?? '—' }}</div>
                                <div style="font-size:var(--text-xs); color:var(--color-muted-foreground);">flujos activos</div>
                            </div>
                            <div style="display:flex; gap:4px;">
                                <a href="{{ route('admin.automatizaciones.edit', $p['id']) }}" class="btn--icon" title="Editar"><i class="fa-solid fa-pen"></i></a>
                                <form method="POST" action="{{ route('admin.automatizaciones.destroy', $p['id']) }}" data-confirm="¿Eliminar el proyecto &quot;{{ $p['nombre'] }}&quot;?">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn--icon" title="Eliminar" style="color:var(--text-danger);"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="proyecto-card__footer">
                        <span style="color:var(--color-muted-foreground);">Horas ahorradas al mes: {{ $p['horas_ahorradas_mes'] ?? '—' }}</span>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
