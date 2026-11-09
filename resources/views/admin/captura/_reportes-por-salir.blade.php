{{--
    «Reportes por salir»: envíos programados a 3 días o menos y lo que les falta. Solo avisa,
    no bloquea el envío (se manda lo que haya). Datos: App\Support\Captura\AlertasProgramadas.
--}}
@if (($alertas['total'] ?? 0) > 0)
    <section class="card card--padded captura-por-salir" data-reportes-por-salir aria-label="Reportes por salir">
        <h2 class="card__header-title">Reportes por salir</h2>
        <p class="field__hint">
            {{ $alertas['total'] }} {{ $alertas['total'] === 1 ? 'envío programado' : 'envíos programados' }} en los próximos {{ $alertas['dias_aviso'] }} días.
            Si falta algo, el reporte sale con lo que haya.
        </p>

        <ul class="captura-por-salir__lista">
            @foreach ($alertas['alertas'] as $alerta)
                @php
                    $cuando = $alerta['dias_restantes'] < 0
                        ? 'venció hace '.abs($alerta['dias_restantes']).($alerta['dias_restantes'] === -1 ? ' día' : ' días').' y sigue pendiente'
                        : ($alerta['dias_restantes'] === 0 ? 'sale hoy' : 'sale en '.$alerta['dias_restantes'].($alerta['dias_restantes'] === 1 ? ' día' : ' días'));
                    // El periodo llega como YYYY-MM; en pantalla va como MM/AAAA (fechas visibles en formato mexicano).
                    $periodoTxt = preg_match('/^(\d{4})-(\d{2})$/', (string) $alerta['periodo'], $m) ? $m[2].'/'.$m[1] : $alerta['periodo'];
                @endphp
                <li data-por-salir data-requiere-atencion="{{ $alerta['requiere_atencion'] ? '1' : '0' }}">
                    <strong>{{ $alerta['cliente_nombre_comercial'] }}</strong> · {{ $alerta['area_label'] }}
                    · {{ $cuando }} ({{ fecha_mx($alerta['proximo_envio_en']) }})

                    @if ($alerta['falta_reporte'])
                        <span class="badge badge--danger" data-falta-reporte>
                            {{ $alerta['reporte_estado'] === 'borrador' ? 'El reporte de '.$periodoTxt.' sigue en borrador' : 'Falta el reporte de '.$periodoTxt }}
                        </span>
                        @if ($alerta['reporte_id'])
                            <a href="{{ route('admin.reportes.show', $alerta['reporte_id']) }}">Abrir</a>
                        @endif
                    @else
                        <span class="badge badge--success">Reporte de {{ $periodoTxt }} listo</span>
                    @endif

                    @foreach ($alerta['celdas_faltantes'] as $celda)
                        <span class="badge badge--warning" data-celda-faltante="{{ $celda['fuente'] }}">
                            {{ $celda['etiqueta'] }}: {{ $celda['estado'] === 'parcial' ? 'parcial' : 'falta' }} de {{ $periodoTxt }}
                        </span>
                    @endforeach
                </li>
            @endforeach
        </ul>
    </section>
@endif
