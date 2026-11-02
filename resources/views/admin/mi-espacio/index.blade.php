@extends('layouts.admin')

@section('styles')
    @vite('resources/css/admin/mi-espacio.css')
@endsection

@section('scripts')
    @vite('resources/js/mi-espacio.js')
@endsection

{{--
    Mi espacio: tareas personales del usuario en 5 vistas (tarjetas, kanban,
    tabla, calendario, timeline). Los datos viajan embebidos como JSON y el JS
    pinta cada vista en cliente: buscar, filtrar y cambiar de vista no pide nada
    al servidor; solo crear/editar/borrar/mover lo hacen (fetch JSON).
--}}
@php
    $banderas = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;
    $vistas = [
        'tarjetas' => ['Tarjetas', 'fa-table-cells-large'],
        'kanban' => ['Kanban', 'fa-table-columns'],
        'tabla' => ['Tabla', 'fa-list'],
        'calendario' => ['Calendario', 'fa-calendar-days'],
        'timeline' => ['Línea de tiempo', 'fa-chart-gantt'],
    ];
@endphp

@section('content')
    <div class="mi-espacio" data-mi-espacio
         data-vista="{{ $vista }}"
         data-hoy="{{ $hoy }}"
         data-url-crear="{{ route('admin.mi-espacio.store') }}"
         data-url-tarea="{{ url('admin/mi-espacio/tareas') }}">
        <div class="page-header">
            <div>
                <h1 class="page-header__title">Mi espacio</h1>
                <p class="page-header__subtitle" data-me-resumen>—</p>
            </div>
            <button type="button" class="btn btn--primary" data-me-nueva><i class="fa-solid fa-plus"></i> Nueva tarea</button>
        </div>

        <div class="me-barra">
            <div class="me-vistas" role="tablist" aria-label="Vista">
                @foreach ($vistas as $clave => [$etiqueta, $icono])
                    <a href="{{ route('admin.mi-espacio.index', ['vista' => $clave]) }}" role="tab"
                       class="me-vistas__item {{ $vista === $clave ? 'is-active' : '' }}" data-me-vista="{{ $clave }}">
                        <i class="fa-solid {{ $icono }}"></i><span>{{ $etiqueta }}</span>
                    </a>
                @endforeach
            </div>
            <div class="filters-bar me-filtros">
                <input type="search" class="input input--search" placeholder="Buscar tarea…" aria-label="Buscar tarea" data-me-buscar>
                <select class="select" aria-label="Prioridad" data-me-prioridad>
                    <option value="">Toda prioridad</option>
                    <option value="alta">Alta</option>
                    <option value="media">Media</option>
                    <option value="baja">Baja</option>
                </select>
            </div>
        </div>

        <div class="me-contenido" data-me-contenido></div>

        <script type="application/json" data-me-datos>{!! json_encode(['tareas' => $tareas, 'clientes' => $clientes], $banderas) !!}</script>

        <x-modal id="tareaModal">
            <x-slot:header><h2 data-me-modal-titulo>Nueva tarea</h2></x-slot:header>
            <form data-me-form novalidate>
                <div class="field">
                    <label class="field__label" for="me-titulo">Título</label>
                    <input class="input" id="me-titulo" name="titulo" maxlength="255" required>
                </div>
                <div class="field">
                    <label class="field__label" for="me-descripcion">Descripción</label>
                    <textarea class="textarea" id="me-descripcion" name="descripcion" rows="3" maxlength="2000"></textarea>
                </div>
                <div class="me-form-grid">
                    <div class="field">
                        <label class="field__label" for="me-prioridad">Prioridad</label>
                        <select class="select" id="me-prioridad" name="prioridad">
                            <option value="alta">Alta</option>
                            <option value="media" selected>Media</option>
                            <option value="baja">Baja</option>
                        </select>
                    </div>
                    <div class="field">
                        <label class="field__label" for="me-estado">Estado</label>
                        <select class="select" id="me-estado" name="estado">
                            <option value="pendiente">Pendiente</option>
                            <option value="en_progreso">En progreso</option>
                            <option value="en_revision">En revisión</option>
                            <option value="completada">Completada</option>
                        </select>
                    </div>
                    <div class="field">
                        <label class="field__label" for="me-inicio">Fecha de inicio</label>
                        <input class="input" type="date" id="me-inicio" name="fecha_inicio">
                    </div>
                    <div class="field">
                        <label class="field__label" for="me-limite">Vencimiento</label>
                        <input class="input" type="date" id="me-limite" name="fecha_limite">
                    </div>
                </div>
                <div class="field">
                    <label class="field__label" for="me-cliente">Cliente (opcional)</label>
                    <select class="select" id="me-cliente" name="cliente_id">
                        <option value="">Sin cliente</option>
                        @foreach ($clientes as $c)
                            <option value="{{ $c->id }}">{{ $c->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <p class="field__error" data-me-error hidden></p>
                <div class="me-form-pie">
                    <button type="button" class="btn btn--danger" data-me-borrar hidden>Eliminar</button>
                    <span class="me-form-pie__fin">
                        <button type="button" class="btn" data-modal-close="tareaModal">Cancelar</button>
                        <button type="submit" class="btn btn--primary">Guardar</button>
                    </span>
                </div>
            </form>
        </x-modal>
    </div>
@endsection
