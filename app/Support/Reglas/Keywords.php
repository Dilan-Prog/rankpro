<?php

namespace App\Support\Reglas;

use App\Models\Keyword;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Reglas de validación de Keyword, compartidas entre el controlador web
 * (App\Http\Controllers\Admin\KeywordsController) y el de la API
 * (App\Http\Controllers\Api\V1\Keywords\KeywordsApiController).
 */
class Keywords
{
    /** @return array<string, array<int, mixed>> */
    public static function guardar(Request $request, ?Keyword $keyword = null): array
    {
        $clienteId = $request->input('cliente_id', $keyword?->cliente_id);

        return [
            'cliente_id' => ['required', 'integer', 'exists:clientes,id'],
            'lista_id' => [
                'nullable',
                'integer',
                Rule::exists('keyword_listas', 'id')->where(fn ($q) => $q->where('cliente_id', $clienteId)),
            ],
            'keyword' => ['required', 'string', 'max:255'],
            'tipo' => ['required', 'in:principal,secundaria,long_tail,lsi'],
            'volumen_busqueda' => ['nullable', 'integer', 'min:0'],
            'dificultad' => ['nullable', 'integer', 'min:0', 'max:100'],
            'cpc_estimado' => ['nullable', 'numeric', 'min:0'],
            'intencion' => ['nullable', 'in:informacional,transaccional,navegacional'],
            'idioma' => ['nullable', 'string', 'max:10'],
            'pais' => ['nullable', 'string', 'max:10'],
            'herramienta_origen' => ['nullable', 'in:semrush,ahrefs,google_kp,otro'],
            'url_asignada' => ['nullable', 'string', 'max:255'],
            'posicion_actual' => ['nullable', 'integer', 'min:0'],
            'estado' => ['required', 'in:en_uso,seguimiento,descartada'],
            'fecha_incorporacion' => ['nullable', 'date'],
            'notas' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
