@extends('layouts.admin')

{{--
    Tablero de pendientes de captura del mes. El diseño visual se rehace aparte:
    aquí solo hay estructura semántica y data-* (data-estado en cada celda).
--}}
@php
    $resumen = $tablero['resumen'];
    $etiquetas = ['completo' => 'Completo', 'parcial' => 'Parcial', 'falta' => 'Falta', 'no_aplica' => '—'];
    $clases = ['completo' => 'badge--success', 'parcial' => 'badge--warning', 'falta' => 'badge--danger', 'no_aplica' => 'badge--neutral'];
@endphp

@section('content')
    <div class="captura" data-captura>
        <div class="page-header">
            <div>
                <h1 class="page-header__title">Pendientes de captura</h1>
                <p class="page-header__subtitle">Qué le falta medir a cada cliente activo este mes.</p>
            </div>
        </div>

        <p data-captura-resumen>
            <strong>{{ $resumen['pendientes'] }}</strong> de <strong>{{ $resumen['total'] }}</strong> pendientes del mes
        </p>

        <form method="GET" action="{{ route('admin.captura.index') }}" class="filters-bar" data-captura-filtro>
            <input type="month" class="input" name="mes" value="{{ $mes->format('Y-m') }}" aria-label="Mes">
            <label>
                <input type="checkbox" name="pendientes" value="1" @checked($soloPendientes)>
                Solo clientes con pendientes
            </label>
            <button type="submit" class="btn">Ver</button>
        </form>

        @if (count($tablero['filas']) === 0)
            <p class="field__hint" data-captura-vacio>
                {{ $soloPendientes ? 'Ningún cliente tiene pendientes este mes.' : 'No hay clientes activos con captura que hacer.' }}
            </p>
        @else
            <table class="table" data-captura-tabla>
                <thead>
                    <tr>
                        <th scope="col">Cliente</th>
                        @foreach ($fuentes as $etiqueta)
                            <th scope="col">{{ $etiqueta }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($tablero['filas'] as $fila)
                        @include('admin.captura._fila', ['fila' => $fila, 'etiquetas' => $etiquetas, 'clases' => $clases])
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
