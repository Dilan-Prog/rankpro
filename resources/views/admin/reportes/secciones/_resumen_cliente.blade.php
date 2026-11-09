{{--
    Editor de la sección "resumen_cliente" — la versión de 1 página para el cliente:
    {logros:[{texto}], retos:[{texto}], proximos_pasos:[{texto}], grafica:{...}}.

    Los logros y retos que trae "Generar reporte del mes" son SUGERENCIAS redactadas con
    cifras reales (variación de tráfico, conversiones, keywords); el equipo los ajusta o
    los reescribe. Las filas las pinta reportes.js desde el contenido, igual que en el resto de tipos.
--}}
<div class="rep-editor" data-editor="resumen_cliente">
    <p class="rep-editor__ayuda">Esta sección es lo que ve el cliente en la versión de una página y en su enlace. Máximo 3 logros y 3 retos.</p>

    <div class="rep-editor__bloque">
        <div class="rep-editor__bloque-head">
            <h3 class="rep-editor__titulo">Logros <span class="rep-editor__conteo" data-conteo="logros"></span></h3>
            <button type="button" class="btn btn--secondary btn--sm" data-add-fila="logros">
                <i class="fa-solid fa-plus"></i> Añadir logro
            </button>
        </div>
        <div class="rep-rows" data-lista="logros" data-vacio="Sin logros todavía."></div>
    </div>

    <div class="rep-editor__bloque">
        <div class="rep-editor__bloque-head">
            <h3 class="rep-editor__titulo">Retos <span class="rep-editor__conteo" data-conteo="retos"></span></h3>
            <button type="button" class="btn btn--secondary btn--sm" data-add-fila="retos">
                <i class="fa-solid fa-plus"></i> Añadir reto
            </button>
        </div>
        <div class="rep-rows" data-lista="retos" data-vacio="Sin retos todavía."></div>
    </div>

    <div class="rep-editor__bloque">
        <div class="rep-editor__bloque-head">
            <h3 class="rep-editor__titulo">Próximos pasos <span class="rep-editor__conteo" data-conteo="proximos_pasos"></span></h3>
            <button type="button" class="btn btn--secondary btn--sm" data-add-fila="proximos_pasos">
                <i class="fa-solid fa-plus"></i> Añadir paso
            </button>
        </div>
        <div class="rep-rows" data-lista="proximos_pasos" data-vacio="Sin próximos pasos todavía."></div>
    </div>

    <p class="rep-editor__ayuda" data-resumen-grafica></p>
</div>
