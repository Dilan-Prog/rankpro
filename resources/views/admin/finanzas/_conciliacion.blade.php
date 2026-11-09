{{-- Conciliación SOLO LECTURA: esperado (servicios mensuales) vs facturado vs cobrado, y datos a revisar. Nada aquí crea, corrige ni borra filas. --}}
@php
    $fmt = fn ($x) => '$' . number_format((float) $x, 2);
    $estadoServicio = [
        'sin_fila' => ['Sin fila de ingreso', 'badge--danger'],
        'pendiente' => ['Facturado, sin cobrar', 'badge--warning'],
        'pagado' => ['Cobrado', 'badge--success'],
    ];
    $estadoFila = ['pagado' => 'badge--success', 'pendiente' => 'badge--warning', 'vencido' => 'badge--danger'];
    $totalRevision = $revision['total'];
@endphp

<div class="card card--padded conciliacion-card">
    <div class="chart-head">
        <h2 class="card__header-title">Esperado, facturado y cobrado por mes</h2>
    </div>
    <p class="conciliacion-nota">
        <strong>Esperado</strong> = precio mensual de los servicios de cobro mensual activos en ese mes (respeta fecha de inicio y, en meses cerrados, fecha de fin).
        <strong>Facturado</strong> = filas de ingreso del mes, en cualquier estado. <strong>Cobrado</strong> = las ya pagadas.
        Es solo una comparación: no se crea ni se cambia ningún registro.
    </p>
    <div class="table-wrap">
        <table class="table conciliacion-tabla">
            <thead>
                <tr>
                    <th>Mes</th>
                    <th class="u-right">Esperado</th>
                    <th class="u-right">Facturado</th>
                    <th class="u-right">Cobrado</th>
                    <th class="u-right">Por cobrar</th>
                    <th class="u-right">Falta facturar</th>
                    <th>Situación</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($conciliacionMeses as $m)
                    <tr data-conciliacion-mes="{{ $m['periodo'] }}">
                        <td>
                            <strong>{{ $m['etiqueta'] }}</strong>
                            @if ($m['es_actual'])<span class="conciliacion-chip">en curso</span>@endif
                        </td>
                        <td class="u-mono u-right">
                            {{ $fmt($m['esperado']) }}
                            @if ($m['incluye_vencidos'] > 0)
                                <small class="conciliacion-sub" title="Servicios activos con fecha de fin vencida: el MRR los sigue contando hasta que se renueven o cierren.">{{ $fmt($m['incluye_vencidos']) }} con fecha de fin vencida</small>
                            @endif
                        </td>
                        <td class="u-mono u-right">
                            {{ $fmt($m['facturado']) }}
                            @if ($m['sobre_esperado'] > 0)
                                <small class="conciliacion-sub">{{ $fmt($m['sobre_esperado']) }} sobre lo esperado</small>
                            @endif
                        </td>
                        <td class="u-mono u-right">{{ $fmt($m['cobrado']) }}</td>
                        <td class="u-mono u-right {{ $m['falta_cobrar'] > 0 ? 'conciliacion-alerta' : '' }}">{{ $fmt($m['por_cobrar']) }}</td>
                        <td class="u-mono u-right {{ $m['falta_facturar'] > 0 ? 'conciliacion-alerta conciliacion-alerta--roja' : '' }}">{{ $fmt($m['falta_facturar']) }}</td>
                        <td>
                            <div class="conciliacion-etiquetas">
                                @foreach ($m['estado']['etiquetas'] as $e)
                                    <span class="badge badge--{{ $e['tono'] }}">{{ $e['texto'] }}</span>
                                @endforeach
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="card card--padded conciliacion-card">
    <div class="chart-head">
        <h2 class="card__header-title">Detalle por cliente y servicio</h2>
    </div>
    <p class="conciliacion-nota">Cada servicio mensual indica si ese mes tiene una fila de ingreso vinculada a él. Las filas sin servicio vinculado se muestran aparte porque no se pueden cruzar.</p>

    @foreach (array_reverse($conciliacionMeses) as $m)
        <details class="conciliacion-detalle" @if ($m['estado']['clave'] === 'falta_facturar' || $m['estado']['clave'] === 'falta_cobrar') open @endif>
            <summary>
                <strong>{{ $m['etiqueta'] }}</strong>
                <span class="conciliacion-resumen">esperado {{ $fmt($m['esperado']) }} · facturado {{ $fmt($m['facturado']) }} · cobrado {{ $fmt($m['cobrado']) }}</span>
                @foreach ($m['estado']['etiquetas'] as $e)
                    <span class="badge badge--{{ $e['tono'] }}">{{ $e['texto'] }}</span>
                @endforeach
            </summary>

            @if (empty($m['clientes']))
                <p class="conciliacion-nota">Sin servicios mensuales esperados ni ingresos registrados este mes.</p>
            @else
                @foreach ($m['clientes'] as $c)
                    <div class="conciliacion-cliente">
                        <div class="conciliacion-cliente__head">
                            <strong>{{ $c['cliente'] }}</strong>
                            <span class="conciliacion-resumen">esperado {{ $fmt($c['esperado']) }} · facturado {{ $fmt($c['facturado']) }} · cobrado {{ $fmt($c['cobrado']) }} · por cobrar {{ $fmt($c['por_cobrar']) }}</span>
                        </div>
                        <div class="table-wrap">
                            <table class="table">
                                <tbody>
                                    @foreach ($c['servicios'] as $s)
                                        <tr>
                                            <td>
                                                {{ $s['nombre'] }}
                                                @if ($s['fecha_fin_vencida'])
                                                    <small class="conciliacion-sub" title="Cuenta como esperado porque el MRR sigue contando servicios activos con fecha de fin vencida.">fecha de fin vencida</small>
                                                @endif
                                            </td>
                                            <td class="u-mono u-right">{{ $fmt($s['precio']) }}</td>
                                            <td class="u-mono u-right">{{ $s['estado'] === 'sin_fila' ? '—' : $fmt($s['facturado']) }}</td>
                                            <td><span class="badge {{ $estadoServicio[$s['estado']][1] }}">{{ $estadoServicio[$s['estado']][0] }}</span></td>
                                        </tr>
                                    @endforeach
                                    @if ($c['sin_servicio']['filas'] > 0)
                                        <tr class="conciliacion-aparte">
                                            <td>{{ $c['sin_servicio']['filas'] }} {{ $c['sin_servicio']['filas'] === 1 ? 'fila' : 'filas' }} de ingreso sin servicio vinculado</td>
                                            <td></td>
                                            <td class="u-mono u-right">{{ $fmt($c['sin_servicio']['monto']) }}</td>
                                            <td><span class="badge badge--neutral">No se puede cruzar</span></td>
                                        </tr>
                                    @endif
                                    @if ($c['otros_servicios']['filas'] > 0)
                                        <tr class="conciliacion-aparte">
                                            <td>{{ $c['otros_servicios']['filas'] }} {{ $c['otros_servicios']['filas'] === 1 ? 'fila' : 'filas' }} de otros servicios (proyectos, pago único)</td>
                                            <td></td>
                                            <td class="u-mono u-right">{{ $fmt($c['otros_servicios']['monto']) }}</td>
                                            <td><span class="badge badge--neutral">Fuera del esperado</span></td>
                                        </tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endforeach
            @endif
        </details>
    @endforeach
