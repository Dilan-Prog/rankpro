{{--
    Editor de la sección "serie" —
    {etiqueta_x, series:[{clave, titulo, eje, color}], filas:[{x, valores:{clave:num}}], lectura}.

    Cada serie declara su eje (izquierdo o derecho, para escalas no comparables)
    y su color; ambos los aplica el renderizador del entregable.

    Las series se configuran arriba porque definen las columnas de la rejilla de
    abajo: cambiar una serie repinta la cabecera y las celdas de todas las filas.
--}}
<div class="rep-editor" data-editor="serie">
    <div class="rep-editor__bloque">
        <div class="rep-editor__bloque-head">
            <h3 class="rep-editor__titulo">Series <span class="rep-editor__conteo" data-conteo="series"></span></h3>
            <button type="button" class="btn btn--secondary btn--sm" data-add-fila="series">
                <i class="fa-solid fa-plus"></i> Añadir serie
            </button>
        </div>
        <div class="rep-rows" data-lista="series" data-vacio="Define al menos una serie para poder capturar filas."></div>
    </div>

    <div class="field rep-editor__campo-corto">
        <label class="field__label">Etiqueta del eje X</label>
        <input class="input" type="text" data-campo-raiz="etiqueta_x" placeholder="Fecha">
    </div>

    <div class="rep-editor__bloque">
        <div class="rep-editor__bloque-head">
            <h3 class="rep-editor__titulo">Filas <span class="rep-editor__conteo" data-conteo="filas"></span></h3>
            <button type="button" class="btn btn--secondary btn--sm" data-add-fila="filas">
                <i class="fa-solid fa-plus"></i> Añadir fila
            </button>
        </div>
        <div class="rep-grid-scroll">
            <table class="table rep-grid">
                <thead><tr data-cabecera="filas"></tr></thead>
                <tbody data-lista="filas" data-vacio="Sin filas todavía."></tbody>
            </table>
        </div>
    </div>

    <div class="field">
        <label class="field__label">Lectura de la serie</label>
        <textarea class="textarea" data-campo-raiz="lectura" placeholder="Qué cuenta esta serie: quiebres, estacionalidad, causas."></textarea>
    </div>
</div>
