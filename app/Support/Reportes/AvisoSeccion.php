<?php

namespace App\Support\Reportes;

use Carbon\Carbon;

/**
 * Aviso de "dato desactualizado" de una sección: {texto, fecha}. La etiqueta
 * "DATO DESACTUALIZADO" es fija en los entregables; lo único editable es el texto y
 * una fecha ISO real (así no se parece a contenido y se puede formatear igual en PDF y XLSX).
 */
class AvisoSeccion
{
    /**
     * Deja el aviso con fecha ISO válida o sin fecha. Una fecha libre ("18 ago 2026")
     * pasa al final del texto en vez de perderse.
     *
     * @param  array<string, mixed>|null  $aviso
     * @return array{texto: string, fecha: ?string}|null
     */
    public static function normalizar(?array $aviso): ?array
    {
        $texto = trim((string) ($aviso['texto'] ?? ''));
        if ($texto === '') {
            return null;
        }

        $fecha = trim((string) ($aviso['fecha'] ?? ''));
        if ($fecha !== '' && ! self::esIso($fecha)) {
            $texto = rtrim($texto, ". \t").' ('.$fecha.')';
            $fecha = '';
        }

        return ['texto' => $texto, 'fecha' => $fecha !== '' ? $fecha : null];
    }

    public static function esIso(string $valor): bool
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
            return false;
        }

        try {
            return Carbon::createFromFormat('!Y-m-d', $valor)->format('Y-m-d') === $valor;
        } catch (\Throwable) {
            return false;
        }
    }

    /** Fecha lista para mostrar (dd/mm/aaaa) o cadena vacía. */
    public static function fechaVisible(?string $fecha): string
    {
        return $fecha && self::esIso($fecha) ? Carbon::createFromFormat('!Y-m-d', $fecha)->format('d/m/Y') : '';
    }
}
