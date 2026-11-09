{{--
    Fuente y fecha de una cifra, con insignia ámbar si el dato está viejo. Usage:
    <x-dato-fecha fuente="Auditoría SEO" :fecha="$aud->fecha_auditoria" tipo="auditoria" sin-fecha="Sin fecha de auditoría" />
    $texto sustituye a la fecha en pantalla (p. ej. "Periodo 10/2026" en datos mensuales) pero la
    antigüedad se sigue calculando con $fecha. tipo="calculado" = cifra que sale de now().
    La insignia lleva ícono y texto además del color (no depende solo del ámbar).
--}}
@props(['fecha' => null, 'fuente' => null, 'tipo' => null, 'dias' => null, 'texto' => null, 'sinFecha' => 'Sin fecha de captura'])
@php
    $fechaCarbon = \App\Support\Calidad\Antiguedad::aCarbon($fecha);
    $evaluacion = \App\Support\Calidad\Antiguedad::evaluar($fechaCarbon, $dias, $tipo);
    $esCalculado = $tipo === \App\Support\Calidad\Antiguedad::TIPO_CALCULADO;
    $cuando = $esCalculado ? 'Calculado al momento' : ($texto ?? ($fechaCarbon ? fecha_mx($fechaCarbon) : $sinFecha));
@endphp
<span {{ $attributes->merge(['class' => 'dato-fecha']) }}>
    <span class="dato-fecha__texto">{{ $fuente ? $fuente.' · ' : '' }}{{ $cuando }}</span>
    @if ($evaluacion['estado'] === 'antiguo')
        <span class="dato-fecha__alerta" title="Este dato tiene {{ $evaluacion['dias'] }} días; se considera desactualizado después de {{ $evaluacion['umbral'] }}.">
            <i class="fa-solid fa-clock" aria-hidden="true"></i> Hace {{ $evaluacion['dias'] }} días
        </span>
    @endif
</span>
