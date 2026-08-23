@extends('layouts.admin')

@section('styles')
    @vite('resources/css/admin/integraciones.css')
@endsection

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Embudo de Conversiones — {{ $cliente->nombre }}</h1>
            <p class="page-header__subtitle">Arrastra una tarjeta a otra columna para reclasificarla</p>
        </div>
        <div style="display:flex; gap: var(--space-2);">
            <a href="{{ route('admin.clientes.conversiones', $cliente) }}" class="btn btn--secondary">
                <i class="fa-solid fa-table"></i> Ver como tabla
            </a>
            <a href="{{ route('admin.clientes.integraciones', $cliente) }}" class="btn btn--secondary">
                <i class="fa-solid fa-arrow-left"></i> Volver a Integraciones
            </a>
        </div>
    </div>

    @if ($etapas->isEmpty())
        <div class="card empty-state">
            <div class="empty-state__icon"><i class="fa-solid fa-columns"></i></div>
            <p class="empty-state__text">Este cliente no tiene etapas del embudo todavía.</p>
            <a href="{{ route('admin.clientes.conversiones', $cliente) }}" class="btn btn--primary" style="margin-top:var(--space-3);">Agregar etapas</a>
        </div>
    @else
        <div class="kanban" data-kanban data-asignar-base="{{ url('admin/clientes/'.$cliente->id.'/conversiones') }}">
            @php $sinClasificar = $conversiones->whereNull('ads_embudo_etapa_id'); @endphp
            <div class="kanban__column" data-etapa-id="">
                <div class="kanban__column-header">
                    <span>Sin clasificar</span>
                    <span class="kanban__count" data-kanban-count>{{ $sinClasificar->count() }}</span>
                </div>
                <div class="kanban__column-body" data-kanban-dropzone>
                    @foreach ($sinClasificar as $conversion)
                        @include('admin.integraciones._kanban-card', ['conversion' => $conversion, 'columnasPersonalizadas' => $columnasPersonalizadas])
                    @endforeach
                </div>
            </div>

            @foreach ($etapas as $etapa)
                @php $enEtapa = $conversiones->where('ads_embudo_etapa_id', $etapa->id); @endphp
                <div class="kanban__column" data-etapa-id="{{ $etapa->id }}">
                    <div class="kanban__column-header">
                        <span>{{ $etapa->nombre }}</span>
                        <span class="kanban__count" data-kanban-count>{{ $enEtapa->count() }}</span>
                    </div>
                    <div class="kanban__column-body" data-kanban-dropzone>
                        @foreach ($enEtapa as $conversion)
                            @include('admin.integraciones._kanban-card', ['conversion' => $conversion, 'columnasPersonalizadas' => $columnasPersonalizadas])
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection

@section('scripts')
    @vite('resources/js/conversiones-embudo.js')
@endsection
