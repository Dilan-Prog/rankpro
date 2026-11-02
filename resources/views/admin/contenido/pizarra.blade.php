@extends('layouts.admin')

@section('styles')
    {{-- contenido.css aporta la cabecera común (migas, nombre editable); pizarra.css, el lienzo. --}}
    @vite(['resources/css/admin/contenido.css', 'resources/css/admin/pizarra.css'])
@endsection

@section('content')
    {{-- Los datos viajan en un atributo (escapado por Blade) y no en un <script>: ningún texto de la
         pizarra puede cerrar una etiqueta ni inyectar marcado. El JS los lee con JSON.parse. --}}
    <div class="cm pz" data-pz
         data-datos="{{ json_encode($datos, JSON_UNESCAPED_UNICODE) }}"
         data-url-guardar="{{ $urlGuardar }}">

        <x-contenido.cabecera :elemento="$elemento" :migas="$migas" :url-volver="$urlVolver" :url-renombrar="$urlRenombrar">
            <button type="button" class="btn btn--primary" data-pz-guardar><i class="fa-solid fa-floppy-disk"></i> Guardar</button>
        </x-contenido.cabecera>

        <div class="pz-barra" role="toolbar" aria-label="Herramientas de la pizarra">
            <div class="pz-grupo">
                <button type="button" class="pz-herr is-activa" data-pz-herramienta="seleccionar" title="Seleccionar y mover (V)" aria-label="Seleccionar y mover"><i class="fa-solid fa-arrow-pointer"></i></button>
                <button type="button" class="pz-herr" data-pz-herramienta="nota" title="Nota adhesiva" aria-label="Nota adhesiva"><i class="fa-solid fa-note-sticky"></i></button>
                <button type="button" class="pz-herr" data-pz-herramienta="texto" title="Texto" aria-label="Texto"><i class="fa-solid fa-font"></i></button>
                <button type="button" class="pz-herr" data-pz-herramienta="rectangulo" title="Rectángulo" aria-label="Rectángulo"><i class="fa-regular fa-square"></i></button>
                <button type="button" class="pz-herr" data-pz-herramienta="elipse" title="Elipse" aria-label="Elipse"><i class="fa-regular fa-circle"></i></button>
                <button type="button" class="pz-herr" data-pz-herramienta="flecha" title="Flecha (arrastra de un punto a otro)" aria-label="Flecha"><i class="fa-solid fa-arrow-right-long"></i></button>
            </div>

            <div class="pz-grupo">
                <button type="button" class="pz-herr" data-pz-deshacer title="Deshacer (Ctrl+Z)" aria-label="Deshacer" disabled><i class="fa-solid fa-rotate-left"></i></button>
                <button type="button" class="pz-herr" data-pz-rehacer title="Rehacer (Ctrl+Y)" aria-label="Rehacer" disabled><i class="fa-solid fa-rotate-right"></i></button>
                <button type="button" class="pz-herr pz-herr--peligro" data-pz-borrar title="Borrar lo seleccionado (Supr)" aria-label="Borrar lo seleccionado" disabled><i class="fa-solid fa-trash"></i></button>
            </div>

            <div class="pz-grupo pz-colores" data-pz-colores aria-label="Color" hidden></div>

            <div class="pz-grupo pz-grupo--fin">
                <button type="button" class="pz-herr" data-pz-zoom="menos" title="Alejar" aria-label="Alejar"><i class="fa-solid fa-minus"></i></button>
                <button type="button" class="pz-zoom-valor" data-pz-zoom="reiniciar" title="Volver al 100 %" aria-label="Restablecer zoom"><span data-pz-zoom-texto>100%</span></button>
                <button type="button" class="pz-herr" data-pz-zoom="mas" title="Acercar" aria-label="Acercar"><i class="fa-solid fa-plus"></i></button>
            </div>
        </div>

        <div class="pz-lienzo" data-pz-lienzo tabindex="0" aria-label="Lienzo de la pizarra">
            <div class="pz-mundo" data-pz-mundo>
                <svg class="pz-flechas" data-pz-flechas width="1" height="1" aria-hidden="true">
                    <defs>
                        <marker id="pzPunta" markerWidth="10" markerHeight="8" refX="9" refY="4" orient="auto" markerUnits="userSpaceOnUse">
                            <path d="M0 0L10 4L0 8z" fill="context-stroke"></path>
                        </marker>
                    </defs>
                </svg>
            </div>
            <p class="pz-vacio" data-pz-vacio>Elige una herramienta de arriba y haz clic en el lienzo. Arrastra el fondo para moverte y usa la rueda para acercar o alejar.</p>
        </div>
    </div>
@endsection

@section('scripts')
    @vite('resources/js/pizarra.js')
@endsection
