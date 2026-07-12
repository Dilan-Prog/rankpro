@extends('layouts.admin')

@section('styles')
    @vite('resources/css/admin/seo.css')
@endsection

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Módulo SEO</h1>
            <p class="page-header__subtitle">{{ $enProceso }} campañas SEO</p>
        </div>
        <a href="{{ route('admin.seo.create') }}" class="btn btn--primary">
            <i class="fa-solid fa-plus"></i> Nueva Campaña SEO
        </a>
    </div>

    @if (session('status'))
        <div class="form-status"><i class="fa-solid fa-circle-check" style="margin-top:2px"></i><span>{{ session('status') }}</span></div>
    @endif

    @if ($campanas->isEmpty())
        <div class="card empty-state">
            <div class="empty-state__icon"><i class="fa-solid fa-magnifying-glass"></i></div>
            <p class="empty-state__text">Aún no hay campañas SEO registradas.</p>
            <a href="{{ route('admin.seo.create') }}" class="btn btn--primary">Crear la primera campaña</a>
        </div>
    @else
        <div style="display:flex; flex-direction:column; gap: var(--space-4);">
            @foreach ($campanas as $c)
                <div class="card card--padded">
                    <div class="proyecto-card__head">
                        <div>
                            <div style="display:flex; align-items:center; gap:10px; margin-bottom:6px; flex-wrap:wrap;">
                                <a href="{{ route('admin.seo.show', $c['id']) }}" style="font-weight:600; color:var(--color-foreground); font-size:var(--text-lg);">{{ $c['nombre'] }}</a>
                                <x-badge :status="$c['fase_actual']" />
                                <x-badge :status="$c['estado']" />
                            </div>
                            <div style="display:flex; flex-wrap:wrap; gap: 6px; align-items:center; font-size:var(--text-xs); color:var(--color-muted-foreground);">
                                <span>{{ $c['cliente'] }}</span>
                                @if ($c['url_sitio'])
                                    <span>·</span><span class="u-mono">{{ $c['url_sitio'] }}</span>
                                @endif
                                <span>·</span><span>Ciclo {{ $c['ciclo_actual'] }}</span>
                            </div>
                        </div>
                        <div style="text-align:right; display:flex; align-items:flex-start; gap: var(--space-3);">
                            <div>
                                <div class="u-mono" style="font-size:var(--text-2xl); font-weight:700;">{{ $c['seo_score'] ?? '—' }}</div>
                                <div style="font-size:var(--text-xs); color:var(--color-muted-foreground);">SEO score</div>
                            </div>
                            <div style="display:flex; gap:4px;">
                                <a href="{{ route('admin.seo.edit', $c['id']) }}" class="btn--icon" title="Editar"><i class="fa-solid fa-pen"></i></a>
                                <form method="POST" action="{{ route('admin.seo.destroy', $c['id']) }}" data-confirm="¿Eliminar la campaña &quot;{{ $c['nombre'] }}&quot;?">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn--icon" title="Eliminar" style="color:var(--text-danger);"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="proyecto-card__footer">
                        <span style="color:var(--color-muted-foreground);">Tráfico orgánico mensual: {{ number_format($c['trafico_organico_mensual'] ?? 0) }}</span>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
