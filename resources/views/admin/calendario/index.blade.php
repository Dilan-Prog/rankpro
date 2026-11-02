@extends('layouts.admin')

@section('styles')
    @vite('resources/css/admin/calendario.css')
@endsection

@section('scripts')
    @vite('resources/js/calendario.js')
@endsection

{{--
    Calendario: cuadrícula mensual + panel del día. Los eventos manuales se
    crean, editan y borran aquí; reuniones, entregas, pagos y campañas vienen de su módulo
    (solo lectura, con enlace). El mes inicial viaja embebido; al navegar, el JS pide
    el rango de 42 días a la ruta `eventos`.
--}}
@php
    $banderas = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;
@endphp

@section('content')
    <div class="calendario" data-calendario
         data-mes="{{ $mes }}"
         data-hoy="{{ $hoy }}"
         data-url-eventos="{{ route('admin.calendario.eventos') }}"
         data-url-crear="{{ route('admin.calendario.store') }}"
         data-url-borrar="{{ url('admin/calendario/eventos') }}"
         data-url-editar="{{ url('admin/calendario/eventos') }}">
        <div class="page-header">
            <div>
                <h1 class="page-header__title">Calendario</h1>
                <p class="page-header__subtitle">Reuniones, entregas, pagos y campañas de la agencia.</p>
            </div>
        </div>

        <div class="cal-barra">
            <div class="cal-nav">
                <button type="button" class="btn btn--icon" aria-label="Mes anterior" data-cal-anterior><i class="fa-solid fa-chevron-left"></i></button>
                <strong class="cal-nav__titulo" data-cal-titulo>—</strong>
                <button type="button" class="btn btn--icon" aria-label="Mes siguiente" data-cal-siguiente><i class="fa-solid fa-chevron-right"></i></button>
                <button type="button" class="btn btn--sm" data-cal-hoy>Hoy</button>
            </div>
            <div class="cal-filtros" role="group" aria-label="Filtrar por tipo">
                @foreach ($tipos as $tipo)
                    <button type="button" class="cal-filtro is-activo" data-tipo="{{ $tipo['valor'] }}" data-cal-filtro="{{ $tipo['valor'] }}" aria-pressed="true">
                        <span class="cal-punto"></span>{{ $tipo['etiqueta'] }}
                    </button>
                @endforeach
            </div>
        </div>

        <div class="cal-layout">
            <div class="card card--padded cal-mes">
                <div class="cal-cuadricula" data-cal-cuadricula></div>
            </div>

            <aside class="card card--padded cal-panel" aria-live="polite">
                <h2 class="cal-panel__titulo" data-cal-dia-titulo>—</h2>
                <ul class="cal-lista" data-cal-lista></ul>

                <form class="cal-form" data-cal-form novalidate>
                    <h3 class="cal-form__titulo" data-cal-form-titulo>Agregar evento</h3>
                    <div class="field">
                        <label class="field__label" for="cal-titulo">Título</label>
                        <input class="input" id="cal-titulo" name="titulo" maxlength="255" required>
                    </div>
                    <div class="field">
                        <label class="field__label" for="cal-fecha">Fecha</label>
                        <input class="input" type="date" id="cal-fecha" name="fecha" required>
                    </div>
                    <div class="cal-form__fila">
                        <div class="field">
                            <label class="field__label" for="cal-tipo">Tipo</label>
                            <select class="select" id="cal-tipo" name="tipo">
                                @foreach ($tiposManuales as $tipo)
                                    <option value="{{ $tipo['valor'] }}" @selected($tipo['valor'] === 'otro')>{{ $tipo['etiqueta'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label class="field__label" for="cal-hora">Hora (opcional)</label>
                            <input class="input" type="time" id="cal-hora" name="hora">
                        </div>
                    </div>
                    <div class="field">
                        <label class="field__label" for="cal-cliente">Cliente (opcional)</label>
                        <select class="select" id="cal-cliente" name="cliente_id">
                            <option value="">Sin cliente</option>
                            @foreach ($clientes as $c)
                                <option value="{{ $c->id }}">{{ $c->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <p class="field__error" data-cal-error hidden></p>
                    <div class="cal-form__acciones">
                        <button type="submit" class="btn btn--primary" data-cal-guardar>Agregar al día</button>
                        <button type="button" class="btn" data-cal-cancelar hidden>Cancelar edición</button>
                    </div>
                </form>
            </aside>
        </div>

        <script type="application/json" data-cal-datos>{!! json_encode(['eventos' => $eventos, 'tipos' => $tipos], $banderas) !!}</script>
    </div>
@endsection
