<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TipoElementoContenido;
use App\Models\ElementoContenido;
use App\Support\Contenido\RenderizadorMarkdown;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Guardado y vista previa de los documentos Markdown del módulo «Contenido». */
class ContenidoDocumentoController extends ContenidoBaseController
{
    public function guardar(Request $request, ElementoContenido $elemento): JsonResponse
    {
        $this->delClienteActivo($elemento);
        abort_unless($elemento->tipo === TipoElementoContenido::Documento, 404);

        $data = $request->validate([
            'contenido' => ['nullable', 'string', 'max:200000'],
        ]);

        $elemento->contenido = $data['contenido'] ?? '';
        $elemento->save();

        return response()->json(['guardado' => $elemento->updated_at->format('H:i:s')]);
    }

    /**
     * Vista previa con el MISMO renderizador seguro del Blog (html_input escapado,
     * enlaces inseguros desactivados). Devuelve JSON, nunca una vista con HTML
     * crudo, y no consulta ningún elemento: solo transforma el texto recibido.
     */
    public function previsualizar(Request $request, RenderizadorMarkdown $renderizador): JsonResponse
    {
        $data = $request->validate([
            'contenido' => ['nullable', 'string', 'max:200000'],
        ]);

        $render = $renderizador->procesar($data['contenido'] ?? '');

        return response()->json(['html' => $render['html'], 'palabras' => $render['palabras']]);
    }
}
