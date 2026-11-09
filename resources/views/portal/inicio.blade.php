@extends('layouts.portal')

@section('titulo', 'Inicio')

@section('contenido')
    @php
        $token = request()->route('token');
    @endphp
    <h1 class="portal-titulo">Bienvenido al portal de {{ $marca['nombre'] }}</h1>
    <p class="portal-sub">Aquí encuentras lo que preparamos para ti: tus reportes, propuestas y métricas. Es una vista de solo lectura.</p>

    <section class="portal-tarjeta" aria-labelledby="portalReportesTit" data-portal-inicio-reportes>
        <div class="portal-tarjeta__cab">
            <h2 id="portalReportesTit">Últimos reportes</h2>
            <a href="{{ route('portal.reportes', ['token' => $token]) }}">Ver todos</a>
        </div>
        @if ($reportes === [])
            <p class="portal-vacio">Todavía no hay reportes entregados.</p>
        @else
            @include('portal._reportes-lista', ['reportes' => $reportes, 'token' => $token])
        @endif
    </section>

    <section class="portal-tarjeta" aria-labelledby="portalMetTit" data-portal-inicio-metricas>
        <div class="portal-tarjeta__cab">
            <h2 id="portalMetTit">Semáforo de métricas</h2>
            <a href="{{ route('portal.metricas', ['token' => $token]) }}">Ver detalle</a>
        </div>
        @include('portal._semaforo', ['clave' => $general['clave'], 'etiqueta' => $general['etiqueta']])
        <p class="portal-lista__meta">{{ $general['calificables'] }} de {{ $general['total'] }} {{ $general['total'] === 1 ? 'métrica' : 'métricas' }} con meta y dato.</p>
    </section>

    <section class="portal-tarjeta" aria-labelledby="portalVigTit">
        <h2 id="portalVigTit">Vigencia de este enlace</h2>
        <p>Vence el {{ fecha_mx($expira) }}. Antes de esa fecha puedes volver a entrar con el mismo enlace.</p>
    </section>
@endsection
