@extends('layouts.admin')

@section('styles')
    @vite('resources/css/admin/metricas.css')
@endsection

@section('scripts')
    @vite('resources/js/metricas.js')
@endsection

{{-- Histórico de 12 meses de una métrica: tabla + mini-gráfica SVG inline con la línea de la meta. --}}
@php
    $fmt = fn ($n) => $n === null ? '—' : rtrim(rtrim(number_format((float) $n, 2, '.', ','), '0'), '.');
    $semaforoClase = ['verde' => 'badge--success', 'amarillo' => 'badge--warning', 'rojo' => 'badge--danger', 'sin_meta' => 'badge--neutral', 'sin_dato' => 'badge--neutral'];
    $semaforoTexto = ['verde' => 'Verde', 'amarillo' => 'Amarillo', 'rojo' => 'Rojo', 'sin_meta' => 'Sin meta', 'sin_dato' => 'Sin dato'];
@endphp

@section('content')
    <div class="metricas" data-metricas-historico>
        <div class="page-header">
            <div>
                <h1 class="page-header__title">{{ $metrica->nombre }}</h1>
                <p class="page-header__subtitle">
                    {{ $metrica->cliente->nombre }} · Meta: {{ $metrica->meta === null ? 'sin meta' : $fmt($metrica->meta).($metrica->unidad ? ' '.$metrica->unidad : '') }} · {{ $metrica->sentido->label() }}
                </p>
            </div>
            <a class="btn" href="{{ route('admin.metricas.index', ['cliente' => $metrica->cliente_id, 'mes' => $fin->format('Y-m')]) }}">Volver</a>
        </div>

        <svg class="metricas__grafica" viewBox="0 0 {{ $grafica['ancho'] }} {{ $grafica['alto'] }}" role="img"
             aria-label="Valores de los últimos 12 meses de {{ $metrica->nombre }}" data-metricas-grafica>
            @if ($grafica['meta_y'] !== null)
                <line x1="0" x2="{{ $grafica['ancho'] }}" y1="{{ $grafica['meta_y'] }}" y2="{{ $grafica['meta_y'] }}"
                      stroke="currentColor" stroke-dasharray="6 4" opacity="0.5" data-grafica-meta />
            @endif
            @foreach ($grafica['lineas'] as $linea)
                <polyline fill="none" stroke="currentColor" stroke-width="2" points="{{ $linea }}" data-grafica-linea />
            @endforeach
            @foreach ($grafica['puntos'] as $p)
                <circle cx="{{ $p['x'] }}" cy="{{ $p['y'] }}" r="3.5" fill="currentColor"><title>{{ $p['etiqueta'] }}</title></circle>
            @endforeach
        </svg>

        <x-data-table :headers="['Mes', 'Valor', 'Semáforo', 'Vs. mes anterior', 'Nota']">
            @foreach ($meses as $fila)
                <tr data-mes="{{ $fila['mes']->format('Y-m') }}">
                    <td>{{ $fila['mes']->format('Y-m') }}</td>
                    <td>{{ $fmt($fila['valor']) }}</td>
                    <td><span class="badge {{ $semaforoClase[$fila['semaforo']] }}">{{ $semaforoTexto[$fila['semaforo']] }}</span></td>
                    <td>
                        @if ($fila['variacion'])
                            {{ $fila['variacion']['abs'] > 0 ? '+' : '' }}{{ $fmt($fila['variacion']['abs']) }}
                            @if ($fila['variacion']['pct'] !== null)({{ $fila['variacion']['pct'] > 0 ? '+' : '' }}{{ $fila['variacion']['pct'] }} %)@endif
                        @else
                            —
                        @endif
                    </td>
                    <td>{{ $fila['nota'] ?: '—' }}</td>
                </tr>
            @endforeach
        </x-data-table>
    </div>
@endsection
