<?php

namespace App\Services\Reportes;

use RuntimeException;

/**
 * Aún no hay reporte listo del periodo. Se distingue del resto de fallos porque
 * es el único que se reintenta solo: basta con que el equipo termine el reporte.
 */
class ReporteNoListoException extends RuntimeException
{
}
