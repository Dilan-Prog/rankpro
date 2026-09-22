<?php

namespace App\Support\Reglas;

/**
 * Reglas de validación de CorreoAdjunto, compartidas entre
 * CorreoAdjuntosController::store() (subida multipart) y ::desdeArchivo()
 * (copia desde el módulo Archivos), que además chequean los topes de
 * cantidad y de tamaño combinado a mano (no son reglas de un solo campo).
 */
class CorreoAdjuntos
{
    public const MIMES = 'pdf,doc,docx,xls,xlsx,csv,ppt,pptx,png,jpg,jpeg,zip,txt';

    public const MAX_KB = 15360; // 15 MB por archivo

    public const MAX_ADJUNTOS = 5;

    public const MAX_TOTAL_BYTES = 20 * 1024 * 1024; // 20 MB combinados: límite típico y seguro de proveedores SMTP

    public static function multipart(): array
    {
        return [
            'archivo' => ['required', 'file', 'max:'.self::MAX_KB, 'mimes:'.self::MIMES],
        ];
    }
}
