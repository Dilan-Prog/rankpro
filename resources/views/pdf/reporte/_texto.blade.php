{{--
    texto (artboard 08). El formato 'pares' es la metodología: tabla de dos
    columnas (170 / resto) con filete inferior. Cada par corta por página
    limpiamente y el concepto en negrita funciona como índice lateral.

    El formato 'narrativa' apila los bloques. El aviso de dato desactualizado no
    se pinta aquí: lo emite el documento antes del cuerpo de la sección, porque
    siempre va antes del texto, nunca al final.
--}}
@php
    $contenido = $seccion['contenido'];
    $bloques = $contenido['bloques'] ?? [];
    $formato = ($contenido['formato'] ?? '') ?: 'pares';
@endphp

@if (empty($bloques))
    <div class="empty">Sin contenido redactado.</div>
@elseif ($formato === 'pares')
    <table class="w pares">
        @foreach ($bloques as $bloque)
            <tr class="avoid {{ $loop->first ? 'primera' : '' }} {{ $loop->last ? 'ultima' : '' }}">
                <td class="pa-k">{{ $bloque['titulo'] ?? '' }}</td>
                <td class="pa-v">{{ $bloque['cuerpo'] ?? '' }}</td>
            </tr>
        @endforeach
    </table>
@else
    @foreach ($bloques as $bloque)
        <div class="bloque">
            @if (!empty($bloque['titulo']))
                <div class="bloque-titulo">{{ $bloque['titulo'] }}</div>
            @endif
            <div class="bloque-cuerpo">{{ $bloque['cuerpo'] ?? '' }}</div>
        </div>
    @endforeach
@endif

@if (!empty($contenido['nota']))
    <div class="nota">{{ $contenido['nota'] }}</div>
@endif
