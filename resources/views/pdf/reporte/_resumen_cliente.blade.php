{{--
    resumen_cliente dentro del PDF completo. Recibe $seccion (armada). El aviso de la
    sección ya lo imprime el documento antes del cuerpo, así que aquí no se repite.
--}}
@include('pdf.reporte._resumen_cuerpo', ['resumen' => ($seccion['derivados'] ?? []) + ['aviso' => null]])
