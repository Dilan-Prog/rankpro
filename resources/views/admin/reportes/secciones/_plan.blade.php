{{--
    Editor de la sección "plan" —
    {acciones:[{prioridad, accion, evidencia, area, impacto, esfuerzo, kpi}],
     leyenda, leyenda_prioridades}.

    El score (impacto / esfuerzo) es derivado, nunca capturado: se recalcula en
    vivo al mover impacto o esfuerzo y se muestra como texto de solo lectura,
    para que no viaje al backend un valor que el renderizador vuelve a calcular.
--}}
<div class="rep-editor" data-editor="plan">
    <div class="rep-editor__bloque">
        <div class="rep-editor__bloque-head">
            <h3 class="rep-editor__titulo">Acciones <span class="rep-editor__conteo" data-conteo="acciones"></span></h3>
            <button type="button" class="btn btn--secondary btn--sm" data-add-fila="acciones">
                <i class="fa-solid fa-plus"></i> Añadir acción
            </button>
        </div>
        <div class="rep-rows" data-lista="acciones" data-vacio="Sin acciones todavía."></div>
    </div>

    <div class="field">
        <label class="field__label">Leyenda</label>
        <textarea class="textarea" data-campo-raiz="leyenda" placeholder="Cómo leer las puntuaciones de impacto y esfuerzo."></textarea>
    </div>

    <div class="field">
        <label class="field__label">Leyenda de prioridades</label>
        <textarea class="textarea" data-campo-raiz="leyenda_prioridades" placeholder="Qué plazo implica cada P0 / P1 / P2 / P3."></textarea>
    </div>
</div>
