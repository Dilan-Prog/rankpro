{{--
    Calendario mensual de actividades (solo lectura): cada actividad cae en el
    día de su fecha límite. Se renderizan todos los meses del rango y el JS solo
    alterna cuál se ve (data-spl-cal-mes). Las actividades sin fecha límite van aparte.
--}}
@php $cal = \App\Support\SeoPlan\VistasActividades::calendario($actividades); @endphp
<div data-spl-vista-panel="calendario" hidden>
    <div class="spl-cal card card--padded" data-spl-cal data-indice-hoy="{{ $cal['indice_hoy'] }}">
        <div class="spl-cal__nav">
            <button type="button" class="btn btn--ghost btn--sm" data-spl-cal-prev aria-label="Mes anterior"><i class="fa-solid fa-chevron-left"></i></button>
            <strong class="spl-cal__titulo" data-spl-cal-titulo aria-live="polite"></strong>
            <button type="button" class="btn btn--ghost btn--sm" data-spl-cal-sig aria-label="Mes siguiente"><i class="fa-solid fa-chevron-right"></i></button>
            <button type="button" class="btn btn--secondary btn--sm" data-spl-cal-hoy>Hoy</button>
        </div>
        @foreach ($cal['meses'] as $mes)
            <div class="spl-cal__mes" data-spl-cal-mes="{{ $mes['clave'] }}" data-etiqueta="{{ $mes['etiqueta'] }}" hidden>
                <div class="spl-cal__scroll">
                    <div class="spl-cal__grid">
                        @foreach (['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'] as $dn)
                            <div class="spl-cal__dow">{{ $dn }}</div>
                        @endforeach
                        @foreach ($mes['semanas'] as $semana)
                            @foreach ($semana as $dia)
                                <div class="spl-cal__dia {{ $dia['del_mes'] ? '' : 'is-fuera' }} {{ $dia['hoy'] ? 'is-hoy' : '' }}">
                                    <span class="spl-cal__num">{{ $dia['numero'] }}</span>
                                    @foreach ($dia['actividades'] as $a)
                                        <button type="button" class="spl-cal__ev spl-cal__ev--{{ $a->estado->value }} {{ $a->estaVencida() ? 'is-vencida' : '' }}"
                                            data-spl-abrir data-spl-vista-item data-id="{{ $a->id }}"
                                            data-tipo="{{ $a->tipo?->value }}" data-responsable="{{ $a->responsable_id }}" data-cluster="{{ $a->cluster_id }}" data-estado="{{ $a->estado->value }}"
                                            data-actividad="{{ json_encode(\App\Support\SeoPlan\VistasActividades::datosModal($a)) }}"
                                            title="{{ $a->accion }} · {{ $a->estado->label() }}">{{ $a->accion }}</button>
                                    @endforeach
                                </div>
                            @endforeach
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    @if ($cal['sin_fecha'] !== [])
        <p class="spl-lt__nota">
            Sin fecha límite (no aparecen en el calendario):
            @foreach ($cal['sin_fecha'] as $a)
                <button type="button" class="spl-lt__enlace spl-lt__enlace--inline" data-spl-abrir data-spl-vista-item
                    data-tipo="{{ $a->tipo?->value }}" data-responsable="{{ $a->responsable_id }}" data-cluster="{{ $a->cluster_id }}" data-estado="{{ $a->estado->value }}"
                    data-actividad="{{ json_encode(\App\Support\SeoPlan\VistasActividades::datosModal($a)) }}">{{ $a->accion }}</button>
            @endforeach
        </p>
    @endif
</div>
