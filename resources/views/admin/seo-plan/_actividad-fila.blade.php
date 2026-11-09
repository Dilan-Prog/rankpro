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
@endphp
<tr class="is-clickable" data-spl-fila data-id="{{ $a->id }}"
    data-tipo="{{ $a->tipo?->value }}" data-responsable="{{ $a->responsable_id }}"
    data-cluster="{{ $a->cluster_id }}" data-estado="{{ $a->estado->value }}"
    data-actividad="{{ json_encode($datos) }}">
    <td>{{ $a->accion }} @if ($a->estaVencida()) <span class="badge badge--danger">Vencida</span> @endif</td>
    <td>{{ $a->tipo?->label() ?? '—' }}</td>
    <td>{{ $a->cluster?->nombre ?? '—' }}</td>
    <td>{{ $a->responsable?->name ?? '—' }}</td>
    <td class="u-mono">{{ fecha_mx($a->fecha_limite) }}</td>
    <td><x-badge :status="$a->estado" /></td>
</tr>
