{{--
    Línea de tiempo de actividades (solo lectura). Barra = de la fecha de inicio
    (o de creación) a la fecha límite; la geometría viene de
    VistasActividades::lineaTiempo(). Las sin fecha límite no tienen barra honesta
    y se listan aparte. Los data-* alimentan los filtros del navegador.
--}}
@php $lt = \App\Support\SeoPlan\VistasActividades::lineaTiempo($actividades); @endphp
<div data-spl-vista-panel="linea" hidden>
    @if ($lt['barras'] === [])
        <div class="card">
            <div class="empty-state">
                <div class="empty-state__icon"><i class="fa-solid fa-chart-gantt"></i></div>
                <p class="empty-state__text">Ninguna actividad tiene fecha límite para dibujar la línea de tiempo.</p>
            </div>
        </div>
    @else
        <div class="spl-lt card">
            <div class="spl-lt__scroll">
                <div class="spl-lt__inner" style="width: calc(var(--spl-lt-etiqueta) + {{ $lt['ancho'] }}px);">
                    <div class="spl-lt__fila spl-lt__fila--cabecera">
                        <div class="spl-lt__etiqueta">Actividad</div>
                        <div class="spl-lt__pista" style="width: {{ $lt['ancho'] }}px;">
                            @foreach ($lt['meses'] as $mes)
                                <span class="spl-lt__mes" style="left: {{ $mes['left'] }}px; width: {{ $mes['width'] }}px;">{{ $mes['etiqueta'] }}</span>
                            @endforeach
                            @foreach ($lt['semanas'] as $sem)
                                <span class="spl-lt__semana" style="left: {{ $sem['left'] }}px;">{{ $sem['etiqueta'] }}</span>
                            @endforeach
                        </div>
                    </div>
                    @foreach ($lt['barras'] as $b)
                        @php $a = $b['actividad']; @endphp
                        <div class="spl-lt__fila" data-spl-vista-item data-id="{{ $a->id }}"
                            data-tipo="{{ $a->tipo?->value }}" data-responsable="{{ $a->responsable_id }}"
                            data-cluster="{{ $a->cluster_id }}" data-estado="{{ $a->estado->value }}">
                            <div class="spl-lt__etiqueta">
                                <button type="button" class="spl-lt__enlace" data-spl-abrir data-actividad="{{ json_encode(\App\Support\SeoPlan\VistasActividades::datosModal($a)) }}" title="{{ $a->accion }}">{{ $a->accion }}</button>
                                <small>{{ $a->responsable?->name ?? 'Sin asignar' }}</small>
                            </div>
                            <div class="spl-lt__pista" style="width: {{ $lt['ancho'] }}px;">
                                <div class="spl-lt__barra spl-lt__barra--{{ $a->estado->value }} {{ $b['vencida'] ? 'spl-lt__barra--vencida' : '' }}"
                                    style="left: {{ $b['left'] }}px; width: {{ $b['width'] }}px;"
                                    title="{{ $a->accion }}: {{ $b['inicio'] }} a {{ $b['fin'] }} · {{ $a->estado->label() }}">
                                    <span class="spl-lt__texto">{{ $a->estado->label() }}</span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                    @if ($lt['hoy_left'] !== null)
                        <div class="spl-lt__hoy" style="left: calc(var(--spl-lt-etiqueta) + {{ $lt['hoy_left'] }}px);" title="Hoy"><span>Hoy</span></div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    @if ($lt['sin_fecha'] !== [])
        <p class="spl-lt__nota" data-spl-sin-fecha>
            Sin fecha límite (no se dibujan):
            @foreach ($lt['sin_fecha'] as $a)
                <button type="button" class="spl-lt__enlace spl-lt__enlace--inline" data-spl-abrir data-spl-vista-item
                    data-tipo="{{ $a->tipo?->value }}" data-responsable="{{ $a->responsable_id }}" data-cluster="{{ $a->cluster_id }}" data-estado="{{ $a->estado->value }}"
                    data-actividad="{{ json_encode(\App\Support\SeoPlan\VistasActividades::datosModal($a)) }}">{{ $a->accion }}</button>
            @endforeach
        </p>
    @endif
</div>
