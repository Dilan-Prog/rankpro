{{--
    Editor de la sección "ficha" —
    {dimensiones:[{clave, titulo}], campos:[{clave, titulo, dimension}],
     registros:[{titulo, valores:{clave:{valor, estado}}, veredicto, estado}], nota}.

    Un registro por URL auditada: se pinta como tarjeta y no como fila de tabla
    porque los campos son muchos y de lectura vertical (title, H1, schema...).
    Las dimensiones agrupan esos campos dentro de cada tarjeta; sin ninguna
    definida, los campos se pintan en una sola rejilla.
--}}
<div class="rep-editor" data-editor="ficha">
    <div class="rep-editor__bloque">
        <div class="rep-editor__bloque-head">
            <h3 class="rep-editor__titulo">Dimensiones <span class="rep-editor__conteo" data-conteo="dimensiones"></span></h3>
            <button type="button" class="btn btn--secondary btn--sm" data-add-fila="dimensiones">
                <i class="fa-solid fa-plus"></i> Añadir dimensión
            </button>
        </div>
        <p class="rep-editor__hint">Opcional: agrupa los campos de cada ficha (Indexación, Contenido, Rendimiento).</p>
        <div class="rep-rows" data-lista="dimensiones" data-vacio="Sin dimensiones: los campos se pintan en un solo grupo."></div>
    </div>

    <div class="rep-editor__bloque">
        <div class="rep-editor__bloque-head">
            <h3 class="rep-editor__titulo">Campos de la ficha <span class="rep-editor__conteo" data-conteo="campos"></span></h3>
            <button type="button" class="btn btn--secondary btn--sm" data-add-fila="campos">
                <i class="fa-solid fa-plus"></i> Añadir campo
            </button>
        </div>
        <div class="rep-rows" data-lista="campos" data-vacio="Define al menos un campo para poder capturar registros."></div>
    </div>

    <div class="rep-editor__bloque">
        <div class="rep-editor__bloque-head">
            <h3 class="rep-editor__titulo">Registros <span class="rep-editor__conteo" data-conteo="registros"></span></h3>
            <button type="button" class="btn btn--secondary btn--sm" data-add-fila="registros">
                <i class="fa-solid fa-plus"></i> Añadir registro
            </button>
        </div>
        <div class="rep-fichas" data-lista="registros" data-vacio="Sin registros todavía."></div>
    </div>

    <div class="field">
        <label class="field__label">Nota de la sección</label>
        <textarea class="textarea" data-campo-raiz="nota" placeholder="Criterios de la auditoría, umbrales usados."></textarea>
    </div>
</div>
