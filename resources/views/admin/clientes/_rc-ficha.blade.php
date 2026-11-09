{{--
    Ficha mínima del cliente. Recibe $ficha (App\Support\Tablero\FichaCliente): una sección que el usuario no
    puede ver (reportes, propuestas, archivos, precios) llega como null y no se pinta.
--}}
@php
    $telefonoLimpio = $ficha['telefono'] ? preg_replace('/[^\d+]/', '', $ficha['telefono']) : null;
    $srv = $ficha['servicios'];
@endphp
<section class="rc-ficha" aria-label="Ficha del cliente" data-rc-ficha>
    <div class="card rc-tarjeta" data-rc-datos>
        <div class="card__header"><span class="card__header-title">Datos del cliente</span></div>
        <dl class="rc-datos">
            <div><dt>Nombre comercial</dt><dd>{{ $ficha['nombre'] }}</dd></div>
            <div><dt>Contacto</dt><dd>{{ $ficha['contacto'] ?: '—' }}</dd></div>
            <div><dt>Correo</dt><dd>@if ($ficha['email'])<a href="mailto:{{ $ficha['email'] }}">{{ $ficha['email'] }}</a>@else — @endif</dd></div>
            <div><dt>Teléfono / WhatsApp</dt><dd>@if ($telefonoLimpio)<a href="tel:{{ $telefonoLimpio }}" class="u-mono">{{ $ficha['telefono'] }}</a>@else — @endif</dd></div>
            <div><dt>Estado</dt><dd>@if ($ficha['estado'])<x-badge :status="$ficha['estado']" />@else — @endif</dd></div>
        </dl>
    </div>

    <div class="card rc-tarjeta" data-rc-contrato>
        <div class="card__header"><span class="card__header-title">Contrato</span></div>
        <dl class="rc-datos">
            <div><dt>Inicio</dt><dd>{{ $ficha['contrato']['inicio'] ?? '—' }}</dd></div>
            <div><dt>Renovación</dt><dd>{{ $ficha['contrato']['renovacion'] ?? '—' }}</dd></div>
            <div><dt>Frecuencia de pago</dt><dd>{{ $ficha['contrato']['forma_pago'] ?? '—' }}</dd></div>
            <div><dt>Forma de pago</dt><dd>{{ $ficha['contrato']['metodo_pago'] ?? '—' }}</dd></div>
        </dl>
        @if ($ficha['notas'])
            <p class="rc-notas">{{ $ficha['notas'] }}</p>
        @endif
    </div>

    @if ($srv !== null)
        <div class="card rc-tarjeta rc-tarjeta--ancha" data-rc-servicios>
            <div class="card__header"><span class="card__header-title">Servicios y cobro</span></div>
            @if ($srv['filas'] === [])
                <p class="rc-vacio-lista">Este cliente todavía no tiene servicios contratados.</p>
            @else
                <ul class="rc-lista">
                    @foreach ($srv['filas'] as $s)
                        <li class="rc-lista__fila" data-servicio>
                            <div class="rc-lista__principal">
                                <strong>{{ $s['nombre'] }}</strong>
                                <span class="rc-lista__meta">{{ $s['tipo'] }} · {{ $s['cobro'] }}@if ($s['inicio']) · desde {{ $s['inicio'] }}@endif @if ($s['fin']) · hasta {{ $s['fin'] }}@endif</span>
                            </div>
                            <div class="rc-lista__lado">
                                <span class="rc-precio">{{ $s['precio'] }}</span>
                                <x-badge :status="$s['estado']" />
                                @if ($s['vencido'])<span class="badge badge--danger">Vencido</span>@endif
                                @if ($s['aporta_mrr'])<span class="badge badge--primary" title="Servicio activo de cobro mensual">MRR</span>@endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
            <div class="rc-totales">
                <div data-rc-mrr>
                    <span class="rc-totales__etiqueta">MRR real</span>
                    <strong>${{ number_format($srv['mrr'], 2) }} / mes</strong>
                    <span class="rc-totales__fuente">Solo servicios activos de cobro mensual</span>
                </div>
                <div data-rc-proyectos>
                    <span class="rc-totales__etiqueta">Ingresos por proyecto</span>
                    <strong>${{ number_format($srv['proyectos']['total'], 2) }}</strong>
                    <span class="rc-totales__fuente">{{ $srv['proyectos']['proyectos'] }} {{ $srv['proyectos']['proyectos'] === 1 ? 'servicio de pago único' : 'servicios de pago único' }} contratados; no entran al MRR</span>
                </div>
            </div>
        </div>
    @endif

    @if ($ficha['reportes'] !== null)
        <div class="card rc-tarjeta" data-rc-reportes>
            <div class="card__header"><span class="card__header-title">Últimos reportes</span></div>
            @if ($ficha['reportes'] === [])
                <p class="rc-vacio-lista">Sin reportes todavía.</p>
            @else
                <ul class="rc-lista">
                    @foreach ($ficha['reportes'] as $r)
                        <li class="rc-lista__fila">
                            <div class="rc-lista__principal">
                                <a href="{{ $r['url'] }}">{{ $r['titulo'] }}</a>
                                <span class="rc-lista__meta">{{ $r['area'] }}@if ($r['numero']) · {{ $r['numero'] }}@endif @if ($r['fecha']) · {{ $r['fecha'] }}@endif</span>
                            </div>
                            <div class="rc-lista__lado"><x-badge :status="$r['estado']" /></div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif

    @if ($ficha['propuestas'] !== null)
        <div class="card rc-tarjeta" data-rc-propuestas>
            <div class="card__header"><span class="card__header-title">Últimas propuestas</span></div>
            @if ($ficha['propuestas'] === [])
                <p class="rc-vacio-lista">Sin propuestas todavía.</p>
            @else
                <ul class="rc-lista">
                    @foreach ($ficha['propuestas'] as $p)
                        <li class="rc-lista__fila">
                            <div class="rc-lista__principal">
                                <a href="{{ $p['url'] }}">{{ $p['titulo'] }}</a>
                                <span class="rc-lista__meta">@if ($p['folio']){{ $p['folio'] }}@endif @if ($p['fecha']) · {{ $p['fecha'] }}@endif</span>
                            </div>
                            <div class="rc-lista__lado"><x-badge :status="$p['estado']" /></div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif

    @if ($ficha['archivos'] !== null)
        <div class="card rc-tarjeta" data-rc-archivos>
            <div class="card__header">
                <span class="card__header-title">Contratos en PDF</span>
                <a href="{{ $ficha['archivos']['todos_url'] }}" class="rc-enlace">Todos los archivos</a>
            </div>
            @if ($ficha['archivos']['contratos'] === [])
                <p class="rc-vacio-lista">No hay contratos subidos para este cliente.</p>
            @else
                <ul class="rc-lista">
                    @foreach ($ficha['archivos']['contratos'] as $a)
                        <li class="rc-lista__fila">
                            <div class="rc-lista__principal">
                                <a href="{{ $a['url'] }}"><i class="fa-solid fa-file-pdf" aria-hidden="true"></i> {{ $a['nombre'] }}</a>
                                <span class="rc-lista__meta">@if ($a['extension']){{ strtoupper($a['extension']) }} · @endif subido el {{ $a['fecha'] ?? '—' }}</span>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif

    <div class="card rc-tarjeta" data-rc-integraciones>
        <div class="card__header"><span class="card__header-title">Integraciones del cliente</span></div>
        <ul class="rc-lista">
            @foreach ($ficha['integraciones'] as $enlace)
                <li class="rc-lista__fila">
                    <a href="{{ $enlace['url'] }}">{{ $enlace['etiqueta'] }}</a>
                    <i class="fa-solid fa-arrow-right rc-flecha" aria-hidden="true"></i>
                </li>
            @endforeach
        </ul>
    </div>
</section>
