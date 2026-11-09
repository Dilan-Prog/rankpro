<tr data-captura-fila data-cliente-id="{{ $fila['cliente']->id }}">
    <th scope="row">{{ $fila['cliente']->nombre_comercial }}</th>
    @if ($fila['sin_fuentes'])
        {{-- El perfil no es una fuente de medición: su celda se pinta aparte para que un cliente sin fuentes siga mostrando si le falta ficha. --}}
        <td colspan="{{ count($fila['celdas']) - 1 }}" data-estado="sin_fuentes">
            <span class="badge badge--neutral">Sin fuentes configuradas</span>
            @foreach ($fila['configurar'] as $enlace)
                <a href="{{ $enlace['url'] }}" data-configurar="{{ $enlace['fuente'] }}">{{ $enlace['etiqueta'] }}</a>@if (! $loop->last) · @endif
            @endforeach
        </td>
        @php
            $celdaPerfil = $fila['celdas']['perfil'];
        @endphp
        <td data-fuente="perfil" data-estado="{{ $celdaPerfil['estado'] }}">
            <a href="{{ $celdaPerfil['url'] }}">
                <span class="badge {{ $clases[$celdaPerfil['estado']] }}">{{ $etiquetas[$celdaPerfil['estado']] }}</span>
            </a>
        </td>
    @else
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
    @endif
</tr>
