<?php

use App\Support\Reportes\AvisoSeccion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La fecha del aviso de "dato desactualizado" era un string libre ("18 ago 2026").
 * Ahora es una fecha ISO real: si la existente no lo es, se conserva pasándola al
 * texto del aviso para no perder lo que alguien escribió.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('reporte_secciones')->whereNotNull('aviso')->orderBy('id')->each(function ($fila) {
            $aviso = json_decode((string) $fila->aviso, true);
            if (! is_array($aviso)) {
                return;
            }

            $nuevo = AvisoSeccion::normalizar($aviso);
            if ($nuevo !== $aviso) {
                DB::table('reporte_secciones')->where('id', $fila->id)->update(['aviso' => $nuevo === null ? null : json_encode($nuevo, JSON_UNESCAPED_UNICODE)]);
            }
        });
    }

    public function down(): void
    {
        // La conversión no se puede deshacer (el texto libre original ya forma parte del aviso); no hace falta: ambos formatos se leen.
    }
};
