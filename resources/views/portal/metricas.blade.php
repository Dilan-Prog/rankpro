@extends('layouts.portal')

@section('titulo', 'Métricas')

@section('contenido')
    <h1 class="portal-titulo">Métricas</h1>
    <p class="portal-sub">Cómo vamos contra las metas acordadas. Una métrica sin meta no se califica.</p>

    @include('portal._semaforo', ['clave' => $general['clave'], 'etiqueta' => $general['etiqueta']])

    @if ($metricas === [])
        <p class="portal-vacio" data-portal-vacio>Todavía no hay métricas para mostrar.</p>
    @else
        <div class="portal-metricas">
            @foreach ($metricas as $m)
                <article class="portal-tarjeta portal-metrica" data-metrica>
                    <div class="portal-tarjeta__cab">
                        <h2>{{ $m['nombre'] }}</h2>
                        @include('portal._semaforo', ['clave' => $m['semaforo'], 'etiqueta' => $m['semaforo_label']])
                    </div>
                    <p class="portal-metrica__valor">
                        @if ($m['valor'] !== null)
                            <strong>{{ $m['valor'] }}</strong> {{ $m['unidad'] }}
                            <span class="portal-lista__meta">({{ $m['mes_valor'] }})</span>
                        @else
                            <span class="portal-lista__meta">Sin dato en los últimos {{ \App\Support\Portal\Metricas::MESES }} meses</span>
                        @endif
                    </p>
                    <p class="portal-lista__meta">
                        @if ($m['meta_texto'] !== null)
                            Meta: {{ $m['meta_texto'] }} {{ $m['unidad'] }} · {{ $m['sentido_label'] }}
                        @else
                            Sin meta definida
                        @endif
                    </p>
                    <div class="portal-tabla-caja">
                        <table class="portal-tabla">
                            <caption class="portal-solo-lectores">Últimos {{ \App\Support\Portal\Metricas::MESES }} meses de {{ $m['nombre'] }}</caption>
                            <thead>
                                <tr>@foreach ($m['historial'] as $h)<th scope="col">{{ $h['mes'] }}</th>@endforeach</tr>
                            </thead>
                            <tbody>
                                <tr>@foreach ($m['historial'] as $h)<td>{{ $h['texto'] }}</td>@endforeach</tr>
                            </tbody>
                        </table>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
@endsection
