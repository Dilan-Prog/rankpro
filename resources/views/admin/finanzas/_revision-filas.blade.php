{{-- Filas de «datos a revisar»; el enlace abre el modal de edición sobre la fila (finanzas.js, parámetro ?editar). --}}
<div class="table-wrap">
    <table class="table">
        <thead>
            <tr><th>Folio</th><th>Cliente</th><th>Concepto</th><th>Periodo</th><th class="u-right">Monto</th><th>Estado</th><th>Creada</th><th></th></tr>
        </thead>
        <tbody>
            @foreach ($filas as $f)
                <tr>
                    <td class="u-mono">{{ $f['folio'] }}</td>
                    <td>{{ $f['cliente'] }}</td>
                    <td>{{ $f['concepto'] }}@if ($f['tipo'] === 'gasto') <small class="conciliacion-sub">gasto</small>@endif</td>
                    <td>{{ $f['periodo'] }}</td>
                    <td class="u-mono u-right">{{ $fmt($f['monto']) }}</td>
                    <td>
                        <span class="badge {{ $estadoFila[$f['estado']] ?? 'badge--neutral' }}">{{ ucfirst($f['estado']) }}</span>
                        @if ($f['fecha_pago'] && $f['estado'] !== 'pagado')
                            <small class="conciliacion-sub">fecha de pago {{ fecha_mx($f['fecha_pago']) }}</small>
                        @endif
                    </td>
                    <td class="u-mono">{{ $f['creada'] ?? '—' }}</td>
                    <td><a href="{{ $f['editar_url'] }}" class="btn btn--secondary btn--sm" data-revisar-editar="{{ $f['id'] }}"><i class="fa-solid fa-pen"></i> Editar</a></td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
