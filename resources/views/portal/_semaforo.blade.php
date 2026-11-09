{{-- Insignia de semáforo con texto (el color nunca es lo único que informa). Recibe $clave y $etiqueta. --}}
@php
    $clase = ['verde' => 'verde', 'amarillo' => 'amarillo', 'rojo' => 'rojo'][$clave] ?? 'neutro';
@endphp
<span class="portal-semaforo portal-semaforo--{{ $clase }}"><span class="portal-semaforo__punto" aria-hidden="true"></span>{{ $etiqueta }}</span>
