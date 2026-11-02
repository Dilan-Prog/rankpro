{{--
    Tabla de solo lectura de la vista por cliente. $filas: colección de ['keyword', 'actual', 'anterior']
    (ya resueltas por KeywordClienteController: última medición o, si no hay, la caché del banco).
--}}
@php $pos = fn ($v) => $v === null ? '—' : '#'.$v; @endphp
<div class="table-wrap kwx-tabla kwx-tabla--simple">
    <table class="table">
        <thead>
            <tr>
                <th>Palabra clave</th>
                <th>Volumen</th>
                <th>KD</th>
                <th>Posición</th>
                <th>Anterior</th>
                <th>Cambio</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($filas as $f)
                @php
                    $k = $f['keyword'];
                    // Posición menor = mejor: un cambio positivo significa que la keyword sube.
                    $cambio = ($f['actual'] !== null && $f['anterior'] !== null) ? $f['anterior'] - $f['actual'] : null;
                @endphp
                <tr>
                    <td>{{ $k->keyword }}</td>
                    <td class="u-mono">{{ $k->volumen_busqueda !== null ? number_format($k->volumen_busqueda) : '—' }}</td>
                    <td class="u-mono">{{ $k->dificultad ?? '—' }}</td>
                    <td class="u-mono">{{ $pos($f['actual']) }}</td>
                    <td class="u-mono">{{ $pos($f['anterior']) }}</td>
                    <td class="u-mono {{ $cambio > 0 ? 'kwc-sube' : ($cambio < 0 ? 'kwc-baja' : '') }}">
                        {{ $cambio === null ? '—' : ($cambio > 0 ? '+'.$cambio : $cambio) }}
                    </td>
                </tr>
            @empty
                <tr class="table__empty"><td colspan="6">{{ $vacio }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
