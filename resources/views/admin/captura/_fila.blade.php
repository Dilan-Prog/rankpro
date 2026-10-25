<tr data-captura-fila data-cliente-id="{{ $fila['cliente']->id }}">
    <th scope="row">{{ $fila['cliente']->nombre }}</th>
    @foreach ($fila['celdas'] as $fuente => $celda)
        <td data-fuente="{{ $fuente }}" data-estado="{{ $celda['estado'] }}">
            @if ($celda['url'])
                <a href="{{ $celda['url'] }}">
                    <span class="badge {{ $clases[$celda['estado']] }}">{{ $etiquetas[$celda['estado']] }}</span>
                    <span>{{ $celda['hechas'] }}/{{ $celda['total'] }}</span>
                </a>
            @else
                <span class="badge {{ $clases['no_aplica'] }}">{{ $etiquetas['no_aplica'] }}</span>
            @endif
        </td>
    @endforeach
</tr>
