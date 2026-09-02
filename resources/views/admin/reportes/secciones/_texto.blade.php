{{--
    Editor de la sección "texto" — {formato, bloques:[{titulo, cuerpo}]}.

    "pares" imprime cada bloque como título/valor (alcance, metodología);
    "narrativa" imprime el cuerpo como párrafos corridos (análisis y riesgos).
--}}
<div class="rep-editor" data-editor="texto">
    <div class="field rep-editor__campo-corto">
        <label class="field__label">Formato</label>
        <select class="select" data-campo-raiz="formato">
            <option value="pares">Pares título / valor</option>
            <option value="narrativa">Narrativa</option>
        </select>
    </div>

    <div class="rep-editor__bloque">
        <div class="rep-editor__bloque-head">
            <h3 class="rep-editor__titulo">Bloques <span class="rep-editor__conteo" data-conteo="bloques"></span></h3>
            <button type="button" class="btn btn--secondary btn--sm" data-add-fila="bloques">
                <i class="fa-solid fa-plus"></i> Añadir bloque
            </button>
        </div>
        <div class="rep-rows" data-lista="bloques" data-vacio="Sin bloques todavía."></div>
    </div>
</div>
