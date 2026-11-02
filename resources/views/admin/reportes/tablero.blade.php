@extends('layouts.admin')

@section('styles')
    @vite('resources/css/admin/tablero.css')
@endsection

@section('scripts')
    @vite('resources/js/tablero.js')
@endsection

@php
    $fmt = fn (?float $v, int $dec = 1) => $v === null ? '—' : number_format($v, $dec, '.', ',');
    $fechaCorta = fn (?string $f) => $f ? \Illuminate\Support\Carbon::parse($f)->format('d/m/Y') : null;

    if ($datos) {
        $kw = $datos['keywords'];
        $media = $datos['posicion_media'];
        $tareas = $datos['tareas'];

        // Gráfica de la serie: eje invertido (posición 1 arriba), igual que la de Keywords.
        $serie = $datos['serie'];
        $conMedia = collect($serie)->whereNotNull('media');
        $ancho = 640; $alto = 220; $mIzq = 40; $mDer = 16; $mSup = 16; $mInf = 32;
        $techo = $conMedia->isEmpty() ? 10 : max(5, (int) (ceil($conMedia->max('media') / 5) * 5));
        $x = fn (int $i) => count($serie) <= 1 ? $mIzq + ($ancho - $mIzq - $mDer) / 2 : $mIzq + $i * (($ancho - $mIzq - $mDer) / (count($serie) - 1));
        $y = fn (float $v) => $mSup + (($v - 1) / max(1, $techo - 1)) * ($alto - $mSup - $mInf);
        // Los huecos (ronda sin ninguna posición) cortan la línea en vez de unirse con un valor inventado.
        $tramos = []; $tramo = [];
        foreach ($serie as $i => $p) {
            if ($p['media'] === null) { if ($tramo) { $tramos[] = $tramo; $tramo = []; } continue; }
            $tramo[] = round($x($i), 1).','.round($y($p['media']), 1);
        }
        if ($tramo) { $tramos[] = $tramo; }
        $marcasY = array_values(array_unique([1, (int) round(($techo + 1) / 2), $techo]));
        $maxDistribucion = max(1, collect($datos['distribucion'])->max('cantidad'));
    }
