@extends('layouts.admin')

@section('styles')
    @vite('resources/css/admin/desarrollo.css')
@endsection

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Módulo de Desarrollo</h1>
            <p class="page-header__subtitle">{{ $enProceso }} proyectos en proceso</p>
        </div>
        <a href="{{ route('admin.desarrollo.create') }}" class="btn btn--primary">
            <i class="fa-solid fa-plus"></i> Nuevo Proyecto
        </a>
    </div>

    @if (session('status'))
        <div class="form-status"><i class="fa-solid fa-circle-check" style="margin-top:2px"></i><span>{{ session('status') }}</span></div>
    @endif

    @if ($proyectos->isEmpty())
        <div class="card empty-state">
            <div class="empty-state__icon"><i class="fa-solid fa-code"></i></div>
            <p class="empty-state__text">Aún no hay proyectos registrados.</p>
            <a href="{{ route('admin.desarrollo.create') }}" class="btn btn--primary">Crear el primer proyecto</a>
        </div>
    @else
        <div style="display:flex; flex-direction:column; gap: var(--space-4);">
            @foreach ($proyectos as $p)
                <div class="card card--padded">
                    <div class="proyecto-card__head">
                        <div>
                            <div style="display:flex; align-items:center; gap:10px; margin-bottom:6px; flex-wrap:wrap;">
                                <a href="{{ route('admin.desarrollo.show', $p['id']) }}" style="font-weight:600; color:var(--color-foreground); font-size:var(--text-lg);">{{ $p['nombre'] }}</a>
                                <x-badge :status="$p['fase_actual']" />
                                <x-badge :status="$p['estado']" />
                            </div>
                            <div style="display:flex; flex-wrap:wrap; gap: 6px; align-items:center; font-size:var(--text-xs); color:var(--color-muted-foreground);">
                                <span>{{ $p['cliente'] }}</span><span>·</span><span>{{ \App\Support\Labels::tipoProyecto($p['tipo']) }}</span>
                                @if ($p['responsable'])
                                    <span>·</span><span>Responsable: {{ $p['responsable'] }}</span>
                                @endif
                            </div>
                        </div>
                        <div style="text-align:right; display:flex; align-items:flex-start; gap: var(--space-3);">
                            <div>
                                <div class="u-mono" style="font-size:var(--text-2xl); font-weight:700;">{{ $p['porcentaje_avance'] }}%</div>
                                <div style="font-size:var(--text-xs); color:var(--color-muted-foreground);">completado</div>
                            </div>
                            <div style="display:flex; gap:4px;">
                                <a href="{{ route('admin.desarrollo.edit', $p['id']) }}" class="btn--icon" title="Editar"><i class="fa-solid fa-pen"></i></a>
                                <form method="POST" action="{{ route('admin.desarrollo.destroy', $p['id']) }}" data-confirm="¿Eliminar el proyecto &quot;{{ $p['nombre'] }}&quot;?">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn--icon" title="Eliminar" style="color:var(--text-danger);"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="progress-bar" style="margin: var(--space-4) 0;">
                        <div class="progress-bar__fill" style="width:{{ $p['porcentaje_avance'] }}%;"></div>
                    </div>

                    <div class="proyecto-card__footer">
                        <div style="display:flex; gap:12px; color:var(--color-muted-foreground);">
                            <span>Inicio: {{ $p['fecha_inicio'] ?? '—' }}</span>
                            <span>Entrega estimada: {{ $p['fecha_entrega_estimada'] ?? '—' }}</span>
                        </div>
                        <div class="u-mono" style="display:flex; gap:12px;">
                            <span style="color:var(--text-success)">${{ number_format($p['pagos_recibidos']) }} cobrado</span>
                            @if ($p['presupuesto'] - $p['pagos_recibidos'] > 0)
                                <span style="color:var(--text-warning)">${{ number_format($p['presupuesto'] - $p['pagos_recibidos']) }} pendiente</span>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
