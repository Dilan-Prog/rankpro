<?php

namespace App\Support\Reglas;

use App\Enums\TipoArchivo;
use Illuminate\Validation\Rule;

/**
 * Reglas de validación de Archivo, compartidas entre el controlador web
 * (Admin\ArchivosController, subida multipart) y la API
 * (Api\V1\Crm\ArchivosApiController, que además acepta JSON con
 * contenido_base64 para clientes tipo n8n que no arman multipart).
 */
class Archivos
{
    /** Subida multipart (campo de archivo real en el request). */
    public static function multipart(): array
    {
        return [
            'cliente_id' => ['required', 'integer', 'exists:clientes,id'],
            'tipo' => ['required', Rule::enum(TipoArchivo::class)],
            'nombre' => ['nullable', 'string', 'max:255'],
            // Tope de 100 MB a nivel app — en un deploy dado el techo real
            // también depende de upload_max_filesize/post_max_size de
            // php.ini, sobre los que esta regla no tiene control.
            'archivo' => ['required', 'file', 'max:102400', 'mimes:pdf,zip,rar,doc,docx,xls,xlsx,csv,ppt,pptx,png,jpg,jpeg,gif,svg,fig,txt'],
        ];
    }

    /** Subida JSON con el contenido del archivo en base64 (uso típico: n8n). */
    public static function base64(): array
    {
        return [
            'cliente_id' => ['required', 'integer', 'exists:clientes,id'],
            'tipo' => ['required', Rule::enum(TipoArchivo::class)],
            'nombre' => ['nullable', 'string', 'max:255'],
            'contenido_base64' => ['required', 'string'],
        ];
    }
}