@endphp

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Tablero por cliente</h1>
            <p class="page-header__subtitle">
                @if ($cliente)
                    {{ $datos['cliente']['nombre'] }} · lectura de lo ya capturado en Keywords y Desarrollo
                @else
                    Elige un cliente para ver su posicionamiento y sus tareas
                @endif
            </p>
        </div>
        <div class="tablero-acciones">
            <a href="{{ route('admin.reportes.index') }}" class="btn btn--secondary">
                <i class="fa-solid fa-arrow-left"></i> Reportes
            </a>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.reportes.tablero.index') }}" class="filters-bar" data-tablero-form>
        <label for="tableroCliente" class="tablero-selector__label">Cliente</label>
        <select class="select" name="cliente" id="tableroCliente" data-tablero-selector>
            @unless ($cliente)
                <option value="" selected>Selecciona un cliente…</option>
            @endunless
            @foreach ($clientes as $c)
                <option value="{{ $c->id }}" @selected($cliente && $cliente->id === $c->id)>{{ $c->empresa ?: $c->nombre }}</option>
            @endforeach
        </select>
        {{-- Sin JS el selector sigue funcionando con este botón. --}}
        <noscript><button type="submit" class="btn btn--secondary">Ver</button></noscript>
    </form>

    @unless ($cliente)
        <div class="card empty-state">
            <div class="empty-state__icon"><i class="fa-solid fa-chart-line"></i></div>
            <p class="empty-state__text">
                {{ $clientes->isEmpty() ? 'Aún no hay clientes registrados.' : 'Selecciona un cliente para ver su tablero.' }}
            </p>
        </div>
    @else
        {{-- ---------- KPIs ---------- --}}
        <div class="kpi-grid">
            <x-stat-card label="Keywords en seguimiento" :value="$kw['seguimiento']"
                :sub="$kw['sin_medir'] > 0 ? $kw['sin_medir'].' sin ninguna medición' : ($kw['seguimiento'] > 0 ? 'todas con medición' : 'sin keywords')"
                icon="fa-magnifying-glass-chart" color="primary" />
            @foreach (['top3' => 'En top 3', 'top10' => 'En top 10', 'top20' => 'En top 20'] as $clave => $etiqueta)
                <x-stat-card :label="$etiqueta" :value="$kw['medidas'] > 0 ? $datos['top'][$clave]['cantidad'] : '—'"
                    :sub="$datos['top'][$clave]['porcentaje'] !== null ? $fmt($datos['top'][$clave]['porcentaje']).'% de las medidas' : 'sin mediciones'"
                    icon="fa-trophy" color="{{ $clave === 'top3' ? 'emerald' : ($clave === 'top10' ? 'teal' : 'blue') }}" />
            @endforeach
            <x-stat-card label="Posición media" :value="$fmt($media['actual'])"
                :sub="$media['fecha_actual'] ? 'ronda del '.$fechaCorta($media['fecha_actual']) : 'sin mediciones'"
                icon="fa-ranking-star" color="amber" />
            <x-stat-card label="Variación de posición"
                :value="$media['mejora'] === null ? '—' : (($media['mejora'] > 0 ? '+' : '').$fmt($media['mejora']))"
                :sub="$media['mejora'] !== null
                    ? ($media['mejora'] > 0 ? 'subió' : ($media['mejora'] < 0 ? 'bajó' : 'sin cambio')).' vs '.$fechaCorta($media['fecha_anterior']).' · '.$media['comparables'].' keywords comparables'
                    : ($media['fecha_anterior'] ? 'sin keywords comparables entre rondas' : 'sin ronda anterior con la que comparar')"
                icon="fa-arrow-trend-up" color="{{ $media['mejora'] !== null && $media['mejora'] < 0 ? 'red' : 'emerald' }}" />
            <x-stat-card label="Tareas vencidas" :value="$tareas['vencidas']"
                :sub="$tareas['abiertas'] > 0 ? 'de '.$tareas['abiertas'].' abiertas de Desarrollo' : 'sin tareas abiertas de Desarrollo'"
                icon="fa-clock" color="{{ $tareas['vencidas'] > 0 ? 'red' : 'teal' }}" />
        </div>

        @if ($kw['seguimiento'] === 0)
            <div class="card empty-state">
                <div class="empty-state__icon"><i class="fa-solid fa-magnifying-glass"></i></div>
                <p class="empty-state__text">Este cliente no tiene keywords en seguimiento. Agrégalas en Keywords para ver su posicionamiento aquí.</p>
            </div>
        @elseif ($kw['medidas'] === 0)
            <div class="card empty-state">
                <div class="empty-state__icon"><i class="fa-solid fa-ruler"></i></div>
                <p class="empty-state__text">Hay {{ $kw['seguimiento'] }} keywords en seguimiento, pero ninguna tiene una medición de posición todavía.</p>
            </div>
        @else
            <div class="tablero-grid">
                {{-- ---------- Distribución ---------- --}}
                <section class="card tablero-card">
                    <div class="card__header"><span class="card__header-title">Distribución de posiciones</span></div>
                    <div class="tablero-card__body">
                        <ul class="tablero-barras" data-tablero-distribucion>
                            @foreach ($datos['distribucion'] as $fila)
                                <li class="tablero-barras__fila">
                                    <span class="tablero-barras__etiqueta">{{ $fila['etiqueta'] }}</span>
                                    <span class="tablero-barras__pista" role="img"
                                          aria-label="{{ $fila['cantidad'] }} keywords en {{ $fila['etiqueta'] }}">
                                        <span class="tablero-barras__barra tablero-barras__barra--{{ $fila['clave'] }}"
                                              style="width: {{ round($fila['cantidad'] / $maxDistribucion * 100, 1) }}%"></span>
                                    </span>
                                    <span class="tablero-barras__valor">
                                        {{ $fila['cantidad'] }}
                                        @if ($fila['porcentaje'] !== null)<small>{{ $fmt($fila['porcentaje'], 0) }}%</small>@endif
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                        <p class="tablero-nota">Según la última medición de cada una de las {{ $kw['medidas'] }} keywords medidas.</p>
                    </div>
                </section>

                {{-- ---------- Serie ---------- --}}
                <section class="card tablero-card">
                    <div class="card__header"><span class="card__header-title">Posición media por ronda</span></div>
                    <div class="tablero-card__body">
                        @if ($conMedia->isEmpty())
                            <p class="tablero-nota">Ninguna ronda tiene keywords con posición todavía.</p>
                        @else
                            <svg class="tablero-linea" viewBox="0 0 {{ $ancho }} {{ $alto }}" role="img" data-tablero-serie
                                 aria-label="Posición media por ronda; la posición 1 está arriba">
                                @foreach ($marcasY as $marca)
                                    <line class="tablero-linea__rejilla" x1="{{ $mIzq }}" x2="{{ $ancho - $mDer }}" y1="{{ round($y($marca), 1) }}" y2="{{ round($y($marca), 1) }}" />
                                    <text class="tablero-linea__eje" x="{{ $mIzq - 6 }}" y="{{ round($y($marca), 1) + 4 }}" text-anchor="end">{{ $marca }}</text>
                                @endforeach
                                @foreach ($tramos as $puntos)
                                    @if (count($puntos) > 1)
                                        <polyline class="tablero-linea__trazo" points="{{ implode(' ', $puntos) }}" />
                                    @endif
                                @endforeach
                                @foreach ($serie as $i => $p)
                                    @if ($p['media'] !== null)
                                        <circle class="tablero-linea__punto" cx="{{ round($x($i), 1) }}" cy="{{ round($y($p['media']), 1) }}" r="4">
                                            <title>{{ $fechaCorta($p['fecha']) }}: posición media {{ $fmt($p['media']) }} ({{ $p['con_posicion'] }} de {{ $p['medidas'] }} con posición)</title>
                                        </circle>
                                    @endif
                                    @if (count($serie) <= 6 || $i % 2 === 0 || $i === count($serie) - 1)
                                        <text class="tablero-linea__eje" x="{{ round($x($i), 1) }}" y="{{ $alto - 10 }}" text-anchor="middle">{{ \Illuminate\Support\Carbon::parse($p['fecha'])->format('d/m') }}</text>
                                    @endif
                                @endforeach
                            </svg>
                            <p class="tablero-nota">Una ronda es una fecha de medición. Cada punto promedia solo las keywords que tenían posición ese día; la posición 1 queda arriba.</p>
                        @endif
                    </div>
                </section>
            </div>

            {{-- ---------- Mejoras y caídas ---------- --}}
            <div class="tablero-grid">
                @foreach ([['clave' => 'mejores', 'titulo' => 'Mayor mejora', 'vacio' => 'Ninguna keyword subió entre sus dos últimas mediciones.'], ['clave' => 'peores', 'titulo' => 'Mayor caída', 'vacio' => 'Ninguna keyword bajó entre sus dos últimas mediciones.']] as $bloque)
                    <section class="card tablero-card">
                        <div class="card__header"><span class="card__header-title">{{ $bloque['titulo'] }}</span></div>
                        @if (empty($datos[$bloque['clave']]))
                            <p class="tablero-nota tablero-nota--padded">{{ $bloque['vacio'] }}</p>
                        @else
                            <div class="table-wrap">
                                <table class="table" data-tablero-{{ $bloque['clave'] }}>
                                    <thead>
                                        <tr><th>Keyword</th><th class="tablero-num">Antes</th><th class="tablero-num">Ahora</th><th class="tablero-num">Cambio</th></tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($datos[$bloque['clave']] as $f)
                                            <tr>
                                                <td>{{ $f['nombre'] }}</td>
                                                <td class="tablero-num">{{ $f['anterior'] }}</td>
                                                <td class="tablero-num">{{ $f['actual'] }}</td>
                                                <td class="tablero-num">
                                                    <span class="badge {{ $f['mejora'] > 0 ? 'badge--success' : 'badge--danger' }}">
                                                        {{ $f['mejora'] > 0 ? '▲ +' : '▼ ' }}{{ $f['mejora'] }}
                                                    </span>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </section>
                @endforeach
            </div>
            <p class="tablero-nota">Cambio = posición anterior − actual de cada keyword, entre sus dos últimas mediciones con posición. Positivo = subió.</p>
        @endif
    @endunless
@endsection
