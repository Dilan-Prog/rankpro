<?php

use App\Support\Fecha;

if (! function_exists('fecha_mx')) {
    /**
     * Atajo de `Fecha::mx()` para las vistas: `{{ fecha_mx($x->fecha) }}` → 05/12/2026.
     */
    function fecha_mx(mixed $valor, bool $conHora = false, string $vacio = '—'): string
    {
        return Fecha::mx($valor, $conHora, $vacio);
    }
}

if (! function_exists('fecha_mx_texto')) {
    /**
     * Pasa a dd/mm/aaaa las fechas ISO incrustadas en un texto ya armado.
     */
    function fecha_mx_texto(?string $texto): string
    {
        return Fecha::enTexto($texto);
    }
}
