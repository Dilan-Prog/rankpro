{{--
    Tarjeta del kanban. Los data-* alimentan los filtros y el modal de edición
    sin pedir nada al servidor; solo la bitácora se carga al abrir el detalle.
--}}
@php
    $datos = [
        'id' => $a->id,
        'accion' => $a->accion,
        'tipo' => $a->tipo?->value,
        'estado' => $a->estado->value,
        'cluster_id' => $a->cluster_id,
        'responsable_id' => $a->responsable_id,
        'fecha_limite' => $a->fecha_limite?->format('Y-m-d'),
        'url_pagina' => $a->url_pagina,
        'medicion_antes' => $a->medicion_antes,
        'medicion_despues' => $a->medicion_despues,
    ];
    $vencida = $a->estaVencida();
@endphp
<article class="spl-tarjeta {{ $vencida ? 'is-vencida' : '' }}" draggable="true" tabindex="0"
    data-spl-item data-id="{{ $a->id }}"
    data-tipo="{{ $a->tipo?->value }}" data-responsable="{{ $a->responsable_id }}"
    data-cluster="{{ $a->cluster_id }}" data-estado="{{ $a->estado->value }}"
    data-actividad="{{ json_encode($datos) }}">
    <div class="spl-tarjeta__top">
        @if ($a->tipo)
            <span class="badge badge--neutral">{{ $a->tipo->label() }}</span>
        @else
            <span class="badge badge--neutral">Sin tipo</span>
        @endif
        @if ($vencida)
            <span class="badge badge--danger">Vencida</span>
        @endif
    </div>
    <div class="spl-tarjeta__titulo">{{ $a->accion }}</div>
    @if ($a->cluster)
        <div class="spl-tarjeta__meta"><i class="fa-solid fa-layer-group"></i> {{ $a->cluster->nombre }}</div>
    @endif
    <div class="spl-tarjeta__pie">
        <span>{{ $a->responsable?->name ?? 'Sin asignar' }}</span>
        <span class="u-mono">
            @if ($a->notas_count) <i class="fa-regular fa-note-sticky" title="Notas"></i> {{ $a->notas_count }} @endif
            {{ fecha_mx($a->fecha_limite, false, '') }}
        </span>
    </div>
</article>
