<?php

namespace App\Services\Reportes;

use App\Models\Reporte;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Renderiza el entregable en PDF. No calcula nada: toda la aritmética la hace
 * el Armador, así que este PDF y el XLSX del mismo reporte siempre coinciden.
 *
 * El Armador se resuelve del contenedor en lugar de inyectarse por constructor
 * para que `new RenderizadorPdf()` siga funcionando desde cualquier sitio.
 */
class RenderizadorPdf
{
    /** @return string Bytes del PDF. */
    public function generar(Reporte $reporte): string
    {
        $datos = app(Armador::class)->armar($reporte);

        return $this->renderizar($datos + ['totalPaginas' => $this->contarPaginas($datos)])->output();
    }

    /**
     * El pie de página del diseño dice «Página 5 de 12», y el total es la mitad
     * que dompdf no sabe dar: resuelve `counter(page)` pero no tiene contador
     * `pages`. La alternativa habitual —`page_text()` del canvas— exige activar
     * `enable_php` y pinta el texto en coordenadas absolutas sobre TODAS las
     * páginas, incluida la portada, que precisamente no lleva pie.
     *
     * Así que se renderiza dos veces: la primera solo para contar. Es barato
     * comparado con lo que cuesta un pie que miente, y no altera la paginación
     * porque el pie es de alto fijo: añadir « de 12» no reflota nada.
     */
    private function contarPaginas(array $datos): int
    {
        $pdf = $this->renderizar($datos);

        // El canvas no sabe cuántas páginas hay hasta que dompdf ha maquetado el
        // documento, y `loadView` es perezoso: sin este `output()` el contador
        // devuelve 1 siempre, y el pie acaba diciendo «Página 12 de 1».
        $pdf->output();

        return max(1, $pdf->getDomPDF()->getCanvas()->get_page_count());
    }

    private function renderizar(array $datos): \Barryvdh\DomPDF\PDF
    {
        return Pdf::loadView('pdf.reporte', $datos)->setPaper('letter');
    }
}
