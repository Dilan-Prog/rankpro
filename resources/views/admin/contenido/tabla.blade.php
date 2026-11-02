@extends('layouts.admin')

@section('styles')
    @vite('resources/css/admin/contenido.css')
@endsection

@section('content')
    {{-- Los datos viajan en un atributo (escapado por Blade) y no en un <script>: así ningún valor de celda
         puede cerrar una etiqueta ni inyectar marcado. El JS los lee con JSON.parse. --}}
    <div class="cm" data-cm-tabla
         data-datos="{{ json_encode($datos, JSON_UNESCAPED_UNICODE) }}"
         data-url-guardar="{{ $urlGuardar }}"
         data-url-importar="{{ $urlImportar }}">

        <x-contenido.cabecera :elemento="$elemento" :migas="$migas" :url-volver="$urlVolver" :url-renombrar="$urlRenombrar">
            <button type="button" class="btn btn--secondary" data-cm-importar-abrir><i class="fa-solid fa-file-import"></i> Importar CSV</button>
            <a href="{{ $urlExportar }}" class="btn btn--secondary" data-cm-exportar><i class="fa-solid fa-file-export"></i> Exportar CSV</a>
            <button type="button" class="btn btn--primary" data-cm-guardar><i class="fa-solid fa-floppy-disk"></i> Guardar</button>
        </x-contenido.cabecera>

        <div class="cm-barra">
            <div class="cm-vistas" role="tablist" aria-label="Vista">
                <button type="button" class="cm-vistas__op" role="tab" data-cm-vista="tabla"><i class="fa-solid fa-table"></i> Tabla</button>
                <button type="button" class="cm-vistas__op" role="tab" data-cm-vista="kanban"><i class="fa-solid fa-table-columns"></i> Kanban</button>
            </div>
            <label class="cm-barra__kanban" data-cm-kanban-selector hidden>
                <span>Agrupar por</span>
                <select class="select" data-cm-kanban-columna aria-label="Columna para agrupar"></select>
            </label>
            <div class="cm-barra__espacio"></div>
            <button type="button" class="btn btn--secondary btn--sm" data-cm-columna-nueva><i class="fa-solid fa-plus"></i> Columna</button>
            <button type="button" class="btn btn--secondary btn--sm" data-cm-fila-nueva><i class="fa-solid fa-plus"></i> Fila</button>
        </div>

        <div class="cm-vacio card empty-state" data-cm-sin-columnas hidden>
            <div class="empty-state__icon"><i class="fa-solid fa-table"></i></div>
            <p class="empty-state__text">Esta tabla no tiene columnas. Agrega la primera o importa un CSV.</p>
        </div>

        <div data-cm-panel-tabla></div>
        <div data-cm-panel-kanban hidden></div>
    </div>

    {{-- Columna: crear o editar (el tipo solo se elige al crear: cambiarlo después invalidaría los valores ya escritos). --}}
    <x-modal id="cmColumnaModal">
        <x-slot:header><h2 data-cm-columna-titulo>Nueva columna</h2></x-slot:header>
        <form data-cm-columna-form novalidate>
            <div class="field">
                <label class="field__label" for="cmColumnaNombre">Nombre</label>
                <input class="input" type="text" id="cmColumnaNombre" maxlength="80" required autocomplete="off">
            </div>
            <div class="field" style="margin-top: var(--space-3);">
                <label class="field__label" for="cmColumnaTipo">Tipo</label>
                <select class="select" id="cmColumnaTipo">
                    <option value="texto">Texto</option>
                    <option value="numero">Número</option>
                    <option value="fecha">Fecha</option>
                    <option value="seleccion">Selección</option>
                </select>
            </div>
            <div class="field" style="margin-top: var(--space-3);" data-cm-opciones-campo hidden>
                <label class="field__label" for="cmColumnaOpciones">Opciones (una por línea)</label>
                <textarea class="textarea" id="cmColumnaOpciones" rows="5"></textarea>
                <span class="field__hint">Si quitas una opción, las filas que la usaban quedan sin valor.</span>
            </div>
            <span class="field__error" data-cm-columna-error hidden></span>
            <div class="cm-modal-acciones">
                <button type="button" class="btn btn--danger" data-cm-columna-borrar hidden>Eliminar columna</button>
                <span class="cm-barra__espacio"></span>
                <button type="button" class="btn btn--secondary" data-modal-close="cmColumnaModal">Cancelar</button>
                <button type="submit" class="btn btn--primary">Aceptar</button>
            </div>
        </form>
    </x-modal>

    {{-- Fila: edición completa desde una tarjeta del Kanban. --}}
    <x-modal id="cmFilaModal">
        <x-slot:header><h2>Editar fila</h2></x-slot:header>
        <form data-cm-fila-form novalidate>
            <div data-cm-fila-campos></div>
            <div class="cm-modal-acciones">
                <button type="button" class="btn btn--danger" data-cm-fila-borrar>Eliminar fila</button>
                <span class="cm-barra__espacio"></span>
                <button type="submit" class="btn btn--primary">Listo</button>
            </div>
        </form>
    </x-modal>

    <x-modal id="cmImportarModal">
        <x-slot:header><h2>Importar CSV</h2></x-slot:header>
        <form data-cm-importar-form novalidate>
            <div class="field">
                <label class="field__label" for="cmImportarArchivo">Archivo CSV (máx. 2 MB)</label>
                <input class="input" type="file" id="cmImportarArchivo" accept=".csv,text/csv,text/plain">
                <span class="field__hint">La primera fila es el encabezado. Se aceptan comas o punto y coma.</span>
            </div>
            <div class="field" style="margin-top: var(--space-3);">
                <span class="field__label">Qué hacer con los datos actuales</span>
                <label class="cm-radio"><input type="radio" name="cmImportarModo" value="agregar" checked> Agregar las filas (se emparejan columnas por nombre)</label>
                <label class="cm-radio"><input type="radio" name="cmImportarModo" value="reemplazar"> Reemplazar toda la tabla</label>
            </div>
            <span class="field__error" data-cm-importar-error hidden></span>
            <div class="cm-modal-acciones">
                <button type="button" class="btn btn--secondary" data-modal-close="cmImportarModal">Cancelar</button>
                <button type="submit" class="btn btn--primary">Importar</button>
            </div>
        </form>
    </x-modal>
@endsection

@section('scripts')
    @vite('resources/js/contenido.js')
@endsection
