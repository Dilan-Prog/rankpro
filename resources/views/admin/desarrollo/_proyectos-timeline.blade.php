{{--
    Desarrollo index — línea de tiempo tipo Gantt (solo lectura). La
    geometría (px por barra, meses, línea de hoy) la calcula
    ProyectoProgreso::lineaTiempo(); aquí solo se pinta. Los proyectos sin
    fecha de inicio o de entrega no tienen barra honesta, se listan aparte.
--}}
@php $lt = \App\Support\ProyectoProgreso::lineaTiempo($proyectos); @endphp
<div data-proyectos-timeline>
    @if ($lt['barras'] === [])
        <div class="empty-state">
            <div class="empty-state__icon"><i class="fa-solid fa-chart-gantt"></i></div>
            <p class="empty-state__text">Ningún proyecto tiene fecha de inicio y de entrega estimada para dibujar la línea de tiempo.</p>
        </div>
    @else
        <div class="timeline card">
            <div class="timeline__scroll">
                <div class="timeline__inner" style="width: calc(var(--timeline-etiqueta) + {{ $lt['ancho'] }}px);">
                    <div class="timeline__fila timeline__fila--cabecera">
                        <div class="timeline__etiqueta">Proyecto</div>
                        <div class="timeline__pista" style="width: {{ $lt['ancho'] }}px;">
                            @foreach ($lt['meses'] as $mes)
                                <span class="timeline__mes" style="left: {{ $mes['left'] }}px; width: {{ $mes['width'] }}px;">{{ $mes['etiqueta'] }}</span>
                            @endforeach
                        </div>
                    </div>
                    @foreach ($lt['barras'] as $b)
                        @php $p = $b['fila']; @endphp
                        <div class="timeline__fila" data-vista-item data-cliente-id="{{ $p['cliente_id'] }}" data-estado="{{ $p['estado'] }}">
                            <div class="timeline__etiqueta">
                                <a href="{{ $p['show_url'] }}" title="{{ $p['nombre'] }}">{{ $p['nombre'] }}</a>
                                <small>{{ $p['cliente'] }}</small>
                            </div>
                            <div class="timeline__pista" style="width: {{ $lt['ancho'] }}px;">
                                <div class="timeline__barra {{ $b['vencido'] ? 'timeline__barra--vencida' : '' }}"
                                    style="left: {{ $b['left'] }}px; width: {{ $b['width'] }}px;"
                                    title="{{ $p['nombre'] }}: {{ fecha_mx($p['fecha_inicio']) }} a {{ fecha_mx($p['fecha_entrega_estimada']) }} · {{ $b['avance'] }}%">
                                    <span class="timeline__avance" style="width: {{ $b['avance'] }}%;"></span>
                                    <span class="timeline__texto">{{ $b['avance'] }}%</span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                    @if ($lt['hoy_left'] !== null)
                        {{-- Se posiciona respecto a .timeline__inner: ancho de la columna de etiquetas + posición en la pista. --}}
                        <div class="timeline__hoy" style="left: calc(var(--timeline-etiqueta) + {{ $lt['hoy_left'] }}px);" title="Hoy"><span>Hoy</span></div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    @if ($lt['sin_fechas'] !== [])
        <p class="timeline__nota">
            Sin fechas para dibujar:
            @foreach ($lt['sin_fechas'] as $p)
                <a href="{{ $p['show_url'] }}">{{ $p['nombre'] }}</a>{{ $loop->last ? '.' : ',' }}
            @endforeach
        </p>
    @endif
</div>
