<?php

namespace App\Http\Controllers\Api\V1\Ads;

use App\Models\AdsGrupo;
use App\Models\AdsGrupoKeyword;
use App\Support\Api\{Respuesta, Serializador};
use Illuminate\Http\Request;

class AdsGrupoKeywordApiController
{
    public function index(AdsGrupo $grupo)
    {
        return Respuesta::coleccion(Serializador::coleccion($grupo->keywords()->get()));
    }

    public function store(Request $request, AdsGrupo $grupo)
    {
        $data = $this->validated($request);

        $keyword = $grupo->keywords()->create($data);

        return Respuesta::recurso(Serializador::modelo($keyword), 201);
    }

    public function update(Request $request, AdsGrupoKeyword $keyword)
    {
        $data = $this->validated($request);

        $keyword->update($data);

        return Respuesta::recurso(Serializador::modelo($keyword->fresh()));
    }

    public function destroy(AdsGrupoKeyword $keyword)
    {
        $keyword->delete();

        return Respuesta::eliminado();
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'keyword' => ['required', 'string', 'max:255'],
            'volumen_busqueda' => ['nullable', 'integer', 'min:0'],
            'competencia' => ['nullable', 'in:baja,media,alta'],
            'cpc' => ['nullable', 'numeric', 'min:0'],
            'datos_personalizados' => ['nullable', 'array'],
            'datos_personalizados.*' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