</div>

<div class="card card--padded conciliacion-card" id="finanzasRevision">
    <div class="chart-head">
        <h2 class="card__header-title">Posibles duplicados y datos a revisar</h2>
        <span class="badge {{ $totalRevision > 0 ? 'badge--warning' : 'badge--success' }}">{{ $totalRevision }} {{ $totalRevision === 1 ? 'punto' : 'puntos' }}</span>
    </div>
    <p class="conciliacion-nota">Solo se listan: nada se borra ni se corrige solo. Abre cada fila para decidir cuál es la correcta.</p>

    @if ($totalRevision === 0)
        <p class="conciliacion-nota">No se encontró nada que revisar.</p>
    @endif

    @if (! empty($revision['duplicados']))
        <h3 class="conciliacion-subtitulo">Posibles duplicados ({{ count($revision['duplicados']) }})</h3>
        @foreach ($revision['duplicados'] as $g)
            <div class="conciliacion-grupo">
                <p class="conciliacion-grupo__motivo">{{ $g['motivo'] }} · si fuera un doble registro sobrarían {{ $fmt($g['monto_sobrante']) }}</p>
                @include('admin.finanzas._revision-filas', ['filas' => $g['filas'], 'estadoFila' => $estadoFila, 'fmt' => $fmt])
            </div>
        @endforeach
    @endif

    @foreach ($revision['inconsistencias'] as $g)
        <h3 class="conciliacion-subtitulo">{{ $g['titulo'] }} ({{ count($g['filas']) }})</h3>
        <p class="conciliacion-nota">{{ $g['explicacion'] }}</p>
        <div class="conciliacion-grupo">
            @include('admin.finanzas._revision-filas', ['filas' => $g['filas'], 'estadoFila' => $estadoFila, 'fmt' => $fmt])
        </div>
    @endforeach
</div>
