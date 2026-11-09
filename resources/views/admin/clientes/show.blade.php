@extends('layouts.admin')

@section('styles')
    @vite(['resources/css/admin/resumen-cliente.css', 'resources/css/admin/portal-accesos.css'])
@endsection

@section('scripts')
    @vite('resources/js/portal-accesos.js')
@endsection

@section('content')
    @php
        $nombre = $ficha['nombre'];
        $puedeTablero = auth()->user()?->tienePermiso('reportes');
        $conteo = $resumen['conteo'];
        $leyendas = [
            'verde' => ['Al día', 'Hay dato y está al día, sin problemas duros.'],
            'amarillo' => ['Por revisar', 'Dato con más de 30 días sin actualizar, captura incompleta o tareas vencidas.'],
            'rojo' => ['Requiere atención', 'Contrato vencido o pagos vencidos.'],
            'neutro' => ['Sin dato', 'No hay dato (o no hay integración) para calificar. Nunca se pinta verde por no tener datos.'],
        ];
    @endphp

    <div class="rc" data-resumen-cliente data-cliente-id="{{ $cliente->id }}">
        <a href="{{ route('admin.clientes.index') }}" class="rc-volver">
            <i class="fa-solid fa-chevron-left" aria-hidden="true"></i> Clientes
        </a>

        <div class="page-header">
            <div class="rc-titulo">
                <h1 class="page-header__title">{{ $nombre }}</h1>
                <p class="page-header__subtitle">
                    Resumen del cliente
                    @if ($ficha['contacto'])
                        · Contacto: {{ $ficha['contacto'] }}
                    @endif
                </p>
            </div>
            <div class="rc-acciones" data-rc-acciones>
                @if ($esClienteActivo)
                    <span class="badge badge--primary" data-rc-activo><i class="fa-solid fa-star" aria-hidden="true"></i>&nbsp;Cliente activo del menú</span>
                @else
                    <form method="POST" action="{{ route('admin.cliente-activo') }}">
                        @csrf
                        <input type="hidden" name="cliente_id" value="{{ $cliente->id }}">
                        <button type="submit" class="btn btn--secondary" data-rc-fijar-activo>
                            <i class="fa-regular fa-star" aria-hidden="true"></i> Fijar como cliente activo
                        </button>
                    </form>
                @endif
                @if ($puedeTablero)
                    <a href="{{ route('admin.reportes.tablero.index', ['cliente' => $cliente->id]) }}" class="btn btn--secondary" data-rc-tablero-reportes>
                        <i class="fa-solid fa-chart-line" aria-hidden="true"></i> Tablero de Reportes
                    </a>
                @endif
            </div>
        </div>

        @include('admin.clientes._rc-ficha')

        @include('admin.clientes._rc-portal')

        <section class="rc-tablero" aria-labelledby="rcTableroTitulo" data-rc-tablero>
            <div class="rc-tablero__cab">
                <div>
                    <h2 class="rc-seccion-titulo" id="rcTableroTitulo">Tablero de la cuenta</h2>
                    <p class="rc-tablero__sub">Cada bloque trae la fecha y la fuente de sus cifras. Hoy: {{ $resumen['hoy'] }}.</p>
                </div>
                <ul class="rc-conteo" aria-label="Bloques por estado">
                    @foreach ($leyendas as $clave => [$etiqueta])
                        <li class="rc-conteo__item rc-conteo__item--{{ $clave }}" data-rc-conteo="{{ $clave }}">
                            <span class="rc-conteo__n">{{ $conteo[$clave] }}</span> {{ $etiqueta }}
                        </li>
                    @endforeach
                </ul>
            </div>

            <details class="rc-leyenda">
                <summary>¿Cómo se califica cada bloque?</summary>
                <ul>
                    @foreach ($leyendas as $clave => [$etiqueta, $explica])
                        <li><span class="rc-semaforo rc-semaforo--{{ $clave }}">{{ $etiqueta }}</span> {{ $explica }}</li>
                    @endforeach
                    <li>Las métricas propias con meta se califican contra su meta; sin meta no se inventa ningún umbral.</li>
                </ul>
            </details>

            <div class="rc-bloques">
                @foreach ($resumen['bloques'] as $bloque)
                    @include('admin.clientes._rc-bloque', ['bloque' => $bloque])
                @endforeach
            </div>
        </section>
    </div>
@endsection
