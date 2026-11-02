{{--
    Desarrollo index — vista de tabla (solo lectura). $proyectos son las
    filas que arma DesarrolloController::toRow(). Cada celda ordenable lleva
    data-sort con el valor real (no el texto): la fase ordena por su orden
    en el proceso, la entrega por fecha ISO y el progreso como número, así
    desarrollo.js ordena sin parsear lo que se ve. Las filas llevan
    data-vista-item para que los filtros de cliente/estado también las oculten.
--}}
@php
    $columnas = [
        'cliente' => 'Cliente',
        'proyecto' => 'Proyecto',
        'fase' => 'Fase',
        'estado' => 'Estado',
        'entrega' => 'Entrega',
        'progreso' => 'Progreso',
    ];
@endphp
<div class="table-wrap" data-proyectos-tabla>
    <table class="table">
        <thead>
            <tr>
                @foreach ($columnas as $clave => $titulo)
                    <th aria-sort="none">
                        <button type="button" class="tabla-orden" data-ordenar="{{ $clave }}">
                            {{ $titulo }} <i class="fa-solid fa-sort tabla-orden__icono" aria-hidden="true"></i>
                        </button>
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody data-tabla-cuerpo>
            @forelse ($proyectos as $p)
                @php $dias = $p['dias_restantes'] ?? null; @endphp
                <tr data-vista-item data-cliente-id="{{ $p['cliente_id'] }}" data-estado="{{ $p['estado'] }}">
                    <td data-sort="{{ mb_strtolower($p['cliente']) }}">{{ $p['cliente'] }}</td>
                    <td data-sort="{{ mb_strtolower($p['nombre']) }}">
                        <a href="{{ $p['show_url'] }}" style="color:var(--color-foreground); font-weight:500;">{{ $p['nombre'] }}</a>
                    </td>
                    <td data-sort="{{ $p['fase_orden'] }}"><x-badge :status="$p['fase_actual']" /></td>
                    <td data-sort="{{ $p['estado'] }}"><x-badge :status="$p['estado']" /></td>
                    <td data-sort="{{ $p['fecha_entrega_estimada'] ?? '' }}">
                        @if ($p['fecha_entrega_estimada'])
                            <span class="u-mono" style="font-size:var(--text-xs);">{{ $p['fecha_entrega_estimada'] }}</span>
                            @if ($dias !== null)
                                @if ($dias < 0)
                                    <span class="badge badge--danger">Vencida ({{ abs($dias) }}d)</span>
                                @elseif ($dias === 0)
                                    <span class="badge badge--warning">Hoy</span>
                                @else
                                    <span style="font-size:var(--text-xs); color:var(--color-muted-foreground);">en {{ $dias }} d</span>
                                @endif
                            @endif
                        @else
                            <span style="color:var(--color-muted-foreground);">—</span>
                        @endif
                    </td>
                    <td data-sort="{{ $p['porcentaje_avance'] }}">
                        <div class="tabla-progreso" title="{{ $p['avance_origen'] === 'tareas' ? $p['tareas_hechas'].' de '.$p['tareas_total'].' tareas' : 'Avance de la fase de Dirección' }}">
                            <div class="progress-bar"><div class="progress-bar__fill" style="width:{{ $p['porcentaje_avance'] }}%;"></div></div>
                            <span class="u-mono">{{ $p['porcentaje_avance'] }}%</span>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="table__empty">Aún no hay proyectos.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
