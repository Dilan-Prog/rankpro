@extends('layouts.admin')

@section('styles')
    @vite('resources/css/admin/contenido.css')
@endsection

@section('content')
    <div class="cm" data-cm-documento
         data-url-guardar="{{ $urlGuardar }}"
         data-url-previa="{{ $urlPrevia }}">

        <x-contenido.cabecera :elemento="$elemento" :migas="$migas" :url-volver="$urlVolver" :url-renombrar="$urlRenombrar">
            <button type="button" class="btn btn--primary" data-cm-guardar><i class="fa-solid fa-floppy-disk"></i> Guardar</button>
        </x-contenido.cabecera>

        <div class="cm-editor">
            <div class="cm-editor__entrada">
                <label class="cm-editor__etiqueta" for="cmMarkdown">
                    Markdown <span class="cm-contador" data-cm-palabras>0 palabras</span>
                </label>
                <textarea class="textarea cm-editor__texto" id="cmMarkdown" data-cm-markdown spellcheck="true"
                          placeholder="Escribe aquí. Usa # para títulos, ** para negritas, - para listas.">{{ $elemento->contenido }}</textarea>
            </div>
            <div class="cm-editor__previa">
                <div class="cm-editor__previa-cabecera">
                    <span>Vista previa</span>
                    <button type="button" class="btn btn--secondary btn--sm" data-cm-previa-actualizar>
                        <i class="fa-solid fa-rotate"></i> Actualizar
                    </button>
                </div>
                <div class="cm-editor__previa-cuerpo" data-cm-previa>
                    <p class="cm-placeholder">Escribe Markdown a la izquierda para ver el resultado aquí.</p>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    @vite('resources/js/contenido.js')
@endsection
