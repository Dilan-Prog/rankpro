@extends('layouts.portal')

@section('titulo', 'Propuestas')

@section('contenido')
    @php
        $token = request()->route('token');
    @endphp
    <h1 class="portal-titulo">Propuestas</h1>
    <p class="portal-sub">Las propuestas que te hemos enviado.</p>

    @if ($propuestas === [])
        <p class="portal-vacio" data-portal-vacio>Todavía no hay propuestas para mostrar.</p>
    @else
        <section class="portal-tarjeta">
            <ul class="portal-lista">
                @foreach ($propuestas as $p)
                    <li class="portal-lista__fila" data-propuesta="{{ $p['id'] }}">
                        <div class="portal-lista__principal">
                            <strong>{{ $p['titulo'] }}</strong>
                            <span class="portal-lista__meta">
                                @if ($p['folio']){{ $p['folio'] }} · @endif{{ $p['estado_label'] }}@if ($p['fecha']) · {{ fecha_mx($p['fecha']) }}@endif
                            </span>
                        </div>
                        @if ($p['con_pdf'])
                            <a class="portal-boton" href="{{ route('portal.propuestas.descargar', ['token' => $token, 'propuesta' => $p['id']]) }}" rel="nofollow noopener noreferrer">Descargar PDF</a>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
@endsection
