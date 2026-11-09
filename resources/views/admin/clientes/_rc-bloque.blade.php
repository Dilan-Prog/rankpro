{{--
    Un bloque del tablero de la cuenta. Recibe $bloque con el contrato de App\Support\Tablero\Bloques\Bloque:
    {clave, titulo, icono, estado_semaforo, leyenda, motivo, cifras[], enlace, requiere_cliente_activo}.
    El estado nunca depende solo del color: lleva icono y texto.
--}}
@php
    $estado = $bloque['estado_semaforo'];
    $iconos = ['verde' => 'fa-circle-check', 'amarillo' => 'fa-triangle-exclamation', 'rojo' => 'fa-circle-xmark', 'neutro' => 'fa-circle-minus'];
    $iconoBloque = str_contains($bloque['icono'], ' ') ? $bloque['icono'] : 'fa-solid '.$bloque['icono'];
@endphp
<article class="card rc-bloque rc-bloque--{{ $estado }}" data-bloque="{{ $bloque['clave'] }}" data-estado="{{ $estado }}">
    <header class="rc-bloque__cab">
        <h3 class="rc-bloque__titulo"><i class="{{ $iconoBloque }}" aria-hidden="true"></i> {{ $bloque['titulo'] }}</h3>
        <span class="rc-semaforo rc-semaforo--{{ $estado }}" data-semaforo>
            <i class="fa-solid {{ $iconos[$estado] }}" aria-hidden="true"></i> {{ $bloque['leyenda'] }}
        </span>
    </header>

    <p class="rc-bloque__motivo" data-motivo>{{ $bloque['motivo'] }}</p>

    @if ($bloque['cifras'] === [])
        <div class="rc-sindato" data-sin-dato>
            <i class="fa-solid fa-inbox" aria-hidden="true"></i>
            <span>{{ $bloque['leyenda'] === 'Sin integración' ? 'Sin integración: aquí no llega ningún dato automático.' : 'Todavía no hay cifras que mostrar.' }}</span>
        </div>
    @else
        <dl class="rc-cifras">
            @foreach ($bloque['cifras'] as $cifra)
                <div class="rc-cifra" data-cifra @if ($cifra['semaforo']) data-estado="{{ $cifra['semaforo'] }}" @endif>
                    <dt class="rc-cifra__etiqueta">
                        @if (! empty($cifra['enlace']))
                            <a href="{{ $cifra['enlace'] }}">{{ $cifra['etiqueta'] }}</a>
                        @else
                            {{ $cifra['etiqueta'] }}
                        @endif
                    </dt>
                    <dd class="rc-cifra__cuerpo">
                        <span class="rc-cifra__valor @if ($cifra['valor'] === null) rc-cifra__valor--vacio @endif">
                            @if ($cifra['semaforo'])
                                <i class="fa-solid {{ $iconos[$cifra['semaforo']] }} rc-punto rc-punto--{{ $cifra['semaforo'] }}" aria-hidden="true"></i>
                            @endif
                            {{ $cifra['valor'] ?? 'Sin dato' }}
                        </span>
                        <span class="rc-cifra__origen">
                            @if ($cifra['fecha'])
                                <span data-cifra-fecha>{{ $cifra['fecha'] }}</span> ·
                            @endif
                            <span data-cifra-fuente>{{ $cifra['fuente'] }}</span>
                            @if ($cifra['parcial'])
                                <span class="badge badge--warning" data-cifra-parcial>Mes parcial</span>
                            @endif
                            @if ($cifra['capturado'])
                                · capturado el {{ $cifra['capturado'] }}
                            @endif
                            @if ($cifra['registrado_por'])
                                por {{ $cifra['registrado_por'] }}
                            @endif
                        </span>
                        @if ($cifra['nota'])
                            <span class="rc-cifra__nota">{{ $cifra['nota'] }}</span>
                        @endif
                    </dd>
                </div>
            @endforeach
        </dl>
    @endif

    @if ($bloque['enlace'])
        <footer class="rc-bloque__pie">
            <a href="{{ $bloque['enlace']['url'] }}" class="rc-enlace" data-bloque-enlace>
                {{ $bloque['enlace']['etiqueta'] }} <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
            </a>
            @if ($bloque['requiere_cliente_activo'] && ! $esClienteActivo)
                <span class="rc-aviso" data-rc-aviso-activo>Este módulo usa el cliente activo del menú: fija a {{ $cliente->nombre_comercial }} como activo para abrirlo con su información.</span>
            @endif
        </footer>
    @endif
</article>
