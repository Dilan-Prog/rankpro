<?php

namespace App\Http\Controllers\Api\V1\Ads;

use App\Models\AdsGrupo;
use App\Models\AdsKeywordColumna;
use App\Support\Api\{Respuesta, Serializador};
use Illuminate\Http\Request;

class AdsKeywordColumnaApiController
{
    public function index(AdsGrupo $grupo)
    {
        return Respuesta::coleccion(Serializador::coleccion($grupo->columnasPersonalizadas()->get()));
    }

    public function store(Request $request, AdsGrupo $grupo)
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
        ]);

        $columna = $grupo->columnasPersonalizadas()->create($data);

        return Respuesta::recurso(Serializador::modelo($columna), 201);
    }

    public function update(Request $request, AdsKeywordColumna $columna)
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
        ]);

        $columna->update($data);

        return Respuesta::recurso(Serializador::modelo($columna->fresh()));
    }

    public function destroy(AdsKeywordColumna $columna)
    {
        $columna->delete();

        return Respuesta::eliminado();
    }
}
