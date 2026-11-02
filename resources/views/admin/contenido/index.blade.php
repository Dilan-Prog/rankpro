@extends('layouts.admin')

@section('styles')
    @vite('resources/css/admin/contenido.css')
@endsection

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Contenido</h1>
            <p class="page-header__subtitle">
                @if ($cliente)
                    Carpetas, documentos, tablas y archivos de {{ $cliente->empresa ?: $cliente->nombre }}
                @else
                    Material de trabajo organizado por cliente
                @endif
            </p>
        </div>
    </div>

    @if (! $cliente)
        <div class="card empty-state">
            <div class="empty-state__icon"><i class="fa-solid fa-folder-tree"></i></div>
            <p class="empty-state__text">Elige un cliente en el selector «Marca» del menú para ver y crear su contenido.</p>
        </div>
    @else
        @error('archivo')
            <div class="form-status form-status--error"><i class="fa-solid fa-circle-exclamation" style="margin-top:2px"></i><span>{{ $message }}</span></div>
        @enderror

        <div class="cm"
             data-cm-root
             data-url-store="{{ route('admin.contenido.store') }}"
             data-url-subir="{{ route('admin.contenido.subir') }}"
             data-parent-id="{{ $carpetaActual?->id }}"
             data-max-mb="{{ $tipos['maxMb'] }}">

            <div class="cm-toolbar">
                <nav class="cm-migas" aria-label="Ruta de carpetas">
                    @if ($busqueda !== '')
                        <a href="{{ route('admin.contenido.index') }}" class="cm-migas__item">Contenido</a>
                        <span class="cm-migas__sep">/</span>
                        <span class="cm-migas__item is-actual">Búsqueda: «{{ $busqueda }}»</span>
                    @else
                        @if ($carpetaActual)
                            <a href="{{ route('admin.contenido.index') }}" class="cm-migas__item">Contenido</a>
                        @else
                            <span class="cm-migas__item is-actual">Contenido</span>
                        @endif
                        @foreach ($migas as $miga)
                            <span class="cm-migas__sep">/</span>
                            @if ($loop->last)
                                <span class="cm-migas__item is-actual">{{ $miga->nombre }}</span>
                            @else
                                <a href="{{ route('admin.contenido.index', ['carpeta' => $miga->id]) }}" class="cm-migas__item">{{ $miga->nombre }}</a>
                            @endif
                        @endforeach
                    @endif
                </nav>

                <form method="GET" action="{{ route('admin.contenido.index') }}" class="cm-buscador" role="search">
                    <input type="search" name="q" class="input input--search" value="{{ $busqueda }}" placeholder="Buscar por nombre..." aria-label="Buscar por nombre" maxlength="100">
                </form>

                <div class="cm-crear">
                    <button type="button" class="btn btn--primary" data-dropdown-trigger="cmCrearPanel" aria-haspopup="true">
                        <i class="fa-solid fa-plus"></i> Crear <i class="fa-solid fa-chevron-down" style="font-size:10px"></i>
                    </button>
                    <div class="dropdown-panel cm-crear__panel" id="cmCrearPanel" data-dropdown-panel hidden>
                        <button type="button" class="cm-crear__op" data-cm-crear="carpeta"><i class="fa-solid fa-folder"></i> Carpeta</button>
                        <button type="button" class="cm-crear__op" data-cm-crear="documento"><i class="fa-solid fa-file-lines"></i> Documento</button>
                        <button type="button" class="cm-crear__op" data-cm-crear="tabla"><i class="fa-solid fa-table"></i> Tabla</button>
                        <button type="button" class="cm-crear__op" data-cm-crear="tabla" data-plantilla="calendario"><i class="fa-solid fa-calendar-days"></i> Calendario de contenido</button>
                        <button type="button" class="cm-crear__op" data-cm-crear="pizarra"><i class="fa-solid fa-chalkboard"></i> Pizarra en blanco</button>
                        <button type="button" class="cm-crear__op" data-cm-crear="pizarra" data-plantilla="lluvia_ideas"><i class="fa-solid fa-lightbulb"></i> Pizarra: lluvia de ideas</button>
                        <button type="button" class="cm-crear__op" data-cm-crear="pizarra" data-plantilla="flujo"><i class="fa-solid fa-diagram-project"></i> Pizarra: flujo de 3 pasos</button>
                        <button type="button" class="cm-crear__op" data-cm-subir><i class="fa-solid fa-upload"></i> Subir archivo</button>
                    </div>
                </div>
                <input type="file" class="cm-input-archivo" data-cm-input-archivo accept="{{ $tipos['extensiones'] }}" hidden>
            </div>

            @if ($filas->isEmpty())
                <div class="card empty-state">
                    <div class="empty-state__icon"><i class="fa-solid {{ $busqueda !== '' ? 'fa-magnifying-glass' : 'fa-folder-open' }}"></i></div>
                    <p class="empty-state__text">
                        @if ($busqueda !== '')
                            Ningún elemento se llama así.
                        @else
                            {{ $carpetaActual ? 'Esta carpeta está vacía.' : 'Aún no hay contenido para este cliente.' }} Usa «Crear» para empezar.
                        @endif
                    </p>
                </div>
            @else
                <div class="table-wrap">
                    <table class="table cm-tabla">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Tipo</th>
                                <th>Modificado</th>
                                <th aria-label="Acciones"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($filas as $fila)
                                <tr data-cm-fila
                                    data-id="{{ $fila['id'] }}"
                                    data-nombre="{{ $fila['nombre'] }}"
                                    data-parent-id="{{ $fila['parent_id'] }}"
                                    data-tipo="{{ $fila['tipo'] }}"
                                    data-url-actualizar="{{ $fila['url_actualizar'] }}"
                                    data-url-borrar="{{ $fila['url_borrar'] }}">
                                    <td>
                                        <a href="{{ $fila['abrir'] }}" class="cm-nombre">
                                            <i class="fa-solid {{ $fila['icono'] }} cm-nombre__icono cm-nombre__icono--{{ $fila['tipo'] }}"></i>
                                            <span class="cm-nombre__texto">{{ $fila['nombre'] }}</span>
                                        </a>
                                        @if ($busqueda !== '')
                                            <div class="cm-ruta">{{ $fila['ruta'] ? 'En '.$fila['ruta'] : 'En la raíz' }}</div>
                                        @endif
                                    </td>
                                    <td class="cm-meta">{{ $fila['tipo_label'] }}@if ($fila['tamano']) · {{ $fila['tamano'] }}@endif</td>
                                    <td class="cm-meta">{{ $fila['actualizado'] }}</td>
                                    <td class="cm-acciones">
                                        @if ($fila['tipo'] === 'archivo')
                                            <a href="{{ $fila['abrir'] }}" class="btn btn--ghost btn--sm btn--icon" title="Descargar" aria-label="Descargar {{ $fila['nombre'] }}"><i class="fa-solid fa-download"></i></a>
                                        @endif
                                        <button type="button" class="btn btn--ghost btn--sm btn--icon" data-cm-renombrar title="Renombrar" aria-label="Renombrar {{ $fila['nombre'] }}"><i class="fa-solid fa-pen"></i></button>
                                        <button type="button" class="btn btn--ghost btn--sm btn--icon" data-cm-mover title="Mover" aria-label="Mover {{ $fila['nombre'] }}"><i class="fa-solid fa-folder-tree"></i></button>
                                        <button type="button" class="btn btn--ghost btn--sm btn--icon" data-cm-borrar title="Borrar" aria-label="Borrar {{ $fila['nombre'] }}"><i class="fa-solid fa-trash"></i></button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Un solo modal de nombre sirve para crear (carpeta/documento/tabla) y renombrar. --}}
        <x-modal id="cmNombreModal">
            <x-slot:header><h2 data-cm-nombre-titulo>Nuevo elemento</h2></x-slot:header>
            <form data-cm-nombre-form novalidate>
                <div class="field">
                    <label class="field__label" for="cmNombreInput">Nombre</label>
                    <input class="input" type="text" id="cmNombreInput" maxlength="255" required autocomplete="off">
                    <span class="field__error" data-cm-nombre-error hidden></span>
                </div>
                <div class="cm-modal-acciones">
                    <button type="button" class="btn btn--secondary" data-modal-close="cmNombreModal">Cancelar</button>
                    <button type="submit" class="btn btn--primary" data-cm-nombre-guardar>Guardar</button>
                </div>
            </form>
        </x-modal>

        <x-modal id="cmMoverModal">
            <x-slot:header><h2>Mover a otra carpeta</h2></x-slot:header>
            <form data-cm-mover-form novalidate>
                <div class="field">
                    <label class="field__label" for="cmMoverSelect">Carpeta de destino</label>
                    <select class="select" id="cmMoverSelect">
                        <option value="">Raíz (sin carpeta)</option>
                        @foreach ($destinos as $d)
                            <option value="{{ $d['id'] }}">{{ $d['ruta'] }}</option>
                        @endforeach
                    </select>
                    <span class="field__error" data-cm-mover-error hidden></span>
                </div>
                <div class="cm-modal-acciones">
                    <button type="button" class="btn btn--secondary" data-modal-close="cmMoverModal">Cancelar</button>
                    <button type="submit" class="btn btn--primary">Mover</button>
                </div>
            </form>
        </x-modal>
    @endif
@endsection

@section('scripts')
    @vite('resources/js/contenido.js')
@endsection
