@extends('layouts.app')

@section('title', 'Cancelar reunión | RankPro Agencia de Marketing Digital')
@section('description', 'Cancela tu reunión agendada con RankPro.')

@push('styles')
    @vite('resources/css/web/agendar.css')
@endpush

{{--
    Página normal (sin JS): el token en la URL identifica la reunión, el
    controlador ya resolvió $reunion. Si ya está cancelada solo mostramos el
    aviso; si no, un form POST clásico con @csrf hacia agendar.cancelar.confirmar.
    Reutiliza las clases .agendar__tarjeta/.agendar__confirmacion-* que ya
    trae agendar.css (mismo lenguaje visual que /agendar) en vez de tener su
    propio look aparte.
--}}
@section('content')
    @include('components.topbar')
    @include('components.navbar')

    <main>
        <section class="agendar-hero">
            <div class="container agendar-hero__inner">
                <h1 class="agendar-hero__titulo">Cancelar reunión</h1>
            </div>
        </section>

        <section class="agendar-seccion">
            <div class="container">
                <div class="agendar-cancelar__tarjeta">
                    @if (session('status'))
                        <div class="form-status" style="margin-bottom: 1.25rem;"><span>{{ session('status') }}</span></div>
                    @endif

                    @if ($reunion->estado->value === 'cancelada')
                        <div class="agendar__confirmacion">
                            <div class="agendar__confirmacion-icono agendar-cancelar__icono--ok">
                                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"><path d="M20 6L9 17l-5-5"></path></svg>
                            </div>
                            <h2 class="agendar__confirmacion-titulo">Esta reunión ya está cancelada</h2>
                            <p class="agendar__confirmacion-texto">Si quieres, puedes agendar una nueva cuando gustes.</p>
                            <a href="{{ route('agendar.mostrar') }}" class="btn btn-primary">Agendar otra reunión</a>
                        </div>
                    @else
                        <div class="agendar__confirmacion">
                            <div class="agendar__confirmacion-icono agendar-cancelar__icono--alerta">
                                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"><path d="M12 9v4M12 17h.01"></path><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path></svg>
                            </div>
                            <h2 class="agendar__confirmacion-titulo">¿Seguro que quieres cancelar?</h2>
                            <p class="agendar__confirmacion-texto">Esta acción no se puede deshacer.</p>

                            <div class="agendar__confirmacion-cita">
                                <div class="agendar__confirmacion-cita-etiqueta">TU CITA</div>
                                <div class="agendar__confirmacion-cita-fecha">{{ $reunion->inicia_en->translatedFormat('l d \d\e F') }}</div>
                                <div class="agendar__confirmacion-cita-hora">{{ $reunion->inicia_en->format('g:i A') }} · {{ $reunion->nombre }}</div>
                                <div class="agendar__confirmacion-cita-tema">{{ $reunion->email }}</div>
                            </div>

                            <div class="agendar-cancelar__acciones">
                                <a href="{{ route('agendar.mostrar') }}" class="btn btn-outline">Ya no, regresar</a>
                                <form method="POST" action="{{ route('agendar.cancelar.confirmar', $reunion->token) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-primary">Sí, cancelar</button>
                                </form>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </section>
    </main>

    @include('components.footer')
@endsection
