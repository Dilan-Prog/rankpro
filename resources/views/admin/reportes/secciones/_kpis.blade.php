{{--
    Editor de la sección "kpis" —
    {items:[{label, valor, formato, comparativo, direccion, detalle, destacado, desactualizado}]}.

    Como todos los editores del módulo, aquí solo vive el esqueleto: las filas
    las pinta reportes.js desde el objeto `contenido` que la sección lleva en su
    data-contenido, para que un repintado tras un pegado masivo o un alta de
    fila use exactamente el mismo código que el render inicial.
--}}
<div class="rep-editor" data-editor="kpis">
    <div class="rep-editor__bloque">
        <div class="rep-editor__bloque-head">
            <h3 class="rep-editor__titulo">Indicadores <span class="rep-editor__conteo" data-conteo="items"></span></h3>
            <button type="button" class="btn btn--secondary btn--sm" data-add-fila="items">
                <i class="fa-solid fa-plus"></i> Añadir indicador
            </button>
        </div>
        <div class="rep-rows" data-lista="items" data-vacio="Sin indicadores todavía."></div>
    </div>
</div>
