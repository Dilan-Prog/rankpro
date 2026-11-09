@extends('layouts.portal')

@section('titulo', 'Reportes')

@section('contenido')
    @php
        $token = request()->route('token');
    @endphp
    <h1 class="portal-titulo">Reportes</h1>
    <p class="portal-sub">Los reportes que ya te entregamos, del más reciente al más antiguo.</p>

    @if ($reportes === [])
        <p class="portal-vacio" data-portal-vacio>Todavía no hay reportes entregados.</p>
    @else
        <section class="portal-tarjeta">
            @include('portal._reportes-lista', ['reportes' => $reportes, 'token' => $token])
        </section>
    @endif
@endsection
