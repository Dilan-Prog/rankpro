{{--
    Editor de la sección "tabla" —
    {columnas:[{clave, titulo, tipo, alineacion}],
     filas:[{valores:{clave:val}, destacada, motivo}],
     filas_excluidas:[{valores:{clave:val}, motivo}], nota_excluidas,
     totales:{clave:modo}, nota}.

    Los valores de cada fila viven bajo `valores`; lo que queda en la raíz es
    metadato de la fila (destacada, motivo), que el editor pinta como dos
    columnas extra al final de la rejilla.

    Las columnas mandan: definen la cabecera de las dos rejillas (filas y filas
    excluidas), el orden del pegado masivo y qué columnas totalizan. El modo de
    total se edita en la propia fila de configuración de cada columna, que es
    donde el usuario ya está pensando en esa columna.

    Las rejillas viven dentro de un contenedor con scroll propio para aguantar
    decenas de filas sin empujar el resto del editor fuera de pantalla.
--}}
<div class="rep-editor" data-editor="tabla">
    <div class="rep-editor__bloque">
        <div class="rep-editor__bloque-head">
            <h3 class="rep-editor__titulo">Columnas <span class="rep-editor__conteo" data-conteo="columnas"></span></h3>
            <button type="button" class="btn btn--secondary btn--sm" data-add-fila="columnas">
                <i class="fa-solid fa-plus"></i> Añadir columna
            </button>
        </div>
        <div class="rep-rows" data-lista="columnas" data-vacio="Define al menos una columna para poder capturar filas."></div>
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

    <div class="rep-editor__bloque rep-editor__bloque--excluidas">
        <div class="rep-editor__bloque-head">
            <h3 class="rep-editor__titulo">Filas excluidas <span class="rep-editor__conteo" data-conteo="filas_excluidas"></span></h3>
            <button type="button" class="btn btn--secondary btn--sm" data-add-fila="filas_excluidas">
                <i class="fa-solid fa-plus"></i> Añadir fila excluida
            </button>
        </div>
        <p class="rep-editor__hint">Filas que se dejan fuera de los totales del entregable pero cuya existencia conviene documentar.</p>
        <div class="rep-grid-scroll">
            <table class="table rep-grid">
                <thead><tr data-cabecera="filas_excluidas"></tr></thead>
                <tbody data-lista="filas_excluidas" data-vacio="Sin filas excluidas."></tbody>
            </table>
        </div>
        <div class="field" style="margin-top: var(--space-3);">
            <label class="field__label">Nota sobre las exclusiones</label>
            <textarea class="textarea" data-campo-raiz="nota_excluidas" placeholder="Por qué se excluyeron estas filas."></textarea>
        </div>
    </div>

    <div class="field">
        <label class="field__label">Nota de la tabla</label>
        <textarea class="textarea" data-campo-raiz="nota" placeholder="Aclaraciones de lectura, fuente de los datos, salvedades."></textarea>
    </div>
</div>
